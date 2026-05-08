<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\MahnungMail;
use App\Models\Invoice;
use App\Models\Mahnung;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class MahnungController extends Controller
{
    /**
     * Alle Mahnungen der Firma.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->company->mahnungen()
            ->with([
                'invoice:id,invoice_number,total_gross,due_date',
                'customer:id,first_name,last_name,company_name,type,email',
            ]);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('mahnung_number', 'like', "%{$s}%")
                  ->orWhereHas('invoice', fn($qi) => $qi->where('invoice_number', 'like', "%{$s}%"))
                  ->orWhereHas('customer', fn($qc) =>
                      $qc->where('last_name', 'like', "%{$s}%")
                         ->orWhere('company_name', 'like', "%{$s}%")
                  );
            });
        }

        $mahnungen = $query->orderBy('created_at', 'desc')->paginate(20);

        // Überblick Stats
        $stats = $this->getStats($request->user()->company_id);

        return response()->json([
            'mahnungen' => $mahnungen,
            'stats'     => $stats,
        ]);
    }

    /**
     * Alle überfälligen Rechnungen ohne offene Mahnung anzeigen (für "Mahnlauf").
     */
    public function overdueInvoices(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        $overdue = Invoice::where('company_id', $company->id)
            ->whereIn('status', ['sent', 'partial_paid', 'overdue'])
            ->where('due_date', '<', now()->toDateString())
            ->with('customer:id,first_name,last_name,company_name,type,email')
            ->get()
            ->map(function (Invoice $invoice) {
                $daysOverdue = now()->diffInDays($invoice->due_date);
                $nextLevel   = $this->getNextMahnungLevel($invoice->id);

                return [
                    'invoice'      => $invoice,
                    'days_overdue' => $daysOverdue,
                    'next_level'   => $nextLevel,
                    'can_mahnung'  => $nextLevel <= 3,
                ];
            })
            ->filter(fn($item) => $item['can_mahnung'])
            ->values();

        return response()->json($overdue);
    }

    /**
     * Mahnung erstellen (Entwurf).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'notes'      => 'nullable|string|max:1000',
        ]);

        $company = $request->user()->company;

        $invoice = Invoice::with('customer')->findOrFail($request->invoice_id);

        if ($invoice->company_id !== $company->id) {
            abort(403);
        }

        if (!in_array($invoice->status, ['sent', 'partial_paid', 'overdue'])) {
            return response()->json([
                'message' => 'Nur versendete oder überfällige Rechnungen können gemahnt werden.',
            ], 422);
        }

        if ($invoice->due_date >= now()->toDateString()) {
            return response()->json([
                'message' => 'Rechnung ist noch nicht fällig.',
            ], 422);
        }

        $level = $this->getNextMahnungLevel($invoice->id);

        if ($level > 3) {
            return response()->json([
                'message' => 'Maximale Mahnstufe (3) bereits erreicht.',
            ], 422);
        }

        // Bereits eine offene Mahnung auf dieser Stufe?
        $existing = Mahnung::where('invoice_id', $invoice->id)
            ->where('level', $level)
            ->whereIn('status', ['draft', 'sent'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => "Es existiert bereits eine {$level}. Mahnung für diese Rechnung.",
            ], 422);
        }

        // Berechnungen
        $daysOverdue    = (int) \Carbon\Carbon::parse($invoice->due_date)->diffInDays(now());
        $interestRate   = $company->mahnung_interest_rate ?? 9.00;
        $mahnungFee     = $this->getMahnungFee($company, $level);
        $interestAmount = $this->calculateInterest($invoice->remaining_amount ?? $invoice->total_gross, $interestRate, $daysOverdue);

        $daysUntilNextDue = match ($level) {
            1 => $company->mahnung_days_level1 ?? 7,
            2 => $company->mahnung_days_level2 ?? 14,
            3 => $company->mahnung_days_level3 ?? 21,
        };

        $totalAmount = ($invoice->remaining_amount ?? $invoice->total_gross) + $mahnungFee + $interestAmount;

        $mahnung = DB::transaction(function () use (
            $company, $invoice, $request, $level,
            $daysOverdue, $interestRate, $interestAmount,
            $mahnungFee, $totalAmount, $daysUntilNextDue
        ) {
            $number = $this->generateMahnungNumber($company);

            return Mahnung::create([
                'company_id'        => $company->id,
                'invoice_id'        => $invoice->id,
                'customer_id'       => $invoice->customer_id,
                'created_by'        => $request->user()->id,
                'mahnung_number'    => $number,
                'level'             => $level,
                'status'            => 'draft',
                'original_amount'   => $invoice->remaining_amount ?? $invoice->total_gross,
                'mahnung_fee'       => $mahnungFee,
                'interest_rate'     => $interestRate,
                'interest_days'     => $daysOverdue,
                'interest_amount'   => $interestAmount,
                'total_amount'      => $totalAmount,
                'original_due_date' => $invoice->due_date,
                'new_due_date'      => now()->addDays($daysUntilNextDue),
                'notes'             => $request->notes,
            ]);
        });

        $mahnung->load(['invoice', 'customer', 'creator']);

        return response()->json([
            'mahnung' => $mahnung,
            'message' => "{$mahnung->level_label} erstellt.",
        ], 201);
    }

    /**
     * Einzelne Mahnung anzeigen.
     */
    public function show(Request $request, Mahnung $mahnung): JsonResponse
    {
        $this->authorizeMahnung($request, $mahnung);
        $mahnung->load(['invoice.items', 'customer', 'creator', 'company']);

        return response()->json($mahnung);
    }

    /**
     * Mahnung per E-Mail versenden.
     */
    public function send(Request $request, Mahnung $mahnung): JsonResponse
    {
        $this->authorizeMahnung($request, $mahnung);

        if ($mahnung->status === 'sent') {
            return response()->json(['message' => 'Mahnung wurde bereits versendet.'], 422);
        }

        if ($mahnung->status === 'paid') {
            return response()->json(['message' => 'Rechnung ist bereits bezahlt.'], 422);
        }

        $customer = $mahnung->customer;

        if (!$customer || !$customer->email) {
            return response()->json([
                'message' => 'Kein Kunde oder keine E-Mail-Adresse hinterlegt.',
            ], 422);
        }

        $mahnung->load(['invoice', 'customer', 'company', 'creator']);

        // PDF generieren
        $pdfContent = $this->generatePdfContent($mahnung);
        $pdfFilename = $mahnung->mahnung_number . '.pdf';

        // PDF speichern
        $pdfPath = 'mahnungen/' . $mahnung->company_id . '/' . $pdfFilename;
        Storage::disk('local')->put($pdfPath, $pdfContent);
        $mahnung->update(['pdf_path' => $pdfPath]);

        // Mail senden
        $senderName = $mahnung->company->name;
        $replyTo    = $mahnung->company->email;

        Mail::to($customer->email, $this->getCustomerName($customer))
            ->send(new MahnungMail(
                mahnung: $mahnung,
                senderName: $senderName,
                replyToEmail: $replyTo,
                pdfContent: $pdfContent,
                pdfFilename: $pdfFilename,
            ));

        $mahnung->update([
            'status'  => 'sent',
            'sent_at' => now(),
        ]);

        // Rechnung auf "overdue" setzen falls noch nicht
        if ($mahnung->invoice->status === 'sent') {
            $mahnung->invoice->update(['status' => 'overdue']);
        }

        return response()->json([
            'message' => "Mahnung erfolgreich an {$customer->email} versendet.",
            'mahnung' => $mahnung->fresh(),
        ]);
    }

    /**
     * Mahnung PDF herunterladen.
     */
    public function downloadPdf(Request $request, Mahnung $mahnung)
    {
        $this->authorizeMahnung($request, $mahnung);
        $mahnung->load(['invoice', 'customer', 'company', 'creator']);

        $pdfContent = $this->generatePdfContent($mahnung);

        return response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $mahnung->mahnung_number . '.pdf"',
        ]);
    }

    /**
     * Mahnung als bezahlt markieren.
     */
    public function markAsPaid(Request $request, Mahnung $mahnung): JsonResponse
    {
        $this->authorizeMahnung($request, $mahnung);

        $request->validate([
            'paid_at' => 'nullable|date',
        ]);

        $mahnung->update([
            'status'  => 'paid',
            'paid_at' => $request->paid_at ?? now()->toDateString(),
        ]);

        // Rechnung ebenfalls als bezahlt markieren
        $mahnung->invoice->update([
            'status'     => 'paid',
            'paid_at'    => $request->paid_at ?? now()->toDateString(),
            'paid_amount' => $mahnung->invoice->total_gross,
        ]);

        return response()->json([
            'message' => 'Zahlung erfasst. Rechnung wurde als bezahlt markiert.',
            'mahnung' => $mahnung->fresh(),
        ]);
    }

    /**
     * Mahnung stornieren.
     */
    public function cancel(Request $request, Mahnung $mahnung): JsonResponse
    {
        $this->authorizeMahnung($request, $mahnung);

        if ($mahnung->status === 'paid') {
            return response()->json(['message' => 'Bezahlte Mahnungen können nicht storniert werden.'], 422);
        }

        $mahnung->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Mahnung storniert.', 'mahnung' => $mahnung->fresh()]);
    }

    // ── Private Helpers ──

    private function getNextMahnungLevel(int $invoiceId): int
    {
        $lastLevel = Mahnung::where('invoice_id', $invoiceId)
            ->whereIn('status', ['draft', 'sent', 'paid'])
            ->max('level');

        return $lastLevel ? $lastLevel + 1 : 1;
    }

    private function getMahnungFee($company, int $level): float
    {
        return match ($level) {
            1 => (float) ($company->mahnung_fee_level1 ?? 0),
            2 => (float) ($company->mahnung_fee_level2 ?? 5),
            3 => (float) ($company->mahnung_fee_level3 ?? 15),
            default => 0,
        };
    }

    private function calculateInterest(float $amount, float $rate, int $days): float
    {
        if ($days <= 0 || $rate <= 0) return 0;
        // Tageszins: Betrag × Zinssatz / 100 / 365 × Tage
        return round($amount * ($rate / 100) / 365 * $days, 2);
    }

    private function generateMahnungNumber($company): string
    {
        $locked = \App\Models\Company::where('id', $company->id)->lockForUpdate()->first();
        $number = $locked->next_mahnung_number;
        $locked->increment('next_mahnung_number');

        $prefix = $locked->mahnung_prefix ?? 'MN';
        return $prefix . '-' . date('Y') . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    private function generatePdfContent(Mahnung $mahnung): string
    {
        $pdf = Pdf::loadView('pdf.mahnung', ['mahnung' => $mahnung]);
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('defaultFont', 'DejaVu Sans');
        $pdf->setOption('isHtml5ParserEnabled', true);
        return $pdf->output();
    }

    private function getCustomerName($customer): string
    {
        if ($customer->type === 'business' && $customer->company_name) {
            return $customer->company_name;
        }
        return trim($customer->first_name . ' ' . $customer->last_name);
    }

    private function authorizeMahnung(Request $request, Mahnung $mahnung): void
    {
        if ($mahnung->company_id !== $request->user()->company_id) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    private function getStats(int $companyId): array
    {
        return [
            'total'       => Mahnung::where('company_id', $companyId)->count(),
            'draft'       => Mahnung::where('company_id', $companyId)->where('status', 'draft')->count(),
            'sent'        => Mahnung::where('company_id', $companyId)->where('status', 'sent')->count(),
            'paid'        => Mahnung::where('company_id', $companyId)->where('status', 'paid')->count(),
            'open_amount' => Mahnung::where('company_id', $companyId)
                                ->whereIn('status', ['draft', 'sent'])
                                ->sum('total_amount'),
        ];
    }
}
