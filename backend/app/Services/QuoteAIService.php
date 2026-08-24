<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Material;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\AiUsageLog;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use App\Services\TradeReferenceService;

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
            'temperature' => 0.15,
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

        // Sicherheitsnetz: unrealistisch niedrige KI-Preise korrigieren
            $aiResult['groups'] = $this->enforceMinimumPrices($aiResult['groups']);

            $aiResult['groups'] = $this->enforceLaborRateSanity($aiResult['groups'], (float) $company->default_hourly_rate);

                  // Sicherheitsnetz: vom Kunden explizit genannten Preis erzwingen
        // (z.B. "105€/m²"), funktioniert einheitenbasiert für alle Gewerke
            $aiResult['groups'] = $this->enforceExplicitPricing($aiResult['groups'], $description);

                // Sicherheitsnetz: verdächtige Pauschal-Materialien markieren
        // (rein additiv, ändert keine Preise/Mengen/Katalog-Zuordnung)
            $aiResult['groups'] = $this->flagVaguePauschalMaterials($aiResult['groups']);

        // Sicherheitsnetz: unrealistisch teure Nebenmaterial-Positionen markieren
        // (rein additiv, ändert keine Preise/Mengen/Katalog-Zuordnung)
            $aiResult['groups'] = $this->flagOverpricedMinorMaterials($aiResult['groups']);

      

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
        $tradeLabel = TradeReferenceService::getLabel($company->trade);
        $tradePrices = TradeReferenceService::getPrices($company->trade);

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
- Nur wenn kein passendes Material im Katalog ist, suche das beste verfügbare Produkt im internet und schlage eine Marge von 5% auf.
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
0. HÖCHSTE PRIORITÄT — VERBINDLICH, KEINE AUSNAHME: Wenn der Nutzer in der
   Projektbeschreibung einen konkreten Preis explizit nennt (z.B. "105€/m²",
   "65 Euro die Stunde", "Pauschalpreis 3000€"), MUSS die Summe aller Material-
   und Arbeitspositionen dieser Leistung EXAKT diesem Preis mal Menge
   entsprechen. Beispiel: "230m² zu 105€/m²" ergibt zwingend 24.150,00 EUR
   Netto für diese Leistung — nicht mehr, nicht weniger. Verteile diesen
   Gesamtbetrag realistisch auf Material- und Arbeitspositionen, aber die
   SUMME muss exakt stimmen. Ignoriere in diesem Fall die Referenzpreise
   unten vollständig für diese Leistung. Prüfe nach der Kalkulation selbst
   nach, ob deine Summe exakt mit Menge × genanntem Preis übereinstimmt,
   bevor du antwortest.
1. Gliedere das Angebot in logische Gewerke-Gruppen (z.B. "Demontage & Entsorgung", "Sanitärinstallation", "Rohrleitungen", "Heizungsarbeiten", etc.)
2. Trenne IMMER Material und Arbeitsleistung als separate Positionen
3. Kalkuliere realistische Mengen und Preise für den deutschen Markt (Stand 2026) 
4. Verwende marktübliche Markenmaterialien (Grohe, Hansgrohe, Viega, Geberit, Buderus, Vaillant etc.)
5. Plane eine Kleinmaterial-Pauschale ein (5-8% der Materialkosten) NUR für wirklich
   unteilbares Kleinzeug (Schrauben, Dübel, Dichtungen, Klebeband). Diese Pauschale
   darf NIEMALS Hauptmaterialien enthalten.
5a. PAUSCHALEN-VERBOT FÜR HAUPTMATERIALIEN: Fliesenkleber, Fugenmasse, Silikon (in
    größerer Menge für eine Verlegung), Spachtelmasse, Grundierung, Mauersteine,
    Estrich, Ausgleichsmasse und vergleichbare Hauptmaterialien MÜSSEN als EIGENE
    Position mit realistischer Menge und Einheit (kg, Liter, Stück, m², pauschal
    NUR wenn wirklich nicht anders quantifizierbar) ausgewiesen werden — NIEMALS
    in einer "1x pauschal"-Sammelposition versteckt.
    Beispiel FALSCH: "Fliesen und Sockelleisten, 1x pauschal, 600€"
    Beispiel RICHTIG: getrennte Positionen für "Fliesen 24m²", "Fliesenkleber ca.
    8 Säcke à 25kg", "Fugenmasse ca. 3 Eimer", "Sockelleisten 12 lfm", "Silikon
    für Randfugen 4 Kartuschen" — jede mit eigener Menge/Einheit/Preis.
