<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Nicht authentifiziert → Sanctum kümmert sich
        if (!$user) {
            return $next($request);
        }

        $company = $user->company;

        if (!$company) {
            return response()->json(['message' => 'Keine Firma gefunden.'], 403);
        }

        // Aktives Abo oder Trial noch gültig → Zugang erlaubt
        if ($company->hasActiveSubscription()) {
            return $next($request);
        }

        // Trial abgelaufen
        return response()->json([
            'message'        => 'Ihr Testzeitraum ist abgelaufen. Bitte wählen Sie ein Abonnement.',
            'trial_expired'  => true,
            'trial_ends_at'  => $company->trial_ends_at?->toDateTimeString(),
            'plan'           => $company->plan,
        ], 402);
    }
}
