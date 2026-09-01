<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
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
     * Erstellt eine Stripe Checkout Session für den gewünschten Plan
     * und gibt die URL zurück, zu der das Frontend weiterleiten soll.
     */
       public function createCheckoutSession(Request $request): JsonResponse
    {
        $request->validate([
            'plan' => 'required|in:starter,pro',
            'quantity' => 'nullable|integer|min:1|max:100',
        ]);

        $company = $request->user()->company;
        $frontendUrl = env('APP_FRONTEND_URL', 'http://localhost:9000');

        $priceId = $request->plan === 'pro'
            ? config('services.stripe.price_pro')
            : config('services.stripe.price_starter');

        if (empty($priceId)) {
            Log::error('Stripe Checkout: Keine Preis-ID konfiguriert', ['plan' => $request->plan]);
            return response()->json([
                'message' => 'Dieser Plan ist aktuell nicht verfügbar. Bitte kontaktieren Sie uns.',
            ], 500);
        }

        // Starter wird pro Nutzer abgerechnet (Menge = Anzahl Sitzplätze,
        // einmalig beim Checkout festgelegt). Pro ist ein fester
        // Zusatzpreis unabhängig von der Nutzerzahl - daher hier bewusst
        // immer Menge 1, unabhängig davon was im Request mitgeschickt wird.
        $quantity = $request->plan === 'starter'
            ? (int) $request->input('quantity', 1)
            : 1;

        try {
            $sessionParams = [
                'mode' => 'subscription',
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => $quantity,
                ]],
                'success_url' => $frontendUrl . '/#/settings?checkout=success',
                'cancel_url' => $frontendUrl . '/#/upgrade?checkout=cancelled',
                'client_reference_id' => (string) $company->id,
                'metadata' => [
                    'company_id' => $company->id,
                    'plan' => $request->plan,
                    'quantity' => $quantity,
                ],
            ];

            // Falls die Firma schon einen Stripe-Kunden hat, wiederverwenden -
            // verhindert doppelte Kunden-Datensätze bei erneutem Checkout
            // (z.B. nach vorheriger Kündigung).
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
                'plan' => $request->plan,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Checkout konnte nicht gestartet werden. Bitte versuchen Sie es erneut.',
            ], 500);
        }
    }

    /**
     * Webhook-Endpoint für Stripe-Events. Läuft OHNE Sanctum-Auth,
     * da Stripe selbst der Aufrufer ist - Absicherung erfolgt über
     * die Signaturprüfung, nicht über einen Login-Token.
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

        try {
            match ($event->type) {
                'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
                'invoice.payment_failed' => $this->handlePaymentFailed($event->data->object),
                default => Log::info('Stripe Webhook: Event-Typ nicht behandelt', ['type' => $event->type]),
            };
        } catch (\Throwable $e) {
            // Fehler beim Verarbeiten NICHT nach außen als 500 zurückgeben -
            // Stripe würde das Event sonst wiederholt erneut senden.
            // Stattdessen loggen wir es und bestätigen den Empfang trotzdem,
            // damit wir das Problem in Ruhe manuell untersuchen können.
            Log::error('Stripe Webhook: Fehler bei Verarbeitung', [
                'type' => $event->type,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Checkout erfolgreich abgeschlossen: Firma auf den gebuchten Plan
     * setzen und Stripe-IDs speichern.
     */
    private function handleCheckoutCompleted(object $session): void
    {
        $companyId = $session->metadata->company_id ?? $session->client_reference_id ?? null;
        $plan = $session->metadata->plan ?? 'starter';

        if (!$companyId) {
            Log::error('Stripe Webhook: checkout.session.completed ohne company_id', [
                'session_id' => $session->id,
            ]);
            return;
        }

        $company = Company::find($companyId);
        if (!$company) {
            Log::error('Stripe Webhook: Firma nicht gefunden', ['company_id' => $companyId]);
            return;
        }

              // Verlängerungsdatum direkt von Stripe holen, damit wir es dem
        // Kunden anzeigen können, ohne bei jedem Aufruf Stripe zu fragen.
        $currentPeriodEnd = null;
        try {
            $subscription = \Stripe\Subscription::retrieve($session->subscription);
            $currentPeriodEnd = \Carbon\Carbon::createFromTimestamp($subscription->current_period_end);
        } catch (\Exception $e) {
            Log::warning('Stripe: current_period_end konnte nicht geladen werden', [
                'subscription_id' => $session->subscription,
                'error' => $e->getMessage(),
            ]);
        }

        $company->update([
            'plan' => $plan,
            'stripe_customer_id' => $session->customer,
            'stripe_subscription_id' => $session->subscription,
            'subscription_started_at' => now(),
            'current_period_end' => $currentPeriodEnd,
            'cancelled_at' => null,
            'access_until' => null,
        ]);

              Log::info('Stripe: Abo erfolgreich aktiviert', [
            'company_id' => $company->id,
            'plan' => $plan,
            'quantity' => $session->metadata->quantity ?? null,
        ]);
    }

    /**
     * Abo wurde in Stripe geändert (z.B. Plan-Wechsel direkt in Stripe,
     * oder Kündigung zum Periodenende wurde markiert).
     */
    private function handleSubscriptionUpdated(object $subscription): void
    {
        $company = Company::where('stripe_subscription_id', $subscription->id)->first();
        if (!$company) {
            Log::warning('Stripe Webhook: Firma zu Subscription nicht gefunden', [
                'subscription_id' => $subscription->id,
            ]);
            return;
        }

           $currentPeriodEnd = \Carbon\Carbon::createFromTimestamp($subscription->current_period_end);

        // Wenn Stripe meldet, dass zum Periodenende gekündigt wird
        if ($subscription->cancel_at_period_end) {
            $company->update([
                'current_period_end' => $currentPeriodEnd,
                'cancelled_at' => now(),
                'access_until' => $currentPeriodEnd,
            ]);
        } else {
            // Kündigung wurde zurückgenommen, oder normale monatliche
            // Verlängerung - Datum trotzdem aktuell halten.
            $company->update([
                'current_period_end' => $currentPeriodEnd,
                'cancelled_at' => null,
                'access_until' => null,
            ]);
        }
    }

    /**
     * Abo wurde in Stripe endgültig beendet (nach Ablauf der Kündigungsfrist).
     * Firma fällt zurück auf "kein aktiver Plan".
     */
    private function handleSubscriptionDeleted(object $subscription): void
    {
        $company = Company::where('stripe_subscription_id', $subscription->id)->first();
        if (!$company) {
            Log::warning('Stripe Webhook: Firma zu gelöschter Subscription nicht gefunden', [
                'subscription_id' => $subscription->id,
            ]);
            return;
        }

        $company->update([
            'plan' => 'trial',
            'stripe_subscription_id' => null,
        ]);

        Log::info('Stripe: Abo beendet, Firma zurückgesetzt', ['company_id' => $company->id]);
    }

    /**
     * Zahlung fehlgeschlagen (z.B. Kreditkarte abgelehnt). Wir sperren
     * nicht sofort - Stripe versucht automatisch mehrfach erneut zu
     * belasten (Smart Retries), bevor die Subscription endgültig endet.
     */
    private function handlePaymentFailed(object $invoice): void
    {
        Log::warning('Stripe: Zahlung fehlgeschlagen', [
            'customer' => $invoice->customer,
            'invoice_id' => $invoice->id,
        ]);
        // Kein automatisches Sperren hier - customer.subscription.deleted
        // übernimmt das, falls Stripe nach mehreren Fehlversuchen aufgibt.
    }
}