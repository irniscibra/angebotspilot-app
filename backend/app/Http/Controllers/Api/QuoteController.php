<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\QuoteMail;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Services\QuoteAIService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class QuoteController extends Controller
{
    public function __construct(
        private QuoteAIService $aiService
    ) {}

    /**
     * Alle Angebote der Firma.
     */
    public function index(Request $request): JsonResponse
    {
        $quotes = $request->user()->company->quotes()
            ->with('customer')
            ->withCount('items')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($quotes);
    }

    /**
     * Einzelnes Angebot mit allen Details.
     */
    public function show(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        $quote->load(['customer', 'items', 'creator']);

        return response()->json($quote);
    }

    /**
     * Neues Angebot erstellen (leer oder mit KI).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'project_description' => 'required|string|min:10|max:5000',
            'customer_id' => 'nullable|exists:customers,id',
            'project_address' => 'nullable|string|max:500',
            'use_ai' => 'boolean',
        ]);

        $company = $request->user()->company;

        // Angebot erstellen
        $quote = Quote::create([
            'company_id' => $company->id,
            'customer_id' => $request->customer_id,
            'created_by' => $request->user()->id,
            'quote_number' => $company->generateQuoteNumber(),
            'project_title' => 'Neues Angebot',
            'project_description' => $request->project_description,
            'project_address' => $request->project_address,
            'vat_rate' => $company->default_vat_rate,
            'valid_until' => now()->addDays($company->quote_validity_days),
        ]);

        // KI-Angebot generieren
        if ($request->input('use_ai', true)) {
            try {
                $aiResult = $this->aiService->generateQuote($quote, $request->project_description);
                $quote->refresh();
                $quote->load('items');

                return response()->json([
                    'quote' => $quote,
                    'ai_notes' => $aiResult['notes'] ?? null,
                    'estimated_days' => $aiResult['estimated_days'] ?? null,
                ], 201);
            } catch (\Exception $e) {
                return response()->json([
                    'quote' => $quote,
                    'ai_error' => $e->getMessage(),
                ], 201);
            }
        }

        return response()->json(['quote' => $quote], 201);
    }

    /**
     * Angebot aktualisieren.
     */
    public function update(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        $request->validate([
            'project_title' => 'sometimes|string|max:255',
            'project_description' => 'sometimes|string|max:5000',
            'project_address' => 'nullable|string|max:500',
            'customer_id' => 'nullable|exists:customers,id',
            'discount_percent' => 'sometimes|numeric|min:0|max:100',
            'header_text' => 'nullable|string',
            'footer_text' => 'nullable|string',
            'terms_text' => 'nullable|string',
            'internal_notes' => 'nullable|string',
        ]);

        $quote->update($request->only([
            'project_title',
            'project_description',
            'project_address',
            'customer_id',
            'discount_percent',
            'header_text',
            'footer_text',
            'terms_text',
            'internal_notes',
        ]));

        // Neu kalkulieren falls Rabatt geändert
        if ($request->has('discount_percent')) {
            $quote->recalculate();
        }

        return response()->json($quote->fresh()->load('items'));
    }

    /**
     * Angebot löschen.
     */
    public function destroy(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        $quote->delete();

        return response()->json(['message' => 'Angebot gelöscht.']);
    }

    /**
     * KI: Angebot neu generieren.
     */
    public function regenerate(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        $request->validate([
            'project_description' => 'required|string|min:10|max:5000',
        ]);

        try {
            $aiResult = $this->aiService->generateQuote($quote, $request->project_description);
            $quote->refresh()->load('items');

            return response()->json([
                'quote' => $quote,
                'ai_notes' => $aiResult['notes'] ?? null,
                'estimated_days' => $aiResult['estimated_days'] ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Position hinzufügen.
     */
    public function addItem(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        $request->validate([
            'group_name' => 'required|string|max:100',
            'type' => 'required|in:material,labor,flat,text',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'quantity' => 'required|numeric|min:0',
            'unit' => 'required|string|max:20',
            'unit_price' => 'required|numeric|min:0',
            'material_id' => 'nullable|exists:materials,id',
        ]);

        $lastPosition = $quote->items()->max('position_number') ?? 0;

        $item = QuoteItem::create([
            'quote_id' => $quote->id,
            'position_number' => $lastPosition + 1,
            'group_name' => $request->group_name,
            'type' => $request->type,
            'title' => $request->title,
            'description' => $request->description,
            'quantity' => $request->quantity,
            'unit' => $request->unit,
            'unit_price' => $request->unit_price,
            'is_ai_generated' => false,
            'sort_order' => $lastPosition + 1,
            'material_id' => $request->material_id,
        ]);

        return response()->json($item, 201);
    }

    /**
     * Position aktualisieren.
     */
    public function updateItem(Request $request, Quote $quote, QuoteItem $item): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'quantity' => 'sometimes|numeric|min:0',
            'unit' => 'sometimes|string|max:20',
            'unit_price' => 'sometimes|numeric|min:0',
            'group_name' => 'sometimes|string|max:100',
        ]);

        $item->update($request->only([
            'title', 'description', 'quantity', 'unit', 'unit_price', 'group_name',
        ]));

        return response()->json($item);
    }

    /**
     * Position löschen.
     */
    public function deleteItem(Request $request, Quote $quote, QuoteItem $item): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        $item->delete();

        return response()->json(['message' => 'Position gelöscht.']);
    }

    /**
     * Angebot senden (Status auf "sent").
     */
public function send(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);
 
        $request->validate([
            'recipient_email' => 'required|email',
            'recipient_name' => 'required|string|max:255',
            'subject' => 'nullable|string|max:500',
            'message' => 'nullable|string|max:5000',
        ]);
 
        // Quote mit allen Relations laden
        $quote->load(['company', 'customer', 'items', 'creator']);
 
        // PDF generieren
        $groupedItems = $quote->items->groupBy('group_name');
        $pdf = Pdf::loadView('pdf.quote', [
            'quote' => $quote,
            'company' => $quote->company,
            'customer' => $quote->customer,
            'groupedItems' => $groupedItems,
            'creator' => $quote->creator,
        ]);
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('defaultFont', 'DejaVu Sans');
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('isHtml5ParserEnabled', true);
 
        $pdfContent = $pdf->output();
        $pdfFilename = $quote->quote_number . '.pdf';
 
        // Absender-Info
        $user = $request->user();
        $company = $quote->company;
        $senderName = $user->name;
        $replyToEmail = $company->email ?? $user->email;
 
        try {
            // E-Mail senden
            Mail::to($request->recipient_email)
                ->send(new QuoteMail(
                    quote: $quote,
                    recipientName: $request->recipient_name,
                    senderName: $senderName,
                    customMessage: $request->message,
                    replyToEmail: $replyToEmail,
                    pdfContent: $pdfContent,
                    pdfFilename: $pdfFilename,
                ));
 
            // Status auf "sent" setzen
            $quote->markAsSent();
 
            // PDF lokal speichern
            $storagePath = 'angebote/' . $company->id . '/' . $pdfFilename;
            \Illuminate\Support\Facades\Storage::disk('local')->put($storagePath, $pdfContent);
            $quote->update([
                'pdf_path' => $storagePath,
                'pdf_generated_at' => now(),
            ]);
 
            Log::info("Angebot versendet", [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'recipient' => $request->recipient_email,
                'sender' => $replyToEmail,
            ]);
 
            return response()->json([
                'message' => 'Angebot wurde erfolgreich per E-Mail versendet.',
                'quote' => $quote->fresh()->load(['customer', 'items']),
            ]);
 
        } catch (\Exception $e) {
            Log::error("E-Mail-Versand fehlgeschlagen", [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
            ]);
 
            return response()->json([
                'message' => 'E-Mail konnte nicht gesendet werden: ' . $e->getMessage(),
            ], 500);
        }
    }
 
    /**
     * Angebot duplizieren.
     */
    public function duplicate(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        $company = $request->user()->company;

        $newQuote = $quote->replicate();
        $newQuote->uuid = null; // Wird automatisch generiert
        $newQuote->quote_number = $company->generateQuoteNumber();
        $newQuote->status = 'draft';
        $newQuote->sent_at = null;
        $newQuote->viewed_at = null;
        $newQuote->accepted_at = null;
        $newQuote->rejected_at = null;
        $newQuote->pdf_path = null;
        $newQuote->pdf_generated_at = null;
        $newQuote->save();

        // Positionen kopieren
        foreach ($quote->items as $item) {
            $newItem = $item->replicate();
            $newItem->quote_id = $newQuote->id;
            $newItem->save();
        }

        $newQuote->recalculate();

        return response()->json($newQuote->load('items'), 201);
    }

    /**
     * KI-Preischeck: Analysiert ob Preise marktgerecht sind.
     */
    public function priceCheck(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        $request->validate([
            'plz' => 'nullable|string|max:5',
        ]);

        $quote->load('items');

        Log::info('Items für Preischeck', [
    'items' => $quote->items->map(fn($i) => [
        'id' => $i->id,
        'title' => $i->title,
        'unit_price' => $i->unit_price,
    ])->toArray()
]);


        if ($quote->items->isEmpty()) {
            return response()->json(['error' => 'Keine Positionen vorhanden.'], 422);
        }

        $plz = $request->input('plz', '');
        $region = $this->getRegionFromPlz($plz);

        // Positionen für KI aufbereiten
        $itemsForAi = $quote->items->map(fn($item) => [
            'id'         => $item->id,
            'titel'      => $item->title,
            'typ'        => $item->type === 'labor' ? 'Arbeit' : 'Material',
            'menge'      => $item->quantity,
            'einheit'    => $item->unit,
            'einzelpreis'=> (float) $item->unit_price,
            'gesamtpreis'=> (float) $item->total_price,
        ])->values()->toJson(JSON_UNESCAPED_UNICODE);

      // Gewerk aus Company holen
$trade = $quote->company->trade ?? 'shk';

$tradeLabel = match($trade) {
    'shk'        => 'Sanitär, Heizung, Klima (SHK)',
    'elektro'    => 'Elektroinstallation',
    'maler'      => 'Maler & Lackierer',
    'trockenbau' => 'Trockenbau & Innenausbau',
    'fliesen'    => 'Fliesen & Naturstein',
    'schreiner'  => 'Schreiner & Tischler',
    'dachdecker' => 'Dachdecker',
    'gartenbau'  => 'Garten & Landschaftsbau',
    'geruestbau' => 'Gerüstbau',
    'kaelte'     => 'Kälte & Klimatechnik',
    default      => 'Allgemeines Baugewerk',
};

$tradeReferenz = match($trade) {
    'shk' => '
STUNDENSÄTZE SHK (Netto, ohne MwSt):
- Geselle/Monteur:  Ost 65-75€ | Mitte 75-95€ | Süd/West 90-120€
- Meister:          Ost 85-100€ | Mitte 100-125€ | Süd/West 120-150€

MATERIAL-VERKAUFSPREISE SHK (Handwerker-Verkaufspreis an Endkunde, Netto):
Heizgeräte:
- Gasbrennwerttherme 24kW (Wolf, Vaillant, Viessmann, Buderus): 2.800-4.200€
- Gasbrennwerttherme 32kW: 3.500-5.000€
- Wärmepumpe Luft/Wasser 8kW: 8.000-12.000€
- Wärmepumpe Luft/Wasser 12kW: 10.000-15.000€
- Wärmepumpe Luft/Wasser 20kW: 14.000-22.000€
- Pelletheizung 15kW: 12.000-20.000€
Speicher:
- Warmwasserspeicher 100L: 400-700€
- Warmwasserspeicher 150L: 600-1.000€
- Warmwasserspeicher 200L (Wolf SM1-200, Vaillant, Stiebel): 900-1.600€
- Warmwasserspeicher 300L: 1.200-2.200€
- Pufferspeicher 200L: 800-1.400€
- Pufferspeicher 500L: 1.500-2.500€
- Pufferspeicher 1000L: 2.500-4.000€
- Pufferspeicher 4000L: 8.000-15.000€
Heizkörper:
- Flachheizkörper Typ 22 600x600mm: 120-200€
- Flachheizkörper Typ 22 600x800mm: 150-260€
- Flachheizkörper Typ 22 600x1000mm: 180-320€
- Flachheizkörper Typ 22 600x1200mm: 220-400€
- Flachheizkörper Typ 33 600x1000mm: 250-420€
Pumpen/Armaturen:
- Hocheffizienzpumpe (Wilo Yonos, Grundfos Alpha): 280-480€
- Standardumwälzpumpe: 120-250€
- Thermostatventil komplett (Heimeier, Danfoss): 35-65€
- Ausdehnungsgefäß 25L: 80-150€
- Ausdehnungsgefäß 50L: 130-220€
- Sicherheitsventil: 25-60€
- Fußbodenheizung Heizkreisverteiler 6-Kreis: 400-800€
- Fußbodenheizung je m² (Material): 18-35€
Rohrleitungen (Verkaufspreis je Laufmeter inkl. Fittings-Anteil):
- Kupferrohr 15mm: 8-18€/m
- Kupferrohr 22mm: 12-25€/m
- Kupferrohr 28mm: 18-35€/m
- Viega Sanpress 15mm: 10-20€/m
- Viega Sanpress 22mm: 16-28€/m
- Viega Sanpress 28mm: 22-38€/m
- Viega Sanpress 35mm: 28-48€/m
- Viega Sanpress 42mm: 38-65€/m
- Viega Temponox Edelstahl 54mm: 55-95€/m
- SML-Rohr DN50: 12-22€/m
- SML-Rohr DN70: 16-28€/m
- SML-Rohr DN100: 20-38€/m
- PP-Silent DN110 1m: 12-22€ je Stück
- PP-Silent DN110 2m: 22-38€ je Stück
Sanitär:
- Vorwandelement WC (Geberit Duofix, Viega): 280-450€
- UP-Spülkasten (Geberit Sigma, Grohe): 150-280€
- Betätigungsplatte: 60-180€
- Wand-WC spülrandlos (Duravit, Villeroy, Geberit): 250-550€
- Stand-WC: 150-350€
- Waschtisch 55cm (Keramag, Duravit): 120-350€
- Waschtisch 80cm: 200-500€
- Einhebelmischer Waschtisch (Grohe, Hansgrohe): 80-250€
- Einhebelmischer Küche: 100-300€
- Thermostatarmatur Dusche (Grohe, Hansgrohe): 200-500€
- Duscharmatur Unterputz: 350-800€
- Duschrinne 80cm (Geberit, Viega): 180-380€
- Badewanne Stahl/Acryl: 300-700€
- Einbauwanne freistehend: 800-2.500€
- Duschkabine komplett: 400-1.500€
Solarthermie:
- Solarkollektoranlage 4m² (Flachkollektor): 2.800-4.500€
- Solarkollektoranlage 8m²: 5.000-8.000€
Verbindungsstücke/Fittings Pauschal: je nach Umfang 200-5.000€',

    'elektro' => '
STUNDENSÄTZE ELEKTRO (Netto):
- Elektriker Geselle:  Ost 60-75€ | Mitte 72-90€ | Süd/West 85-115€
- Elektromeister:      Ost 80-100€ | Mitte 95-120€ | Süd/West 115-145€

MATERIAL-VERKAUFSPREISE ELEKTRO (Netto):
Installationsmaterial:
- Unterputzdose: 3-8€
- Steckdose UP (Busch-Jaeger, Gira, Jung): 8-25€
- Schalter UP: 8-20€
- Steckdose AP: 12-30€
- USB-Steckdose: 25-60€
- Dimmer UP: 40-120€
Schutzeinrichtungen:
- Leitungsschutzschalter B16: 12-30€
- Leitungsschutzschalter B32: 15-35€
- FI-Schutzschalter 40A/30mA: 45-120€
- FI/LS-Kombination: 60-150€
- Überspannungsschutz: 80-200€
Kabel/Leitungen:
- NYM 3x1,5mm je m: 1,50-3,50€
- NYM 3x2,5mm je m: 2,50-5€
- NYM 5x2,5mm je m: 3-6€
- NYM 5x4mm je m: 5-9€
- Leerrohr je m: 1-3€
Verteiler/Zählerschrank:
- Unterverteiler 24-polig: 80-180€
- Zählerschrank 3-polig: 200-500€
- Hutschiene je m: 8-20€
Beleuchtung:
- LED-Einbaustrahler: 15-60€
- LED-Deckenleuchte: 30-150€
- Außenleuchte: 40-200€
- Bewegungsmelder: 30-100€
Sonstiges:
- Außensteckdose: 35-80€
- Türklingel komplett: 50-200€
- Rauchmelder: 20-60€
- Netzwerkanschluss: 30-80€',

    'maler' => '
STUNDENSÄTZE MALER (Netto):
- Malergeselle:  Ost 42-55€ | Mitte 52-68€ | Süd/West 62-85€
- Malermeister:  Ost 60-75€ | Mitte 70-90€ | Süd/West 82-105€

LEISTUNGSPREISE MALER (inkl. Material und Arbeit je m², Netto):
Innen:
- Wände streichen 1x (inkl. Grundierung): 5-12€/m²
- Wände streichen 2x: 8-18€/m²
- Decke streichen 2x: 10-22€/m²
- Tapete abziehen: 3-8€/m²
- Tapete kleben Raufaser: 10-20€/m²
- Tapete kleben Vliestapete: 15-35€/m²
- Spachteln glatt: 12-28€/m²
- Grundierung: 3-8€/m²
- Fassade streichen: 18-40€/m²
Holzarbeiten:
- Türen lackieren (je Stück): 80-250€
- Fenster lackieren innen (je Stück): 60-200€
- Heizkörper lackieren: 40-100€
- Treppenstufen streichen: 15-35€/Stück
Boden:
- Bodenfarbe je m²: 8-20€/m²',

    'fliesen' => '
STUNDENSÄTZE FLIESEN (Netto):
- Fliesenleger:  Ost 55-70€ | Mitte 65-85€ | Süd/West 78-105€

LEISTUNGSPREISE FLIESEN (inkl. Material Standard und Arbeit, Netto):
Bodenfliesen:
- Bodenfliesen verlegen Standard (bis 60x60cm): 45-90€/m²
- Bodenfliesen verlegen Großformat (ab 60x60cm): 65-130€/m²
- Naturstein verlegen: 80-200€/m²
- Feinsteinzeug verlegen: 55-120€/m²
Wandfliesen:
- Wandfliesen verlegen Standard: 55-110€/m²
- Mosaikfliesen verlegen: 80-160€/m²
Zusatzleistungen:
- Altbelag entfernen: 8-20€/m²
- Estrich/Untergrund vorbereiten: 10-25€/m²
- Verfugung (separat): 8-18€/m²
- Duschrinne einbauen: 150-400€
- Treppenstufen fliesen: 50-120€/Stück
- Sockelleiste je m: 8-20€/m',

    'schreiner' => '
STUNDENSÄTZE SCHREINER (Netto):
- Schreinergeselle:  Ost 55-70€ | Mitte 65-85€ | Süd/West 78-105€
- Schreinermeister:  Ost 75-95€ | Mitte 90-115€ | Süd/West 108-135€

MATERIAL-/LEISTUNGSPREISE SCHREINER (Netto):
Türen:
- Innentür komplett montiert (Tür+Zarge): 350-800€
- Schallschutztür komplett: 600-1.500€
- Zimmertür Zarge: 180-400€
- Haustür Holz: 2.000-6.000€
Fenster:
- Fenster Kunststoff je m²: 250-500€
- Fenster Holz je m²: 400-800€
- Fenster Holz-Alu je m²: 500-1.000€
- Rolladen elektrisch je Fenster: 400-900€
Einbauschränke/Möbel:
- Einbauschrank je lfdm: 400-1.200€
- Kleiderschrank Sonderanfertigung je m²: 500-1.500€
- Küche Montage je lfdm: 150-400€
Bodenbeläge:
- Parkett verlegen (inkl. Material Mittelklasse): 60-150€/m²
- Dielen verlegen: 50-120€/m²
- Laminat verlegen: 25-60€/m²
Treppen:
- Holztreppe Massiv gerade: 5.000-15.000€
- Holztreppe mit Geländer: 8.000-25.000€
- Treppenstufen erneuern: 100-300€/Stück',

    'dachdecker' => '
STUNDENSÄTZE DACHDECKER (Netto):
- Dachdecker:  Ost 55-70€ | Mitte 65-85€ | Süd/West 78-105€

LEISTUNGSPREISE DACHDECKER (inkl. Material Standard, Netto):
Eindeckung:
- Dachziegel verlegen je m²: 80-180€/m²
- Betondachstein je m²: 60-140€/m²
- Flachdach EPDM je m²: 80-160€/m²
- Flachdach Bitumen je m²: 60-130€/m²
- Metalldach Stehfalz je m²: 100-220€/m²
Dämmung:
- Zwischensparrendämmung je m²: 40-90€/m²
- Aufsparrendämmung je m²: 80-160€/m²
Sonstiges:
- Dachfenster einbauen (Velux, Fakro): 600-1.500€ je Stück
- Regenrinne je m: 25-60€/m
- Fallrohr je m: 20-50€/m
- Schornstein sanieren: 500-3.000€
- Dachstuhl reparieren je m²: 100-300€/m²',

    'gartenbau' => '
STUNDENSÄTZE GARTENBAU (Netto):
- Fachkraft:  Ost 40-55€ | Mitte 50-68€ | Süd/West 60-85€

LEISTUNGSPREISE GARTENBAU (inkl. Material, Netto):
- Rasen anlegen (Rollrasen): 15-35€/m²
- Rasen anlegen (Saatgut): 8-18€/m²
- Pflasterarbeiten: 50-120€/m²
- Terrassenplatten verlegen: 60-140€/m²
- Holzdeck anlegen: 80-200€/m²
- Hecke pflanzen: 15-40€ je Pflanze
- Baum fällen: 200-2.000€ je Baum
- Beet anlegen: 30-80€/m²
- Bewässerungsanlage: 20-50€/m²
- Zaunmontage: 40-120€/m',

    default => '
STUNDENSÄTZE ALLGEMEIN (Netto):
- Fachkraft:  Ost 55-75€ | Mitte 65-90€ | Süd/West 78-115€
- Meister:    Ost 75-100€ | Mitte 90-120€ | Süd/West 110-150€

Schätze Materialpreise anhand aktueller deutscher Marktpreise 2024/2025 
für das jeweilige Gewerk. Berücksichtige Großhandelsaufschlag von 30-60%.',
};

// Regionaler Faktor
$regionalerFaktor = '';
if (str_contains($region, 'sehr hohes Preisniveau')) {
    $regionalerFaktor = 'REGIONAL: +15% auf alle Referenzpreise anwenden (sehr hohes Preisniveau)';
} elseif (str_contains($region, 'hohes Preisniveau')) {
    $regionalerFaktor = 'REGIONAL: +8% auf alle Referenzpreise anwenden (hohes Preisniveau)';
} elseif (str_contains($region, 'niedriges')) {
    $regionalerFaktor = 'REGIONAL: -10% auf alle Referenzpreise anwenden (niedriges Preisniveau)';
} else {
    $regionalerFaktor = 'REGIONAL: Referenzpreise unverändert anwenden (mittleres Preisniveau)';
}

$prompt = 'Du bist ein unabhängiger Sachverständiger für Handwerkerpreise in Deutschland.
Spezialisierung: ' . $tradeLabel . '
Region: ' . $region . '

' . $regionalerFaktor . '

════════════════════════════════════════════
REFERENZPREISE FÜR ' . strtoupper($tradeLabel) . ' (Deutschland 2024/2025)
════════════════════════════════════════════
' . $tradeReferenz . '

════════════════════════════════════════════
BEWERTUNGSREGELN – ABSOLUT KRITISCH
════════════════════════════════════════════
1. MARKTPREIS ZUERST BESTIMMEN – UNABHÄNGIG VOM HANDWERKERPREIS:
   - Schaue dir den Produktnamen/Beschreibung an
   - Finde den passenden Referenzpreis aus obiger Liste
   - Passe ihn regional an (siehe REGIONAL oben)
   - Das sind estimated_min und estimated_max
   - Diese Werte sind FEST und ändern sich NICHT je nachdem was der Handwerker verlangt!
   - einzelpreis = PREIS PRO EINHEIT (nicht Gesamtpreis!)
   - Vergleiche IMMER einzelpreis mit Marktpreis pro Einheit
   - Bei Pauschalpositionen mit komplexer Beschreibung:
     lies die vollständige Beschreibung und schätze realistisch
   - "3 Außeneinheiten + 3 Innengeräte + 4000L Speicher" = Gesamtanlage → Markt 60.000-100.000€
   
   PAUSCHALPOSITIONEN MIT MEHREREN KOMPONENTEN:
   - Wenn Beschreibung mehrere Geräte/Komponenten enthält → summiere alle Marktpreise
   - Fussbodenheizung Heizkreisverteiler = NUR 1 Verteiler-Einheit → Markt 80-160€ pro Stück
     (menge=24 bedeutet 24 Stück, aber einzelpreis wird pro Stück verglichen!)
   - Montagearbeiten pauschal = Arbeitsleistung, nicht Material → realistischen Arbeitspreis schätzen
   - "Arbeitslohn" Positionen = Stundensatz × geschätzte Stunden ODER Pauschalpreis für Leistung

   WICHTIG für Pauschalpositionen (einheit = "pauschal"):
- einzelpreis = Pauschalpreis für EINE komplette Leistungseinheit
- Fussbodenheizung Heizkreisverteiler 1 pauschal = 1 kompletter Verteiler inkl. Einbauschrank
  → Markt pro Stück: 800-1.800€ (Material + Montage zusammen)
- NIEMALS menge × Referenzpreis als Marktvergleich verwenden
- Vergleiche immer einzelpreis direkt mit Markt pro Einheit
   
   BEISPIEL RICHTIG:
   Warmwasserspeicher 200L → Markt immer 900-1.600€, egal ob Handwerker 500€ oder 5.000€ verlangt
   
   BEISPIEL FALSCH (niemals so machen):
   Handwerker verlangt 4.500€ → Markt plötzlich 3.000-4.000€ (VERBOTEN!)
   Handwerker verlangt 1.700€ → Markt plötzlich 1.500-1.900€ (VERBOTEN!)

2. ABWEICHUNG BERECHNEN:
   marktmittelwert = (estimated_min + estimated_max) / 2
   abweichung_prozent = RUNDEN(((einzelpreis - marktmittelwert) / marktmittelwert) × 100)
   
   Beispiel: einzelpreis=4.500€, Markt=900-1.600€, Mittel=1.250€
   abweichung = ((4500-1250)/1250)×100 = +260% → "zu_teuer"

3. BEWERTUNG basiert auf abweichung_prozent:
   unter -20%        → "zu_guenstig"
   -20% bis -10%     → "guenstig"
   -10% bis +10%     → "marktgerecht"
   +10% bis +25%     → "gehoben"
   über +25%         → "zu_teuer"

4. TIP – konkreter Handlungshinweis (max 90 Zeichen):
   zu_guenstig:  "Preis [X]% unter Markt – auf [Y]€ erhöhen empfohlen"
   guenstig:     "Leicht unter Markt – Spielraum bis [Y]€ vorhanden"
   marktgerecht: "Marktgerecht für [Region]"
   gehoben:      "Über Marktdurchschnitt – bei Premium-Service gerechtfertigt"
   zu_teuer:     "Preis [X]% über Markt – Zielpreis ca. [Y]€ empfohlen"

════════════════════════════════════════════
ZU ANALYSIERENDE POSITIONEN:
════════════════════════════════════════════
' . $itemsForAi . '

Antworte NUR mit validem JSON Array ohne Kommentare oder Erklärungen:
[
  {
    "id": 123,
    "bewertung": "marktgerecht",
    "estimated_min": 0.00,
    "estimated_max": 0.00,
    "abweichung_prozent": 0,
    "tip": "Konkreter Hinweis"
  }
]';

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'Content-Type'  => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model'       => 'gpt-4o',
                'messages'    => [['role' => 'user', 'content' => $prompt]],
                'max_tokens'  => 4000,
                'temperature' => 0.1,
            ]);

            $content = $response->json('choices.0.message.content');
            $content = trim(preg_replace('/```json\s*|\s*```/', '', $content ?? ''));
            $results = json_decode($content, true);

            if (!is_array($results)) {
                return response()->json(['error' => 'KI-Analyse fehlgeschlagen.'], 500);
            }

            // Gesamtbewertung berechnen
            $bewertungen = array_column($results, 'bewertung');
            $gesamtScore = $this->calculateGesamtScore($bewertungen);

            // Gesamtabweichung
            $abweichungen = array_column($results, 'abweichung_prozent');
            $avgAbweichung = count($abweichungen) > 0 ? round(array_sum($abweichungen) / count($abweichungen), 1) : 0;

            Log::info('Preischeck durchgeführt', [
                'quote_id'       => $quote->id,
                'items_count'    => $quote->items->count(),
                'gesamt_score'   => $gesamtScore,
                'plz'            => $plz,
            ]);

            return response()->json([
                'items'            => $results,
                'gesamt_bewertung' => $gesamtScore,
                'avg_abweichung'   => $avgAbweichung,
                'region'           => $region,
                'plz'              => $plz,
            ]);

        } catch (\Exception $e) {
            Log::error('Preischeck Fehler', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Fehler bei der Analyse: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Region aus PLZ ermitteln.
     */
    private function getRegionFromPlz(string $plz): string
    {
        if (empty($plz)) return 'Deutschland (keine PLZ angegeben)';

        $prefix = (int) substr($plz, 0, 2);

        return match(true) {
            $prefix >= 1 && $prefix <= 19   => 'Nordostdeutschland (Berlin/Brandenburg/MV) – mittleres Preisniveau',
            $prefix >= 20 && $prefix <= 25   => 'Hamburg/Schleswig-Holstein – hohes Preisniveau',
            $prefix >= 26 && $prefix <= 32   => 'Niedersachsen/Bremen – mittleres Preisniveau',
            $prefix >= 33 && $prefix <= 34   => 'Ostwestfalen – mittleres Preisniveau',
            $prefix >= 35 && $prefix <= 36   => 'Hessen/Mittelhessen – mittleres Preisniveau',
            $prefix >= 37 && $prefix <= 38   => 'Niedersachsen/Sachsen-Anhalt – mittleres Preisniveau',
            $prefix >= 39 && $prefix <= 39   => 'Sachsen-Anhalt – niedriges bis mittleres Preisniveau',
            $prefix >= 40 && $prefix <= 42   => 'Düsseldorf/Wuppertal NRW – hohes Preisniveau',
            $prefix >= 44 && $prefix <= 48   => 'Ruhrgebiet/Münsterland NRW – hohes Preisniveau',
            $prefix >= 50 && $prefix <= 53   => 'Köln/Bonn – hohes Preisniveau',
            $prefix >= 54 && $prefix <= 56   => 'Koblenz/Trier – mittleres Preisniveau',
            $prefix >= 57 && $prefix <= 59   => 'Siegerland/Sauerland – mittleres Preisniveau',
            $prefix >= 60 && $prefix <= 65   => 'Frankfurt/Rhein-Main – sehr hohes Preisniveau',
            $prefix >= 66 && $prefix <= 68   => 'Saarland/Rheinpfalz – mittleres Preisniveau',
            $prefix >= 69 && $prefix <= 69   => 'Heidelberg/Mannheim – hohes Preisniveau',
            $prefix >= 70 && $prefix <= 76   => 'Stuttgart/Baden-Württemberg – sehr hohes Preisniveau',
            $prefix >= 77 && $prefix <= 79   => 'Schwarzwald/Freiburg – hohes Preisniveau',
            $prefix >= 80 && $prefix <= 86   => 'München/Oberbayern – sehr hohes Preisniveau',
            $prefix >= 87 && $prefix <= 89   => 'Allgäu/Augsburg – hohes Preisniveau',
            $prefix >= 90 && $prefix <= 96   => 'Nürnberg/Franken – mittleres bis hohes Preisniveau',
            $prefix >= 97 && $prefix <= 99   => 'Würzburg/Thüringen – mittleres Preisniveau',
            default => 'Deutschland – mittleres Preisniveau',
        };
    }

    /**
     * Gesamtscore aus Einzelbewertungen berechnen.
     */
    private function calculateGesamtScore(array $bewertungen): string
    {
        $scores = [
            'zu_guenstig'  => -2,
            'guenstig'     => -1,
            'marktgerecht' => 0,
            'gehoben'      => 1,
            'zu_teuer'     => 2,
        ];

        if (empty($bewertungen)) return 'marktgerecht';

        $total = array_sum(array_map(fn($b) => $scores[$b] ?? 0, $bewertungen));
        $avg = $total / count($bewertungen);

        return match(true) {
            $avg <= -1.5 => 'zu_guenstig',
            $avg <= -0.5 => 'guenstig',
            $avg <= 0.5  => 'marktgerecht',
            $avg <= 1.5  => 'gehoben',
            default      => 'zu_teuer',
        };
    }

    /**
     * Stellt sicher, dass das Angebot zur Firma des Users gehört.
     */
    private function authorizeQuote(Request $request, Quote $quote): void
    {
        if ($quote->company_id !== $request->user()->company_id) {
            abort(403, 'Zugriff verweigert.');
        }
    }
}