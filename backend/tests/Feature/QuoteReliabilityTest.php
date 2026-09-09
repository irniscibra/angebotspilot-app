<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyRate;
use App\Models\Quote;
use App\Models\User;
use App\Services\QuoteAIService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * KI-Zuverlässigkeits-Regressionstest für QuoteAIService.
 *
 * Hintergrund (siehe positionsmodell-umbau.md, Entscheidung 16 sowie die
 * Shaban-Testläufe 1-4 vom 09.09.2026): GPT-4o reagiert bei fast
 * identischen Spracheingaben nicht immer gleich - Einheiten werden
 * verwechselt (Std vs. Stück), Material wird mal erzeugt und mal
 * vergessen, Mengen (z.B. Anfahrt-Kilometer) werden manchmal verdoppelt.
 * Ohne diesen Test wurde jeder Fix bisher nur einmal von Hand
 * nachgetestet - dieser Test macht "hält der Fix wirklich" reproduzierbar
 * statt einer Hoffnung.
 *
 * Jedes Szenario bildet eine reale oder plausible Situation nach: eigene
 * Sätze ("Meine Sätze") + eine natürliche Sprachbeschreibung, wie sie per
 * Diktat/Text tatsächlich hereinkommt. Geprüft wird nicht nur "kam ein
 * Ergebnis heraus", sondern die konkrete Menge/Einheit/Preis pro Position
 * - genau dort, wo die KI bisher unzuverlässig war.
 *
 * WICHTIG:
 * - Ruft echt GPT-4o über die OpenAI-API auf (kein Mock) - erfordert
 *   einen gültigen OPENAI_API_KEY in .env. Kosten pro vollem Testlauf:
 *   ca. 10 Aufrufe, üblicherweise deutlich unter 1 EUR.
 * - GPT-4o ist nicht 100% deterministisch. Schlägt ein Szenario fehl:
 *   einmal einzeln wiederholen, bevor man es als echte Regression
 *   einstuft. Schlägt es zweimal in Folge fehl, ist es ein echter Befund
 *   und kein Zufall.
 * - Läuft komplett gegen eine In-Memory-SQLite-Datenbank (siehe
 *   phpunit.xml) - die echte lokale/Server-Datenbank wird nicht berührt.
 *
 * Ausführen (aus backend/):
 *   php artisan test --filter=QuoteReliabilityTest
 * Die Testausgabe listet jedes Szenario einzeln mit seinem Namen auf.
 *
 * Neues Szenario ergänzen: einen weiteren Eintrag im Array von
 * scenarios() unten hinzufügen - Aufbau ist überall identisch.
 */
class QuoteReliabilityTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('scenarios')]
    public function test_scenario(
        string $trade,
        array $companyAttrs,
        array $rates,
        string $description,
        \Closure $expect
    ): void {
        $company = Company::create(array_merge([
            'name' => 'Testfirma',
            'trade' => $trade,
            'plan' => 'professional',
        ], $companyAttrs));

        foreach ($rates as $rate) {
            CompanyRate::create(array_merge(['company_id' => $company->id], $rate));
        }

        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Testnutzer',
            'email' => 'quote-reliability-' . uniqid() . '@example.test',
            'password' => 'test-password',
            'role' => 'owner',
        ]);

        $quote = Quote::create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'quote_number' => 'TEST-' . uniqid(),
            'project_title' => 'Testangebot',
            'trade' => $trade,
        ]);

        app(QuoteAIService::class)->generateQuote($quote, $description);

        $items = $quote->refresh()->items()->get();

        $this->assertGreaterThan(0, $items->count(), 'Keine einzige Position wurde erzeugt.');

        // Globale Sicherheitsnetz-Invariante - gilt für JEDES Szenario:
        // eine wegen Einheiten-Konflikt auf 0 EUR gesetzte Position darf
        // NIE trotzdem einen anderen Preis stehen haben (das genaue
        // Problem, das enforceOwnRateMatch() verhindern soll).
        foreach ($items as $item) {
            if (str_contains((string) $item->internal_note, 'Einheit passt aber nicht')) {
                $this->assertEquals(
                    0.0,
                    (float) $item->unit_price,
                    "Einheiten-Konflikt-Position '{$item->title}' hat trotz Warnung einen Preis ungleich 0."
                );
            }
        }

        $expect($items, $this);
    }

    public function findItem(Collection $items, string $nameSubstring)
    {
        return $items->first(fn ($item) => str_contains(
            mb_strtolower($item->title . ' ' . $item->description),
            mb_strtolower($nameSubstring)
        ));
    }

    public function assertRatePosition(Collection $items, string $name, float $quantity, string $unit, float $unitPrice): void
    {
        $item = $this->findItem($items, $name);
        $this->assertNotNull($item, "Keine Position zu '{$name}' gefunden. Tatsächlich erzeugte Positionen: " . $this->describeItems($items));
        // Einheiten-Vergleich wie im echten Code (normalizeUnitForCompare in
        // QuoteAIService): 'Pauschale' (Satz) und 'pauschal' (KI-Ausgabe)
        // sind fachlich dasselbe - die KI schreibt die Einheit nicht immer
        // in derselben Schreibweise wie in "Meine Sätze" hinterlegt, das
        // ändert aber nichts am (korrekt berechneten) Preis.
        $this->assertSame(
            $this->normalizeUnit($unit),
            $this->normalizeUnit($item->unit),
            "Einheit von '{$name}' ist '{$item->unit}', erwartet (normalisiert) '{$unit}'."
        );
        $this->assertEqualsWithDelta($quantity, (float) $item->quantity, 0.05, "Menge von '{$name}' ist {$item->quantity}, erwartet {$quantity} - mögliche Mengen-Verdopplung/-Fehlinterpretation.");
        $this->assertEqualsWithDelta($unitPrice, (float) $item->unit_price, 0.05, "Einzelpreis von '{$name}' ist {$item->unit_price}, erwartet {$unitPrice}.");
    }

    public function assertNoItem(Collection $items, string $nameSubstring): void
    {
        $item = $this->findItem($items, $nameSubstring);
        $this->assertNull($item, "Unerwartete Phantom-Position zu '{$nameSubstring}' gefunden: " . $this->describeItems($items));
    }

    /**
     * Spiegelt QuoteAIService::normalizeUnitForCompare() - Einheiten sollen
     * fachlich, nicht wörtlich verglichen werden.
     */
    private function normalizeUnit(string $unit): string
    {
        $u = mb_strtolower(trim($unit));
        return $u === 'pauschale' ? 'pauschal' : $u;
    }

    /**
     * Für Fehlermeldungen: zeigt, was die KI in diesem Lauf tatsächlich
     * erzeugt hat, damit man nicht blind raten muss.
     */
    private function describeItems(Collection $items): string
    {
        return $items->map(function ($i) {
            return sprintf(
                "[%s | \"%s\" | %s %s | %.2f EUR%s]",
                $i->type,
                $i->title,
                $i->quantity,
                $i->unit,
                (float) $i->unit_price,
                $i->internal_note ? ' | Hinweis: ' . mb_substr($i->internal_note, 0, 60) : ''
            );
        })->implode(' ');
    }

    public static function scenarios(): array
    {
        $shabanRates = [
            ['name' => 'Unterhaltsreinigung', 'price' => 45.00, 'unit' => 'Std'],
            ['name' => 'Grundreinigung', 'price' => 57.50, 'unit' => 'Std'],
            ['name' => 'Endreinigung', 'price' => 57.50, 'unit' => 'Std'],
            ['name' => 'Fensterreinigung', 'price' => 45.00, 'unit' => 'Std'],
            ['name' => 'Anfahrt Pauschale', 'price' => 20.00, 'unit' => 'Pauschale'],
            ['name' => 'Anfahrt weitere Kilometer', 'price' => 0.80, 'unit' => 'km'],
            ['name' => 'Kellerentrümpelung', 'price' => 62.50, 'unit' => 'Std'],
            ['name' => 'Wertstoffhof-Abtransport', 'price' => 62.50, 'unit' => 'Std'],
            ['name' => 'Gewerbeentrümpelung', 'price' => 67.50, 'unit' => 'Std'],
        ];

        $theoRates = [
            ['name' => 'Meister', 'price' => 73.00, 'unit' => 'Std'],
            ['name' => 'Fachangestellter', 'price' => 65.00, 'unit' => 'Std'],
            ['name' => 'Hilfsarbeiter', 'price' => 59.00, 'unit' => 'Std'],
            ['name' => 'Anfahrt Pauschale', 'price' => 25.00, 'unit' => 'Pauschale'],
            ['name' => 'Anfahrt Kilometer', 'price' => 1.20, 'unit' => 'km'],
        ];

        $marcelRates = [
            ['name' => 'Minibagger', 'price' => 75.00, 'unit' => 'Std'],
            ['name' => 'Großbagger', 'price' => 95.00, 'unit' => 'Std'],
            ['name' => 'Schuttabtransport', 'price' => 150.00, 'unit' => 'Pauschale'],
        ];

        return [

            'R1: Grundreinigung Büro + Anfahrt 12km' => [
                'reinigung',
                ['default_hourly_rate' => 57.50],
                $shabanRates,
                'Grundreinigung Büro, 150 m². 2 Mann, je 6 Stunden. Anfahrt einfache Strecke: 12 km.',
                function (Collection $items, QuoteReliabilityTest $t) {
                    $t->assertRatePosition($items, 'Grundreinigung', 12, 'Std', 57.50);
                    $t->assertRatePosition($items, 'Anfahrt Pauschale', 1, 'Pauschale', 20.00);
                    $t->assertRatePosition($items, 'Anfahrt weitere Kilometer', 12, 'km', 0.80);
                },
            ],

            'R2: Endreinigung Wohnung + Anfahrt 18km' => [
                'reinigung',
                ['default_hourly_rate' => 57.50],
                $shabanRates,
                'Endreinigung Wohnung, 90 m². 1 Mann, 5 Stunden. Anfahrt einfache Strecke: 18 km.',
                function (Collection $items, QuoteReliabilityTest $t) {
                    $t->assertRatePosition($items, 'Endreinigung', 5, 'Std', 57.50);
                    $t->assertRatePosition($items, 'Anfahrt Pauschale', 1, 'Pauschale', 20.00);
                    $t->assertRatePosition($items, 'Anfahrt weitere Kilometer', 18, 'km', 0.80);
                },
            ],

            'R3: Kellerentrümpelung + Abtransport + Anfahrt 8km (der frühere km-Verdopplungs-Fall)' => [
                'entruempelung',
                ['default_hourly_rate' => 62.50],
                $shabanRates,
                'Kellerentrümpelung, 20 m². 2 Mann, je 4 Stunden. Zusätzlich Abtransport zum Wertstoffhof: 3 Stunden. Anfahrt einfache Strecke: 8 km.',
                function (Collection $items, QuoteReliabilityTest $t) {
                    $t->assertRatePosition($items, 'Kellerentrümpelung', 8, 'Std', 62.50);
                    $t->assertRatePosition($items, 'Wertstoffhof', 3, 'Std', 62.50);
                    $t->assertRatePosition($items, 'Anfahrt Pauschale', 1, 'Pauschale', 20.00);
                    $t->assertRatePosition($items, 'Anfahrt weitere Kilometer', 8, 'km', 0.80);
                },
            ],

            'R4: Gewerbeentrümpelung + Anfahrt 25km (zweiter km-Wert zur Absicherung)' => [
                'entruempelung',
                ['default_hourly_rate' => 67.50],
                $shabanRates,
                'Gewerbeentrümpelung, 40 m². 2 Mann, je 5 Stunden. Anfahrt einfache Strecke: 25 km.',
                function (Collection $items, QuoteReliabilityTest $t) {
                    $t->assertRatePosition($items, 'Gewerbeentrümpelung', 10, 'Std', 67.50);
                    $t->assertRatePosition($items, 'Anfahrt weitere Kilometer', 25, 'km', 0.80);
                },
            ],

            'R5: Fensterreinigung nach Stück-Angabe - Einheiten-Sicherheitsnetz darf nie falschen Preis stehen lassen' => [
                'reinigung',
                ['default_hourly_rate' => 45.00],
                $shabanRates,
                'Fensterreinigung: 15 Fenster, ca. 20 Minuten pro Fenster.',
                function (Collection $items, QuoteReliabilityTest $t) {
                    $item = $t->findItem($items, 'Fensterreinigung');
                    $t->assertNotNull($item, "Keine Position zu 'Fensterreinigung' gefunden.");
                    if ($item->unit === 'Std') {
                        $t->assertEqualsWithDelta(45.00, (float) $item->unit_price, 0.05, 'Fensterreinigung in Std erkannt, aber falscher Satz-Preis.');
                    } else {
                        $t->assertEquals(0.0, (float) $item->unit_price, "Fensterreinigung in Einheit '{$item->unit}' statt Std, aber Preis ist nicht 0 - Sicherheitsnetz hat nicht gegriffen.");
                    }
                },
            ],

            'H1: Sanierung mit Meister + Hilfsarbeiter + Anfahrt 10km (Paar-Regel in anderem Gewerk)' => [
                'sanierung',
                ['default_hourly_rate' => 65.00],
                $theoRates,
                'Sanierung Badezimmer. Meister: 4 Stunden. Hilfsarbeiter: 6 Stunden. Anfahrt einfache Strecke: 10 km.',
                function (Collection $items, QuoteReliabilityTest $t) {
                    $t->assertRatePosition($items, 'Meister', 4, 'Std', 73.00);
                    $t->assertRatePosition($items, 'Hilfsarbeit', 6, 'Std', 59.00);
                    $t->assertRatePosition($items, 'Anfahrt Pauschale', 1, 'Pauschale', 25.00);
                    $t->assertRatePosition($items, 'Kilometer', 10, 'km', 1.20);
                },
            ],

            'H2: Tiefbau ohne genannte Entfernung - Anfahrt-Pauschale darf automatisch anfallen (Fixkosten), Kilometer-Zuschlag NICHT ohne genannte Entfernung' => [
                'tiefbau',
                ['default_hourly_rate' => 65.00],
                $theoRates,
                'Tiefbau, Grabenaushub für eine Leitung. Fachangestellter: 8 Stunden.',
                function (Collection $items, QuoteReliabilityTest $t) {
                    $t->assertRatePosition($items, 'Fachangestellt', 8, 'Std', 65.00);
                    // Best-Practice-Entscheidung (09.09.2026): die Anfahrt-Pauschale ist
                    // ein Fixkosten-Posten und darf/soll auch OHNE explizite Erwähnung
                    // automatisch berechnet werden - dafür legt der Betrieb sie ja fest
                    // in "Meine Sätze" ab. Der Kilometer-Zuschlag braucht dagegen zwingend
                    // eine genannte Entfernung, sonst gäbe es nichts, worauf er sich stützt.
                    $t->assertRatePosition($items, 'Anfahrt Pauschale', 1, 'Pauschale', 25.00);
                    $t->assertNoItem($items, 'Kilometer');
                },
            ],

            'E1: Abbruch mit Minibagger + 2 Fahrten Schuttabtransport (Namens-Verwechslung Minibagger/Bagger)' => [
                'erdbau',
                ['default_hourly_rate' => 75.00],
                $marcelRates,
                'Abbrucharbeiten mit dem Minibagger: 6 Stunden. Zusätzlich Schuttabtransport: 2 Fahrten.',
                function (Collection $items, QuoteReliabilityTest $t) {
                    $t->assertRatePosition($items, 'Minibagger', 6, 'Std', 75.00);
                    $t->assertRatePosition($items, 'Schuttabtransport', 2, 'Pauschale', 150.00);
                },
            ],

            'E2: Erdaushub mit Großbagger nach m³-Angabe - Einheiten-Sicherheitsnetz (analog altem Bagger-m³-Bug)' => [
                'erdbau',
                ['default_hourly_rate' => 95.00],
                $marcelRates,
                'Erdaushub mit dem Großbagger, ca. 45 Kubikmeter Erdmenge. Geschätzte Einsatzzeit: 5 Stunden.',
                function (Collection $items, QuoteReliabilityTest $t) {
                    $item = $t->findItem($items, 'Großbagger');
                    $t->assertNotNull($item, 'Keine Position zum Großbagger gefunden.');
                    if ($item->unit === 'Std') {
                        $t->assertEqualsWithDelta(95.00, (float) $item->unit_price, 0.05, 'Großbagger in Std erkannt, aber falscher Satz-Preis.');
                    } else {
                        $t->assertEquals(0.0, (float) $item->unit_price, "Großbagger in Einheit '{$item->unit}' statt Std, aber Preis ist nicht 0 - Sicherheitsnetz hat nicht gegriffen.");
                    }
                },
            ],

            'M1: Betonarbeiten ohne eigene Sätze - fehlendes Material muss erzeugt ODER intern markiert werden' => [
                'hochbau',
                ['default_hourly_rate' => 55.00],
                [],
                'Betonfundament gießen, inklusive Schalung und Bewehrung. Facharbeiter: 10 Stunden.',
                function (Collection $items, QuoteReliabilityTest $t) {
                    $hasMaterial = $items->contains(fn ($i) => $i->type === 'material');
                    $hasFlag = $items->contains(fn ($i) => str_contains((string) $i->internal_note, 'Material'));
                    $t->assertTrue(
                        $hasMaterial || $hasFlag,
                        'Betonarbeiten mit Schalung/Bewehrung erzeugten weder eine Material-Position noch eine Sicherheitsnetz-Warnung - stillschweigend fehlendes Material.'
                    );
                },
            ],

            'R6: Reinigung mit knapper, echter Kunden-Formulierung (informell, wie tatsächlich getippt/diktiert)' => [
                'reinigung',
                ['default_hourly_rate' => 57.50],
                $shabanRates,
                'zg Zahnarztpraxis Grundreinigung 120qm 1x woche 4std anfahrt 15km',
                function (Collection $items, QuoteReliabilityTest $t) {
                    $t->assertRatePosition($items, 'Grundreinigung', 4, 'Std', 57.50);
                    $t->assertRatePosition($items, 'Anfahrt Pauschale', 1, 'Pauschale', 20.00);
                    $t->assertRatePosition($items, 'Anfahrt weitere Kilometer', 15, 'km', 0.80);
                },
            ],

            'R7: Zahnarztpraxis-Reinigung + 18 Fenster geputzt (Fensterreinigung-Einheiten-Sicherheitsnetz, kombinierter Auftrag)' => [
                'reinigung',
                ['default_hourly_rate' => 45.00],
                $shabanRates,
                'Zahnarztpraxis Reinigung, 3 Stunden. Zusätzlich 18 Fenster in der Praxis geputzt.',
                function (Collection $items, QuoteReliabilityTest $t) {
                    $t->assertRatePosition($items, 'Unterhaltsreinigung', 3, 'Std', 45.00);

                    $fenster = $t->findItem($items, 'Fensterreinigung');
                    $t->assertNotNull($fenster, 'Keine Position zu \'Fensterreinigung\' gefunden. Tatsächlich erzeugte Positionen: ' . $t->describeItems($items));
                    if ($fenster->unit === 'Std') {
                        $t->assertEqualsWithDelta(45.00, (float) $fenster->unit_price, 0.05, 'Fensterreinigung in Std erkannt, aber falscher Satz-Preis.');
                    } else {
                        $t->assertEquals(0.0, (float) $fenster->unit_price, "Fensterreinigung in Einheit '{$fenster->unit}' statt Std, aber Preis ist nicht 0 - Sicherheitsnetz hat nicht gegriffen.");
                    }
                },
            ],

            'SHK1: Kundendienst ohne eigene Sätze - anderes Gewerk darf nicht crashen oder negative Preise erzeugen (Referenzpreis-Pfad)' => [
                'shk',
                ['default_hourly_rate' => 68.00],
                [],
                'kundendienst heizung defekt techniker vor ort 3 std ventil tauschen',
                function (Collection $items, QuoteReliabilityTest $t) {
                    $t->assertGreaterThan(0, $items->count(), 'SHK-Kundendienst hat keine Position erzeugt.');
                    foreach ($items as $item) {
                        $t->assertGreaterThanOrEqual(0.0, (float) $item->unit_price, "SHK-Position '{$item->title}' hat einen negativen Preis.");
                    }
                    $laborTotal = $items->whereIn('type', ['labor', 'flat'])->sum(fn ($i) => (float) $i->total_price);
                    $t->assertGreaterThan(0.0, $laborTotal, 'SHK-Kundendienst mit 3 Std Techniker ergibt eine Arbeitsleistung von 0 EUR.');
                },
            ],

            'ELEKTRO1: E-Check ohne eigene Sätze - anderes Gewerk darf nicht crashen oder negative Preise erzeugen (Referenzpreis-Pfad)' => [
                'elektro',
                ['default_hourly_rate' => 68.00],
                [],
                'e check wohnung 3 zimmer elektriker 2h zaehlerschrank pruefen',
                function (Collection $items, QuoteReliabilityTest $t) {
                    $t->assertGreaterThan(0, $items->count(), 'Elektro-E-Check hat keine Position erzeugt.');
                    foreach ($items as $item) {
                        $t->assertGreaterThanOrEqual(0.0, (float) $item->unit_price, "Elektro-Position '{$item->title}' hat einen negativen Preis.");
                    }
                    $laborTotal = $items->whereIn('type', ['labor', 'flat'])->sum(fn ($i) => (float) $i->total_price);
                    $t->assertGreaterThan(0.0, $laborTotal, 'E-Check mit 2 Std Elektriker ergibt eine Arbeitsleistung von 0 EUR.');
                },
            ],

        ];
    }
}
