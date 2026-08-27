<?php

namespace App\Console\Commands;

use App\Services\LvImportService;
use Illuminate\Console\Command;

class TestLvImport extends Command
{
    protected $signature = 'lv:test {path}';
    protected $description = 'Testet den LV-Import mit einer PDF-Datei und zeigt das Ergebnis';

    public function handle(LvImportService $service)
    {
        $path = $this->argument('path');

        if (!file_exists($path)) {
            $this->error("Datei nicht gefunden: {$path}");
            return 1;
        }

        $this->info('Starte Import... (kann bei großen Dokumenten mehrere Minuten dauern)');

        $start = microtime(true);
        $positions = $service->importPdf($path);
        $duration = round(microtime(true) - $start, 1);

        $this->info("Fertig in {$duration}s. Gefundene Positionen: " . count($positions));

        // Speziell die "Nebenarbeiten" checken - das ist unser bekannter
        // Vergleichsmaßstab (echte Werte: 450, 600, 1050 Stk, 1350m)
        $this->info("\n=== Positionen mit 'Bohrung' oder 'Wandschlitz' im Titel ===");
        foreach ($positions as $p) {
            if (str_contains(strtolower($p['title'] ?? ''), 'bohrung')
                || str_contains(strtolower($p['title'] ?? ''), 'wandschlitz')) {
                $this->line(sprintf(
                    "%s | %s | %s %s",
                    $p['position_number'] ?? '?',
                    $p['title'] ?? '?',
                    $p['quantity'] ?? '?',
                    $p['unit'] ?? '?'
                ));
            }
        }

        // Vollständiges Ergebnis für genauere Prüfung speichern
        file_put_contents(
            storage_path('app/lv_import_test_result.json'),
            json_encode($positions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
        $this->info("\nVolles Ergebnis gespeichert in: storage/app/lv_import_test_result.json");

        return 0;
    }
}