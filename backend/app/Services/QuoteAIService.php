<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Material;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\AiUsageLog;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class QuoteAIService
{
    /**
     * Generiert ein Angebot aus einer Projektbeschreibung.
     * Nutzt den Materialkatalog des Unternehmens für echte Preise.
     */
    public function generateQuote(Quote $quote, string $description): array
    {
        $company = $quote->company;

        // Alle Materialien laden (für Kontext UND Matching)
        $allMaterials = Material::where('company_id', $company->id)
            ->where('is_active', true)
            ->get();

        // Intelligenten Katalog-Kontext bauen (relevante Artikel zuerst)
        $catalogContext = $this->buildSmartCatalogContext($allMaterials, $description);

        $systemPrompt = $this->buildSystemPrompt($company, $catalogContext);

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $description],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.3,
            'max_tokens' => 4000,
        ]);

        $content = $response->choices[0]->message->content;
        $usage = $response->usage;

        // KI-Nutzung loggen
        AiUsageLog::create([
            'company_id' => $company->id,
            'user_id' => $quote->created_by,
            'quote_id' => $quote->id,
            'action' => 'generate_quote',
            'model' => 'gpt-4o',
            'prompt_tokens' => $usage->promptTokens,
            'completion_tokens' => $usage->completionTokens,
            'total_tokens' => $usage->totalTokens,
            'cost_cents' => $this->calculateCost($usage->promptTokens, $usage->completionTokens),
        ]);

        $aiResult = json_decode($content, true);

        if (!$aiResult || !isset($aiResult['groups'])) {
            Log::error('AI returned invalid response', ['content' => $content]);
            throw new \RuntimeException('KI-Antwort konnte nicht verarbeitet werden. Bitte versuchen Sie es erneut.');
        }

        // Angebot mit KI-Daten aktualisieren
        $quote->update([
            'project_title' => $aiResult['project_title'] ?? $quote->project_title,
            'ai_prompt' => $description,
            'ai_response' => $aiResult,
            'ai_model' => 'gpt-4o',
            'ai_tokens_used' => $usage->totalTokens,
        ]);

        // Positionen erstellen – mit intelligentem Katalog-Matching
        $matchLog = $this->createQuoteItems($quote, $aiResult['groups'], $allMaterials);

        // Match-Log im Angebot speichern (für Debugging/Transparenz)
        $existingResponse = $quote->ai_response ?? [];
        $existingResponse['catalog_matches'] = $matchLog;
        $quote->update(['ai_response' => $existingResponse]);

        // Angebot neu kalkulieren
        $quote->recalculate();

        return $aiResult;
    }

    /**
     * Baut einen intelligenten Katalog-Kontext.
     * Statt einfach abzuschneiden, werden relevante Artikel priorisiert.
     *
     * Strategie:
     * 1. Schlüsselwörter aus der Beschreibung extrahieren
     * 2. Materialien nach Relevanz zur Beschreibung sortieren
     * 3. Relevante zuerst, Rest danach (bis Zeichenlimit)
     */
    private function buildSmartCatalogContext($allMaterials, string $description): string
    {
        if ($allMaterials->isEmpty()) {
            return '';
        }

        $descLower = strtolower($description);

        // Schlüsselwörter aus Beschreibung extrahieren
        $descWords = preg_split('/[\s\-\/\|,\.;:!?]+/', $descLower);
        $descWords = array_filter($descWords, fn($w) => strlen($w) >= 3);
        $descWords = array_unique(array_values($descWords));

        // Materialien mit Relevanz-Score versehen
        $scored = [];
        foreach ($allMaterials as $mat) {
            $matName = strtolower($mat->name);
            $matCategory = strtolower($mat->category ?? '');

            $relevance = 0;
            foreach ($descWords as $word) {
                if (str_contains($matName, $word)) {
                    $relevance += 2;
                }
                if (str_contains($matCategory, $word)) {
                    $relevance += 1;
                }
            }

            $scored[] = [
                'material' => $mat,
                'relevance' => $relevance,
            ];
        }

        // Nach Relevanz sortieren (höchste zuerst), bei Gleichstand nach Name
        usort($scored, function ($a, $b) {
            if ($b['relevance'] !== $a['relevance']) {
                return $b['relevance'] - $a['relevance'];
            }
            return strcmp($a['material']->name, $b['material']->name);
        });

        // Katalog-Text aufbauen (max 6000 Zeichen für große Kataloge)
        $maxChars = 6000;
        $lines = [];
        $currentCategory = '';
        $totalLen = 0;
        $includedCount = 0;
        $totalCount = count($scored);

        foreach ($scored as $entry) {
            $mat = $entry['material'];

            // Kategorie-Header
            $catLine = '';
            if ($mat->category !== $currentCategory) {
                $currentCategory = $mat->category;
                $catLine = "\n[{$currentCategory}]";
            }

            $sku = $mat->datanorm_article_number ?: $mat->sku;
            $price = number_format((float)$mat->selling_price, 2, '.', '');
            $line = "- Art.{$sku}: {$mat->name} | {$mat->unit} | {$price} EUR" .
                    ($mat->supplier ? " | {$mat->supplier}" : '');

            $lineLen = strlen($catLine) + strlen($line) + 2;

            if ($totalLen + $lineLen > $maxChars) {
                break;
            }

            if ($catLine) {
                $lines[] = $catLine;
            }
            $lines[] = $line;
            $totalLen += $lineLen;
            $includedCount++;
        }

        // Info über ausgelassene Artikel
        $skipped = $totalCount - $includedCount;
        if ($skipped > 0) {
            $lines[] = "\n({$includedCount} von {$totalCount} Artikeln angezeigt – weitere verfügbar)";
        }

        Log::info("Katalog-Kontext gebaut", [
            'total_materials' => $totalCount,
            'included' => $includedCount,
            'skipped' => $skipped,
            'context_length' => $totalLen,
        ]);

        return implode("\n", $lines);
    }

    /**
     * Baut den System-Prompt mit Firmendaten und Materialkatalog.
     */
    private function buildSystemPrompt(Company $company, string $catalogContext): string
    {
        $hourlyRate = number_format($company->default_hourly_rate, 2, '.', '');
        $vatRate = number_format($company->default_vat_rate, 2, '.', '');

        // Katalog-Abschnitt nur wenn Materialien vorhanden
        $catalogSection = '';
        if (!empty($catalogContext)) {
            $catalogSection = <<<CATALOG

MATERIALKATALOG DES BETRIEBS (echte Verkaufspreise – BEVORZUGT verwenden!):
{$catalogContext}

WICHTIG ZUM KATALOG:
- Verwende IMMER Materialien aus dem Katalog wenn passende vorhanden sind!
- Nutze die EXAKTEN Preise und Artikelnummern aus dem Katalog.
- Gib bei Katalog-Materialien die EXAKTE Artikelnummer im "sku"-Feld zurück (z.B. "SUN-30K-G04").
- Gib den EXAKTEN Namen aus dem Katalog im "title"-Feld zurück – nicht umformulieren!
- Nur wenn kein passendes Material im Katalog ist, schätze den Marktpreis.
- Kennzeichne Katalog-Materialien mit "from_catalog": true
- ACHTE bei Wechselrichtern, Heizkörpern etc. auf die RICHTIGE GRÖSSE (kW, Typ)!
CATALOG;
        }

        return <<<PROMPT
Du bist ein erfahrener Handwerksmeister und Kalkulator in Deutschland.
Erstelle aus der Projektbeschreibung ein detailliertes, professionelles Angebot.

FIRMENDATEN:
- Standard-Stundensatz Monteur: {$hourlyRate} EUR/Std (netto)
- MwSt-Satz: {$vatRate}%
- Standort: Deutschland
{$catalogSection}

REGELN FÜR DIE KALKULATION:
1. Gliedere das Angebot in logische Gewerke-Gruppen (z.B. "Demontage & Entsorgung", "Sanitärinstallation", "Rohrleitungen", "Heizungsarbeiten", etc.)
2. Trenne IMMER Material und Arbeitsleistung als separate Positionen
3. Kalkuliere realistische Mengen und Preise für den deutschen Markt (Stand 2026)
4. Verwende marktübliche Markenmaterialien (Grohe, Hansgrohe, Viega, Geberit, Buderus, Vaillant etc.)
5. Plane eine Kleinmaterial-Pauschale ein (5-8% der Materialkosten) für Dichtungen, Schrauben, Silikon etc.
6. Berücksichtige Anfahrt, Baustelleneinrichtung und -reinigung wenn sinnvoll
7. Arbeitszeiten realistisch kalkulieren – lieber etwas großzügiger als zu knapp
8. Bei Heizungsarbeiten: EnEV/GEG Normen berücksichtigen
9. Bei Sanitärarbeiten: DIN und DVGW Normen berücksichtigen

MATERIALPREISE (Richtwerte netto – NUR verwenden wenn KEIN Katalog-Artikel passt):
- Kupferrohr 15mm: 10-15 EUR/m
- Kupferrohr 22mm: 15-20 EUR/m
- Verbundrohr 16mm: 5-8 EUR/m
- HT-Rohr DN50: 7-10 EUR/m
- HT-Rohr DN100: 12-18 EUR/m
- Standard WC (Villeroy & Boch / Duravit): 300-600 EUR
- Unterputzspülkasten Geberit: 150-250 EUR
- Waschtisch Keramik: 200-500 EUR
- Waschtischarmatur (Hansgrohe/Grohe): 150-350 EUR
- Duscharmatur Unterputz: 350-600 EUR
- Duschwanne flach: 250-450 EUR
- Badewanne Standard: 400-800 EUR
- Gas-Brennwertgerät (Buderus/Vaillant): 3.000-6.000 EUR
- Heizkörper Typ 22 (60x100): 200-350 EUR
- Fußbodenheizung: 30-50 EUR/m²

STUNDENSÄTZE:
- Monteur/Geselle: {$hourlyRate} EUR/Std
- Helfer: 45.00 EUR/Std

ANTWORTE AUSSCHLIESSLICH als valides JSON in exakt diesem Format:
{
    "project_title": "Kurzer, professioneller Projekttitel",
    "groups": [
        {
            "name": "1. Gruppenname",
            "items": [
                {
                    "type": "material",
                    "title": "Materialbezeichnung mit Hersteller/Spezifikation",
                    "description": "Kurze Beschreibung oder Spezifikation",
                    "quantity": 1.0,
                    "unit": "Stück",
                    "unit_price": 0.00,
                    "sku": "EXAKTE Artikelnummer aus Katalog falls vorhanden, sonst leer",
                    "from_catalog": true
                },
                {
                    "type": "labor",
                    "title": "Beschreibung der Arbeitsleistung",
                    "description": "Was wird gemacht, wenn keine Vorhanden, erstelle eine realistische Beschreibung anhand gesetzlicher Informationen zur Position",
                    "quantity": 2.0,
                    "unit": "Std",
                    "unit_price": {$hourlyRate},
                    "sku": "",
                    "from_catalog": false
                }
            ]
        }
    ],
    "notes": "Wichtige Hinweise zur Ausführung, Normen, Voraussetzungen",
    "estimated_days": 3
}

WICHTIG:
- Einheiten nur: "Stück", "Meter", "m²", "m³", "Std", "pauschal", "Liter", "kg"
- Preise sind NETTO (ohne MwSt)
- Jede Position muss "type" haben: "material" oder "labor"
- Gruppen nummerieren: "1. ...", "2. ...", etc.
- Mindestens 2 Gruppen, realistisch detailliert
- Bei jedem Material "sku" und "from_catalog" angeben
- Bei Katalog-Artikeln: EXAKTE Artikelnummer und EXAKTEN Preis verwenden!
PROMPT;
    }

    /**
     * Erstellt QuoteItems aus der KI-Antwort.
     * Matcht KI-Vorschläge mit echten Katalog-Materialien.
     *
     * Matching-Strategie (Priorität):
     * 1. Exakte SKU-Übereinstimmung
     * 2. Namens-Match mit Zahlen/Größen-Validierung
     * 3. Preis-Nähe als Bonus
     *
     * Gibt ein Match-Log zurück für Transparenz.
     */
    private function createQuoteItems(Quote $quote, array $groups, $allMaterials): array
    {
        // Bestehende Positionen löschen (bei Regenerierung)
        $quote->items()->delete();

        // Index für schnelles SKU-Matching
        $bysku = [];
        foreach ($allMaterials as $mat) {
            $key1 = strtolower(trim($mat->datanorm_article_number ?: ''));
            $key2 = strtolower(trim($mat->sku ?: ''));
            if ($key1) $bysku[$key1] = $mat;
            if ($key2) $bysku[$key2] = $mat;
        }

        $position = 1;
        $sortOrder = 0;
        $matchLog = [];

        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $unitPrice = $item['unit_price'] ?? 0;
                $materialId = null;
                $matchedMaterial = null;
                $matchMethod = 'none';

                // Nur für Material-Positionen matchen
                if (($item['type'] ?? 'material') === 'material') {
                    $result = $this->findCatalogMatch($item, $allMaterials, $bysku);
                    $matchedMaterial = $result['material'];
                    $matchMethod = $result['method'];
                }

                if ($matchedMaterial) {
                    $unitPrice = (float) $matchedMaterial->selling_price;
                    $materialId = $matchedMaterial->id;

                    Log::info("Katalog-Match: [{$matchMethod}]", [
                        'ai_title' => $item['title'],
                        'ai_price' => $item['unit_price'] ?? 0,
                        'matched_name' => $matchedMaterial->name,
                        'matched_price' => $unitPrice,
                        'matched_sku' => $matchedMaterial->sku,
                        'score' => $result['score'] ?? 0,
                    ]);
                }

                // Match-Log für Transparenz
                $matchLog[] = [
                    'ai_title' => $item['title'],
                    'ai_price' => $item['unit_price'] ?? 0,
                    'matched' => $matchedMaterial ? true : false,
                    'method' => $matchMethod,
                    'catalog_name' => $matchedMaterial?->name,
                    'catalog_price' => $matchedMaterial ? $unitPrice : null,
                ];

                QuoteItem::create([
                    'quote_id' => $quote->id,
                    'position_number' => $position++,
                    'group_name' => $group['name'],
                    'type' => $item['type'] ?? 'material',
                    'title' => $matchedMaterial ? $matchedMaterial->name : $item['title'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'unit' => $matchedMaterial ? $matchedMaterial->unit : ($item['unit'] ?? 'Stück'),
                    'unit_price' => $unitPrice,
                    'total_price' => ($item['quantity'] ?? 1) * $unitPrice,
                    'is_ai_generated' => true,
                    'sort_order' => $sortOrder++,
                    'material_id' => $materialId,
                ]);
            }
        }

        // Zusammenfassung loggen
        $matched = count(array_filter($matchLog, fn($m) => $m['matched']));
        $total = count($matchLog);
        Log::info("Katalog-Matching Zusammenfassung: {$matched}/{$total} Positionen gematcht", [
            'quote_id' => $quote->id,
        ]);

        return $matchLog;
    }

    /**
     * Findet das beste Katalog-Material für eine KI-Position.
     *
     * Matching-Strategie (Prioritätsreihenfolge):
     * 1. Exakte SKU → sofortiger Treffer
     * 2. Name + Numerische Werte (kW, mm, DN, Typ) müssen matchen
     * 3. Preis-Nähe als zusätzlicher Faktor
     *
     * Sicherheitsmechanismen:
     * - Numerische Werte (kW, Leistung, Größe) müssen exakt stimmen
     * - Mindest-Score für ein Match (verhindert falsche Zuordnungen)
     * - Bei mehreren möglichen Matches: bester Score gewinnt
     */
    private function findCatalogMatch(array $aiItem, $allMaterials, array $bysku): array
    {
        $noMatch = ['material' => null, 'method' => 'none', 'score' => 0];

        // === Strategie 1: Exakte SKU ===
        $sku = strtolower(trim($aiItem['sku'] ?? ''));
        if (!empty($sku) && isset($bysku[$sku])) {
            return [
                'material' => $bysku[$sku],
                'method' => 'exact_sku',
                'score' => 1.0,
            ];
        }

        $aiTitle = strtolower($aiItem['title'] ?? '');
        $aiPrice = (float) ($aiItem['unit_price'] ?? 0);

        if (empty($aiTitle)) {
            return $noMatch;
        }

        // Numerische Werte aus KI-Titel extrahieren (kW, mm, DN, Typ etc.)
        $aiNumbers = $this->extractNumericValues($aiTitle);

        // Stoppwörter
        $stopWords = [
            'und', 'mit', 'für', 'der', 'die', 'das', 'ein', 'eine', 'inkl',
            'inklusive', 'von', 'zur', 'zum', 'auf', 'aus', 'den', 'dem',
            'set', 'komplett', 'neu', 'neue', 'neuer', 'neues',
        ];

        // Schlüsselwörter aus dem KI-Titel
        $aiWords = $this->extractKeywords($aiTitle, $stopWords);

        if (empty($aiWords)) {
            return $noMatch;
        }

        $bestMatch = null;
        $bestScore = 0;
        $bestMethod = 'none';

        foreach ($allMaterials as $mat) {
            $matName = strtolower($mat->name);
            $matPrice = (float) $mat->selling_price;

            // Numerische Werte aus Katalog-Name extrahieren
            $matNumbers = $this->extractNumericValues($matName);

            // === HARTE PRÜFUNG: Numerische Werte müssen passen ===
            // Wenn die KI "30kW" schreibt und der Katalog "10kW" hat → KEIN Match
            if (!$this->numericValuesCompatible($aiNumbers, $matNumbers)) {
                continue;
            }

            // Schlüsselwörter aus Katalog-Name
            $matWords = $this->extractKeywords($matName, $stopWords);

            // === Wort-Übereinstimmung zählen ===
            $matchingWords = 0;
            $matchedWordList = [];

            foreach ($aiWords as $aiWord) {
                foreach ($matWords as $matWord) {
                    if (
                        $aiWord === $matWord ||
                        (strlen($aiWord) >= 4 && str_contains($matWord, $aiWord)) ||
                        (strlen($matWord) >= 4 && str_contains($aiWord, $matWord))
                    ) {
                        $matchingWords++;
                        $matchedWordList[] = $aiWord;
                        break;
                    }
                }
            }

            // Mindestens 2 Wörter müssen übereinstimmen
            if ($matchingWords < 2) {
                continue;
            }

            // === Score berechnen ===

            // Wort-Score: Wie viele der KI-Wörter matchen
            $wordScore = $matchingWords / max(count($aiWords), 1);

            // Zahlen-Score: Bonus wenn numerische Werte exakt übereinstimmen
            $numberScore = $this->calculateNumberScore($aiNumbers, $matNumbers);

            // Preis-Score: Bonus wenn Preise ähnlich sind
            $priceScore = 0;
            if ($aiPrice > 0 && $matPrice > 0) {
                $priceDiff = abs($aiPrice - $matPrice) / max($aiPrice, $matPrice);
                if ($priceDiff < 0.03) {
                    $priceScore = 1.0;
                } elseif ($priceDiff < 0.10) {
                    $priceScore = 0.7;
                } elseif ($priceDiff < 0.25) {
                    $priceScore = 0.3;
                }
            }

            // Gewichteter Gesamtscore
            $totalScore = ($wordScore * 0.5) + ($numberScore * 0.3) + ($priceScore * 0.2);

            // Minimum-Score für ein Match
            $minScore = 0.35;

            if ($totalScore > $bestScore && $totalScore >= $minScore) {
                $bestScore = $totalScore;
                $bestMatch = $mat;
                $bestMethod = 'fuzzy_name';
            }
        }

        if ($bestMatch) {
            return [
                'material' => $bestMatch,
                'method' => $bestMethod,
                'score' => round($bestScore, 3),
            ];
        }

        return $noMatch;
    }

    /**
     * Extrahiert numerische Werte mit ihren Einheiten aus einem Text.
     * z.B. "Deye 30kW 3-phasig" → ['30kw' => 30, '3phasig' => 3]
     * z.B. "HT-Rohr DN100 1000mm" → ['dn100' => 100, '1000mm' => 1000]
     */
    private function extractNumericValues(string $text): array
    {
        $values = [];

        // Pattern: Zahl + optionale Einheit (kW, kw, mm, DN, Typ, phasig, etc.)
        if (preg_match_all('/(\d+[\.,]?\d*)\s*(kw|kwp|kva|mm|cm|dn|typ|phasig|liter|bar|volt|amp)/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $num = (float) str_replace(',', '.', $match[1]);
                $unit = strtolower($match[2]);
                $key = $num . $unit;
                $values[$key] = $num;
            }
        }

        // Auch: "DN100", "DN50" etc. (ohne Leerzeichen)
        if (preg_match_all('/dn\s*(\d+)/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = 'dn' . $match[1];
                $values[$key] = (float) $match[1];
            }
        }

        // Auch reine Zahlen vor "kW" etc.
        if (preg_match_all('/(\d+[\.,]?\d*)\s*kw/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = str_replace(',', '.', $match[1]) . 'kw';
                $values[$key] = (float) str_replace(',', '.', $match[1]);
            }
        }

        return $values;
    }

    /**
     * Prüft ob die numerischen Werte zweier Produkte kompatibel sind.
     *
     * Regel: Wenn BEIDE eine kW-Angabe haben, müssen die kW-Werte gleich sein.
     * Wenn BEIDE eine DN-Angabe haben, müssen die DN-Werte gleich sein.
     * Wenn nur eines eine Angabe hat, ist das kein Ausschlusskriterium.
     */
    private function numericValuesCompatible(array $aiNumbers, array $matNumbers): bool
    {
        if (empty($aiNumbers) || empty($matNumbers)) {
            return true; // Keine Zahlen → kein Konflikt
        }

        // Gruppen von Einheiten die exakt matchen müssen
        $criticalUnits = ['kw', 'kwp', 'kva', 'dn', 'phasig'];

        foreach ($criticalUnits as $unit) {
            $aiValue = null;
            $matValue = null;

            foreach ($aiNumbers as $key => $val) {
                if (str_contains($key, $unit)) {
                    $aiValue = $val;
                    break;
                }
            }

            foreach ($matNumbers as $key => $val) {
                if (str_contains($key, $unit)) {
                    $matValue = $val;
                    break;
                }
            }

            // Wenn BEIDE einen Wert für diese Einheit haben, müssen sie gleich sein
            if ($aiValue !== null && $matValue !== null) {
                if (abs($aiValue - $matValue) > 0.01) {
                    return false; // z.B. KI will 30kW, Katalog hat 10kW → INKOMPATIBEL
                }
            }
        }

        return true;
    }

    /**
     * Berechnet einen Score für die Übereinstimmung numerischer Werte.
     */
    private function calculateNumberScore(array $aiNumbers, array $matNumbers): float
    {
        if (empty($aiNumbers) && empty($matNumbers)) {
            return 0.5; // Neutral: keine Zahlen vorhanden
        }

        if (empty($aiNumbers) || empty($matNumbers)) {
            return 0.3; // Einer hat Zahlen, der andere nicht
        }

        $matches = 0;
        $total = 0;

        foreach ($aiNumbers as $aiKey => $aiVal) {
            $total++;
            foreach ($matNumbers as $matKey => $matVal) {
                if (abs($aiVal - $matVal) < 0.01) {
                    // Gleiche Zahl gefunden
                    // Bonus: gleiche Einheit
                    $aiUnit = preg_replace('/[\d\.,]/', '', $aiKey);
                    $matUnit = preg_replace('/[\d\.,]/', '', $matKey);
                    if ($aiUnit === $matUnit) {
                        $matches += 1.0;
                    } else {
                        $matches += 0.5;
                    }
                    break;
                }
            }
        }

        return $total > 0 ? min($matches / $total, 1.0) : 0.5;
    }

    /**
     * Extrahiert Schlüsselwörter aus einem Text.
     * Filtert Stoppwörter und zu kurze Wörter heraus.
     */
    private function extractKeywords(string $text, array $stopWords): array
    {
        $words = preg_split('/[\s\-\/\|,\.;:()]+/', $text);
        $words = array_filter($words, function ($w) use ($stopWords) {
            $w = trim($w);
            return strlen($w) >= 3 && !in_array($w, $stopWords) && !is_numeric($w);
        });
        return array_values(array_unique($words));
    }

    /**
     * Berechnet die KI-Kosten in Cent (GPT-4o Preise).
     */
    private function calculateCost(int $promptTokens, int $completionTokens): int
    {
        // GPT-4o: $2.50/1M input, $10/1M output (Stand 2026)
        $inputCost = ($promptTokens / 1_000_000) * 2.50;
        $outputCost = ($completionTokens / 1_000_000) * 10.00;

        return (int) round(($inputCost + $outputCost) * 100);
    }
}