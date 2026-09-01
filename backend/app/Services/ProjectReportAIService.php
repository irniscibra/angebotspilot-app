<?php

namespace App\Services;

use App\Models\AiUsageLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class ProjectReportAIService
{
    /**
     * Formuliert aus kurzen Stichpunkten einen professionellen
     * Bautagesbericht-Fließtext auf Deutsch.
     */
    public function generateDraft(string $notes, Company $company, User $user): string
    {
        $systemPrompt = <<<PROMPT
Du unterstützt einen Handwerksbetrieb dabei, aus kurzen Stichpunkten einen
professionellen Bautagesbericht zu formulieren.

Regeln:
- Antworte ausschließlich mit dem fertigen Bericht als Fließtext (3-6 Sätze).
- Keine Überschrift, keine Aufzählungszeichen, keine Anrede, kein Datum
  (das Datum steht separat im Formular).
- Nur Informationen aus den Stichpunkten verwenden, nichts dazuerfinden.
- Sachlicher, professioneller Ton, wie in einem echten Baustellenbericht üblich.
PROMPT;

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $notes],
            ],
            'temperature' => 0.4,
            'max_tokens' => 500,
        ]);

        $draft = trim($response->choices[0]->message->content ?? '');
        $usage = $response->usage;

        AiUsageLog::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'quote_id' => null,
            'action' => 'generate_report',
            'model' => 'gpt-4o',
            'prompt_tokens' => $usage->promptTokens,
            'completion_tokens' => $usage->completionTokens,
            'total_tokens' => $usage->totalTokens,
            'cost_cents' => $this->calculateCost($usage->promptTokens, $usage->completionTokens),
        ]);

        if ($draft === '') {
            throw new \RuntimeException('KI-Antwort war leer. Bitte erneut versuchen.');
        }

        return $draft;
    }

    private function calculateCost(int $promptTokens, int $completionTokens): int
    {
        // GPT-4o: $2.50/1M Input, $10/1M Output (Stand 2026)
        $inputCost = ($promptTokens / 1_000_000) * 2.50;
        $outputCost = ($completionTokens / 1_000_000) * 10.00;

        return (int) round(($inputCost + $outputCost) * 100);
    }
}
