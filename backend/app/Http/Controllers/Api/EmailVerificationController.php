<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * E-Mail verifizieren (Link aus der E-Mail).
     * → Web-Route mit signierter URL
     * → Redirect zum Frontend nach Erfolg
     */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $frontendUrl = config('app.frontend_url', 'https://app.angebotspilot.app');

        $user = User::find($id);

        if (! $user) {
            return redirect($frontendUrl . '/#/auth/login?verified=0&error=not_found');
        }

        // Signatur prüfen
        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect($frontendUrl . '/#/auth/login?verified=0&error=invalid');
        }

        // Bereits verifiziert
        if ($user->hasVerifiedEmail()) {
            return redirect($frontendUrl . '/#/auth/login?verified=1&already=1');
        }

        $user->markEmailAsVerified();

        return redirect($frontendUrl . '/#/auth/login?verified=1');
    }

    /**
     * Verifikations-E-Mail erneut senden.
     * → API-Route, nur E-Mail als Input (kein Auth-Token nötig)
     */
    public function resend(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            // Aus Sicherheitsgründen gleiche Antwort (kein User-Enumeration)
            return response()->json([
                'message' => 'Falls ein Konto mit dieser E-Mail existiert, wurde die Bestätigungs-E-Mail erneut gesendet.',
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Ihre E-Mail-Adresse ist bereits bestätigt.',
                'already_verified' => true,
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Bestätigungs-E-Mail wurde erneut gesendet.',
        ]);
    }
}
