<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

/**
 * Oeffentliche (aber signierte) Endpunkte fuer die Einladungs-Annahme.
 * Laeuft bewusst AUSSERHALB von auth:sanctum - der eingeladene User hat
 * ja noch keinen Account, mit dem er sich einloggen koennte. Sicherheit
 * kommt aus der 'signed'-Middleware (siehe routes/api.php), nicht aus
 * einem Auth-Token.
 */
class InviteController extends Controller
{
    /**
     * Basisdaten zu einer offenen Einladung, fuer die Annahme-Seite im
     * Frontend.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        if ($user->accepted_at) {
            return response()->json([
                'message' => 'Diese Einladung wurde bereits angenommen. Bitte melden Sie sich normal an.',
            ], 410);
        }

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'company_name' => $user->company->name,
            'role' => $user->role,
        ]);
    }

    /**
     * Nimmt die Einladung an: setzt Passwort, markiert E-Mail als
     * verifiziert (der Link kam ja bereits sicher per E-Mail an) und
     * loggt den User direkt ein.
     */
    public function accept(Request $request, User $user): JsonResponse
    {
        if ($user->accepted_at) {
            return response()->json([
                'message' => 'Diese Einladung wurde bereits angenommen. Bitte melden Sie sich normal an.',
            ], 410);
        }

        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->forceFill([
            'password' => Hash::make($request->input('password')),
            'accepted_at' => now(),
            'email_verified_at' => now(),
        ])->save();

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user->load('company'),
            'token' => $token,
        ]);
    }
}