5b. FEHLENDE STANDARDMATERIALIEN PRÜFEN: Bei folgenden Arbeitsschritten IMMER an
    das dazugehörige Verbrauchsmaterial denken und als eigene Position ergänzen,
    auch wenn der Kunde es nicht explizit erwähnt hat:
    - Fliesenverlegung → Fliesenkleber, Fugenmasse, Silikon für Randfugen
    - Spachtelarbeiten → Spachtelmasse (nach m² und Schichtdicke), ggf. Armierungsvlies
    - Mauerwerksarbeiten → Mauersteine, Mauermörtel, ggf. Sturz/Stahlträger
    - Malerarbeiten → Grundierung, Abdeckfolie/Klebeband, Farbe
    - Bodenbeläge → Ausgleichsmasse, Grundierung, Klebstoff, Sockelleisten
    Falls du unsicher über die genaue Menge bist: realistisch schätzen anhand der
    genannten Fläche/des Umfangs, lieber leicht großzügig als zu knapp.
6. Berücksichtige Anfahrt, Baustelleneinrichtung und -reinigung wenn sinnvoll
7. Arbeitszeiten realistisch kalkulieren – lieber etwas großzügiger als zu knapp
8. Bei Heizungsarbeiten: EnEV/GEG Normen berücksichtigen
9. Bei Sanitärarbeiten: DIN und DVGW Normen berücksichtigen

GEWERK: {$tradeLabel}

REFERENZPREISE FÜR DIESES GEWERK (Netto, Stand 2026 – NUR verwenden wenn KEIN Katalog-Artikel passt):
{$tradePrices}

WICHTIG ZU DEN REFERENZPREISEN:
- Diese Preise sind die verbindliche Grundlage, wenn kein Katalog-Artikel passt.
- NIEMALS außerhalb dieser Preisspannen kalkulieren, auch nicht bei
  ungewöhnlichen Formulierungen oder unklaren Einheiten in der Anfrage.
- Bei Mengen-/Leistungsangaben in kcal oder anderen unüblichen Einheiten:
  IMMER zuerst in kW umrechnen (1 kW ≈ 860 kcal/h), dann die passende
  Preisklasse aus der Liste wählen.
