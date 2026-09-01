<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sperrt Endpunkte fuer bestimmte Rollen. Nutzung in Routen:
 *   Route::middleware('role:owner,admin')->group(...)
 *
 * Laeuft NACH auth:sanctum (braucht $request->user()). Mitarbeiter
 * (role = employee) werden damit z.B. von Angeboten, Rechnungen,
 * Kundendaten und Firmeneinstellungen komplett ferngehalten - unabhaengig
 * von jeder Projekt-Zuweisung, das ist eine harte Grenze.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            return response()->json([
                'message' => 'Fuer diese Aktion fehlt Ihnen die Berechtigung.',
            ], 403);
        }

        return $next($request);
    }
}
