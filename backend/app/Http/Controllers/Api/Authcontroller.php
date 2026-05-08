<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class AuthController extends Controller
{
    /**
     * Registrierung: Erstellt User + Company, sendet Verifikations-E-Mail.
     * Gibt KEINEN Token zurück – erst nach E-Mail-Bestätigung kann man sich einloggen.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'invite_code'  => 'required|string',
            'name'         => 'required|string|max:255',
            'email'        => 'required|string|email|max:255|unique:users',
            'password'     => ['required', 'confirmed', Rules\Password::defaults()],
            'company_name' => 'required|string|max:255',
        ]);

        // Einladungscode prüfen
        if ($request->invite_code !== env('INVITE_CODE', 'PILOT2026')) {
            return response()->json([
                'message' => 'Ungültiger Einladungscode.',
                'errors'  => ['invite_code' => ['Der Einladungscode ist ungültig.']],
            ], 422);
        }

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
}
