<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use App\Mail\NewRegistrationNotification;
use Illuminate\Support\Facades\Mail;


class AuthController extends Controller
{
    /**
     * Registrierung: Erstellt User + Company, sendet Verifikations-E-Mail.
     * Gibt KEINEN Token zurück – erst nach E-Mail-Bestätigung kann man sich einloggen.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|string|email|max:255|unique:users',
            'password'     => ['required', 'confirmed', Rules\Password::defaults()],
            'company_name' => 'required|string|max:255',
        ]);

        
      

        // Firma erstellen
        $company = Company::create([
            'name' => $request->company_name,
        ]);

        // User erstellen (noch nicht verifiziert)
        $user = User::create([
            'company_id' => $company->id,
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'role'       => 'owner',
        ]);

        // Verifikations-E-Mail senden
        $user->sendEmailVerificationNotification();

            // Admin-Benachrichtigung bei neuer Registrierung
        Mail::to('info@angebotspilot.app')
            ->send(new NewRegistrationNotification($user, $company));

        return response()->json([
            'message'             => 'Registrierung erfolgreich. Bitte bestätigen Sie Ihre E-Mail-Adresse.',
            'email_verification_sent' => true,
            'email'               => $user->email,
        ], 201);
    }

    /**
     * Login: Prüft Credentials + E-Mail-Verifikation.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Die eingegebenen Anmeldedaten sind ungültig.',
            ], 401);
        }

        // E-Mail noch nicht bestätigt
        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message'           => 'Bitte bestätigen Sie zuerst Ihre E-Mail-Adresse. Schauen Sie in Ihren Posteingang.',
                'email_not_verified' => true,
                'email'             => $user->email,
            ], 403);
        }

        // Alte Tokens löschen
        $user->tokens()->delete();

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user'  => $user->load('company'),
            'token' => $token,
        ]);
    }

    /**
     * Logout: Token löschen.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Erfolgreich abgemeldet.']);
    }

    /**
     * Aktueller User mit Company.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load('company'),
        ]);
    }

        /**
     * Schritt 1 des Passwort-Reset: E-Mail mit Reset-Link versenden.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        \Illuminate\Support\Facades\Password::sendResetLink(
            $request->only('email')
        );

        // Aus Sicherheitsgründen IMMER dieselbe Erfolgsmeldung zeigen,
        // egal ob die E-Mail existiert - verhindert, dass jemand
        // herausfinden kann, welche E-Mail-Adressen registriert sind.
        return response()->json([
            'message' => 'Falls ein Konto mit dieser E-Mail existiert, wurde ein Link zum Zurücksetzen des Passworts gesendet.',
        ]);
    }

    /**
     * Schritt 2 des Passwort-Reset: Neues Passwort mit Token setzen.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

              $status = \Illuminate\Support\Facades\Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                // Sicherheit: alle bestehenden Login-Sitzungen beenden,
                // nachdem das Passwort zurückgesetzt wurde.
                $user->tokens()->delete();

                // Sicherheits-Benachrichtigung: informiert den echten
                // Kontoinhaber, falls er die Änderung nicht selbst
                // vorgenommen hat.
                $user->notify(new \App\Notifications\PasswordChangedNotification());
            }
        );

        if ($status === \Illuminate\Support\Facades\Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Passwort erfolgreich zurückgesetzt. Sie können sich jetzt anmelden.',
            ]);
        }

        return response()->json([
            'message' => $status === \Illuminate\Support\Facades\Password::INVALID_TOKEN
                ? 'Dieser Link ist ungültig oder abgelaufen. Bitte fordern Sie einen neuen an.'
                : 'Passwort konnte nicht zurückgesetzt werden.',
        ], 400);
    }

    /**
     * Passwort ändern für eingeloggte Nutzer (in den Einstellungen).
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Das aktuelle Passwort ist nicht korrekt.',
            ], 422);
        }

             $user->update([
            'password' => Hash::make($request->password),
        ]);

        $user->notify(new \App\Notifications\PasswordChangedNotification());

        return response()->json([
            'message' => 'Passwort erfolgreich geändert.',
        ]);
    }
}
