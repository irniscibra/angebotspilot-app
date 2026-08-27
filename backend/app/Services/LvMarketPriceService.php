<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class LvMarketPriceService
{
    /**
     * Schätzt Marktpreise für Material-Positionen, die weder einen Katalog-
     * Treffer noch einen eigenen Stundensatz bekommen haben. Arbeitet in
     * Batches statt Einzelaufrufen, um Kosten niedrig zu halten.
     *
     * WICHTIG: Nur für "material"-Positionen mit erkennbarem Produkt/
     * Fabrikat - Pauschal-Arbeitspositionen (z.B. "Inbetriebnahme",
     * "Messungen") werden bewusst NICHT geschätzt, weil ihr Preis zu stark
     * vom Gesamtprojekt abhängt, um ihn ohne Kontext seriös zu schätzen.
     *
     * Jede geschätzte Position wird mit price_source = 'ai_estimated'
     * markiert - klar unterscheidbar von echten Katalogpreisen.
     */
       public function estimate(array $positions, string $tradeLabel = 'Bauleistungen'): array
    {
        // Material- UND Arbeits-Positionen ohne bisherigen Preis schätzen.
        // Arbeitspositionen mit Stück-/Pauschal-Einheit (statt Std) haben
        // keinen direkten Stundensatz-Bezug und brauchen daher ebenfalls
        // eine Marktpreis-Schätzung, z.B. "Bohrung herstellen: 8€/Stück".
        $toEstimate = [];
        foreach ($positions as $idx => $pos) {
            $isUnpriced = ($pos['price_source'] ?? 'none') === 'none';
            $hasTitle = !empty($pos['title']);
            $isMaterial = ($pos['type'] ?? '') === 'material';
            $isLaborWithoutHourlyUnit = ($pos['type'] ?? '') === 'labor'
                && !str_contains(mb_strtolower($pos['unit'] ?? ''), 'std')
                && !str_contains(mb_strtolower($pos['unit'] ?? ''), 'stunde');

            if ($isUnpriced && $hasTitle && ($isMaterial || $isLaborWithoutHourlyUnit)) {
                $toEstimate[$idx] = $pos;
            }
        }

        if (empty($toEstimate)) {
            return $positions;
        }

        Log::info('LV-Marktpreisschätzung gestartet', ['count' => count($toEstimate)]);

               $batches = array_chunk($toEstimate, 15, true);

        foreach ($batches as $batch) {
            $estimates = $this->estimateBatch($batch, $tradeLabel);
            foreach ($estimates as $posNumber => $price) {
                foreach ($batch as $idx => $pos) {
                    if (($pos['position_number'] ?? '') === $posNumber) {
                        $positions[$idx]['resolved_price'] = $price;
                        $positions[$idx]['price_source'] = 'ai_estimated';
                        break;
                    }
                }
            }
        }

        return $positions;
    }

       private function estimateBatch(array $batch, string $tradeLabel): array
    {
              $itemsText = '';
        foreach ($batch as $pos) {
            $itemsText .= sprintf(
                "- Pos %s [%s]: \"%s\" | Fabrikat: %s | Menge: %s %s\n  Beschreibung: %s\n",
                $pos['position_number'] ?? '?',
                ($pos['type'] ?? '') === 'labor' ? 'ARBEIT' : 'MATERIAL',
                $pos['title'] ?? '',
                $pos['fabrikat'] ?? 'nicht angegeben',
                $pos['quantity'] ?? '?',
                $pos['unit'] ?? '',
                mb_substr($pos['description'] ?? '', 0, 300)
            );
        }

            $prompt = <<<PROMPT
Du bist Experte für Baupreise, Materialkosten UND Handwerker-Arbeitspreise
in Deutschland, Stand 2026.

Schätze für JEDE der folgenden Positionen einen realistischen NETTO-
Einzelpreis pro Einheit für den deutschen Markt. Das sind Positionen aus
einer Ausschreibung im Bereich "{$tradeLabel}".

Es gibt ZWEI Arten von Positionen, jeweils unterschiedlich zu bepreisen:
- MATERIAL: ein geliefertes Produkt (oft mit Fabrikat genannt) - Preis =
  Fachhandel-/Großhandels-Einkaufspreis, keine Endkundenpreise mit hoher
  Marge.
- ARBEIT: eine reine Tätigkeit ohne Produkt (z.B. "Bohrung herstellen",
  "Wandschlitz fräsen") - Preis = üblicher Verrechnungspreis pro Einheit
  für diese Tätigkeit (Zeit + Aufwand + Kleinmaterial wie Verschluss-
  masse), NICHT der Wert eines Produkts, denn es wird kein Produkt
  verkauft.

WICHTIG:
- Wenn ein Fabrikat genannt ist, orientiere dich an dessen bekannter
  Preisklasse (z.B. Gira/Hager sind Markenqualität, nicht Billigware).
- Bei Kabeln/Leitungen: Preis PRO METER, nicht für die Gesamtlänge.
- Sei realistisch, nicht großzügig - im Zweifel eher die untere Grenze
  einer plausiblen Preisspanne wählen.

POSITIONEN:
{$itemsText}

Antworte AUSSCHLIESSLICH als valides JSON:
{
    "preise": [
        {"position": "1.2.4.2", "einzelpreis_netto": 12.50}
    ]
}
PROMPT;

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $prompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
                'max_tokens' => 2000,
            ]);

            $content = $response->choices[0]->message->content;
            $result = json_decode($content, true);

            $prices = [];
            foreach ($result['preise'] ?? [] as $entry) {
                if (!empty($entry['position']) && isset($entry['einzelpreis_netto'])) {
                    $prices[$entry['position']] = (float) $entry['einzelpreis_netto'];
                }
            }

            return $prices;
        } catch (\Throwable $e) {
            Log::error('LV-Marktpreisschätzung: Batch fehlgeschlagen', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}