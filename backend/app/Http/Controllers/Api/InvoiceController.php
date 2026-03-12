<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /**
     * Alle Rechnungen der Firma.
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->company->invoices()
            ->with('customer:id,first_name,last_name,company_name,type')
            ->withCount('items');

        // Filter
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->has('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }
        if ($request->has('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('invoice_number', 'like', "%{$s}%")
                  ->orWhere('project_title', 'like', "%{$s}%");
            });
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($invoices);
    }

    /**
     * Einzelne Rechnung mit Positionen.
     */
    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        $invoice->load(['items.material', 'customer', 'quote', 'creator']);

        return response()->json($invoice);
    }

    /**
     * Rechnung aus Angebot erstellen.
     */
    public function createFromQuote(Request $request): JsonResponse
    {
        $request->validate([
            'quote_id' => 'required|exists:quotes,id',
            'type' => 'sometimes|in:standard,partial,final',
            'partial_percent' => 'sometimes|numeric|min:1|max:100',
        ]);

        $quote = Quote::with(['items', 'customer', 'company'])->findOrFail($request->quote_id);

        if ($quote->company_id !== $request->user()->company_id) {
            abort(403);
        }

        $company = $quote->company;
        $type = $request->type ?? 'standard';
        $partialPercent = $request->partial_percent ?? 100;

        // Rechnungsnummer generieren (GoBD: fortlaufend, atomar)
        $invoiceNumber = $this->generateInvoiceNumber($company);

        // Angebots-Referenz Text
        $quoteRef = "Gemäß Angebot {$quote->quote_number} vom {$quote->created_at->format('d.m.Y')}";

        // Bei Schlussrechnung: bisherige Abschläge berechnen
        $partialPayments = 0;
        if ($type === 'final') {
            $partialPayments = Invoice::where('quote_id', $quote->id)
                ->where('type', 'partial')
                ->where('status', '!=', 'cancelled')
                ->sum('total_gross');
        }

        // Fälligkeitsdatum
        $dueDate = now()->addDays($company->default_payment_days ?? 14);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $quote->customer_id,
            'quote_id' => $quote->id,
            'created_by' => $request->user()->id,
            'invoice_number' => $invoiceNumber,
            'type' => $type,
            'project_title' => $quote->project_title,
            'project_description' => $quote->project_description,
            'project_address' => $quote->project_address,
            'quote_reference' => $quoteRef,
            'vat_rate' => $company->is_small_business ? 0 : ($quote->vat_rate ?? $company->default_vat_rate),
            'discount_percent' => $quote->discount_percent ?? 0,
            'partial_payments_total' => $partialPayments,
            'due_date' => $dueDate,
            'status' => 'draft',
        ]);

        // Positionen kopieren
        $sortOrder = 0;
        foreach ($quote->items as $item) {
            $quantity = $item->quantity;

            // Bei Abschlagsrechnung: Mengen anpassen (oder gleich lassen und Gesamtbetrag prozentual)
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'position_number' => $item->position_number,
                'group_name' => $item->group_name,
                'type' => $item->type,
                'title' => $item->title,
                'description' => $item->description,
                'quantity' => $quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'material_id' => $item->material_id,
                'sort_order' => $sortOrder++,
            ]);
        }

        // Beträge berechnen
        $invoice->recalculate();

        // Bei Abschlagsrechnung: Prozentsatz anwenden
        if ($type === 'partial' && $partialPercent < 100) {
            $invoice->update([
                'header_text' => "Abschlagsrechnung ({$partialPercent}%) – {$quoteRef}",
            ]);
        }

        $invoice->load(['items', 'customer', 'quote']);

        return response()->json([
            'invoice' => $invoice,
            'message' => 'Rechnung erstellt.',
        ], 201);
    }

    /**
     * Leere Rechnung erstellen (ohne Angebot).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'project_title' => 'required|string|max:255',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $company = $request->user()->company;
        $invoiceNumber = $this->generateInvoiceNumber($company);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'customer_id' => $request->customer_id,
            'created_by' => $request->user()->id,
            'invoice_number' => $invoiceNumber,
            'type' => 'standard',
            'project_title' => $request->project_title,
            'project_address' => $request->project_address ?? null,
            'vat_rate' => $company->is_small_business ? 0 : $company->default_vat_rate,
            'due_date' => now()->addDays($company->default_payment_days ?? 14),
            'status' => 'draft',
        ]);

        return response()->json(['invoice' => $invoice], 201);
    }

    /**
     * Rechnung aktualisieren (nur im Entwurf!).
     */
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        if (!$invoice->isEditable()) {
            return response()->json([
                'message' => 'Rechnung kann nicht mehr bearbeitet werden. Nur Entwürfe sind editierbar.',
            ], 422);
        }

        $request->validate([
            'project_title' => 'sometimes|string|max:255',
            'project_address' => 'nullable|string|max:500',
            'customer_id' => 'nullable|exists:customers,id',
            'service_date_from' => 'nullable|date',
            'service_date_to' => 'nullable|date',
            'due_date' => 'nullable|date',
            'discount_percent' => 'sometimes|numeric|min:0|max:100',
            'header_text' => 'nullable|string',
            'footer_text' => 'nullable|string',
            'terms_text' => 'nullable|string',
            'internal_notes' => 'nullable|string',
        ]);

        $invoice->update($request->only([
            'project_title', 'project_address', 'customer_id',
            'service_date_from', 'service_date_to', 'due_date',
            'discount_percent', 'header_text', 'footer_text',
            'terms_text', 'internal_notes',
        ]));

        // Neuberechnung falls Rabatt geändert
        if ($request->has('discount_percent')) {
            $invoice->recalculate();
        }

        return response()->json($invoice->fresh()->load(['items', 'customer']));
    }

    /**
     * Position hinzufügen (nur im Entwurf).
     */
    public function addItem(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        if (!$invoice->isEditable()) {
            return response()->json(['message' => 'Rechnung ist nicht mehr editierbar.'], 422);
        }

        $request->validate([
            'type' => 'required|in:material,labor,flat,text',
            'title' => 'required|string|max:255',
            'group_name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'quantity' => 'required|numeric|min:0',
            'unit' => 'required|string|max:20',
            'unit_price' => 'required|numeric|min:0',
            'material_id' => 'nullable|exists:materials,id',
        ]);

        $lastPos = $invoice->items()->max('position_number') ?? 0;
        $lastSort = $invoice->items()->max('sort_order') ?? 0;

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'position_number' => $lastPos + 1,
            'group_name' => $request->group_name ?? 'Sonstiges',
            'type' => $request->type,
            'title' => $request->title,
            'description' => $request->description,
            'quantity' => $request->quantity,
            'unit' => $request->unit,
            'unit_price' => $request->unit_price,
            'material_id' => $request->material_id,
            'sort_order' => $lastSort + 1,
        ]);

        return response()->json($item, 201);
    }

    /**
     * Position aktualisieren (nur im Entwurf).
     */
    public function updateItem(Request $request, Invoice $invoice, InvoiceItem $item): JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        if (!$invoice->isEditable()) {
            return response()->json(['message' => 'Rechnung ist nicht mehr editierbar.'], 422);
        }

        if ($item->invoice_id !== $invoice->id) {
            abort(404);
        }

        $item->update($request->only([
            'group_name', 'type', 'title', 'description',
            'quantity', 'unit', 'unit_price', 'material_id',
        ]));

        return response()->json($item);
    }

    /**
     * Position löschen (nur im Entwurf).
     */
    public function deleteItem(Request $request, Invoice $invoice, InvoiceItem $item): JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        if (!$invoice->isEditable()) {
            return response()->json(['message' => 'Rechnung ist nicht mehr editierbar.'], 422);
        }

        if ($item->invoice_id !== $invoice->id) {
            abort(404);
        }

        $item->delete();

        return response()->json(['message' => 'Position gelöscht.']);
    }

    /**
     * Rechnung als versendet markieren (ab jetzt nicht mehr editierbar).
     */
    public function send(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        if ($invoice->status !== 'draft') {
            return response()->json(['message' => 'Rechnung wurde bereits versendet.'], 422);
        }

        $invoice->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return response()->json([
            'message' => 'Rechnung als versendet markiert.',
            'invoice' => $invoice->fresh(),
        ]);
    }

    /**
     * Zahlung erfassen.
     */
    public function markAsPaid(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        $request->validate([
            'paid_amount' => 'sometimes|numeric|min:0',
            'paid_at' => 'sometimes|date',
        ]);

        $paidAmount = $request->paid_amount ?? $invoice->total_gross;
        $paidAt = $request->paid_at ?? now()->toDateString();

        $status = $paidAmount >= $invoice->total_gross ? 'paid' : 'partial_paid';

        $invoice->update([
            'paid_amount' => $paidAmount,
            'paid_at' => $paidAt,
            'status' => $status,
        ]);

        return response()->json([
            'message' => $status === 'paid' ? 'Rechnung als bezahlt markiert.' : 'Teilzahlung erfasst.',
            'invoice' => $invoice->fresh(),
        ]);
    }

    /**
     * Rechnung stornieren (GoBD: NICHT löschen, sondern stornieren).
     */
    public function cancel(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        if ($invoice->status === 'cancelled') {
            return response()->json(['message' => 'Rechnung ist bereits storniert.'], 422);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $invoice->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->reason,
        ]);

        return response()->json([
            'message' => 'Rechnung storniert.',
            'invoice' => $invoice->fresh(),
        ]);
    }

    /**
     * Rechnung löschen – NUR Entwürfe (GoBD-konform).
     */
    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorizeInvoice($request, $invoice);

        if ($invoice->status !== 'draft') {
            return response()->json([
                'message' => 'Nur Entwürfe können gelöscht werden. Versendete Rechnungen müssen storniert werden (GoBD).',
            ], 422);
        }

        $invoice->delete();

        return response()->json(['message' => 'Entwurf gelöscht.']);
    }

    /**
     * GoBD-konforme, fortlaufende Rechnungsnummer generieren.
     * Nutzt DB-Lock um Rennbedingungen bei gleichzeitigen Anfragen zu vermeiden.
     */
    private function generateInvoiceNumber($company): string
    {
        return \DB::transaction(function () use ($company) {
            // Row-Lock auf die Company-Zeile
            $locked = \App\Models\Company::where('id', $company->id)->lockForUpdate()->first();

            $number = $locked->next_invoice_number;
            $locked->increment('next_invoice_number');

            $prefix = $locked->invoice_prefix ?? 'RE';

            return $prefix . '-' . date('Y') . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Prüft ob Rechnung zur Firma des Users gehört.
     */
    private function authorizeInvoice(Request $request, Invoice $invoice): void
    {
        if ($invoice->company_id !== $request->user()->company_id) {
            abort(403, 'Zugriff verweigert.');
        }
    }
}