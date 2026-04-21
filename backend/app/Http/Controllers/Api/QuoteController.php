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
   /**
     * KI-Preischeck: Analysiert ob Preise marktgerecht sind.
     */
    public function priceCheck(Request $request, Quote $quote): JsonResponse
    {
        $this->authorizeQuote($request, $quote);

        $request->validate([
            'plz' => 'nullable|string|max:5',
        ]);

        $quote->load(['items', 'company']);

        if ($quote->items->isEmpty()) {
            return response()->json(['error' => 'Keine Positionen vorhanden.'], 422);
        }

        $plz    = $request->input('plz', '');
        $region = $this->getRegionFromPlz($plz);

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
- Fußbodenheizung Heizkreisverteiler inkl. Einbauschrank: 800-1.800€ pro Stück
- Fußbodenheizung je m² (Material): 18-35€
Rohrleitungen (Verkaufspreis je Laufmeter):
- Kupferrohr 15mm: 8-18€/m
- Kupferrohr 22mm: 12-25€/m
- Kupferrohr 28mm: 18-35€/m
- Viega Sanpress 15mm: 10-20€/m
- Viega Sanpress 22mm: 16-28€/m
- Viega Sanpress 28mm: 22-38€/m
- Viega Sanpress 35mm: 28-48€/m
- Viega Sanpress 42mm: 38-65€/m
- Viega Temponox Edelstahl 54mm: 55-95€/m
- SML-Rohr DN50 je m: 12-22€
- SML-Rohr DN70 je m: 16-28€
- SML-Rohr DN100 je m: 20-38€
- SML-Rohr DN125 je m: 28-50€
- SML-Rohr DN125 3m Stange: 80-150€
- PP-Silent DN110 1m Stück: 12-22€
- PP-Silent DN110 2m Stück: 22-38€
- PP-Silent DN75 1m Stück: 8-16€
- PP-Silent DN50 1m Stück: 6-12€
Fittings/Formstücke SML (je Stück):
- Bogen SML DN50 45/88Grad: 5-12€
- Bogen SML DN70 45/88Grad: 8-18€
- Bogen SML DN100 45/88Grad: 12-25€
- Bogen SML DN125 45/88Grad: 18-35€
- Abzweig SML DN100: 18-35€
- Abzweig SML DN125: 25-45€
- Reduzierstück SML DN100/70: 8-18€
- Verbinder SML-Rapid DN50: 3-6€
- Verbinder SML-Rapid DN70: 3,50-7€
- Verbinder SML-Rapid DN100: 4-8€
- Verbinder SML-Rapid DN125: 5-10€
- Universal-Kralle DN100: 5-10€
- Universal-Kralle DN125: 6-12€
PP-Silent Formstücke (je Stück):
- PP-Silent Bogen 45Grad DN110: 4-9€
- PP-Silent Bogen 67,5Grad DN75: 3-6€
- PP-Silent Bogen 45Grad DN50: 2-5€
- PP-Silent Abzweig 45Grad DN110/110: 5-12€
- PP-Silent Doppelsteckmuffe DN75: 3-7€
- Übergangsstück PP-Silent DN110x50: 6-14€
Verbundrohr/Mehrschichtrohr (je m):
- MPR Verbundrohr PE-RT 16mm: 8-18€
- MPR Verbundrohr PE-RT 20mm: 10-22€
- MPR Verbundrohr PE-RT 25mm: 12-25€
- MPR Verbundrohr PE-RT 32mm: 15-30€
- MPR Verbundrohr PE-RT 40mm Supersize: 18-35€
- MPR Verbundrohr vorgedämmt 16mm: 8-18€
- MPR Verbundrohr vorgedämmt 20mm: 10-22€
- MPR Verbundrohr vorgedämmt 25mm: 12-25€
- MPR Verbundrohr vorgedämmt 32mm: 15-30€
MPR Fittings (je Stück):
- MPR Winkel 90° 16-40mm: 2-5€
- MPR T-Stück 16-40mm: 2,50-6€
- MPR Kupplung 16-40mm: 2-5€
- MPR Kupplung reduziert: 2,50-6€
- MPR Wandwinkel: 3-7€
- MPR Steck-/Pressübergang: 3-8€
- MPR Übergang AG: 4-10€
Pressfittings Edelstahl Geberit Mapress (je Stück):
- Leitungsrohr Edelstahl 28mm je m: 8-18€
- Leitungsrohr Edelstahl 35mm je m: 10-22€
- Leitungsrohr Edelstahl 42mm je m: 12-28€
- Bogen 42mm 90Grad: 8-18€
- Reduzierstück 42x28mm: 5-12€
- T-Stück 42mm: 8-18€
- T-Stück reduziert 42x28/35: 9-20€
- Übergangsstück 42mm AG: 7-15€
Rohrschellen/Befestigung (je Stück):
- Rohrschelle DA 15-22mm: 1-3€
- Rohrschelle DA 19-26mm: 1,50-3,50€
- Rohrschelle DA 48-51mm: 4-8€
- Rohrschelle DA 68-73mm: 5-10€
- Rohrschelle DA 100-104mm: 8-15€
- Dämmschelle grün 1-1,5": 4-8€
- Gewindestange M8 je m: 2-5€
Armaturen/Ventile:
- Freistromventil DN15 (1/2"): 12-25€
- Freistromventil DN25 (1"): 20-40€
- Freistromventil DN32 (1 1/4"): 30-55€
- Freistromventil DN40 (1 1/2"): 40-70€
- KRV-Ventil DN40: 45-80€
- Rückspülfilter 1 1/4": 80-200€
- Schiebemuffe Kupfer 22mm: 2-5€
- Schiebemuffe Kupfer 35mm: 3-7€
Sanitär komplett:
- Vorwandelement WC (Geberit Duofix, Viega): 280-450€
- UP-Spülkasten (Geberit Sigma, Grohe): 150-280€
- Betätigungsplatte (Geberit Sigma01): 60-180€
- Wand-WC spülrandlos (Duravit, Villeroy, Vigour): 250-550€
- Stand-WC: 150-350€
- WC-Sitz: 25-80€
- Waschtisch 40-55cm: 80-250€
- Waschtisch 60-80cm: 150-400€
- Einhebelmischer Waschtisch (Grohe, Hansgrohe): 80-250€
- Einhebelmischer Küche: 100-300€
- Thermostatarmatur Dusche: 200-500€
- Eckventil 1/2": 4-10€
- Siphon Waschtisch: 10-25€
- Klein-Durchlauferhitzer 3-3,5kW: 80-180€
- Ausgussbecken Stahl: 40-100€
- GIS/Duofix Vorwandsystem:
  - Montageelement WC: 150-350€
  - Profil GIS 5m: 20-50€
  - Montagewinkel: 3-8€
  - Verbindungsstück: 2-5€
  - Paneel GIS 600x1300mm: 12-25€
  - Spachtelmasse 5kg: 8-15€
  - Schalldämmplatte: 2-5€
Entwässerungssysteme:
- Fäkalienhebeanlage (Jung Compli 300E): 500-900€
- ACO Rückstauautomat DN100: 400-700€
- ACO Überflutungsmelder: 60-120€
- Pumpen-Keilflachschieber DN100: 80-150€
- Alarmgeber Hebeanlage: 50-100€
- Handmembranpumpe 1,5": 40-80€
- Notentsorgungsanschluss Zubehör: 25-50€
- E-KS-Stück DN100 Flansch: 30-60€
Brandschutz:
- Brandschutzbandage/Manschette DN70-100: 40-80€
- Curaflam Konfix Pro DN70-100: 40-90€
Wärmedämmung Rohre (je m):
- Dämmung DN15: 3-7€
- Dämmung DN20: 4-8€
- Dämmung DN25: 4-9€
- Dämmung DN32: 5-10€
- Dämmung DN40: 5-12€
- Dämmung DN100-125: 8-20€
- Zulage Bögen/Formstücke DN25-40: 2-5€
Isolierboxen Armaturen:
- CONEL FLEX Isolierbox DN25: 6-12€
- CONEL FLEX Isolierbox DN32: 8-15€
- CONEL FLEX Isolierbox DN40: 10-18€
Abwasserschlauch (je m):
- Abwasserschlauch DN50: 2-4€
- Abwasserschlauch DN70: 2,50-5€
- Abwasserschlauch DN100: 3-6€
Sonstiges SHK:
- Kamerabefahrung/Ausfräsen/Spülen: 150-400€ pauschal
- Kernbohrung DN100 durch Mauerwerk/Beton: 80-200€ je Bohrung
- An- und Abfahrt: 40-80€ pauschal
- Entsorgung Altmaterial Abwasserleitung: 5-20€ je Stück/Laufmeter
- Entsorgung Trinkwasserverteiler: 8-20€
- Schmutzzulage fäkalienhaltige Materialien: 15-30€/Std',

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
Verteiler:
- Unterverteiler 24-polig: 80-180€
- Zählerschrank 3-polig: 200-500€
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
- Wände streichen 1x: 5-12€/m²
- Wände streichen 2x: 8-18€/m²
- Decke streichen 2x: 10-22€/m²
- Tapete abziehen: 3-8€/m²
- Tapete kleben Raufaser: 10-20€/m²
- Tapete kleben Vliestapete: 15-35€/m²
- Spachteln glatt: 12-28€/m²
- Fassade streichen: 18-40€/m²
- Türen lackieren: 80-250€/Stück
- Fenster lackieren: 60-200€/Stück
- Heizkörper lackieren: 40-100€/Stück',

            'fliesen' => '
STUNDENSÄTZE FLIESEN (Netto):
- Fliesenleger:  Ost 55-70€ | Mitte 65-85€ | Süd/West 78-105€

LEISTUNGSPREISE FLIESEN (inkl. Material Standard und Arbeit, Netto):
- Bodenfliesen Standard (bis 60x60cm): 45-90€/m²
- Bodenfliesen Großformat (ab 60x60cm): 65-130€/m²
- Naturstein verlegen: 80-200€/m²
- Wandfliesen Standard: 55-110€/m²
- Mosaikfliesen: 80-160€/m²
- Altbelag entfernen: 8-20€/m²
- Duschrinne einbauen: 150-400€
- Treppenstufen fliesen: 50-120€/Stück',

            'schreiner' => '
STUNDENSÄTZE SCHREINER (Netto):
- Schreinergeselle:  Ost 55-70€ | Mitte 65-85€ | Süd/West 78-105€
- Schreinermeister:  Ost 75-95€ | Mitte 90-115€ | Süd/West 108-135€

MATERIAL-/LEISTUNGSPREISE SCHREINER (Netto):
- Innentür komplett (Tür+Zarge): 350-800€
- Schallschutztür komplett: 600-1.500€
- Haustür Holz: 2.000-6.000€
- Fenster Kunststoff je m²: 250-500€
- Fenster Holz je m²: 400-800€
- Rolladen elektrisch je Fenster: 400-900€
- Einbauschrank je lfdm: 400-1.200€
- Parkett verlegen (inkl. Material Mittelklasse): 60-150€/m²
- Laminat verlegen: 25-60€/m²
- Holztreppe Massiv gerade: 5.000-15.000€',

            'dachdecker' => '
STUNDENSÄTZE DACHDECKER (Netto):
- Dachdecker:  Ost 55-70€ | Mitte 65-85€ | Süd/West 78-105€

LEISTUNGSPREISE DACHDECKER (inkl. Material Standard, Netto):
- Dachziegel verlegen: 80-180€/m²
- Betondachstein: 60-140€/m²
- Flachdach EPDM: 80-160€/m²
- Flachdach Bitumen: 60-130€/m²
- Metalldach Stehfalz: 100-220€/m²
- Zwischensparrendämmung: 40-90€/m²
- Dachfenster einbauen (Velux, Fakro): 600-1.500€/Stück
- Regenrinne: 25-60€/m
- Fallrohr: 20-50€/m
- Schornstein sanieren: 500-3.000€',

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
- Baum fällen: 200-2.000€
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
        if (str_contains($region, 'sehr hohes Preisniveau')) {
            $regionalerFaktor = 'REGIONAL: +15% auf alle Referenzpreise anwenden (sehr hohes Preisniveau)';
        } elseif (str_contains($region, 'hohes Preisniveau')) {
            $regionalerFaktor = 'REGIONAL: +8% auf alle Referenzpreise anwenden (hohes Preisniveau)';
        } elseif (str_contains($region, 'niedriges')) {
            $regionalerFaktor = 'REGIONAL: -10% auf alle Referenzpreise anwenden (niedriges Preisniveau)';
        } else {
            $regionalerFaktor = 'REGIONAL: Referenzpreise unverändert anwenden (mittleres Preisniveau)';
        }

        $promptBase = 'Du bist ein unabhängiger Sachverständiger für Handwerkerpreise in Deutschland.
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
   - Fussbodenheizung Heizkreisverteiler = NUR 1 Verteiler-Einheit → Markt 800-1.800€ pro Stück
     (menge=24 bedeutet 24 Stück, aber einzelpreis wird pro Stück verglichen!)
   - Montagearbeiten pauschal = Arbeitsleistung, nicht Material → realistischen Arbeitspreis schätzen
   - "Arbeitslohn" Positionen = Stundensatz × geschätzte Stunden ODER Pauschalpreis für Leistung

   WICHTIG für Pauschalpositionen (einheit = "pauschal"):
   - einzelpreis = Pauschalpreis für EINE komplette Leistungseinheit
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
';

        // ── Positionen in Batches à 25 aufteilen ──────────────────────────
        $chunks     = $quote->items->chunk(25);
        $allResults = [];

        foreach ($chunks as $chunkIndex => $chunk) {
            $chunkJson = $chunk->map(fn($item) => [
                'id'          => $item->id,
                'titel'       => $item->title,
                'typ'         => $item->type === 'labor' ? 'Arbeit' : 'Material',
                'menge'       => $item->quantity,
                'einheit'     => $item->unit,
                'einzelpreis' => (float) $item->unit_price,
                'gesamtpreis' => (float) $item->total_price,
            ])->values()->toJson(JSON_UNESCAPED_UNICODE);

            $fullPrompt = $promptBase . $chunkJson . '

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
                ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
                    'model'       => 'gpt-4o',
                    'messages'    => [['role' => 'user', 'content' => $fullPrompt]],
                    'max_tokens'  => 8000,
                    'temperature' => 0.1,
                ]);

                $content     = $response->json('choices.0.message.content');
                $content     = trim(preg_replace('/```json\s*|\s*```/', '', $content ?? ''));
                $batchResult = json_decode($content, true);

                if (is_array($batchResult)) {
                    $allResults = array_merge($allResults, $batchResult);
                    Log::info('Preischeck Batch ' . ($chunkIndex + 1) . '/' . $chunks->count() . ' OK', [
                        'quote_id' => $quote->id,
                        'items'    => $chunk->count(),
                        'results'  => count($batchResult),
                    ]);
                } else {
                    Log::warning('Preischeck Batch ' . ($chunkIndex + 1) . ' JSON ungültig', [
                        'content' => substr($content, 0, 200),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Preischeck Batch ' . ($chunkIndex + 1) . ' Fehler', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (empty($allResults)) {
            return response()->json(['error' => 'KI-Analyse fehlgeschlagen.'], 500);
        }

        // Gesamtbewertung
        $bewertungen   = array_column($allResults, 'bewertung');
        $gesamtScore   = $this->calculateGesamtScore($bewertungen);
        $abweichungen  = array_column($allResults, 'abweichung_prozent');
        $avgAbweichung = count($abweichungen) > 0
            ? round(array_sum($abweichungen) / count($abweichungen), 1)
            : 0;

        Log::info('Preischeck fertig', [
            'quote_id'     => $quote->id,
            'total_items'  => $quote->items->count(),
            'analyzed'     => count($allResults),
            'batches'      => $chunks->count(),
            'gesamt_score' => $gesamtScore,
            'plz'          => $plz,
        ]);

        return response()->json([
            'items'            => $allResults,
            'gesamt_bewertung' => $gesamtScore,
            'avg_abweichung'   => $avgAbweichung,
            'region'           => $region,
            'plz'              => $plz,
        ]);
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