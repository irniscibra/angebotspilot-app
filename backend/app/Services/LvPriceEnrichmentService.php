<?php

namespace App\Services;

class LvPriceEnrichmentService
{
    /**
     * Versucht für jede importierte LV-Position einen echten Preis zu finden:
     * 1. Katalog-Match (Datanorm) für Material-Positionen
     * 2. Eigener Stundensatz für Arbeits-Positionen mit Stunden-Einheit
     *
     * Bewusst KEINE KI-Schätzung hier - das ist ein eigener, größerer
     * Ausbauschritt mit eigenem Test. Diese Methode ist rein deterministisch:
     * entweder ein echter Treffer, oder der Preis bleibt 0 und die Position
     * wird weiterhin klar als "manuell zu prüfen" markiert.
     *
     * Ändert die Positionen NICHT im Original-Array, sondern gibt eine neue,
     * angereicherte Version zurück - rein additiv, kein Risiko für die
     * bestehende Struktur-Extraktion.
     */
    public function enrich(array $positions, $companyMaterials, float $defaultHourlyRate): array
    {
        $stopWords = [
            'und', 'mit', 'für', 'der', 'die', 'das', 'ein', 'eine', 'inkl',
            'inklusive', 'von', 'zur', 'zum', 'auf', 'aus', 'den', 'dem',
            'oder', 'gleichwertig', 'bis', 'mm', 'cm',
        ];

        foreach ($positions as &$pos) {
            $pos['resolved_price'] = 0.0;
            $pos['material_id'] = null;
            $pos['price_source'] = 'none';

            $unit = mb_strtolower(trim($pos['unit'] ?? ''));

            // === Arbeitszeit: eigener Stundensatz, kein Katalog nötig ===
            if (($pos['type'] ?? '') === 'labor') {
                if (str_contains($unit, 'std') || str_contains($unit, 'stunde')) {
                    $pos['resolved_price'] = $defaultHourlyRate;
                    $pos['price_source'] = 'hourly_rate';
                }
                continue;
            }

            // === Material: Katalog-Abgleich versuchen ===
            $title = mb_strtolower(trim($pos['title'] ?? ''));
            if (empty($title) || empty($companyMaterials) || count($companyMaterials) === 0) {
                continue;
            }

            $titleWords = $this->extractKeywords($title, $stopWords);
            if (empty($titleWords)) {
                continue;
            }

                // Wörter, die ein fundamental anderes Produkt kennzeichnen -
            // wenn eines davon NUR in einem der beiden Titel vorkommt,
            // wird der Match blockiert, egal wie viele andere Wörter
            // übereinstimmen. Verhindert z.B. "Schuko-Steckdose" fälschlich
            // gegen "Schuko-FUNKsteckdose" zu matchen.
            $distinguishingWords = [
                'funk', 'wlan', 'wifi', 'smart', 'digital', 'elektronisch',
                'led', 'dimmbar', 'wetterfest', 'feuchtraum', 'wasserdicht',
                'aufputz', 'unterputz', 'dreifach', 'zweifach', 'doppel',
            ];

            $bestMatch = null;
            $bestScore = 0;

            foreach ($companyMaterials as $material) {
                $matName = mb_strtolower($material->name);
                $matWords = $this->extractKeywords($matName, $stopWords);

                // Prüfen, ob ein unterscheidendes Wort NUR bei einem der
                // beiden vorkommt - dann sofort ausschließen
                $hasDistinguishingMismatch = false;
                foreach ($distinguishingWords as $dw) {
                    $inTitle = str_contains($title, $dw);
                    $inMaterial = str_contains($matName, $dw);
                    if ($inTitle !== $inMaterial) {
                        $hasDistinguishingMismatch = true;
                        break;
                    }
                }
                if ($hasDistinguishingMismatch) {
                    continue;
                }

                $overlap = 0;
                foreach ($titleWords as $tw) {
                    foreach ($matWords as $mw) {
                        if ($tw === $mw || (strlen($tw) >= 4 && str_contains($mw, $tw))) {
                            $overlap++;
                            break;
                        }
                    }
                }

                // Mindestens 3 statt 2 gemeinsame Wörter - reduziert
                // Zufallstreffer bei kurzen, generischen Bezeichnungen
                if ($overlap >= 3 && $overlap > $bestScore) {
                    $bestScore = $overlap;
                    $bestMatch = $material;
                }
            }

            if ($bestMatch) {
                $pos['resolved_price'] = (float) $bestMatch->selling_price;
                $pos['material_id'] = $bestMatch->id;
                $pos['price_source'] = 'catalog';
            }
        }
        unset($pos);

        return $positions;
    }

    private function extractKeywords(string $text, array $stopWords): array
    {
        $words = preg_split('/[\s\-\/\|,\.;:()]+/', $text);
        $words = array_filter($words, function ($w) use ($stopWords) {
            $w = trim($w);
            return strlen($w) >= 3 && !in_array($w, $stopWords) && !is_numeric($w);
        });
        return array_values(array_unique($words));
    }
}