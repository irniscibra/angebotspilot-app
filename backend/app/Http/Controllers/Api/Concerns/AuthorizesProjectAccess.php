<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Project;
use Illuminate\Http\Request;

/**
 * Zentrale Autorisierungs-Logik fuer alles, was zu einem Projekt gehoert
 * (Fotos, Bautagesberichte, Ausgaben, Zeiterfassung ...).
 *
 * Zwei Ebenen, IMMER beide, IMMER in dieser Reihenfolge:
 *  1) Firmen-Scoping: das Projekt muss zur Firma des eingeloggten Users
 *     gehoeren. Verstoss -> 404 (nicht 403), damit ein fremdes Projekt nicht
 *     mal indirekt seine Existenz verraet.
 *  2) Projekt-Zuweisung: Owner/Admin sehen alle Projekte ihrer Firma.
 *     Mitarbeiter (role = employee) NUR Projekte, denen sie ueber
 *     project_assignments explizit zugewiesen sind. Verstoss -> 403.
 *
 * Jeder Controller, der auf Projekt-Unterdaten zugreift, MUSS diesen Trait
 * nutzen statt eine eigene Kopie der Pruefung zu schreiben - so bleibt die
 * Logik an genau einer Stelle wartbar.
 */
trait AuthorizesProjectAccess
{
    protected function authorizeProjectAccess(Request $request, Project $project): void
    {
        $user = $request->user();

        if ($project->company_id !== $user->company_id) {
            abort(404);
        }

        if ($user->role === 'employee' && ! $user->canAccessProject($project)) {
            abort(403, 'Sie sind diesem Projekt nicht zugewiesen.');
        }
    }
}
