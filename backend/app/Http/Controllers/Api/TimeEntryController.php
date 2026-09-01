<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesProjectAccess;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Zeiterfassung pro Projekt. Jeder zugewiesene User darf seine EIGENEN
 * Zeiten erfassen; Admin/Owner duerfen zusaetzlich fuer jeden Mitarbeiter
 * der Firma Zeiten erfassen ("fuer Kollegen erfassen"). Bearbeiten/Loeschen
 * ist nur dem Ersteller seines EIGENEN Eintrags oder Admin/Owner erlaubt -
 * ein Mitarbeiter darf also keinen Eintrag anfassen, den ein Admin fuer ihn
 * erfasst hat (das bleibt bewusst der Admin-Hoheit vorbehalten).
 */
class TimeEntryController extends Controller
{
    use AuthorizesProjectAccess;

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request, $project);
        $user = $request->user();

        $query = $project->timeEntries()
            ->with(['user:id,name', 'loggedBy:id,name'])
            ->orderBy('entry_date', 'desc')
            ->orderBy('start_time', 'desc');

        // Mitarbeiter sehen aus Datenschutzgruenden nur ihre eigenen
        // Zeiten, nicht die der Kollegen - Admin/Owner sehen alle.
        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        return response()->json($query->get());
    }

    /**
     * Firmenweite Zeituebersicht ueber ALLE Projekte hinweg, fuer die
     * Lohnabrechnung. Nur Admin/Owner (Route liegt im role:owner,admin-
     * Block). ?month=YYYY-MM grenzt auf einen Monat ein, ohne Parameter
     * werden alle Zeiten der Firma geliefert.
     */
    public function companyIndex(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        $query = TimeEntry::where('company_id', $company->id)
            ->with(['user:id,name', 'project:id,title']);

        $month = $request->query('month');
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $start = \Carbon\Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
            $query->whereBetween('entry_date', [
                $start->toDateString(),
                $start->copy()->endOfMonth()->toDateString(),
            ]);
        }

        $entries = $query->orderBy('entry_date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();

        $byEmployee = $entries->groupBy('user_id')->map(function ($group) {
            return [
                'user' => $group->first()->user,
                'total_minutes' => (int) $group->sum('duration_minutes'),
                'entries_count' => $group->count(),
            ];
        })->values()->sortByDesc('total_minutes')->values();

        return response()->json([
            'entries' => $entries,
            'by_employee' => $byEmployee,
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request, $project);
        $actor = $request->user();

        $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'entry_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'break_minutes' => 'nullable|integer|min:0|max:600',
            'description' => 'nullable|string|max:1000',
        ]);

        $targetUser = $this->resolveTargetUser($request, $actor);
        if ($targetUser instanceof JsonResponse) {
            return $targetUser;
        }

        if (! $targetUser->canAccessProject($project)) {
            return response()->json([
                'message' => 'Dieser Mitarbeiter ist dem Projekt nicht zugewiesen.',
            ], 422);
        }

        if ($this->netMinutes($request) <= 0) {
            return response()->json([
                'message' => 'Die Pause darf nicht laenger als die Arbeitszeit sein.',
            ], 422);
        }

        $entry = TimeEntry::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'user_id' => $targetUser->id,
            'logged_by' => $actor->id,
            ...$request->only(['entry_date', 'start_time', 'end_time', 'break_minutes', 'description']),
        ]);

        return response()->json($entry->load(['user:id,name', 'loggedBy:id,name']), 201);
    }

    public function update(Request $request, Project $project, TimeEntry $entry): JsonResponse
    {
        $this->authorizeProjectAccess($request, $project);
        $this->authorizeEntry($project, $entry);
        $actor = $request->user();

        if (! $this->canModify($entry, $actor)) {
            return response()->json([
                'message' => 'Sie duerfen nur selbst erfasste, eigene Zeiten bearbeiten.',
            ], 403);
        }

        $request->validate([
            'entry_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'break_minutes' => 'nullable|integer|min:0|max:600',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($this->netMinutes($request) <= 0) {
            return response()->json([
                'message' => 'Die Pause darf nicht laenger als die Arbeitszeit sein.',
            ], 422);
        }

        $entry->update($request->only(['entry_date', 'start_time', 'end_time', 'break_minutes', 'description']));

        return response()->json($entry->fresh()->load(['user:id,name', 'loggedBy:id,name']));
    }

    public function destroy(Request $request, Project $project, TimeEntry $entry): JsonResponse
    {
        $this->authorizeProjectAccess($request, $project);
        $this->authorizeEntry($project, $entry);
        $actor = $request->user();

        if (! $this->canModify($entry, $actor)) {
            return response()->json([
                'message' => 'Sie duerfen nur selbst erfasste, eigene Zeiten loeschen.',
            ], 403);
        }

        $entry->delete();

        return response()->json(['message' => 'Zeiteintrag geloescht.']);
    }

    /**
     * Ermittelt, fuer wen der Eintrag gilt. Ohne user_id: fuer sich selbst.
     * Mit abweichender user_id: nur Admin/Owner duerfen das, und nur fuer
     * Mitglieder der eigenen Firma.
     */
    private function resolveTargetUser(Request $request, User $actor): User|JsonResponse
    {
        $targetUserId = $request->input('user_id') ?: $actor->id;

        if ((int) $targetUserId !== $actor->id && ! $actor->isAdmin()) {
            return response()->json([
                'message' => 'Sie duerfen nur Ihre eigenen Zeiten erfassen.',
            ], 403);
        }

        $targetUser = User::find($targetUserId);

        if (! $targetUser || $targetUser->company_id !== $actor->company_id) {
            return response()->json(['message' => 'Mitarbeiter nicht gefunden.'], 404);
        }

        return $targetUser;
    }

    private function canModify(TimeEntry $entry, User $actor): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        return $entry->user_id === $actor->id && $entry->logged_by === $actor->id;
    }

    private function netMinutes(Request $request): int
    {
        $start = \Carbon\Carbon::createFromFormat('H:i', $request->input('start_time'));
        $end = \Carbon\Carbon::createFromFormat('H:i', $request->input('end_time'));

        return $start->diffInMinutes($end) - (int) $request->input('break_minutes', 0);
    }

    private function authorizeEntry(Project $project, TimeEntry $entry): void
    {
        if ($entry->project_id !== $project->id) {
            abort(404);
        }
    }
}