- Bei Unsicherheit über die genaue Größe/Klasse: lieber die MITTLERE
  Preisklasse ansetzen als einen Wert außerhalb der Spanne zu raten.

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
                $description = $item['description'] ?? null;
                if (!empty($item['price_suspicious']) && !$matchedMaterial) {
                    $description = trim(
                        ($description ? $description . ' ' : '') .
                        '🔴 ACHTUNG: Preis wirkt unrealistisch hoch für diese Position – unbedingt vor Versand prüfen!'
                    );
                } elseif (!empty($item['needs_quantity_review']) && !$matchedMaterial) {
                    $description = trim(
                        ($description ? $description . ' ' : '') .
                        '⚠ Pauschalpreis – bitte prüfen, ob alle Materialien realistisch mit eingerechnet sind.'
                    );
                }

                QuoteItem::create([
                    'quote_id' => $quote->id,
                    'position_number' => $position++,
                    'group_name' => $group['name'],
                    'type' => $item['type'] ?? 'material',
                    'title' => $matchedMaterial ? $matchedMaterial->name : $item['title'],
                    'description' => $description,
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
 * Sicherheitsnetz: Korrigiert KI-Preise, die trotz Prompt-Anweisungen
 * unrealistisch niedrig ausgefallen sind. Greift NUR bei Positionen
 * ohne Katalog-Match (echte Katalogpreise werden nie angetastet).
 */
private function enforceMinimumPrices(array $groups): array
{
    $minimums = [
        'wärmepumpe' => 6000,
        'wärmeerzeuger' => 2000,
        'brennwerttherme' => 2000,
        'gastherme' => 2000,
        'pelletheizung' => 8000,
        'batteriespeicher' => 3000,
        'wechselrichter' => 800,
        'photovoltaik' => 100,
    ];

    foreach ($groups as &$group) {
        foreach ($group['items'] as &$item) {
            // Katalog-Positionen nie anfassen - die haben echte, geprüfte Preise
            if (!empty($item['from_catalog'])) {
                continue;
            }

            $titleLower = strtolower($item['title'] ?? '');
            $price = (float) ($item['unit_price'] ?? 0);

            foreach ($minimums as $keyword => $minPrice) {
                if (str_contains($titleLower, $keyword) && $price > 0 && $price < $minPrice) {
                    Log::warning('KI-Preis unter Mindestwert automatisch korrigiert', [
                        'title' => $item['title'],
                        'ai_price' => $price,
                        'corrected_to' => $minPrice,
                    ]);
                    $item['unit_price'] = $minPrice;
                    $item['price_auto_corrected'] = true;
                    break;
                }
            }
        }
    }

    return $groups;
}

/**
     * Sicherheitsnetz Teil 2: Korrigiert unrealistisch hohe Stundensätze
     * bei Arbeitspositionen (z.B. wenn die KI einen Pauschalpreis
     * versehentlich als Stundensatz einträgt).
     */
    private function enforceLaborRateSanity(array $groups, float $companyHourlyRate): array
    {
        // Realistischer Korridor: 30€ bis 250€/Std deckt auch Meister-Sätze
        // und Zuschläge ab, aber keine Tausender-Beträge
        $maxHourlyRate = 250.0;
        $minHourlyRate = 30.0;

        foreach ($groups as &$group) {
            foreach ($group['items'] as &$item) {
                if (($item['type'] ?? '') !== 'labor') {
                    continue;
                }

                $unit = strtolower($item['unit'] ?? '');
                if (!str_contains($unit, 'std')) {
                    continue; // nur Stunden-basierte Positionen prüfen
                }

                $price = (float) ($item['unit_price'] ?? 0);

                if ($price > $maxHourlyRate) {
                    Log::warning('Unrealistischer Stundensatz automatisch korrigiert', [
                        'title' => $item['title'],
                        'ai_price' => $price,
                        'corrected_to' => $companyHourlyRate,
                    ]);
                    $item['unit_price'] = $companyHourlyRate;
                    $item['price_auto_corrected'] = true;
                } elseif ($price > 0 && $price < $minHourlyRate) {
                    Log::warning('Zu niedriger Stundensatz automatisch korrigiert', [
                        'title' => $item['title'],
                        'ai_price' => $price,
                        'corrected_to' => $companyHourlyRate,
                    ]);
                    $item['unit_price'] = $companyHourlyRate;
                    $item['price_auto_corrected'] = true;
                }
            }
        }

        return $groups;
    }

    /**
     * Erzwingt einen vom Kunden explizit genannten Preis (z.B. "105€/m²",
     * "65 Euro die Stunde", "Pauschalpreis 3000€"), unabhängig vom Gewerk.
     * Arbeitet rein über Mengeneinheiten, keine Branchen-Sonderfälle.
     *
     * Katalog-Positionen werden NIE skaliert – ihr Preis bleibt exakt der
     * echte Katalogpreis. Nur die übrigen (nicht aus dem Katalog stammenden)
     * Positionen werden proportional angepasst, damit die Gesamtsumme exakt
     * der Kundenvorgabe entspricht.
     */
    private function enforceExplicitPricing(array $groups, string $description): array
    {
        $priceInfo = $this->extractExplicitUnitPrice($description);
        if (!$priceInfo) {
            return $groups;
        }

        $expectedTotal = round($priceInfo['price'] * $priceInfo['quantity'], 2);

        $catalogTotal = 0.0;
        $nonCatalogTotal = 0.0;

        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $lineTotal = (float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0);
                if (!empty($item['from_catalog'])) {
                    $catalogTotal += $lineTotal;
                } else {
                    $nonCatalogTotal += $lineTotal;
                }
            }
        }

        // Zielbetrag für die nicht-katalogierten Positionen = Kundenvorgabe
        // abzüglich dessen, was bereits durch echte Katalogpreise gedeckt ist.
        $targetForNonCatalog = round($expectedTotal - $catalogTotal, 2);

        if ($targetForNonCatalog <= 0 || $nonCatalogTotal <= 0) {
            // Katalog deckt schon alles ab, oder ungültiger Zustand -> nicht antasten
            return $groups;
        }

        $diff = abs($nonCatalogTotal - $targetForNonCatalog) / $targetForNonCatalog;
        if ($diff < 0.005) {
            return $groups; // bereits korrekt genug (Rundungstoleranz)
        }

        $scaleFactor = $targetForNonCatalog / $nonCatalogTotal;

        foreach ($groups as &$group) {
            foreach ($group['items'] as &$item) {
                if (!empty($item['from_catalog'])) continue;
                $item['unit_price'] = round(((float)($item['unit_price'] ?? 0)) * $scaleFactor, 2);
                $item['price_auto_corrected'] = true;
            }
        }
        unset($group, $item);

        Log::info('Explizite Kundenpreis-Vorgabe erzwungen', [
            'unit' => $priceInfo['unit'],
            'unit_price' => $priceInfo['price'],
            'quantity' => $priceInfo['quantity'],
            'expected_total' => $expectedTotal,
            'catalog_total' => $catalogTotal,
            'ai_non_catalog_total_before' => $nonCatalogTotal,
            'scale_factor' => $scaleFactor,
        ]);

        return $groups;
    }

        /**
     * Markiert verdächtige Pauschal-Materialpositionen zur manuellen Prüfung.
     * Rein additiv: ändert NIEMALS Preis, Menge, Typ oder Katalog-Zuordnung –
     * hängt nur einen Hinweis an die Beschreibung. Kann daher nichts an der
     * bestehenden Preis-/Katalog-Logik kaputt machen. Funktioniert gewerks-
     * übergreifend, da rein strukturell (unit=pauschal bei Material) statt
     * über Gewerk-spezifische Schlüsselwörter erkannt wird.
     */
    private function flagVaguePauschalMaterials(array $groups): array
    {
        // Diese Begriffe kennzeichnen legitime, bewusst pauschale Kleinteile –
        // dafür ist "pauschal" korrekt und wird NICHT markiert.
        $allowedSmallParts = [
            'kleinmaterial', 'kleinteile', 'verbrauchsmaterial',
            'sonstiges material', 'befestigungsmaterial',
        ];

        foreach ($groups as &$group) {
            foreach ($group['items'] as &$item) {
                if (($item['type'] ?? '') !== 'material') {
                    continue;
                }

                $unit = strtolower(trim($item['unit'] ?? ''));
                if ($unit !== 'pauschal') {
                    continue;
                }

                $titleLower = strtolower($item['title'] ?? '');

                $isAllowedSmallParts = false;
                foreach ($allowedSmallParts as $kw) {
                    if (str_contains($titleLower, $kw)) {
                        $isAllowedSmallParts = true;
                        break;
                    }
                }
                if ($isAllowedSmallParts) {
                    continue;
                }

                // Nur Hinweis anhängen - Preis/Menge/Typ bleiben unangetastet
                $item['needs_quantity_review'] = true;
            }
        }
        unset($group, $item);

        return $groups;
    }

        /**
     * Erkennt Materialpositionen, die für ihre Bezeichnung unrealistisch teuer
     * sind – typischerweise wenn die KI eine Pauschale grob überschätzt
     * (z.B. "Elektromaterial pauschal: 6.000€" statt realistischer 300-800€).
     * Arbeitet gewerksübergreifend über zwei Signale:
     * 1. Schlüsselwörter, die auf "Kleinteile/Nebenmaterial" hindeuten, aber
     *    einen unverhältnismäßig hohen Preis haben
     * 2. Eine einzelne Position, die einen unplausibel hohen Anteil an der
     *    Gesamt-Materialsumme ausmacht, ohne Katalog-Beleg
     *
     * Greift NIE bei Katalog-Positionen (echte, geprüfte Preise) und ändert
     * NIEMALS den Preis selbst – markiert nur zur Prüfung, genau wie
     * flagVaguePauschalMaterials(). Rein additiv, kein Risiko für Bestehendes.
     */
    private function flagOverpricedMinorMaterials(array $groups): array
    {
        // Begriffe, die auf tendenziell kleinteiliges/günstiges Nebenmaterial
        // hindeuten - wenn diese trotzdem sehr hochpreisig sind, ist das
        // verdächtig, unabhängig vom Gewerk.
        $minorMaterialKeywords = [
            'elektromaterial', 'kleinmaterial', 'verbrauchsmaterial',
            'installationsmaterial', 'anschlussmaterial', 'befestigungsmaterial',
            'montagematerial', 'zubehör', 'dichtungsmaterial',
        ];

        // Absolute Warnschwelle für solche "Nebenmaterial"-Positionen.
        // Bewusst hoch angesetzt (1.500€), damit legitime größere Pauschalen
        // nicht ständig fälschlich markiert werden - Ziel ist der Faktor-10-
        // Ausreißer, nicht jede etwas großzügige Schätzung.
        $minorMaterialMaxPrice = 1500.0;

        // Gesamte Materialsumme berechnen (für die Anteils-Prüfung)
        $totalMaterialValue = 0.0;
        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                if (($item['type'] ?? '') === 'material') {
                    $totalMaterialValue += (float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0);
                }
            }
        }

        foreach ($groups as &$group) {
            foreach ($group['items'] as &$item) {
                if (($item['type'] ?? '') !== 'material' || !empty($item['from_catalog'])) {
                    continue;
                }

                $titleLower = strtolower($item['title'] ?? '');
                $lineTotal = (float)($item['quantity'] ?? 0) * (float)($item['unit_price'] ?? 0);

                $isMinorMaterialLabel = false;
                foreach ($minorMaterialKeywords as $kw) {
                    if (str_contains($titleLower, $kw)) {
                        $isMinorMaterialLabel = true;
                        break;
                    }
                }

                // Signal 1: "Nebenmaterial" laut Bezeichnung, aber unrealistisch teuer
                if ($isMinorMaterialLabel && $lineTotal > $minorMaterialMaxPrice) {
                    $item['price_suspicious'] = true;
                    Log::warning('Verdächtig teures "Nebenmaterial" erkannt', [
                        'title' => $item['title'],
                        'line_total' => $lineTotal,
                    ]);
                    continue;
                }

                // Signal 2: Einzelne Position dominiert die Materialsumme
                // unverhältnismäßig (>40%), UND ist als "pauschal" ausgewiesen
                // (also nicht durch eine belastbare Menge x Einzelpreis belegt)
                $unit = strtolower(trim($item['unit'] ?? ''));
                if ($unit === 'pauschal' && $totalMaterialValue > 0) {
                    $share = $lineTotal / $totalMaterialValue;
                    if ($share > 0.4 && $lineTotal > 1000) {
                        $item['price_suspicious'] = true;
                        Log::warning('Pauschal-Position dominiert Materialsumme unverhältnismäßig', [
                            'title' => $item['title'],
                            'line_total' => $lineTotal,
                            'share_of_material_total' => round($share, 2),
                        ]);
                    }
                }
            }
        }
        unset($group, $item);

        return $groups;
    }

    /**
     * Sucht in der Projektbeschreibung nach einem explizit genannten
     * Preis pro Mengeneinheit (z.B. "105€/m²", "65 Euro die Stunde",
     * "Pauschalpreis 3000€") sowie der dazugehörigen Menge.
     * Rein einheitenbasiert, funktioniert branchenübergreifend.
     */
    private function extractExplicitUnitPrice(string $description): ?array
    {
        $text = mb_strtolower($description);

        $unitPatterns = [
            'm²' => 'm²|qm|m2|quadratmeter',
            'Std' => 'std\.?|stunde|stunden',
            'Stück' => 'stück|stk\.?|stueck',
            'm³' => 'm³|m3|kubikmeter|cbm',
            'kg' => 'kg|kilogramm',
            'Liter' => 'liter',
        ];

        $unitPrice = null;
        $priceUnit = null;

        // Pauschalpreis separat behandeln (Menge = 1)
        if (preg_match('/pauschal(?:preis)?\s*(?:von|:)?\s*(\d+(?:[.,]\d+)?)\s*(?:€|eur|euro)?/u', $text, $m)
            || preg_match('/(\d+(?:[.,]\d+)?)\s*(?:€|eur|euro)\s*pauschal/u', $text, $m)) {
            return [
                'unit' => 'pauschal',
                'price' => (float) str_replace(',', '.', $m[1]),
                'quantity' => 1.0,
            ];
        }

        // Muster: "105€/m²", "105 € pro qm", "65 Euro die Stunde"
        foreach ($unitPatterns as $normUnit => $alt) {
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:€|eur|euro)?\s*(?:\/|pro|je|die|der)\s*(?:' . $alt . ')/u', $text, $m)) {
                $unitPrice = (float) str_replace(',', '.', $m[1]);
                $priceUnit = $normUnit;
                break;
            }
        }

        // Muster: "qm preis von 105" (Einheit vor dem Preis)
        if ($unitPrice === null) {
            foreach ($unitPatterns as $normUnit => $alt) {
                if (preg_match('/(?:' . $alt . ')\s*preis\s*(?:von|:)?\s*(\d+(?:[.,]\d+)?)/u', $text, $m)) {
                    $unitPrice = (float) str_replace(',', '.', $m[1]);
                    $priceUnit = $normUnit;
                    break;
                }
            }
        }

        if ($unitPrice === null || $priceUnit === null || $unitPrice <= 0) {
            return null;
        }

        // Menge derselben Einheit im Text finden
        $alt = $unitPatterns[$priceUnit];
        if (!preg_match('/(\d+(?:[.,]\d+)?)\s*(?:' . $alt . ')/u', $text, $qm)) {
            return null;
        }
        $quantity = (float) str_replace(',', '.', $qm[1]);

        if ($quantity <= 0) {
            return null;
        }

        return ['unit' => $priceUnit, 'price' => $unitPrice, 'quantity' => $quantity];
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
        $aiDims = $this->extractDimensionPairs($aiTitle);

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
            $matDims = $this->extractDimensionPairs($matName);

            // === HARTE PRÜFUNG: Numerische Werte müssen passen ===
            // Wenn die KI "30kW" schreibt und der Katalog "10kW" hat → KEIN Match
            if (!$this->numericValuesCompatible($aiNumbers, $matNumbers)) {
                continue;
            }

            // === HARTE PRÜFUNG: Maß-Paare müssen passen ===
            // Wenn die KI "40/250" schreibt und der Katalog "50/350" hat → KEIN Match
            $hasDimensionMatch = false;
            if (!empty($aiDims) && !empty($matDims)) {
                if (!$this->dimensionPairsMatch($aiDims, $matDims)) {
                    continue;
                }
                $hasDimensionMatch = true;
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

          // Mindestens 2 Wörter müssen übereinstimmen – Ausnahme: bei exakt
            // übereinstimmenden Maßangaben (z.B. "40/250") reicht 1 Wort,
            // da die Maß-Übereinstimmung bereits ein starkes Signal ist.
            $minWordsRequired = $hasDimensionMatch ? 1 : 2;
            if ($matchingWords < $minWordsRequired) {
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
     * Extrahiert Maß-Paare wie "40/250" oder "60/300" (z.B. bei Bügeln, Kanälen, Rinnen).
     */
    private function extractDimensionPairs(string $text): array
    {
        $pairs = [];
        if (preg_match_all('/(\d+)\s*\/\s*(\d+)/', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $pairs[] = [(float) $match[1], (float) $match[2]];
            }
        }
        return $pairs;
    }

    /**
     * Prüft ob zwei Maß-Paar-Listen mindestens ein exakt übereinstimmendes Paar haben.
     */
    private function dimensionPairsMatch(array $aiDims, array $matDims): bool
    {
        foreach ($aiDims as [$a1, $a2]) {
            foreach ($matDims as [$m1, $m2]) {
                if (abs($a1 - $m1) < 0.01 && abs($a2 - $m2) < 0.01) {
                    return true;
                }
            }
        }
        return false;
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