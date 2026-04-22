<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PublicQuoteController extends Controller
{
    /**
     * Öffentliches Angebot abrufen (kein Login nötig).
     */
    public function show(string $uuid): JsonResponse
    {
        $quote = Quote::where('uuid', $uuid)
            ->with(['company', 'customer', 'items'])
            ->firstOrFail();

        // Abgelaufen?
        if ($quote->valid_until && $quote->valid_until->isPast()) {
            return response()->json(['error' => 'Dieses Angebot ist abgelaufen.'], 410);
        }

        // Abgelehnt oder bereits angenommen?
        if (in_array($quote->status, ['rejected'])) {
            return response()->json(['error' => 'Dieses Angebot ist nicht mehr verfügbar.'], 410);
        }

        // Als "gesehen" markieren
        $quote->markAsViewed();

        // Gruppierte Items
        $groupedItems = $quote->items->groupBy('group_name');

        return response()->json([
            'quote' => [
                'uuid'               => $quote->uuid,
                'quote_number'       => $quote->quote_number,
                'project_title'      => $quote->project_title,
                'project_description'=> $quote->project_description,
                'project_address'    => $quote->project_address,
                'status'             => $quote->status,
                'valid_until'        => $quote->valid_until?->format('d.m.Y'),
                'created_at'         => $quote->created_at->format('d.m.Y'),
                'subtotal_materials' => $quote->subtotal_materials,
                'subtotal_labor'     => $quote->subtotal_labor,
                'subtotal_net'       => $quote->subtotal_net,
                'vat_rate'           => $quote->vat_rate,
                'vat_amount'         => $quote->vat_amount,
                'total_gross'        => $quote->total_gross,
                'discount_percent'   => $quote->discount_percent,
                'discount_amount'    => $quote->discount_amount,
                'header_text'        => $quote->header_text,
                'footer_text'        => $quote->footer_text,
                'terms_text'         => $quote->terms_text,
            ],
            'company' => [
                'name'           => $quote->company->name,
                'address_street' => $quote->company->address_street,
                'address_zip'    => $quote->company->address_zip,
                'address_city'   => $quote->company->address_city,
                'phone'          => $quote->company->phone,
                'email'          => $quote->company->email,
                'website'        => $quote->company->website,
                'tax_id'         => $quote->company->tax_id,
                'primary_color'  => $quote->company->primary_color ?? '#1E40AF',
                'logo_url'       => $quote->company->logo_path
                    ? asset('storage/' . $quote->company->logo_path)
                    : null,
            ],
            'customer' => $quote->customer ? [
                'name'    => $quote->customer->type === 'business'
                    ? $quote->customer->company_name
                    : $quote->customer->first_name . ' ' . $quote->customer->last_name,
                'address' => $quote->customer->address_street,
                'zip'     => $quote->customer->address_zip,
                'city'    => $quote->customer->address_city,
            ] : null,
            'grouped_items' => $groupedItems->map(fn($items) => $items->map(fn($item) => [
                'id'          => $item->id,
                'title'       => $item->title,
                'description' => $item->description,
                'type'        => $item->type,
                'quantity'    => $item->quantity,
                'unit'        => $item->unit,
                'unit_price'  => $item->unit_price,
                'total_price' => $item->total_price,
            ]))->toArray(),
        ]);
    }

    /**
     * Angebot annehmen (Kunde klickt "Annehmen").
     */
    public function accept(Request $request, string $uuid): JsonResponse
    {
        $quote = Quote::where('uuid', $uuid)->firstOrFail();

        // Validierung
        if ($quote->valid_until && $quote->valid_until->isPast()) {
            return response()->json(['error' => 'Angebot ist abgelaufen.'], 410);
        }

        if ($quote->status === 'accepted') {
            return response()->json(['error' => 'Angebot wurde bereits angenommen.'], 409);
        }

        if ($quote->status === 'rejected') {
            return response()->json(['error' => 'Angebot ist nicht mehr verfügbar.'], 410);
        }

        $request->validate([
            'signer_name' => 'required|string|max:255',
            'signature'   => 'required|string', // Base64 SVG der Unterschrift
        ]);

        // IP und Timestamp speichern
        $quote->update([
            'status'      => 'accepted',
            'accepted_at' => now(),
            'internal_notes' => ($quote->internal_notes ?? '') .
                "\n[Digital angenommen am " . now()->format('d.m.Y H:i') .
                " von " . $request->signer_name .
                " | IP: " . $request->ip() . "]",
        ]);

        Log::info('Angebot digital angenommen', [
            'quote_id'    => $quote->id,
            'quote_number'=> $quote->quote_number,
            'signer_name' => $request->signer_name,
            'ip'          => $request->ip(),
        ]);

        return response()->json([
            'message'      => 'Angebot erfolgreich angenommen!',
            'accepted_at'  => now()->format('d.m.Y H:i'),
            'signer_name'  => $request->signer_name,
            'quote_number' => $quote->quote_number,
        ]);
    }

    /**
     * Angebot ablehnen.
     */
    public function reject(Request $request, string $uuid): JsonResponse
    {
        $quote = Quote::where('uuid', $uuid)->firstOrFail();

        if (in_array($quote->status, ['accepted', 'rejected'])) {
            return response()->json(['error' => 'Angebot bereits bearbeitet.'], 409);
        }

        $quote->markAsRejected();

        Log::info('Angebot abgelehnt', [
            'quote_id' => $quote->id,
            'ip'       => $request->ip(),
        ]);

        return response()->json(['message' => 'Angebot abgelehnt.']);
    }

    /**
     * Online-Link generieren (für eingeloggten Handwerker).
     */
    public function generateLink(Request $request, Quote $quote): JsonResponse
    {
        // Sicherstellen dass UUID existiert
        if (empty($quote->uuid)) {
            $quote->update(['uuid' => \Illuminate\Support\Str::uuid()]);
        }

        $frontendUrl = config('app.frontend_url', 'https://app.angebotspilot.app');
        $publicUrl = $frontendUrl . '/#/angebot/' . $quote->uuid;

        return response()->json([
            'url'          => $publicUrl,
            'uuid'         => $quote->uuid,
            'valid_until'  => $quote->valid_until?->format('d.m.Y'),
        ]);
    }
}