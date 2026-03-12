<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'company_id',
        'customer_id',
        'quote_id',
        'created_by',
        'invoice_number',
        'type',
        'project_title',
        'project_description',
        'project_address',
        'quote_reference',
        'service_date_from',
        'service_date_to',
        'subtotal_materials',
        'subtotal_labor',
        'subtotal_net',
        'vat_rate',
        'vat_amount',
        'total_gross',
        'discount_percent',
        'discount_amount',
        'partial_payments_total',
        'remaining_amount',
        'due_date',
        'paid_at',
        'paid_amount',
        'status',
        'cancelled_by_invoice_id',
        'cancellation_reason',
        'pdf_path',
        'pdf_generated_at',
        'header_text',
        'footer_text',
        'terms_text',
        'internal_notes',
        'sent_at',
    ];

    protected $casts = [
        'subtotal_materials' => 'decimal:2',
        'subtotal_labor' => 'decimal:2',
        'subtotal_net' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total_gross' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'partial_payments_total' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'service_date_from' => 'date',
        'service_date_to' => 'date',
        'due_date' => 'date',
        'paid_at' => 'date',
        'sent_at' => 'datetime',
        'pdf_generated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            if (empty($invoice->uuid)) {
                $invoice->uuid = Str::uuid();
            }
        });
    }

    // ---- Relationships ----

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('position_number');
    }

    // Stornierte Rechnung (wenn diese eine Storno-Rechnung ist)
    public function cancelledByInvoice()
    {
        return $this->belongsTo(Invoice::class, 'cancelled_by_invoice_id');
    }

    // Andere Rechnungen zum selben Angebot (für Abschlagsrechnungen)
    public function relatedInvoices()
    {
        return $this->hasMany(Invoice::class, 'quote_id', 'quote_id')
            ->where('id', '!=', $this->id)
            ->where('status', '!=', 'cancelled');
    }

    // ---- Business Logic ----

    /**
     * Beträge neu berechnen basierend auf Positionen.
     */
    public function recalculate(): void
    {
        $materialTotal = $this->items()->where('type', 'material')->sum('total_price');
        $laborTotal = $this->items()->whereIn('type', ['labor', 'flat'])->sum('total_price');

        $subtotalNet = $materialTotal + $laborTotal;

        // Rabatt
        $discountAmount = $subtotalNet * ($this->discount_percent / 100);
        $netAfterDiscount = $subtotalNet - $discountAmount;

        // Kleinunternehmer: keine MwSt
        $vatRate = $this->company->is_small_business ? 0 : $this->vat_rate;
        $vatAmount = $netAfterDiscount * ($vatRate / 100);

        $totalGross = $netAfterDiscount + $vatAmount;

        // Bei Schlussrechnung: Abschläge abziehen
        $partialPayments = $this->partial_payments_total ?? 0;
        $remainingAmount = $totalGross - $partialPayments;

        $this->update([
            'subtotal_materials' => $materialTotal,
            'subtotal_labor' => $laborTotal,
            'subtotal_net' => $netAfterDiscount,
            'discount_amount' => $discountAmount,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'total_gross' => $totalGross,
            'remaining_amount' => max(0, $remainingAmount),
        ]);
    }

    /**
     * Summe aller bezahlten Abschlagsrechnungen für dasselbe Angebot.
     */
    public function calculatePartialPayments(): float
    {
        if (!$this->quote_id) return 0;

        return Invoice::where('quote_id', $this->quote_id)
            ->where('id', '!=', $this->id)
            ->where('type', 'partial')
            ->where('status', '!=', 'cancelled')
            ->sum('total_gross');
    }

    /**
     * Prüft ob Rechnung noch editierbar ist (nur im Entwurf).
     */
    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Prüft ob Rechnung überfällig ist.
     */
    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->isPast()
            && !in_array($this->status, ['paid', 'cancelled']);
    }

    /**
     * Status-Label für Frontend.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Entwurf',
            'sent' => 'Versendet',
            'paid' => 'Bezahlt',
            'partial_paid' => 'Teilweise bezahlt',
            'overdue' => 'Überfällig',
            'cancelled' => 'Storniert',
            default => $this->status,
        };
    }

    /**
     * Typ-Label.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'standard' => 'Rechnung',
            'partial' => 'Abschlagsrechnung',
            'final' => 'Schlussrechnung',
            default => $this->type,
        };
    }

    /**
     * Gruppenweise Positionen.
     */
    public function getGroupedItemsAttribute(): array
    {
        return $this->items->groupBy('group_name')->toArray();
    }
}