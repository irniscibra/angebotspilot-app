<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesProjectAccess;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Verwaltung, welche Mitarbeiter/Admins einem Projekt zugewiesen sind.
 * Nur Owner/Admin (siehe role-Middleware in routes/api.php) - Mitarbeiter
 * duerfen sich nicht selbst oder gegenseitig Projekte zuweisen.
 */
class ProjectAssignmentController extends Controller
{
    use AuthorizesProjectAccess;

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request, $project);

        return response()->json(
            $project->assignedUsers()->select('users.id', 'users.name', 'users.email', 'users.role')->get()
        );
    }

    /**
     * Einen User (Mitarbeiter oder Admin der eigenen Firma) einem Projekt
     * zuweisen.
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProjectAccess($request, $project);

        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $targetUser = User::findOrFail($request->input('user_id'));

        if ($targetUser->company_id !== $request->user()->company_id) {
            abort(404);
        }

        if ($targetUser->role === 'owner') {
            return response()->json([
                'message' => 'Der Firmeninhaber hat ohnehin Zugriff auf alle Projekte.',
            ], 422);
        }

        $project->assignedUsers()->syncWithoutDetaching([
            $targetUser->id => [
                'company_id' => $project->company_id,
                'assigned_by' => $request->user()->id,
            ],
        ]);

        return response()->json([
            'message' => 'Zugewiesen.',
            'assigned_users' => $project->assignedUsers()->select('users.id', 'users.name', 'users.email', 'users.role')->get(),
        ], 201);
    }

    /**
     * Zuweisung entfernen (der User selbst bleibt bestehen, nur die
     * Verknuepfung zu diesem Projekt wird geloescht).
     */
    public function destroy(Request $request, Project $project, User $user): JsonResponse
    {
        $this->authorizeProjectAccess($request, $project);

        if ($user->company_id !== $request->user()->company_id) {
            abort(404);
        }

        $project->assignedUsers()->detach($user->id);

        return response()->json(['message' => 'Zuweisung entfernt.']);
    }
}
