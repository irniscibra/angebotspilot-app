<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Meine Sätze": eigene, firmenspezifische Preise (z.B. Minibagger 89€/Std),
 * die die KI bei jeder Angebotserstellung zusätzlich zu den allgemeinen
 * Gewerke-Referenzpreisen berücksichtigt. Anders als der Materialkatalog gibt
 * es hier keinen exakten Artikelabgleich (SKU) — die KI ordnet die Sätze
 * anhand von Name + Notiz selbst der Projektbeschreibung zu.
 */
class CompanyRateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rates = $request->user()->company->companyRates()
            ->orderBy('name')
            ->get();

        return response()->json($rates);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'unit' => 'required|string|max:20',
            'note' => 'nullable|string|max:1000',
        ]);

        $rate = CompanyRate::create([
            'company_id' => $request->user()->company_id,
            ...$request->only(['name', 'price', 'unit', 'note']),
        ]);

        return response()->json($rate, 201);
    }

    public function update(Request $request, CompanyRate $companyRate): JsonResponse
    {
        if ($companyRate->company_id !== $request->user()->company_id) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'unit' => 'required|string|max:20',
            'note' => 'nullable|string|max:1000',
        ]);

        $companyRate->update($request->only(['name', 'price', 'unit', 'note']));

        return response()->json($companyRate);
    }

    public function destroy(Request $request, CompanyRate $companyRate): JsonResponse
    {
        if ($companyRate->company_id !== $request->user()->company_id) {
            abort(403);
        }

        $companyRate->delete();

        return response()->json(['message' => 'Satz gelöscht.']);
    }
}
