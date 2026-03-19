<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Intelligente Dashboard-Daten mit Handlungsempfehlungen.
     */
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        $now = Carbon::now();

        // ── Statistik-Karten ──
        $stats = $this->getStats($company, $now);

        // ── Handlungsempfehlungen (Action Items) ──
        $actions = $this->getActionItems($company, $now);

        // ── Letzte Aktivitäten ──
        $recentActivity = $this->getRecentActivity($company);

        // ── Umsatz-Übersicht (letzte 6 Monate) ──
        $revenueChart = $this->getRevenueChart($company, $now);

        return response()->json([
            'stats' => $stats,
            'actions' => $actions,
            'recent_activity' => $recentActivity,
            'revenue_chart' => $revenueChart,
        ]);
    }

    /**
     * Statistik-Karten für das Dashboard.
     */
    private function getStats($company, Carbon $now): array
    {
        $quotesThisMonth = $company->quotes()
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count();

        $quotesLastMonth = $company->quotes()
            ->whereYear('created_at', $now->copy()->subMonth()->year)
            ->whereMonth('created_at', $now->copy()->subMonth()->month)
            ->count();

        $revenueThisMonth = $company->invoices()
            ->where('status', 'paid')
            ->whereYear('paid_at', $now->year)
            ->whereMonth('paid_at', $now->month)
            ->sum('total_gross');

        $revenueLastMonth = $company->invoices()
            ->where('status', 'paid')
            ->whereYear('paid_at', $now->copy()->subMonth()->year)
            ->whereMonth('paid_at', $now->copy()->subMonth()->month)
            ->sum('total_gross');

        $openQuotes = $company->quotes()
            ->whereIn('status', ['sent', 'viewed'])
            ->sum('total_gross');

        $unpaidInvoices = $company->invoices()
            ->whereIn('status', ['sent', 'partial_paid'])
            ->sum('total_gross');

        // Erfolgsquote
        $sentQuotes = $company->quotes()
            ->whereIn('status', ['sent', 'viewed', 'accepted', 'rejected'])
            ->count();
        $acceptedQuotes = $company->quotes()
            ->where('status', 'accepted')
            ->count();
        $conversionRate = $sentQuotes > 0
            ? round(($acceptedQuotes / $sentQuotes) * 100, 1)
            : 0;

        return [
            'quotes_this_month' => $quotesThisMonth,
            'quotes_last_month' => $quotesLastMonth,
            'quotes_total' => $company->quotes()->count(),
            'quotes_draft' => $company->quotes()->where('status', 'draft')->count(),
            'quotes_accepted' => $acceptedQuotes,
            'revenue_this_month' => round($revenueThisMonth, 2),
            'revenue_last_month' => round($revenueLastMonth, 2),
            'open_quotes_value' => round($openQuotes, 2),
            'unpaid_invoices_value' => round($unpaidInvoices, 2),
            'conversion_rate' => $conversionRate,
        ];
    }

    /**
     * Handlungsempfehlungen – das Herzstück des intelligenten Dashboards.
     * Zeigt dem Handwerker genau was er heute tun sollte.
     */
    private function getActionItems($company, Carbon $now): array
    {
        $actions = [];

        // 1. Angebote nachfassen (älter als 7 Tage, noch nicht angenommen/abgelehnt)
        $staleQuotes = $company->quotes()
            ->whereIn('status', ['sent', 'viewed'])
            ->where('sent_at', '<', $now->copy()->subDays(7))
            ->with('customer:id,first_name,last_name,company_name,type')
            ->orderBy('sent_at', 'asc')
            ->limit(10)
            ->get(['id', 'quote_number', 'project_title', 'total_gross', 'sent_at', 'viewed_at', 'customer_id']);

        foreach ($staleQuotes as $q) {
            $daysSince = $now->diffInDays(Carbon::parse($q->sent_at));
            $customerName = $this->getCustomerName($q->customer);
            $viewed = $q->viewed_at ? ' (vom Kunden angesehen)' : '';

            $actions[] = [
                'type' => 'follow_up',
                'priority' => $daysSince > 14 ? 'high' : 'medium',
                'icon' => 'phone_callback',
                'color' => $daysSince > 14 ? 'red' : 'orange',
                'title' => "Angebot nachfassen: {$q->project_title}",
                'subtitle' => "{$q->quote_number} · {$customerName} · Seit {$daysSince} Tagen offen{$viewed}",
                'value' => $q->total_gross,
                'link' => "/quotes/{$q->id}",
                'days' => $daysSince,
            ];
        }

        // 2. Überfällige Rechnungen
        $overdueInvoices = $company->invoices()
            ->whereIn('status', ['sent', 'partial_paid'])
            ->where('due_date', '<', $now)
            ->with('customer:id,first_name,last_name,company_name,type')
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get(['id', 'invoice_number', 'project_title', 'total_gross', 'due_date', 'customer_id']);

        foreach ($overdueInvoices as $inv) {
            $daysOverdue = $now->diffInDays(Carbon::parse($inv->due_date));
            $customerName = $this->getCustomerName($inv->customer);

            $actions[] = [
                'type' => 'overdue_invoice',
                'priority' => $daysOverdue > 14 ? 'high' : 'medium',
                'icon' => 'warning',
                'color' => 'red',
                'title' => "Rechnung überfällig: {$inv->invoice_number}",
                'subtitle' => "{$inv->project_title} · {$customerName} · Seit {$daysOverdue} Tagen überfällig",
                'value' => $inv->total_gross,
                'link' => "/invoices/{$inv->id}",
                'days' => $daysOverdue,
            ];
        }

        // 3. Entwürfe fertigstellen (älter als 3 Tage)
        $oldDrafts = $company->quotes()
            ->where('status', 'draft')
            ->where('created_at', '<', $now->copy()->subDays(3))
            ->orderBy('created_at', 'asc')
            ->limit(5)
            ->get(['id', 'quote_number', 'project_title', 'total_gross', 'created_at']);

        foreach ($oldDrafts as $draft) {
            $daysSince = $now->diffInDays(Carbon::parse($draft->created_at));

            $actions[] = [
                'type' => 'draft_reminder',
                'priority' => 'low',
                'icon' => 'edit_note',
                'color' => 'grey',
                'title' => "Entwurf fertigstellen: {$draft->project_title}",
                'subtitle' => "{$draft->quote_number} · Erstellt vor {$daysSince} Tagen",
                'value' => $draft->total_gross,
                'link' => "/quotes/{$draft->id}",
                'days' => $daysSince,
            ];
        }

        // 4. Angebote die kürzlich angesehen wurden (Kunde hat Interesse!)
        $recentlyViewed = $company->quotes()
            ->where('status', 'viewed')
            ->where('viewed_at', '>', $now->copy()->subDays(3))
            ->with('customer:id,first_name,last_name,company_name,type')
            ->orderBy('viewed_at', 'desc')
            ->limit(5)
            ->get(['id', 'quote_number', 'project_title', 'total_gross', 'viewed_at', 'customer_id']);

        foreach ($recentlyViewed as $v) {
            $customerName = $this->getCustomerName($v->customer);
            $viewedAgo = $now->diffInHours(Carbon::parse($v->viewed_at));
            $timeText = $viewedAgo < 24 ? "vor {$viewedAgo} Stunden" : "vor " . $now->diffInDays(Carbon::parse($v->viewed_at)) . " Tagen";

            $actions[] = [
                'type' => 'viewed',
                'priority' => 'medium',
                'icon' => 'visibility',
                'color' => 'blue',
                'title' => "Kunde hat Angebot angesehen!",
                'subtitle' => "{$v->quote_number} · {$v->project_title} · {$customerName} · {$timeText}",
                'value' => $v->total_gross,
                'link' => "/quotes/{$v->id}",
                'days' => 0,
            ];
        }

        // Nach Priorität sortieren: high → medium → low
        $priorityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($actions, function ($a, $b) use ($priorityOrder) {
            $pA = $priorityOrder[$a['priority']] ?? 9;
            $pB = $priorityOrder[$b['priority']] ?? 9;
            if ($pA !== $pB) return $pA - $pB;
            return ($b['days'] ?? 0) - ($a['days'] ?? 0);
        });

        return $actions;
    }

    /**
     * Letzte Aktivitäten (Timeline).
     */
    private function getRecentActivity($company): array
    {
        $activities = [];

        // Letzte Angebote
        $recentQuotes = $company->quotes()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'quote_number', 'project_title', 'status', 'total_gross', 'created_at']);

        foreach ($recentQuotes as $q) {
            $activities[] = [
                'type' => 'quote',
                'icon' => 'description',
                'color' => 'blue',
                'title' => "Angebot erstellt: {$q->project_title}",
                'subtitle' => "{$q->quote_number} · " . number_format((float)$q->total_gross, 2, ',', '.') . ' €',
                'date' => $q->created_at,
                'link' => "/quotes/{$q->id}",
            ];
        }

        // Letzte Rechnungen
        $recentInvoices = $company->invoices()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'invoice_number', 'project_title', 'status', 'total_gross', 'created_at']);

        foreach ($recentInvoices as $inv) {
            $activities[] = [
                'type' => 'invoice',
                'icon' => 'receipt_long',
                'color' => 'green',
                'title' => "Rechnung erstellt: {$inv->project_title}",
                'subtitle' => "{$inv->invoice_number} · " . number_format((float)$inv->total_gross, 2, ',', '.') . ' €',
                'date' => $inv->created_at,
                'link' => "/invoices/{$inv->id}",
            ];
        }

        // Nach Datum sortieren (neueste zuerst)
        usort($activities, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        return array_slice($activities, 0, 10);
    }

    /**
     * Umsatz-Übersicht der letzten 6 Monate.
     */
    private function getRevenueChart($company, Carbon $now): array
    {
        $months = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);

            $quotesValue = $company->quotes()
                ->where('status', 'accepted')
                ->whereYear('accepted_at', $month->year)
                ->whereMonth('accepted_at', $month->month)
                ->sum('total_gross');

            $invoicesPaid = $company->invoices()
                ->where('status', 'paid')
                ->whereYear('paid_at', $month->year)
                ->whereMonth('paid_at', $month->month)
                ->sum('total_gross');

            $months[] = [
                'month' => $month->translatedFormat('M Y'),
                'month_short' => $month->translatedFormat('M'),
                'quotes' => round((float)$quotesValue, 2),
                'invoices_paid' => round((float)$invoicesPaid, 2),
            ];
        }

        return $months;
    }

    /**
     * Kundenname aus Customer-Objekt holen.
     */
    private function getCustomerName($customer): string
    {
        if (!$customer) return 'Kein Kunde';
        return $customer->type === 'business'
            ? ($customer->company_name ?? 'Unbekannt')
            : trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
    }
}