<?php

namespace App\Services;

use Smalot\PdfParser\Parser as PdfParser;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class LvImportService
{
    /**
     * Liest eine LV-PDF ein und extrahiert alle Positionen strukturiert.
     * Strategie: Statt das komplette Dokument (oft 50.000+ Tokens) in einem
     * Rutsch an die KI zu schicken (unzuverlässig, "Lost in the Middle"),
     * wird das Dokument an den GAEB-typischen "Abschnitt"-Überschriften in
     * kleine, thematisch geschlossene Blöcke zerlegt. Jeder Block ist klein
     * genug, dass die KI zuverlässig JEDE Einzelposition mit ihrer echten
     * Menge erfasst, statt zusammenzufassen.
     */
    public function importPdf(string $filePath): array
    {
        $fullText = $this->extractText($filePath);
        $sections = $this->splitIntoSections($fullText);

        Log::info('LV-Import: Dokument in Abschnitte zerlegt', [
            'total_sections' => count($sections),
            'total_chars' => strlen($fullText),
        ]);

        $allPositions = [];
        foreach ($sections as $section) {
            $positions = $this->extractPositionsFromSection($section);
            foreach ($positions as $pos) {
                $pos['group_path'] = $section['group_path'];
                $allPositions[] = $pos;
            }
        }

        return $allPositions;
    }

    /**
     * Extrahiert den kompletten Text aus der PDF, Seite für Seite.
     */
    private function extractText(string $filePath): string
    {
        $parser = new PdfParser();
        $pdf = $parser->parseFile($filePath);

        $text = '';
        foreach ($pdf->getPages() as $page) {
            $text .= $page->getText() . "\n";
        }

        return $text;
    }

    /**
     * Zerlegt den Volltext an GAEB-typischen "Abschnitt"-Überschriften
     * (z.B. "1.5.2 Abschnitt Nebenarbeiten") in einzelne Blöcke.
     * Jeder Block behält seinen vollen Hierarchie-Pfad (Titel > Bereich >
     * Abschnitt) für die spätere Gruppierung im Angebot.
     */
    private function splitIntoSections(string $text): array
    {
        $lines = explode("\n", $text);

        $sections = [];
        $currentTitel = '';
        $currentBereich = '';
        $currentAbschnitt = '';
        $currentBuffer = [];

        $flushSection = function () use (&$sections, &$currentTitel, &$currentBereich, &$currentAbschnitt, &$currentBuffer) {
            if (!empty($currentBuffer) && $currentAbschnitt) {
                $sections[] = [
                    'group_path' => trim("{$currentTitel} > {$currentBereich} > {$currentAbschnitt}", ' >'),
                    'text' => implode("\n", $currentBuffer),
                ];
            }
            $currentBuffer = [];
        };

        foreach ($lines as $line) {
                       // "1 Titel Starkstromanlagen KG 440]"
            if (preg_match('/^\d+\s+Titel\s+(.+)$/u', trim($line), $m)) {
                $flushSection();
                $currentTitel = trim($m[1]);
                // Bereich/Abschnitt zurücksetzen - sonst werden die
                // "Technische Vorbemerkungen" des neuen Kapitels (die vor der
                // ersten echten Bereich/Abschnitt-Zeile stehen) fälschlich
                // noch dem alten Kapitel zugeordnet.
                $currentBereich = '';
                $currentAbschnitt = '';
                continue;
            }
            // "1.1 Bereich Niederspannungsschaltanlagen KG 443]"
            if (preg_match('/^\d+\.\d+\s+Bereich\s+(.+)$/u', trim($line), $m)) {
                $flushSection();
                $currentBereich = trim($m[1]);
                continue;
            }
            // "1.1.1 Abschnitt Zählerverteilung"
            if (preg_match('/^\d+(?:\.\d+){2}\s+Abschnitt\s+(.+)$/u', trim($line), $m)) {
                $flushSection();
                $currentAbschnitt = trim($m[1]);
                continue;
            }

            $currentBuffer[] = $line;
        }
        $flushSection();

        return $sections;
    }

    /**
     * Schickt EINEN Abschnitt (typischerweise 1-5 Seiten, klein genug für
     * zuverlässige Verarbeitung) an die KI. Aufgabe ist NICHT Preise zu
     * erfinden, sondern JEDE im Text vorkommende Position mit ihrer exakten
     * Menge/Einheit zu übernehmen - reine Struktur-Extraktion.
     */
    private function extractPositionsFromSection(array $section): array
    {
        // Sehr kurze/leere Abschnitte (z.B. reine Vorbemerkungen ohne
        // Positionen) überspringen, spart unnötige KI-Aufrufe.
        if (strlen(trim($section['text'])) < 100) {
            return [];
        }

        $prompt = <<<PROMPT
Du bekommst einen Ausschnitt aus einem Leistungsverzeichnis (LV) einer
Bauausschreibung. Deine EINZIGE Aufgabe ist es, JEDE einzelne Position
(Leistungsposition) exakt so zu übernehmen, wie sie im Text steht.

REGELN - UNBEDINGT EINHALTEN:
1. ERFINDE KEINE PREISE. Preisfelder in Ausschreibungen sind für den Bieter
   leer (z.B. "EP ......... GP ............") - das ist normal und korrekt so.
2. ERFINDE KEINE MENGEN. Übernimm die Menge EXAKT wie im Text angegeben
   (z.B. "450Stk", "1.350m", "1Stk"). Wenn keine Menge angegeben ist
   (z.B. nur "EP ...... - Nur EP -" ohne Zahl davor), setze quantity auf
   1 und "quantity_unclear": true.
3. LASS KEINE POSITION AUS. Auch kleine, unscheinbare Positionen zählen.
4. TRENNE Material und Arbeit korrekt:
   - "Stundenlöhne", "Monteurstunden", "Helferstunden" u.ä. = "labor"
   - Physische Gegenstände/Materialien = "material"
5. Erkenne die Positionsnummer (z.B. "1.1.1.1", "U01", "1.5.2.1") und
   übernimm sie ins Feld "position_number".
6. Nutze als "title" die kurze Bezeichnung, als "description" die
   ausführlichere technische Beschreibung (Normen, Fabrikat, Typ etc.
   soweit vorhanden).

Antworte AUSSCHLIESSLICH als valides JSON in diesem Format:
{
    "positions": [
        {
            "position_number": "1.5.2.1",
            "title": "Bohrungen bis 30 cm",
            "description": "Bohrung durch Geschoss-Decken und Mauern herstellen und rauchdicht verschließen, Durchmesser 40mm, Deckenstärke bis 30cm",
            "type": "material",
            "quantity": 450,
            "unit": "Stück",
            "quantity_unclear": false,
            "fabrikat": "Hager oder gleichwertig"
        }
    ]
}

Wenn ein Fabrikat/Hersteller explizit genannt wird (z.B. "Fabrikat: Hager"),
trage ihn im Feld "fabrikat" ein, sonst leer lassen.

TEXT-AUSSCHNITT:
{$section['text']}
PROMPT;

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => $prompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.1,
                'max_tokens' => 4000,
            ]);

            $content = $response->choices[0]->message->content;
            $result = json_decode($content, true);

            return $result['positions'] ?? [];
        } catch (\Throwable $e) {
            Log::error('LV-Import: Fehler bei Abschnitt-Extraktion', [
                'group_path' => $section['group_path'],
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}