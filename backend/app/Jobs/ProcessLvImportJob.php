<?php

namespace App\Jobs;

use App\Models\Material;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Services\LvImportService;
use App\Services\LvPriceEnrichmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessLvImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Große LVs (150+ Seiten) brauchen viele einzelne KI-Aufrufe -
    // Standard-Timeout von 60s reicht nicht, hier großzügig bemessen.
    public $timeout = 900;

    public function __construct(
        public int $quoteId,
        public string $filePath,
        public string $originalFilename,
        public int $companyId,
        public int $userId,
    ) {}

    public function handle(LvImportService $service, LvPriceEnrichmentService $priceService): void
    {
        $quote = Quote::find($this->quoteId);
        if (!$quote) {
            Log::error('LV-Import Job: Angebot nicht mehr gefunden', ['quote_id' => $this->quoteId]);
            return;
        }

        try {
            $positions = $service->importPdf($this->filePath);

            if (empty($positions)) {
                $quote->update(['internal_notes' => 'lv_import_failed: Keine Positionen im Dokument gefunden. Bitte prüfen Sie, ob es sich um ein Text-PDF handelt (kein Scan).']);
                return;
            }

            // Preis-Anreicherung: Katalog-Match + eigener Stundensatz.
            // Rein deterministisch, keine KI-Schätzung (das ist ein
            // separater, späterer Ausbauschritt).
            $company = $quote->company;
            $companyMaterials = Material::where('company_id', $this->companyId)
                ->where('is_active', true)
                ->get();
            $positions = $priceService->enrich(
                $positions,
                $companyMaterials,
                (float) ($company->default_hourly_rate ?? 65)
            );

            // Bestehende Positionen löschen (falls Job erneut läuft)
            $quote->items()->delete();

            $sortOrder = 0;
            foreach ($positions as $pos) {
                $sortOrder++;

                $description = trim($pos['description'] ?? '');
                $prefixParts = [];
                if (!empty($pos['position_number'])) {
                    $prefixParts[] = "Pos. {$pos['position_number']}";
                }
                if (!empty($pos['fabrikat'])) {
                    $prefixParts[] = "Fabrikat: {$pos['fabrikat']}";
                }
                $prefix = !empty($prefixParts) ? '[' . implode(' | ', $prefixParts) . '] ' : '';

                // Preishinweis nur zeigen, wenn tatsächlich noch kein Preis
                // gefunden wurde - bei Katalog-Match oder eigenem Stundensatz
                // ist der Preis bereits verlässlich, kein Warnhinweis nötig.
                $resolvedPrice = $pos['resolved_price'] ?? 0.0;
                if ($resolvedPrice > 0) {
                    $priceNote = match ($pos['price_source'] ?? '') {
                        'catalog' => '✓ Preis aus Ihrem Materialkatalog übernommen.',
                        'hourly_rate' => '✓ Ihr hinterlegter Stundensatz wurde übernommen.',
                        default => '',
                    };
                } else {
                    $priceNote = '💰 Preis noch nicht ermittelt – bitte eintragen.';
                    if (!empty($pos['quantity_unclear'])) {
                        $priceNote .= ' ℹ Menge im Dokument nicht eindeutig angegeben – bitte prüfen.';
                    }
                }

                $fullDescription = trim($prefix . $description . ' ' . $priceNote);

                // group_name ist in der DB längenbegrenzt (VARCHAR) - der volle
                // Hierarchie-Pfad kann bei tief verschachtelten LV-Kapiteln
                // diese Grenze überschreiten. Sicherheitshalber kürzen, damit
                // der Import nie an einem einzelnen zu langen Pfad scheitert.
                $groupName = $pos['group_path'] ?? 'Ohne Zuordnung';
                if (mb_strlen($groupName) > 250) {
                    $groupName = mb_substr($groupName, 0, 247) . '...';
                }

                $unitPrice = $resolvedPrice;
                $quantity = $pos['quantity'] ?? 1;

                QuoteItem::create([
                    'quote_id' => $quote->id,
                    'position_number' => $sortOrder,
                    'group_name' => $groupName,
                    'type' => in_array($pos['type'] ?? '', ['material', 'labor']) ? $pos['type'] : 'material',
                    'title' => $pos['title'] ?? 'Position',
                    'description' => $fullDescription,
                    'quantity' => $quantity,
                    'unit' => $pos['unit'] ?? 'Stück',
                    'unit_price' => $unitPrice,
                    'total_price' => $quantity * $unitPrice,
                    'is_ai_generated' => true,
                    'sort_order' => $sortOrder,
                    'material_id' => $pos['material_id'] ?? null,
                ]);
            }

            $titleFromFile = pathinfo($this->originalFilename, PATHINFO_FILENAME);

            $quote->update([
                'project_title' => "LV-Import: {$titleFromFile}",
                'project_description' => 'Importierte Ausschreibung – ' . count($positions) . ' Positionen automatisch übernommen. Preise bitte prüfen und ergänzen.',
                'internal_notes' => 'lv_import_done',
            ]);

            $quote->recalculate();

            Log::info('LV-Import Job erfolgreich abgeschlossen', [
                'quote_id' => $quote->id,
                'positions_count' => count($positions),
            ]);
        } catch (\Throwable $e) {
            Log::error('LV-Import Job fehlgeschlagen', [
                'quote_id' => $this->quoteId,
                'error' => $e->getMessage(),
            ]);
            $quote->update(['internal_notes' => 'lv_import_failed: ' . $e->getMessage()]);
        } finally {
            // Temporäre Datei aufräumen, unabhängig von Erfolg/Fehler
            if (file_exists($this->filePath)) {
                @unlink($this->filePath);
            }
        }
    }
}