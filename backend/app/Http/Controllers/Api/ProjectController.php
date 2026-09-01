<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * Alle Projekte der Firma.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->company->projects()
            ->with('customer')
            ->withCount(['quotes', 'invoices'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('project_address', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($c) use ($search) {
                        $c->where('company_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = min((int) $request->input('per_page', 12), 50);
        $projects = $query->paginate($perPage);

        return response()->json($projects);
    }

    /**
     * Neues Projekt anlegen.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'customer_id' => 'nullable|exists:customers,id',
            'description' => 'nullable|string|max:5000',
            'project_address' => 'nullable|string|max:500',
            'status' => 'nullable|in:angefragt,kalkuliert,beauftragt,in_ausfuehrung,abgeschlossen,storniert',
            'planned_start' => 'nullable|date',
            'planned_end' => 'nullable|date',
        ]);

        $project = Project::create([
            'company_id' => $request->user()->company_id,
            ...$request->only([
                'customer_id', 'title', 'description', 'project_address',
                'status', 'planned_start', 'planned_end',
            ]),
        ]);

        return response()->json($project->load('customer'), 201);
    }

    /**
     * Einzelnes Projekt.
     */
    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $project->load([
            'customer',
            'quotes' => fn ($q) => $q->orderBy('created_at', 'desc'),
            'invoices' => fn ($q) => $q->orderBy('created_at', 'desc'),
            'expenses' => fn ($q) => $q->orderBy('expense_date', 'desc')->orderBy('created_at', 'desc'),
            'photos' => fn ($q) => $q->orderBy('created_at', 'desc'),
            'reports' => fn ($q) => $q->orderBy('report_date', 'desc')->orderBy('created_at', 'desc'),
        ]);

        return response()->json($project);
    }

    /**
     * Projekt aktualisieren.
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'customer_id' => 'nullable|exists:customers,id',
            'description' => 'nullable|string|max:5000',
            'project_address' => 'nullable|string|max:500',
            'status' => 'sometimes|in:angefragt,kalkuliert,beauftragt,in_ausfuehrung,abgeschlossen,storniert',
            'planned_start' => 'nullable|date',
            'planned_end' => 'nullable|date',
        ]);

        $project->update($request->only([
            'customer_id', 'title', 'description', 'project_address',
            'status', 'planned_start', 'planned_end',
        ]));

        return response()->json($project->fresh()->load('customer'));
    }

    /**
     * Projekt löschen.
     * Nur möglich, wenn keine Angebote oder Rechnungen mehr am Projekt hängen.
     */
    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        if ($project->quotes()->exists() || $project->invoices()->exists()) {
            return response()->json([
                'message' => 'Projekt kann nicht gelöscht werden, solange noch Angebote oder Rechnungen zugeordnet sind.',
            ], 422);
        }

        $project->delete();

        return response()->json(['message' => 'Projekt gelöscht.']);
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
}
