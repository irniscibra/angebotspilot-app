<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyRate;
use App\Models\AiUsageLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * "Meine Sätze": eigene, firmenspezifische Preise (z.B. Minibagger 89€/Std),
 * die die KI bei jeder Angebotserstellung zusätzlich zu den allgemeinen
 * Gewerke-Referenzpreisen berücksichtigt. Anders als der Materialkatalog gibt
 * es hier keinen exakten Artikelabgleich (SKU) — die KI ordnet die Sätze
 * anhand von Name + Notiz selbst der Projektbeschreibung zu.
 */
class CompanyRateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rates = $request->user()->company->companyRates()
            ->orderBy('name')
            ->get();

        return response()->json($rates);
    }

    /**
     * Liest einen frei eingefügten Text (z.B. aus einer TikTok-/WhatsApp-Nachricht
     * kopiert) und lässt die KI daraus eine Vorschlagsliste für "Meine Sätze"
     * erkennen. Legt NICHTS in der Datenbank an - reine Vorschau, die im Frontend
     * geprüft/angepasst werden kann, bevor der Nutzer sie über den normalen
     * store()-Endpunkt (wie beim manuellen Anlegen) tatsächlich speichert.
     */
    public function importPreview(Request $request): JsonResponse
    {
        $request->validate([
            'text' => 'required|string|max:20000',
        ]);

        $company = $request->user()->company;
        $unitOptions = 'Std, Tag, Pauschale, km, Stück, Tonne';

        $systemPrompt = <<<PROMPT
Du hilfst einem deutschen Handwerks-/Dienstleistungsbetrieb dabei, aus einem frei
eingefügten Text (z.B. aus einer Chat-Nachricht kopiert) eine Liste seiner eigenen
Preissätze zu erkennen ("Meine Sätze" - eigene Stundensätze/Pauschalen/Gerätesätze).

Gib AUSSCHLIESSLICH JSON zurück im Format:
{"rates": [{"name": "...", "price": 45.00, "unit": "Std", "note": "..."}]}

REGELN:
1. Erkenne nur echte Preis-Positionen (Bezeichnung + Preis). Ignoriere Grußformeln,
   Fragen, Smalltalk und alles ohne erkennbaren Preis.
2. "name": kurz und eindeutig, so wie im Text benannt (z.B. "Unterhaltsreinigung").
3. "price": eine einzelne Zahl (Netto). Bei einer Preisspanne (z.B. "40-50€/Std")
   den Mittelwert nehmen (hier: 45) und die genannte Spanne zusätzlich im "note"-Feld
   festhalten, damit nichts verloren geht.
4. "unit": IMMER eine der folgenden Einheiten wählen, die inhaltlich am besten passt:
   {$unitOptions}. Bei Unklarheit "Std" verwenden.
5. "note": optional, kurzer Hinweis für die KI bei der späteren Angebotserstellung
   (z.B. die ursprüngliche Preisspanne, oder Kontext wie "bis 3 Mann").
6. Keine Dopplungen - wenn dieselbe Leistung mehrfach im Text vorkommt, nur einmal
   übernehmen (die spätere/genauere Nennung bevorzugen).
7. Wenn du im Text KEINE einzige Preis-Position erkennst, gib {"rates": []} zurück.
PROMPT;

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $request->input('text')],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.1,
            'max_tokens' => 3000,
        ]);

        $content = $response->choices[0]->message->content;
        $usage = $response->usage;

        AiUsageLog::create([
            'company_id' => $company->id,
            'user_id' => $request->user()->id,
            'quote_id' => null,
            'action' => 'import_company_rates',
            'model' => 'gpt-4o',
            'prompt_tokens' => $usage->promptTokens,
            'completion_tokens' => $usage->completionTokens,
            'total_tokens' => $usage->totalTokens,
            'cost_cents' => (int) round((($usage->promptTokens / 1_000_000) * 2.50 + ($usage->completionTokens / 1_000_000) * 10.00) * 100),
        ]);

        $result = json_decode($content, true);

        if (!$result || !isset($result['rates']) || !is_array($result['rates'])) {
            Log::error('Company-Rate-Import: KI-Antwort ungültig', ['content' => $content]);
            return response()->json(['message' => 'Die Sätze konnten nicht erkannt werden. Bitte versuchen Sie es erneut.'], 422);
        }

        // Nur valide Zeilen (Name + numerischer Preis) durchreichen, den Rest
        // defensiv verwerfen statt eine kaputte Zeile ins Frontend zu geben.
        $rates = [];
        foreach ($result['rates'] as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '' || !isset($row['price']) || !is_numeric($row['price'])) {
                continue;
            }
            $rates[] = [
                'name' => $name,
                'price' => round((float) $row['price'], 2),
                'unit' => trim((string) ($row['unit'] ?? 'Std')) ?: 'Std',
                'note' => trim((string) ($row['note'] ?? '')) ?: null,
            ];
        }

        return response()->json(['rates' => $rates]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'unit' => 'required|string|max:20',
            'note' => 'nullable|string|max:1000',
        ]);

        $rate = CompanyRate::create([
            'company_id' => $request->user()->company_id,
            ...$request->only(['name', 'price', 'unit', 'note']),
        ]);

        return response()->json($rate, 201);
    }

    public function update(Request $request, CompanyRate $companyRate): JsonResponse
    {
        if ($companyRate->company_id !== $request->user()->company_id) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'unit' => 'required|string|max:20',
            'note' => 'nullable|string|max:1000',
        ]);

        $companyRate->update($request->only(['name', 'price', 'unit', 'note']));

        return response()->json($companyRate);
    }

    public function destroy(Request $request, CompanyRate $companyRate): JsonResponse
    {
        if ($companyRate->company_id !== $request->user()->company_id) {
            abort(403);
        }

        $companyRate->delete();

        return response()->json(['message' => 'Satz gelöscht.']);
    }
}
