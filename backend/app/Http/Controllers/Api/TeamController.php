<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\EmployeeInviteNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Team-Verwaltung (Owner/Admin only - siehe routes/api.php role-Middleware).
 */
class TeamController extends Controller
{
    /**
     * Alle Team-Mitglieder der Firma inkl. offener Einladungen + Sitzplatz-Info.
     */
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        $members = $company->users()
            ->whereNull('deactivated_at')
            ->orderByRaw("FIELD(role, 'owner', 'admin', 'employee')")
            ->orderBy('name')
            ->withCount('assignedProjects')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => ($user->role === 'owner' || $user->accepted_at) ? 'aktiv' : 'eingeladen',
                'invited_at' => $user->invited_at,
                'assigned_projects_count' => $user->assigned_projects_count,
            ]);

        return response()->json([
            'members' => $members,
            'seats' => [
                'used' => $company->activeEmployeeCount(),
                'limit' => $company->employeeSeatLimit(),
            ],
        ]);
    }

    /**
     * Mitarbeiter oder Admin per E-Mail einladen. Legt den User-Datensatz
     * sofort an (role + invited_at gesetzt, Passwort unbekannt/zufaellig),
     * damit der Sitzplatz ab sofort mitgezaehlt wird - verhindert, dass
     * mehr Einladungen als Sitzplaetze verschickt werden koennen.
     */
    public function invite(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'role' => 'required|in:admin,employee',
        ]);

        $company = $request->user()->company;

        if ($request->input('role') === 'employee' && ! $company->canAddEmployee()) {
            return response()->json([
                'message' => 'Kein freier Mitarbeiter-Sitzplatz mehr verfuegbar. Bitte Sitzplaetze erweitern, bevor Sie weitere Mitarbeiter einladen.',
            ], 422);
        }

        $user = User::create([
            'company_id' => $company->id,
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make(Str::random(40)),
            'role' => $request->input('role'),
        ]);
        // invited_at ist absichtlich NICHT in User::$fillable (Lifecycle-Feld,
        // soll nicht per Mass-Assignment von irgendwo aus setzbar sein) -
        // deshalb hier ueber forceFill() gesetzt, sonst wuerde Laravel das
        // Feld beim ->create() oben still und ohne Fehler verwerfen.
        $user->forceFill(['invited_at' => now()])->save();

        $user->notify(new EmployeeInviteNotification($company->name, $request->user()->name));

        return response()->json([
            'message' => 'Einladung wurde per E-Mail verschickt.',
        ], 201);
    }

    /**
     * Team-Mitglied entfernen. Der Owner kann nicht entfernt werden, ein
     * User kann sich nicht selbst entfernen (verhindert versehentliches
     * Aussperren). Loescht per Cascade auch alle Projekt-Zuweisungen.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();

        if ($user->company_id !== $currentUser->company_id) {
            abort(404);
        }

        if ($user->role === 'owner') {
            return response()->json(['message' => 'Der Firmeninhaber kann nicht entfernt werden.'], 422);
        }

        if ($user->id === $currentUser->id) {
            return response()->json(['message' => 'Sie koennen sich nicht selbst entfernen.'], 422);
        }

        if ($user->deactivated_at) {
            return response()->json(['message' => 'Dieses Team-Mitglied wurde bereits entfernt.'], 422);
        }

        // Kein Hard-Delete: der User-Datensatz bleibt bestehen, damit bereits
        // erfasste Zeiten, Fotos und Bautagesberichte weiterhin korrekt
        // seinem Namen zugeordnet bleiben (Nachvollziehbarkeit von Lohn-
        // relevanten Daten). Stattdessen wird der Zugang entzogen: Tokens
        // geloescht (sofortiger Logout aller aktiven Sitzungen), Login
        // kuenftig blockiert (siehe AuthController::login), und der User
        // erscheint nicht mehr in der Team-Liste oder bei neuen Zuweisungen.
        // deactivated_at ist ebenfalls nicht in $fillable - forceFill() aus
        // demselben Grund wie bei invited_at oben.
        $user->tokens()->delete();
        $user->forceFill(['deactivated_at' => now()])->save();

        return response()->json(['message' => 'Team-Mitglied entfernt.']);
    }
}
