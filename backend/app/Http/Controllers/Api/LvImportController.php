<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessLvImportJob;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LvImportController extends Controller
{
    /**
     * Schritt 1: Leeres Platzhalter-Angebot anlegen, bevor die PDF hochgeladen wird.
     * Gleiches zweistufige Muster wie der bestehende Scan-Import
     * (scanPrepare/scanUpload), damit große Uploads nicht an einem
     * einzigen, langen HTTP-Request hängen.
     */
   public function prepare(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'project_id' => 'nullable|exists:projects,id',
            'project_address' => 'nullable|string|max:500',
        ]);

        $company = $request->user()->company;

        // LV-Import ist ein Pro-Feature. Während der Testphase (Trial)
        // bleibt es für alle sichtbar/nutzbar, damit Interessenten es
        // ausprobieren können. Sobald ein Plan aktiv ist, ist es nur
        // noch für Pro freigeschaltet.
        if (!$company->isTrialActive() && !$company->hasProAccess()) {
            return response()->json([
                'message' => 'LV-Import ist ein Pro-Feature. Bitte upgraden Sie auf den Pro-Plan, um Ausschreibungen zu importieren.',
                'requires_pro' => true,
            ], 403);
        }

        $quote = Quote::create([
            'company_id' => $company->id,
            'customer_id' => $request->customer_id,
            'project_id' => $request->project_id,
            'created_by' => $request->user()->id,
            'quote_number' => $company->generateQuoteNumber(),
            'project_title' => 'LV wird analysiert...',
            'project_description' => 'Ausschreibung wird importiert',
            'project_address' => $request->project_address,
            'vat_rate' => $company->default_vat_rate ?? 19,
            'valid_until' => now()->addDays($company->quote_validity_days ?? 30),
            'internal_notes' => 'lv_import_processing',
        ]);

        return response()->json([
            'quote_id' => $quote->id,
            'message' => 'Bereit für Upload',
        ], 201);
    }

    /**
     * Schritt 2: PDF hochladen und Verarbeitung im Hintergrund anstoßen.
     * Läuft bewusst NICHT synchron - bei 100+ Seiten dauert die
     * Verarbeitung mehrere Minuten (viele einzelne KI-Aufrufe).
     */
    public function upload(Request $request, int $quoteId): JsonResponse
    {
        $request->validate([
            'pdf' => 'required|file|mimetypes:application/pdf,application/octet-stream|max:51200',
        ]);

        $quote = Quote::where('id', $quoteId)
            ->where('company_id', $request->user()->company_id)
            ->firstOrFail();

        $file = $request->file('pdf');
        $originalFilename = $file->getClientOriginalName();

        $storedPath = $file->store('temp/lv_imports', 'local');
        $fullPath = storage_path('app/private/' . $storedPath);
        if (!file_exists($fullPath)) {
            $fullPath = storage_path('app/' . $storedPath);
        }

        ProcessLvImportJob::dispatch(
            $quote->id,
            $fullPath,
            $originalFilename,
            $quote->company_id,
            $request->user()->id,
        );

        return response()->json([
            'quote_id' => $quote->id,
            'message' => 'LV-Import gestartet. Das kann bei großen Dokumenten mehrere Minuten dauern.',
        ], 202);
    }

    /**
     * Fortschritt abfragen (Polling vom Frontend).
     */
    public function status(Request $request, int $quoteId): JsonResponse
    {
        $quote = Quote::where('id', $quoteId)
            ->where('company_id', $request->user()->company_id)
            ->firstOrFail();

        $notes = $quote->internal_notes ?? '';

        if ($notes === 'lv_import_processing') {
            return response()->json([
                'status' => 'processing',
                'message' => 'LV wird analysiert... Das kann bei großen Dokumenten mehrere Minuten dauern.',
            ]);
        }

        if ($notes === 'lv_import_done') {
            $quote->load('items');
            return response()->json([
                'status' => 'done',
                'message' => 'Import abgeschlossen!',
                'quote' => $quote,
            ]);
        }

        if (str_starts_with($notes, 'lv_import_failed')) {
            return response()->json([
                'status' => 'failed',
                'message' => str_replace('lv_import_failed: ', '', $notes),
            ]);
        }

        return response()->json([
            'status' => 'done',
            'quote' => $quote->load('items'),
        ]);
    }
}