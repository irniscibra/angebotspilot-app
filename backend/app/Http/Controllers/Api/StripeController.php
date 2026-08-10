<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Erstellt eine Stripe Checkout Session und gibt die URL zurück,
     * zu der das Frontend weiterleiten soll.
     */
    public function createCheckoutSession(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        $frontendUrl = config('app.frontend_url', 'https://app.angebotspilot.app');

        try {
            $sessionParams = [
                'mode' => 'subscription',
                'line_items' => [[
                    'price' => config('services.stripe.price_starter'),
                    'quantity' => 1,
                ]],
                'success_url' => $frontendUrl . '/#/settings?checkout=success',
                'cancel_url' => $frontendUrl . '/#/upgrade?checkout=cancelled',
                'client_reference_id' => (string) $company->id,
                'metadata' => [
                    'company_id' => $company->id,
                ],
            ];

            // Falls die Firma schon einen Stripe-Kunden hat, wiederverwenden
            if ($company->stripe_customer_id) {
                $sessionParams['customer'] = $company->stripe_customer_id;
            } else {
                $sessionParams['customer_email'] = $request->user()->email;
            }

            $session = Session::create($sessionParams);

            return response()->json([
                'checkout_url' => $session->url,
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe Checkout Session Fehler', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Checkout konnte nicht gestartet werden. Bitte versuchen Sie es erneut.',
            ], 500);
        }
    }

    /**
     * Webhook-Endpoint für Stripe-Events.
     * Baut das noch nicht komplett aus - erstmal nur das Grundgerüst,
     * das eigentliche Event-Handling kommt im nächsten Schritt.
     */
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\Exception $e) {
            Log::error('Stripe Webhook Signatur ungültig', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        Log::info('Stripe Webhook empfangen', ['type' => $event->type]);

        // Event-Handling kommt im nächsten Schritt

        return response()->json(['received' => true]);
    }
}