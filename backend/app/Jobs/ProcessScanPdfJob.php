<?php

namespace App\Jobs;

use App\Models\Material;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Services\ScanPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessScanPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(
        public readonly int $quoteId,
        public readonly string $pdfPath,
        public readonly int $companyId,
        public readonly int $userId,
    ) {}

    public function handle(ScanPdfService $scanService): void
    {
        Log::info('ProcessScanPdfJob gestartet', ['quote_id' => $this->quoteId]);

        Quote::where('id', $this->quoteId)->update([
            'internal_notes' => 'scan_processing'
        ]);

        try {
            $positions = $scanService->extractPositions($this->pdfPath);

            if (empty($positions)) {
                $this->markFailed('Keine Positionen gefunden');
                return;
            }

            Log::info('Positionen extrahiert', [
                'quote_id' => $this->quoteId,
                'count' => count($positions)
            ]);

            $this->savePositions($positions);

            Quote::where('id', $this->quoteId)->update([
                'internal_notes' => 'scan_done',
            ]);

            Log::info('ProcessScanPdfJob fertig', ['quote_id' => $this->quoteId]);

        } catch (\Throwable $e) {
            Log::error('ProcessScanPdfJob Fehler', [
                'quote_id' => $this->quoteId,
                'error' => $e->getMessage()
            ]);
            $this->markFailed($e->getMessage());
        }
    }

   private function savePositions(array $positions): void
{
    $quote = Quote::findOrFail($this->quoteId);
    $company = $quote->company;

    $hourlyRate = (float) ($company->default_hourly_rate ?? 65);

    // Katalog laden
    $catalog = Material::where('company_id', $this->companyId)
        ->where('is_active', true)
        ->select('id', 'name', 'selling_price', 'unit', 'category')
        ->get();

    $catalogJson = $catalog->map(fn($m) => [
        'id'    => $m->id,
        'name'  => $m->name,
        'price' => (float) $m->selling_price,
        'unit'  => $m->unit,
    ])->values()->toJson(JSON_UNESCAPED_UNICODE);

    // ── Schritt 1: Originale Mengen und Einheiten sichern ──────────────
    // Key = erste 60 Zeichen der Beschreibung
    $originals = [];
    foreach ($positions as $i => $pos) {
        $originals[$i] = [
            'menge'        => floatval(str_replace(',', '.', (string)($pos['menge'] ?? 1))),
            'einheit'      => $pos['einheit'] ?? 'Stk',
            'beschreibung' => $pos['beschreibung'] ?? '',
        ];
    }

    // ── Schritt 2: Katalog-Matching in Batches à 20 ────────────────────
    // Wir schicken NUR Beschreibung und Index – KEINE Mengen
    $chunks = array_chunk(array_keys($originals), 20, true);
    $matchResults = []; // index → [catalog_id, catalog_price, type, match_confidence, estimated_price]

    foreach ($chunks as $chunkNum => $indices) {
        Log::info('Katalog-Matching Batch ' . ($chunkNum + 1) . '/' . count($chunks), [
            'quote_id' => $this->quoteId
        ]);

        $batchInput = [];
        foreach ($indices as $i) {
            $batchInput[] = [
                'index'       => $i,
                'beschreibung' => mb_substr($originals[$i]['beschreibung'], 0, 200),
            ];
        }

        $batchJson = json_encode($batchInput, JSON_UNESCAPED_UNICODE);

        $prompt = 'Du bist ein Experte für Handwerkerangebote in Deutschland.

AUFGABE:
Analysiere diese Positionen. Für jede Position:
1. Vergleiche mit dem KATALOG – wenn ähnliches Produkt → catalog_id + catalog_price
2. Kein Katalog-Match → schätze realistischen deutschen EINZELPREIS (Netto, ohne MwSt)
3. Erkenne ob es Arbeitsleistung ist (Monteur, Helfer, Montage, Demontage, Stemm, Kernbohrung, Anfahrt, Entsorgung, Kamerabefahrung, Schmutzzulage)

WICHTIG:
- estimated_price = EINZELPREIS pro Einheit (nicht Gesamtpreis!)
- Beispiele: SML-Rohr DN100/lfdm → 8-12€, Bogen DN100 → 15-25€, WC → 150-300€
- Arbeitsleistung: type="labor" (kein Preis nötig, kommt aus Settings)

EIGENER KATALOG:
' . $catalogJson . '

POSITIONEN (JSON Array mit index und beschreibung):
' . $batchJson . '

Antworte NUR mit validem JSON Array:
[
  {
    "index": 0,
    "type": "material oder labor",
    "catalog_id": null,
    "catalog_price": null,
    "estimated_price": 0.00,
    "match_confidence": "high|medium|low|none"
  }
]';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
            'Content-Type'  => 'application/json',
        ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
            'model'       => 'gpt-4o',
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'max_tokens'  => 4000,
            'temperature' => 0.1,
        ]);

        $content = $response->json('choices.0.message.content');
        $content = trim(preg_replace('/```json\s*|\s*```/', '', $content ?? ''));
        $batchResult = json_decode($content, true);

        if (!is_array($batchResult)) {
            Log::warning('Katalog-Matching Batch ' . ($chunkNum + 1) . ' JSON ungültig');
            // Fallback: alle Positionen ohne Match
            foreach ($indices as $i) {
                $matchResults[$i] = [
                    'type'             => $this->isLaborPosition($originals[$i]['beschreibung']) ? 'labor' : 'material',
                    'catalog_id'       => null,
                    'catalog_price'    => null,
                    'estimated_price'  => 0,
                    'match_confidence' => 'none',
                ];
            }
            continue;
        }

        foreach ($batchResult as $result) {
            $i = $result['index'] ?? null;
            if ($i === null || !isset($originals[$i])) continue;
            $matchResults[$i] = [
                'type'             => $result['type'] ?? 'material',
                'catalog_id'       => $result['catalog_id'] ?? null,
                'catalog_price'    => $result['catalog_price'] ?? null,
                'estimated_price'  => (float) ($result['estimated_price'] ?? 0),
                'match_confidence' => $result['match_confidence'] ?? 'none',
            ];
        }
    }

    // ── Schritt 3: Quote Items speichern ───────────────────────────────
    $subtotalMaterials = 0;
    $subtotalLabor     = 0;
    $matchedCount      = 0;
    $estimatedCount    = 0;

    foreach ($originals as $index => $orig) {
        $beschreibung = $orig['beschreibung'];
        $menge        = $orig['menge'] ?: 1;   // originale Menge aus Vision-Scan
        $einheit      = $orig['einheit'];

        $match      = $matchResults[$index] ?? null;
        $type       = $match['type'] ?? ($this->isLaborPosition($beschreibung) ? 'labor' : 'material');
        $confidence = $match['match_confidence'] ?? 'none';
        $catalogId  = null;

        // Preis bestimmen
        if ($type === 'labor') {
            // Immer Stundensatz aus Settings
            $finalPrice  = $hourlyRate;
            $description = $beschreibung;
        } elseif (
            !empty($match['catalog_id']) &&
            in_array($confidence, ['high', 'medium'])
        ) {
            // Katalog-Match validieren
            $material = $catalog->firstWhere('id', $match['catalog_id']);
            if ($material) {
                $finalPrice  = (float) $material->selling_price;
                $description = $beschreibung . ' | ✓ Aus Katalog: ' . $material->name;
                $catalogId   = $material->id;
                $matchedCount++;
            } else {
                $finalPrice  = (float) ($match['estimated_price'] ?? 0);
                $description = $beschreibung . ' | ⚠ KI-Schätzpreis – bitte prüfen';
                $estimatedCount++;
            }
        } else {
            // KI-Schätzpreis
            $finalPrice  = (float) ($match['estimated_price'] ?? 0);
            $description = $beschreibung . ' | ⚠ KI-Schätzpreis – bitte prüfen';
            $estimatedCount++;
        }

        $gp = round($menge * $finalPrice, 2);

        if ($type === 'labor') {
            $subtotalLabor += $gp;
        } else {
            $subtotalMaterials += $gp;
        }

        QuoteItem::create([
            'quote_id'        => $this->quoteId,
            'position_number' => $index + 1,
            'sort_order'      => $index + 1,
            'group_name'      => 'Allgemein',
            'type'            => $type,
            'title'           => mb_substr($beschreibung, 0, 100),
            'description'     => $description,
            'quantity'        => $menge,
            'unit'            => $einheit ?: 'Stk',
            'unit_price'      => $finalPrice,
            'total_price'     => $gp,
            'material_id'     => $catalogId,
            'is_ai_generated' => true,
        ]);
    }

    $subtotalNet = $subtotalMaterials + $subtotalLabor;
    $vatRate     = (float) ($company->default_vat_rate ?? 19);
    $vatAmount   = round($subtotalNet * ($vatRate / 100), 2);

    $quote->update([
        'project_title'      => 'Importiertes Angebot (Scan)',
        'subtotal_materials' => $subtotalMaterials,
        'subtotal_labor'     => $subtotalLabor,
        'subtotal_net'       => $subtotalNet,
        'vat_amount'         => $vatAmount,
        'total_gross'        => $subtotalNet + $vatAmount,
    ]);

    Log::info('Scan Import fertig', [
        'quote_id'  => $this->quoteId,
        'total'     => count($originals),
        'matched'   => $matchedCount,
        'estimated' => $estimatedCount,
        'labor'     => round($subtotalLabor, 2),
        'materials' => round($subtotalMaterials, 2),
    ]);
}

    private function isLaborPosition(string $text): bool
    {
        $keywords = ['monteur', 'montage', 'installation', 'einbau', 'arbeit', 'stunde', 'std', 'fachkraft', 'helfer', 'inbetriebnahme', 'demontage', 'entsorgung', 'reinigen', 'stemm', 'bohren', 'kernbohrung', 'schmutzzulage', 'anfahrt'];
        $text = strtolower($text);
        foreach ($keywords as $kw) {
            if (str_contains($text, $kw)) return true;
        }
        return false;
    }

    private function markFailed(string $reason): void
    {
        Quote::where('id', $this->quoteId)->update([
            'internal_notes' => 'scan_failed: ' . $reason
        ]);
    }
}