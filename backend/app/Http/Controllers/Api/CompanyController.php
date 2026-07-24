<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * Firmendaten abrufen.
     */
    public function show(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        return response()->json($company);
    }

    /**
     * Firmendaten aktualisieren.
     */
    public function update(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'trade' => 'nullable|string|max:100',
            'address_street' => 'nullable|string|max:255',
            'address_zip' => 'nullable|string|max:10',
            'address_city' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'trade_register' => 'nullable|string|max:100',
            'primary_color' => 'nullable|string|max:7',
            'default_vat_rate' => 'nullable|numeric|min:0|max:100',
            'default_hourly_rate' => 'nullable|numeric|min:0',
            'quote_validity_days' => 'nullable|integer|min:1|max:365',
            'quote_prefix' => 'nullable|string|max:10',
        ]);

        $company->update($request->only([
            'name', 'address_street', 'address_zip', 'address_city',
            'phone', 'email', 'website', 'tax_id', 'trade_register',
            'primary_color', 'default_vat_rate', 'default_hourly_rate',
            'quote_validity_days', 'quote_prefix','trade'
        ]));

        return response()->json([
            'message' => 'Firmendaten aktualisiert.',
            'company' => $company->fresh(),
        ]);
    }

    /**
     * Logo hochladen.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,svg|max:2048',
        ]);

        $company = $request->user()->company;

        // Altes Logo löschen
        if ($company->logo_path) {
            $oldPath = storage_path('app/public/' . $company->logo_path);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $path = $request->file('logo')->store('logos/' . $company->id, 'public');

        $company->update(['logo_path' => $path]);

        return response()->json([
            'message' => 'Logo hochgeladen.',
            'logo_url' => asset('storage/' . $path),
            'company' => $company->fresh(),
        ]);
    }

    /**
     * Logo entfernen.
     */
    public function removeLogo(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        if ($company->logo_path) {
            $oldPath = storage_path('app/public/' . $company->logo_path);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
            $company->update(['logo_path' => null]);
        }

        return response()->json([
            'message' => 'Logo entfernt.',
            'company' => $company->fresh(),
        ]);
    }


    /**
 * Abo kündigen. Setzt NUR Datumsfelder, löscht keinerlei Daten.
 * Zugriff bleibt bis access_until bestehen (Restlaufzeit, hier: Ende des aktuellen Abrechnungsmonats).
 */
public function cancelSubscription(Request $request): JsonResponse
{
    $company = $request->user()->company;

    if ($company->isCancelled()) {
        return response()->json([
            'message' => 'Das Abo wurde bereits gekündigt.',
        ], 422);
    }

    if (!in_array($company->plan, ['starter', 'professional', 'enterprise'])) {
        return response()->json([
            'message' => 'Es besteht kein aktives Abo, das gekündigt werden könnte.',
        ], 422);
    }

    // Restlaufzeit: bis zum Ende des aktuellen Abrechnungsmonats
    // (Später mit Stripe: stripe_subscription->current_period_end verwenden)
    $accessUntil = now()->endOfMonth();

    $company->update([
        'cancelled_at' => now(),
        'access_until' => $accessUntil,
    ]);

    return response()->json([
        'message' => 'Kündigung bestätigt. Ihr Zugriff bleibt bis ' . $accessUntil->format('d.m.Y') . ' bestehen. Alle Ihre Daten (Angebote, Kunden, Materialien) bleiben danach vollständig erhalten.',
        'company' => $company->fresh(),
    ]);
}

/**
 * Kündigung zurücknehmen (falls Nutzer es sich anders überlegt, solange Frist noch läuft).
 */
public function reactivateSubscription(Request $request): JsonResponse
{
    $company = $request->user()->company;

    if (!$company->isCancelled()) {
        return response()->json(['message' => 'Es liegt keine Kündigung vor.'], 422);
    }

    $company->update([
        'cancelled_at' => null,
        'access_until' => null,
    ]);

    return response()->json([
        'message' => 'Kündigung zurückgenommen. Ihr Abo läuft normal weiter.',
        'company' => $company->fresh(),
    ]);
}
}