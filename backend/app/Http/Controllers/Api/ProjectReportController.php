<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesProjectAccess;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectReport;
use App\Services\ProjectReportAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectReportController extends Controller
{
    use AuthorizesProjectAccess;

    public function __construct(
        private ProjectReportAIService $aiService
    ) {}

    /**
     * Alle Bautagesberichte eines Projekts.
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $reports = $project->reports()
            ->orderBy('report_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($reports);
    }

    /**
     * Aus kurzen Stichpunkten per KI einen Berichts-Entwurf formulieren
     * lassen. Speichert nichts - der Entwurf wird im Frontend erst nach
     * Prüfung/Bearbeitung durch den Nutzer über store()/update() gespeichert.
     */
    public function generateDraft(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $request->validate([
            'notes' => 'required|string|max:2000',
        ]);

        try {
            $draft = $this->aiService->generateDraft(
                $request->input('notes'),
                $request->user()->company,
                $request->user(),
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'KI-Generierung fehlgeschlagen. Bitte Text manuell eingeben oder erneut versuchen.',
            ], 422);
        }

        return response()->json(['content' => $draft]);
    }

    /**
     * Neuen Bautagesbericht anlegen.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $request->validate([
            'report_date' => 'nullable|date',
            'content' => 'required|string|max:5000',
        ]);

        $report = ProjectReport::create([
            'company_id' => $request->user()->company_id,
            'project_id' => $project->id,
            'created_by' => $request->user()->id,
            'report_date' => $request->input('report_date') ?: now()->toDateString(),
            'content' => $request->input('content'),
        ]);

        return response()->json($report, 201);
    }

    /**
     * Bautagesbericht aktualisieren.
     */
    public function update(Request $request, Project $project, ProjectReport $report): JsonResponse
    {
        $this->authorizeProject($request, $project);
        $this->authorizeReport($project, $report);

        $request->validate([
            'report_date' => 'nullable|date',
            'content' => 'sometimes|string|max:5000',
        ]);

        $report->update($request->only(['report_date', 'content']));

        return response()->json($report->fresh());
    }

    /**
     * Bautagesbericht löschen.
     */
    public function destroy(Request $request, Project $project, ProjectReport $report): JsonResponse
    {
        $this->authorizeProject($request, $project);
        $this->authorizeReport($project, $report);

        $report->delete();

        return response()->json(['message' => 'Bautagesbericht gelöscht.']);
    }

    /**
     * Stellt sicher, dass das Projekt zur Firma des Users gehört.
     */
    private function authorizeProject(Request $request, Project $project): void
    {
        $this->authorizeProjectAccess($request, $project);
    }

    /**
     * Stellt sicher, dass der Bericht zu diesem Projekt gehört.
     */
    private function authorizeReport(Project $project, ProjectReport $report): void
    {
        if ($report->project_id !== $project->id) {
            abort(404);
        }
    }
}
