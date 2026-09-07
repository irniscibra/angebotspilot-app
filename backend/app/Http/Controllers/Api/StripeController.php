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

        // Beide Plaene starten immer mit Menge 1 (Basispreis pro Firma).
        // Zusaetzliche Mitarbeiter-Sitzplaetze werden NICHT mehr hier beim
        // Checkout mitgekauft, sondern jederzeit danach im Team-Bereich
        // ueber updateSeats() als separate Abo-Position hinzugefuegt -
        // einheitlich fuer Starter und Pro.
        try {
            $sessionParams = [
                'mode' => 'subscription',
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'success_url' => $frontendUrl . '/#/settings?checkout=success',
                'cancel_url' => $frontendUrl . '/#/upgrade?checkout=cancelled',
                'client_reference_id' => (string) $company->id,
                'metadata' => [
                    'company_id' => $company->id,
                    'plan' => $request->plan,
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
     * Zusaetzliche Mitarbeiter-Sitzplaetze jederzeit im Team-Bereich
     * anpassen (nicht nur beim initialen Checkout). $seats ist die
     * GESAMTZAHL der gewuenschten zugekauften Sitzplaetze (on top der im
     * Plan inkludierten) - analog zu Slack/GitHub "Sitzplatzkontingent auf
     * X setzen" statt "einen dazu buchen", das ist robuster gegen
     * Doppelklicks/Race-Conditions und einfacher zu testen als ein
     * Inkrement-Endpoint. Preisaenderungen werden SOFORT abgerechnet (proration_behavior=always_invoice,
     * nicht erst bei der naechsten reguleren Rechnung) - gaengige Praxis bei selbstbedienbarem
     * Sitzplatz-Zukauf (Slack, GitHub etc.): der Kunde sieht die Belastung sofort, statt Monate
     * spaeter einem unerklaerten hoeheren Betrag zu begegnen. Bei einer Reduzierung entsteht dadurch
     * kein Rueckerstattungs-Vorgang, sondern ein Guthaben, das automatisch mit der naechsten
     * Rechnung verrechnet wird (Stripe-Standard).
     */
    public function updateSeats(Request $request): JsonResponse
    {
        $request->validate([
            'seats' => 'required|integer|min:0|max:50',
        ]);

        $company = $request->user()->company;
        $seats = (int) $request->input('seats');

        if (!$company->stripe_subscription_id) {
            return response()->json([
                'message' => 'Zusätzliche Sitzplätze sind erst nach Buchung eines Abos verfügbar.',
            ], 422);
        }

        $included = Company::EMPLOYEE_SEATS_INCLUDED[$company->plan] ?? 0;
        $newLimit = $included + $seats;
        if ($company->activeEmployeeCount() > $newLimit) {
            return response()->json([
                'message' => 'Dazu müssten Sie zuerst Mitarbeiter aus dem Team entfernen - aktuell sind mehr aktive Mitarbeiter im Team, als die neue Anzahl an Sitzplätzen erlauben würde.',
            ], 422);
        }

        $priceId = config('services.stripe.price_seat');
        if (empty($priceId)) {
            Log::error('Stripe: Keine Preis-ID fuer Zusatzsitzplaetze konfiguriert');
            return response()->json([
                'message' => 'Zusätzliche Sitzplätze sind aktuell nicht verfügbar. Bitte kontaktieren Sie uns.',
            ], 500);
        }

        try {
            $subscription = \Stripe\Subscription::retrieve($company->stripe_subscription_id);

            $seatItem = null;
            foreach ($subscription->items->data as $item) {
                if ($item->price->id === $priceId) {
                    $seatItem = $item;
                    break;
                }
            }

            if ($seats === 0) {
                // Nur aufraeumen, falls tatsaechlich eine Sitzplatz-Position
                // existiert - sonst gibt es nichts zu loeschen.
                if ($seatItem) {
                    \Stripe\Subscription::update($company->stripe_subscription_id, [
                        'items' => [['id' => $seatItem->id, 'deleted' => true]],
                        'proration_behavior' => 'always_invoice',
                    ]);
                }
            } elseif ($seatItem) {
                \Stripe\Subscription::update($company->stripe_subscription_id, [
                    'items' => [['id' => $seatItem->id, 'quantity' => $seats]],
                    'proration_behavior' => 'always_invoice',
                ]);
            } else {
                \Stripe\Subscription::update($company->stripe_subscription_id, [
                    'items' => [['price' => $priceId, 'quantity' => $seats]],
                    'proration_behavior' => 'always_invoice',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Stripe: Sitzplatz-Anpassung fehlgeschlagen', [
                'company_id' => $company->id,
                'seats' => $seats,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Sitzplätze konnten nicht angepasst werden. Bitte versuchen Sie es erneut.',
            ], 500);
        }

        // Erst NACH erfolgreicher Stripe-Aenderung lokal speichern, damit
        // wir nie einen Sitzplatz zeigen, der bei Stripe nicht auch
        // tatsaechlich abgerechnet wird.
        $company->update(['employee_seats_purchased' => $seats]);

        Log::info('Stripe: Mitarbeiter-Sitzplaetze angepasst', [
            'company_id' => $company->id,
            'seats' => $seats,
        ]);

        return response()->json([
            'seats' => [
                'used' => $company->activeEmployeeCount(),
                'limit' => $company->employeeSeatLimit(),
                'purchased' => $seats,
                'price_per_seat' => Company::SEAT_PRICE_EUR,
            ],
        ]);
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
            $currentPeriodEnd = $this->resolveCurrentPeriodEnd($subscription);
        } catch (\Throwable $e) {
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
        ]);
    }

    /**
     * Ermittelt das Ende der aktuellen Abrechnungsperiode robust.
     *
     * Aeltere Stripe-API-Versionen liefern "current_period_end" direkt auf
     * der Subscription. Seit Stripes Umstellung auf "flexible billing mode"
     * (relevant fuer uns, seit ein Abo durch die Sitzplatz-Zusatzposition
     * mehrere Subscription-Items haben kann) steht das Feld dort nicht mehr
     * zuverlaessig - dann liegt es nur noch auf dem jeweiligen Item. Diese
     * Methode prueft beide Stellen und gibt bei Nichtverfuegbarkeit null
     * zurueck, statt (wie zuvor) einen TypeError zu werfen, der die
     * komplette Webhook-Verarbeitung samt Company-Update abgebrochen hat.
     */
    private function resolveCurrentPeriodEnd(object $subscription): ?\Carbon\Carbon
    {
        $timestamp = $subscription->current_period_end
            ?? ($subscription->items->data[0]->current_period_end ?? null);

        if (empty($timestamp)) {
            return null;
        }

        try {
            return \Carbon\Carbon::createFromTimestamp($timestamp);
        } catch (\Throwable $e) {
            Log::warning('Stripe: current_period_end konnte nicht in Carbon umgewandelt werden', [
                'subscription_id' => $subscription->id ?? null,
                'timestamp' => $timestamp,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
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

        $currentPeriodEnd = $this->resolveCurrentPeriodEnd($subscription);

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