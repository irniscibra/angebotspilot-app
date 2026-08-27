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
    public function estimate(array $positions): array
    {
        // Nur Material-Positionen ohne bisherigen Preis schätzen
        $toEstimate = [];
        foreach ($positions as $idx => $pos) {
            if (
                ($pos['type'] ?? '') === 'material'
                && ($pos['price_source'] ?? 'none') === 'none'
                && !empty($pos['title'])
            ) {
                $toEstimate[$idx] = $pos;
            }
        }

        if (empty($toEstimate)) {
            return $positions;
        }

        Log::info('LV-Marktpreisschätzung gestartet', ['count' => count($toEstimate)]);

        $batches = array_chunk($toEstimate, 15, true);

        foreach ($batches as $batch) {
            $estimates = $this->estimateBatch($batch);
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

    private function estimateBatch(array $batch): array
    {
        $itemsText = '';
        foreach ($batch as $pos) {
            $itemsText .= sprintf(
                "- Pos %s: \"%s\" | Fabrikat: %s | Menge: %s %s\n  Beschreibung: %s\n",
                $pos['position_number'] ?? '?',
                $pos['title'] ?? '',
                $pos['fabrikat'] ?? 'nicht angegeben',
                $pos['quantity'] ?? '?',
                $pos['unit'] ?? '',
                mb_substr($pos['description'] ?? '', 0, 300)
            );
        }

        $prompt = <<<PROMPT
Du bist Experte für Baupreise und Materialkosten in Deutschland, Stand 2026.

Schätze für JEDE der folgenden Positionen einen realistischen NETTO-
Einzelpreis pro Einheit für den deutschen Markt. Das sind Positionen aus
einer Elektro-Ausschreibung mit oft konkret genanntem Fabrikat.

WICHTIG:
- Preise sind Fachhandel-/Großhandelspreise, keine Endkundenpreise mit
  hoher Marge - realistisch kalkuliert, wie ein Elektro-Großhändler sie
  anbieten würde.
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