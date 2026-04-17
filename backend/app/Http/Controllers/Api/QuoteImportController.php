<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QuoteImportController extends Controller
{
    public function importFromPdf(Request $request): JsonResponse
    {

      Log::info('Import PDF Request', [
        'has_file' => $request->hasFile('pdf'),
        'files' => array_keys($request->allFiles()),
        'content_type' => $request->header('Content-Type'),
    ]);
        $request->validate([
            'pdf'              => 'required|file|mimetypes:application/pdf,application/octet-stream|max:51200',
            'customer_id'      => 'nullable|exists:customers,id',
            'project_address'  => 'nullable|string|max:500',
        ]);

        $company = $request->user()->company;

        // ── 1. PDF Text extrahieren ──────────────────────────────────────────
        $pdfPath = $request->file('pdf')->getRealPath();
        $text    = '';

  // pdftotext bevorzugen (Server hat poppler-utils)
$pdfPath = $request->file('pdf')->getRealPath();
$text = '';

// Blitzschnelle Scan-Erkennung: nur 1 Seite prüfen (< 1 Sekunde)
$pdftotext = trim(shell_exec('which pdftotext') ?? '');
if (!empty($pdftotext)) {
    $rawText = shell_exec('pdftotext -layout -f 1 -l 1 ' . escapeshellarg($pdfPath) . ' - 2>/dev/null');
    $textClean = trim(str_replace(["\f", "\r", "\n", " "], '', $rawText ?? ''));
    $text = empty($textClean) ? '' : ($rawText ?? '');
}

// Fallback: smalot/pdfparser (lokal ohne poppler)


     if (empty(trim($text ?? ''))) {
    // Scan-PDF erkannt → Queue Job starten
    $storedPath = $request->file('pdf')->store('temp/scan_pdfs', 'local');
    $fullPath = storage_path('app/private/' . $storedPath);
    
    // Fallback falls Pfad anders
    if (!file_exists($fullPath)) {
        $fullPath = storage_path('app/' . $storedPath);
    }

    // Leeres Angebot erstellen als Platzhalter
    $quote = Quote::create([
        'company_id'          => $company->id,
        'customer_id'         => $request->customer_id,
        'created_by'          => $request->user()->id,
        'quote_number'        => $company->generateQuoteNumber(),
        'project_title'       => 'Scan wird verarbeitet...',
        'project_description' => 'Aus Scan-PDF importiert',
        'project_address'     => $request->project_address,
        'vat_rate'            => $company->default_vat_rate ?? 19,
        'valid_until'         => now()->addDays($company->quote_validity_days ?? 30),
        'internal_notes'      => 'scan_processing',
    ]);

    // Queue Job dispatchen
    \App\Jobs\ProcessScanPdfJob::dispatch(
        $quote->id,
        $fullPath,
        $company->id,
        $request->user()->id,
    );

    return response()->json([
        'quote'      => $quote,
        'is_scan'    => true,
        'message'    => 'Scan-PDF erkannt. Angebot wird im Hintergrund verarbeitet.',
    ], 202)->header('Connection', 'close');
}

        // Text auf 8000 Zeichen begrenzen
        $text = mb_substr($text, 0, 50000);

        // ── 2. Katalog der Firma laden (für Matching) ────────────────────────
        $catalogMaterials = Material::where('company_id', $company->id)
            ->where('is_active', true)
            ->select('id', 'name', 'selling_price', 'unit', 'category', 'match_code', 'datanorm_article_number')
            ->get();

        // Katalog als kompaktes JSON für den Prompt aufbereiten
        $catalogJson = $catalogMaterials->map(fn($m) => [
            'id'    => $m->id,
            'name'  => $m->name,
            'price' => (float) $m->selling_price,
            'unit'  => $m->unit,
        ])->values()->toJson(JSON_UNESCAPED_UNICODE);

        // ── 3. GPT-4o Prompt mit Katalog-Matching ───────────────────────────
        $apiKey = env('OPENAI_API_KEY');

        $prompt = 'Du bist ein Experte für Handwerkerangebote im SHK- und Elektrobereich.

AUFGABE:
Analysiere das folgende Handwerker-Angebot und extrahiere alle Positionen.
Vergleiche jede Position mit dem EIGENEN KATALOG des Betriebs.
Wenn ein ähnliches Produkt im Katalog vorhanden ist, verwende die catalog_id und den catalog_price.
Wenn kein passendes Produkt gefunden wird, verwende den Preis aus dem Angebot (pdf_price).

EIGENER KATALOG DES BETRIEBS:
' . $catalogJson . '

ANGEBOT ZUM ANALYSIEREN:
' . $text . '

MATCHING-REGELN:
- Vergleiche Produktnamen semantisch (z.B. "Grohe Eurosmart DN15" matcht "Einhebelwaschtischarmatur Grohe Eurosmart DN15")
- Bei Markenartikeln: nur exakt gleiche Marke UND Produktlinie matchen (Grohe ≠ Hansgrohe, Wolf ≠ Vaillant)
- Bei Rohren: gleicher Durchmesser und Material müssen übereinstimmen
- Arbeitsleistung (Std, Stunden, Fachkraft, Helfer, Montage) → type="labor", KEIN Katalog-Match
- Im Zweifel: kein Match, lieber pdf_price nehmen

Antworte NUR mit validem JSON ohne Markdown:
{
  "project_title": "Titel des Projekts",
  "positions": [
    {
      "group_name": "Gruppenname",
      "type": "material oder labor",
      "title": "Bezeichnung der Position",
      "description": "Kurze Beschreibung optional",
      "quantity": 1.0,
      "unit": "Stück",
      "pdf_price": 0.00,
      "catalog_id": null,
      "catalog_price": null,
      "match_confidence": "high|medium|low|none"
    }
  ]
}

Felder:
- pdf_price: Preis aus dem importierten Angebot (immer ausfüllen)
- catalog_id: ID aus dem Katalog wenn Match gefunden, sonst null
- catalog_price: Preis aus dem Katalog wenn Match gefunden, sonst null
- match_confidence: "high" (gleiche Marke+Produkt), "medium" (ähnliches Produkt), "low" (unsicher), "none" (kein Match)
- quantity und preise sind immer Zahlen, kein Tausendertrennzeichen, Punkt als Dezimaltrennzeichen';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(90)->post('https://api.openai.com/v1/chat/completions', [
                'model'      => 'gpt-4o',
                'max_tokens' => 8000,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if (!$response->successful()) {
                Log::error('PDF Import API Error', [
                    'status'   => $response->status(),
                    'response' => $response->body(),
                ]);
                return response()->json([
                    'message' => 'KI-Analyse fehlgeschlagen: ' . $response->body(),
                ], 500);
            }

            // ── 4. JSON aus Antwort extrahieren ─────────────────────────────
            $content = $response->json('choices.0.message.content');
            $content = trim($content);
            $content = preg_replace('/^```json\s*/i', '', $content);
            $content = preg_replace('/^```\s*/i', '', $content);
            $content = preg_replace('/\s*```$/i', '', $content);
            $content = trim($content);

            $data = json_decode($content, true);

            if (!$data || !isset($data['positions']) || count($data['positions']) === 0) {
                Log::error('PDF Import JSON ungültig', ['content' => $content]);
                return response()->json([
                    'message' => 'Keine Positionen gefunden. Bitte prüfe das PDF.',
                ], 422);
            }

            // ── 5. Angebot erstellen ─────────────────────────────────────────
            $quote = Quote::create([
                'company_id'          => $company->id,
                'customer_id'         => $request->customer_id,
                'created_by'          => $request->user()->id,
                'quote_number'        => $company->generateQuoteNumber(),
                'project_title'       => $data['project_title'] ?? 'Importiertes Angebot',
                'project_description' => 'Aus PDF importiert',
                'project_address'     => $request->project_address,
                'vat_rate'            => $company->default_vat_rate,
                'valid_until'         => now()->addDays($company->quote_validity_days),
            ]);

            // ── 6. Positionen mit Katalog-Matching erstellen ─────────────────
            $position       = 1;
            $matchedCount   = 0;
            $unmatchedCount = 0;

            foreach ($data['positions'] as $pos) {
                $catalogId   = null;
                $finalPrice  = 0.0;
                $description = $pos['description'] ?? null;
                $confidence  = $pos['match_confidence'] ?? 'none';

                // Preis aus PDF bereinigen
                $pdfPriceRaw = str_replace(['.', ','], ['', '.'], $pos['pdf_price'] ?? 0);
                $pdfPrice    = is_numeric($pdfPriceRaw) ? (float) $pdfPriceRaw : 0.0;

                // Katalog-Match prüfen
                if (
                    !empty($pos['catalog_id']) &&
                    in_array($confidence, ['high', 'medium']) &&
                    $pos['type'] !== 'labor'
                ) {
                    // Katalog-Material validieren (gehört zur Firma?)
                    $material = $catalogMaterials->firstWhere('id', $pos['catalog_id']);

                    if ($material) {
                        $catalogId  = $material->id;
                        $finalPrice = (float) $material->selling_price;
                        $matchedCount++;

                        // Hinweis in Beschreibung
                        $description = ($description ? $description . ' | ' : '')
                            . '✓ Aus Katalog: ' . $material->name;
                    } else {
                        // Katalog-ID ungültig → PDF-Preis nehmen
                        $finalPrice = $pdfPrice;
                        $unmatchedCount++;
                        $description = ($description ? $description . ' | ' : '')
                            . '⚠ Preis aus PDF – bitte prüfen';
                    }
                } else {
                    // Kein Match → PDF-Preis
                    $finalPrice = $pdfPrice;

                    if ($pos['type'] !== 'labor') {
                        $unmatchedCount++;
                        $description = ($description ? $description . ' | ' : '')
                            . '⚠ Preis aus PDF – bitte prüfen';
                    }
                }

                // Menge bereinigen
                $quantityRaw = str_replace(['.', ','], ['', '.'], $pos['quantity'] ?? 1);
                $quantity    = is_numeric($quantityRaw) ? (float) $quantityRaw : 1.0;

                QuoteItem::create([
                    'quote_id'        => $quote->id,
                    'position_number' => $position,
                    'sort_order'      => $position,
                    'group_name'      => $pos['group_name'] ?? 'Allgemein',
                    'type'            => in_array($pos['type'], ['material', 'labor']) ? $pos['type'] : 'material',
                    'title'           => $pos['title'] ?? 'Position',
                    'description'     => $description,
                    'quantity'        => $quantity,
                    'unit'            => $pos['unit'] ?? 'Stück',
                    'unit_price'      => $finalPrice,
                    'material_id'     => $catalogId,
                    'is_ai_generated' => true,
                ]);

                $position++;
            }

            $quote->recalculate();
            $quote->refresh()->load('items');

            Log::info('PDF Import mit Katalog-Matching erfolgreich', [
                'quote_id'       => $quote->id,
                'total'          => count($data['positions']),
                'matched'        => $matchedCount,
                'unmatched'      => $unmatchedCount,
            ]);

            return response()->json([
                'quote'            => $quote,
                'positions_count'  => count($data['positions']),
                'matched_count'    => $matchedCount,
                'unmatched_count'  => $unmatchedCount,
                'message'          => "PDF importiert: {$matchedCount} aus Katalog, {$unmatchedCount} bitte prüfen.",
            ], 201);

        } catch (\Exception $e) {
            Log::error('PDF Import Exception', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Fehler beim Import: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
 * Status eines Scan-PDF Jobs abfragen
 */
public function scanStatus(Request $request, int $quoteId): JsonResponse
{
    $quote = Quote::where('id', $quoteId)
        ->where('company_id', $request->user()->company_id)
        ->firstOrFail();

    $notes = $quote->internal_notes ?? '';

    if (str_starts_with($notes, 'scan_processing')) {
        return response()->json([
            'status'  => 'processing',
            'message' => 'Scan wird verarbeitet...',
        ]);
    }

    if (str_starts_with($notes, 'scan_done')) {
        $quote->load('items');
        return response()->json([
            'status'  => 'done',
            'message' => 'Angebot fertig!',
            'quote'   => $quote,
        ]);
    }

    if (str_starts_with($notes, 'scan_failed')) {
        return response()->json([
            'status'  => 'failed',
            'message' => str_replace('scan_failed: ', '', $notes),
        ]);
    }

    // Normales Angebot (kein Scan)
    return response()->json([
        'status'  => 'done',
        'quote'   => $quote->load('items'),
    ]);
}
}