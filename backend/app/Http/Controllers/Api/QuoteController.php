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
        $query = $request->user()->company->quotes()
            ->with('customer')
            ->withCount('items')
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('quote_number', 'like', "%{$search}%")
                    ->orWhere('project_title', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($c) use ($search) {
                        $c->where('company_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = min((int) $request->input('per_page', 12), 50);
        $quotes = $query->paginate($perPage);

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
    'project_description' => $request->boolean('use_ai', true)
        ? 'required|string|min:10|max:5000'
        : 'required|string|max:5000',
    'customer_id' => 'nullable|exists:customers,id',
    'project_address' => 'nullable|string|max:500',
    'use_ai' => 'boolean',
]);

        $company = $request->user()->company;

        // Trial-Limit prüfen — nur relevant wenn KI genutzt wird
    if ($request->input('use_ai', true) && !$company->canGenerateQuote()) {
        return response()->json([
            'message' => 'Dein Testzeitraum ist ausgeschöpft — 5 kostenlose Angebote sind erstellt. Upgrade für unbegrenzte Angebote.',
            'trial_limit_reached' => true,
        ], 403);
    }

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

                if ($company->plan === 'trial') {
            $company->increment('trial_quotes_used');
        }

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

        $tradeLabel = \App\Services\TradeReferenceService::getLabel($trade);


     $tradeReferenz = \App\Services\TradeReferenceService::getPrices($trade);

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