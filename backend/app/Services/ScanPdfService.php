<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class ScanPdfService
{
    /**
     * Konvertiert PDF-Seiten zu JPGs und extrahiert Positionen via GPT-4o Vision
     */
    public function extractPositions(string $pdfPath): array
    {
        $tempDir = storage_path('app/temp/scan_' . uniqid());
        mkdir($tempDir, 0755, true);

        try {
            // PDF → JPGs (150 DPI reicht für OCR, spart Zeit)
            $cmd = "pdftoppm -jpeg -r 150 " . escapeshellarg($pdfPath) . " " . escapeshellarg($tempDir . '/page');
            shell_exec($cmd);

            $images = glob($tempDir . '/*.jpg');
            sort($images);

            if (empty($images)) {
                throw new \RuntimeException('PDF konnte nicht in Bilder konvertiert werden.');
            }

            Log::info('Scan PDF: ' . count($images) . ' Seiten gefunden');

            // Seiten in Batches à 8 aufteilen
            $batches = array_chunk($images, 8);
            $allPositions = [];

            foreach ($batches as $batchIndex => $batch) {
                Log::info('Scan PDF: Batch ' . ($batchIndex + 1) . '/' . count($batches) . ' wird verarbeitet');
                
                $positions = $this->processBatch($batch, $batchIndex + 1, count($batches));
                $allPositions = array_merge($allPositions, $positions);
            }

            return $allPositions;

        } finally {
            // Cleanup
            $this->cleanup($tempDir);
        }
    }

    /**
     * Verarbeitet einen Batch von Seiten mit GPT-4o Vision
     */
  private function processBatch(array $imagePaths, int $batchNum, int $totalBatches): array
{
    $messages = [
        [
            'role' => 'system',
            'content' => 'Du bist ein Experte für SHK-Angebote. Extrahiere alle Positionen aus den Bildern als JSON Array. 
Jede Position hat folgende Felder: pos, beschreibung, menge, einheit, einzelpreis.
Gib NUR das JSON Array zurück, kein Text davor oder danach.
Wenn ein Feld nicht lesbar ist, setze einen leeren String "".
Ignoriere Kopfzeilen, Summen und Seitenränder.'
        ],
        [
            'role' => 'user',
            'content' => []
        ]
    ];

    $messages[1]['content'][] = [
        'type' => 'text',
        'text' => "Batch $batchNum von $totalBatches: Extrahiere alle Angebotspositionen aus diesen Seiten als JSON Array."
    ];

    foreach ($imagePaths as $imagePath) {
        $imageData = base64_encode(file_get_contents($imagePath));
        $messages[1]['content'][] = [
            'type' => 'image_url',
            'image_url' => [
                'url' => 'data:image/jpeg;base64,' . $imageData,
                'detail' => 'high'
            ]
        ];
    }

    $response = \Illuminate\Support\Facades\Http::withHeaders([
        'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
        'Content-Type' => 'application/json',
    ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
        'model' => 'gpt-4o',
        'messages' => $messages,
        'max_tokens' => 4000,
        'temperature' => 0.1,
    ]);

    if (!$response->successful()) {
        Log::error('Scan Batch API Fehler', [
            'batch' => $batchNum,
            'status' => $response->status(),
        ]);
        return [];
    }

    $content = $response->json('choices.0.message.content');
    $content = preg_replace('/```json\s*|\s*```/', '', $content);
    $content = trim($content);

    $positions = json_decode($content, true);

    if (!is_array($positions)) {
        Log::warning('Scan PDF Batch ' . $batchNum . ': Kein gültiges JSON', ['content' => substr($content, 0, 200)]);
        return [];
    }

    return $positions;
}
    /**
     * Bereinigt temporäre Dateien
     */
    private function cleanup(string $dir): void
    {
        if (is_dir($dir)) {
            array_map('unlink', glob($dir . '/*'));
            rmdir($dir);
        }
    }
}