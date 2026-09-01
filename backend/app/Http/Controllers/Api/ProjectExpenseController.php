<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectExpenseController extends Controller
{
    /**
     * Alle Ausgaben eines Projekts.
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $expenses = $project->expenses()
            ->orderBy('expense_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($expenses);
    }

    /**
     * Neue Ausgabe zum Projekt hinzufügen.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'category' => 'nullable|in:material,lohn,fremdleistung,sonstiges',
            'expense_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $expense = ProjectExpense::create([
            'company_id' => $request->user()->company_id,
            'project_id' => $project->id,
            'created_by' => $request->user()->id,
            'category' => $request->input('category', 'sonstiges'),
            ...$request->only(['description', 'amount', 'expense_date', 'notes']),
        ]);

        return response()->json($expense, 201);
    }

    /**
     * Ausgabe aktualisieren.
     */
    public function update(Request $request, Project $project, ProjectExpense $expense): JsonResponse
    {
        $this->authorizeProject($request, $project);
        $this->authorizeExpense($project, $expense);

        $request->validate([
            'description' => 'sometimes|string|max:255',
            'amount' => 'sometimes|numeric|min:0',
            'category' => 'nullable|in:material,lohn,fremdleistung,sonstiges',
            'expense_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $expense->update($request->only([
            'description', 'amount', 'category', 'expense_date', 'notes',
        ]));

        return response()->json($expense->fresh());
    }

    /**
     * Ausgabe löschen.
     */
    public function destroy(Request $request, Project $project, ProjectExpense $expense): JsonResponse
    {
        $this->authorizeProject($request, $project);
        $this->authorizeExpense($project, $expense);

        $expense->delete();

        return response()->json(['message' => 'Ausgabe gelöscht.']);
    }

    /**
     * Stellt sicher, dass das Projekt zur Firma des Users gehört.
     */
    private function authorizeProject(Request $request, Project $project): void
    {
        if ($project->company_id !== $request->user()->company_id) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    /**
     * Stellt sicher, dass die Ausgabe zu diesem Projekt gehört.
     */
    private function authorizeExpense(Project $project, ProjectExpense $expense): void
    {
        if ($expense->project_id !== $project->id) {
            abort(404);
        }
    }
}
