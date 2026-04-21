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
            // Prüfen ob Text-PDF oder Scan-PDF
            $rawText   = shell_exec('pdftotext -layout ' . escapeshellarg($this->pdfPath) . ' - 2>/dev/null');
            $textClean = trim(str_replace(["\f", "\r", "\n", " "], '', $rawText ?? ''));

            if (!empty($textClean)) {
                Log::info('Text-PDF erkannt', ['quote_id' => $this->quoteId]);
                $positions = $this->extractPositionsFromText($rawText);
            } else {
                Log::info('Scan-PDF erkannt', ['quote_id' => $this->quoteId]);
                $positions = $scanService->extractPositions($this->pdfPath);
            }

            if (empty($positions)) {
                $this->markFailed('Keine Positionen gefunden');
                return;
            }

            Log::info('Positionen extrahiert', [
                'quote_id' => $this->quoteId,
                'count'    => count($positions)
            ]);

            $this->savePositions($positions);

            Quote::where('id', $this->quoteId)->update([
                'internal_notes' => 'scan_done',
            ]);

            Log::info('ProcessScanPdfJob fertig', ['quote_id' => $this->quoteId]);

        } catch (\Throwable $e) {
            Log::error('ProcessScanPdfJob Fehler', [
                'quote_id' => $this->quoteId,
                'error'    => $e->getMessage()
            ]);
            $this->markFailed($e->getMessage());
        }
    }

    private function extractPositionsFromText(string $text): array
    {
        $text = mb_substr($text, 0, 50000);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
            'Content-Type'  => 'application/json',
        ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
            'model'       => 'gpt-4o',
            'max_tokens'  => 16000,
            'temperature' => 0.1,
            'messages'    => [
                [
                    'role'    => 'user',
     'content' => 'Du bist ein Experte für Handwerkerangebote. Extrahiere ALLE Positionen Angebot als JSON Array lückenlos, auch wenn es viele sind.
Kürze keine Beschreibungen ab. 
Jede Position hat folgende Felder: pos, beschreibung, menge, einheit, einzelpreis.
Gib NUR das JSON Array zurück, kein Text davor oder danach.
Ignoriere Zwischensummen, Gesamtsummen und Seitentitel.

DEUTSCHES ZAHLENFORMAT - SEHR WICHTIG:
- Punkt = Tausendertrennzeichen: 76.847,37 = sechsundsiebzigtausend
- Komma = Dezimaltrennzeichen: 1.430,80 = eintausendvierhundertdreißig
- einzelpreis IMMER als Dezimalzahl: 76.847,37 → 76847.37, 1.430,80 → 1430.80, 364,96 → 364.96
- NIEMALS durch 1000 teilen oder runden!

ANGEBOT:
' . $text,
                ],
            ],
        ]);

        if (!$response->successful()) {
            Log::error('Text-PDF API Fehler', ['status' => $response->status()]);
            return [];
        }

        $content = $response->json('choices.0.message.content');
        $content = trim(preg_replace('/```json\s*|\s*```/', '', $content ?? ''));
        $positions = json_decode($content, true);

        if (!is_array($positions)) {
            Log::warning('Text-PDF JSON ungültig', ['content' => substr($content, 0, 200)]);
            return [];
        }
        Log::info('Extrahierte Positionen RAW', [
    'quote_id' => $this->quoteId,
    'positions' => array_slice($positions, 0, 5) // erste 5
]);

        return $positions;
    }

    private function savePositions(array $positions): void
    {
        $quote   = Quote::findOrFail($this->quoteId);
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

        // Originale Mengen, Einheiten und Einzelpreise sichern
        $originals = [];
        foreach ($positions as $i => $pos) {
            $originals[$i] = [
                'menge'        => floatval(str_replace(',', '.', (string)($pos['menge'] ?? 1))),
                'einheit'      => $pos['einheit'] ?? 'Stk',
                'beschreibung' => $pos['beschreibung'] ?? '',
                'einzelpreis'  => floatval(str_replace(',', '.', (string)($pos['einzelpreis'] ?? 0))),
            ];
        }

        // Katalog-Matching in Batches à 20
        $chunks      = array_chunk(array_keys($originals), 20, true);
        $matchResults = [];

        foreach ($chunks as $chunkNum => $indices) {
            Log::info('Katalog-Matching Batch ' . ($chunkNum + 1) . '/' . count($chunks), [
                'quote_id' => $this->quoteId
            ]);

            $batchInput = [];
            foreach ($indices as $i) {
                $batchInput[] = [
                    'index'        => $i,
                    'beschreibung' => mb_substr($originals[$i]['beschreibung'], 0, 200),
                    'einheit'      => $originals[$i]['einheit'],
                    'einzelpreis'  => $originals[$i]['einzelpreis'],
                ];
            }

            $batchJson = json_encode($batchInput, JSON_UNESCAPED_UNICODE);

            $prompt = 'Du bist ein Experte für Handwerkerangebote in Deutschland.

AUFGABE:
Analysiere diese Positionen. Für jede Position:
1. Vergleiche mit dem KATALOG – wenn ähnliches Produkt → catalog_id + catalog_price
2. Bestimme den Typ: "material", "labor" oder "lumpsum" (Pauschale)
3. Schätze bei keinem Katalog-Match einen realistischen EINZELPREIS (Netto, ohne MwSt)

TYP-REGELN (sehr wichtig!):
- "lumpsum" = Pauschale mit Pauschalpreis: Wärmepumpenanlage pauschal, Rohr Form- und Verbindungsstücke pauschal, Montagearbeiten pauschal, Fussbodenheizung Heizkreisverteiler pauschal, Küchen-Anschluss pauschal, Fallstrang-Errichtung pauschal, Kamerabefahrung, Kernbohrung, Anfahrtspauschale, Entsorgungspauschale
- "labor" = NUR reine Arbeitsstunden ohne festen Pauschalpreis: Arbeitslohn pro Stunde, Monteurstunde, Fachkraft/Std
- "material" = einzelne Materialien/Produkte: Rohre, Fittings, Ventile, Geräte, Armaturen

WICHTIG für Pauschalen (lumpsum):
- Wenn einzelpreis > 0 im Input → verwende diesen als estimated_price
- Wenn einheit = "pauschal" → fast immer lumpsum
- estimated_price = EINZELPREIS pro Einheit (nicht Gesamtpreis!)

EIGENER KATALOG:
' . $catalogJson . '

POSITIONEN:
' . $batchJson . '

Antworte NUR mit validem JSON Array:
[
  {
    "index": 0,
    "type": "material|labor|lumpsum",
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

            $content     = $response->json('choices.0.message.content');
            $content     = trim(preg_replace('/```json\s*|\s*```/', '', $content ?? ''));
            $batchResult = json_decode($content, true);

            if (!is_array($batchResult)) {
                Log::warning('Katalog-Matching Batch ' . ($chunkNum + 1) . ' JSON ungültig');
                foreach ($indices as $i) {
                    $matchResults[$i] = [
                        'type'            => $this->isLaborPosition($originals[$i]['beschreibung'], $originals[$i]['einheit']),
                        'catalog_id'      => null,
                        'catalog_price'   => null,
                        'estimated_price' => $originals[$i]['einzelpreis'] ?: 0,
                        'match_confidence'=> 'none',
                    ];
                }
                continue;
            }

            foreach ($batchResult as $result) {
                $i = $result['index'] ?? null;
                if ($i === null || !isset($originals[$i])) continue;

                // Wenn KI keinen Preis schätzt aber wir einen aus dem PDF haben → nehmen wir den PDF-Preis
                $estimatedPrice = (float) ($result['estimated_price'] ?? 0);
                if ($estimatedPrice <= 0 && $originals[$i]['einzelpreis'] > 0) {
                    $estimatedPrice = $originals[$i]['einzelpreis'];
                }

                $matchResults[$i] = [
                    'type'            => $result['type'] ?? 'material',
                    'catalog_id'      => $result['catalog_id'] ?? null,
                    'catalog_price'   => $result['catalog_price'] ?? null,
                    'estimated_price' => $estimatedPrice,
                    'match_confidence'=> $result['match_confidence'] ?? 'none',
                ];
            }
        }

        // Quote Items speichern
        $subtotalMaterials = 0;
        $subtotalLabor     = 0;
        $matchedCount      = 0;
        $estimatedCount    = 0;

        foreach ($originals as $index => $orig) {
            $beschreibung = $orig['beschreibung'];
            $menge        = $orig['menge'] ?: 1;
            $einheit      = $orig['einheit'];

            $match      = $matchResults[$index] ?? null;
            $type       = $match['type'] ?? $this->isLaborPosition($beschreibung, $einheit);
            $confidence = $match['match_confidence'] ?? 'none';
            $catalogId  = null;

            // Preis bestimmen
            if ($type === 'labor') {
                // Reine Arbeitsstunden → Stundensatz
                $finalPrice  = $hourlyRate;
                $description = $beschreibung;
            } elseif ($type === 'lumpsum') {
                // Pauschale → Preis aus PDF oder KI-Schätzung, KEIN Stundensatz
                $finalPrice = $match['estimated_price'] ?? $orig['einzelpreis'];
                if ($finalPrice <= 0) {
                    $finalPrice = $orig['einzelpreis'] ?: 0;
                }
                $description = $beschreibung . ' | ⚠ KI-Schätzpreis – bitte prüfen';
                $estimatedCount++;
            } elseif (
                !empty($match['catalog_id']) &&
                in_array($confidence, ['high', 'medium'])
            ) {
                // Katalog-Match
                $material = $catalog->firstWhere('id', $match['catalog_id']);
                if ($material) {
                    $finalPrice  = (float) $material->selling_price;
                    $description = $beschreibung . ' | ✓ Aus Katalog: ' . $material->name;
                    $catalogId   = $material->id;
                    $matchedCount++;
                } else {
                    $finalPrice  = (float) ($match['estimated_price'] ?? $orig['einzelpreis'] ?? 0);
                    $description = $beschreibung . ' | ⚠ KI-Schätzpreis – bitte prüfen';
                    $estimatedCount++;
                }
            } else {
                // KI-Schätzpreis oder PDF-Preis
                $finalPrice  = (float) ($match['estimated_price'] ?? $orig['einzelpreis'] ?? 0);
                $description = $beschreibung . ' | ⚠ KI-Schätzpreis – bitte prüfen';
                $estimatedCount++;
            }

            $gp = round($menge * $finalPrice, 2);

            // Für Kalkulation: lumpsum geht zu Materials
            if ($type === 'labor') {
                $subtotalLabor += $gp;
            } else {
                $subtotalMaterials += $gp;
            }

            // DB-type: nur 'material' oder 'labor' (lumpsum → material mit pauschal-Einheit)
            $dbType = ($type === 'labor') ? 'labor' : 'material';

            QuoteItem::create([
                'quote_id'        => $this->quoteId,
                'position_number' => $index + 1,
                'sort_order'      => $index + 1,
                'group_name'      => 'Allgemein',
                'type'            => $dbType,
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

    /**
     * Fallback-Klassifizierung wenn KI-Matching fehlschlägt.
     * Gibt 'labor', 'lumpsum' oder 'material' zurück.
     */
    private function isLaborPosition(string $text, string $einheit = ''): string
    {
        $text    = strtolower($text);
        $einheit = strtolower($einheit);

        // Reine Stundenlohn-Keywords
        $laborKeywords = ['monteurstunde', 'fachkraft/std', 'arbeitsstunde', 'lohnstunde', 'std.', '/std', 'pro stunde'];
        foreach ($laborKeywords as $kw) {
            if (str_contains($text, $kw)) return 'labor';
        }

        // Pauschale-Keywords
        $lumpsumKeywords = [
            'pauschal', 'pauschale', 'kamerabefahrung', 'kernbohrung', 'anfahrt',
            'entsorgung', 'montagearbeiten', 'arbeitslohn', 'verlegearbeiten',
            'installationsarbeiten', 'einbauarbeiten', 'demontagearbeiten',
            'inbetriebnahme', 'schmutzzulage', 'reinigung', 'stemm'
        ];
        foreach ($lumpsumKeywords as $kw) {
            if (str_contains($text, $kw)) return 'lumpsum';
        }

        if ($einheit === 'pauschal' || $einheit === 'psch') return 'lumpsum';

        return 'material';
    }

    private function markFailed(string $reason): void
    {
        Quote::where('id', $this->quoteId)->update([
            'internal_notes' => 'scan_failed: ' . $reason
        ]);
    }
}