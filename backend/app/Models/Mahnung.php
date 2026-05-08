<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Mahnung extends Model
{
    use HasFactory;

    protected $table = 'mahnungen';

    protected $fillable = [
        'uuid',
        'company_id',
        'invoice_id',
        'customer_id',
        'created_by',
        'mahnung_number',
        'level',
        'status',
        'original_amount',
        'mahnung_fee',
        'interest_rate',
        'interest_days',
        'interest_amount',
        'total_amount',
        'original_due_date',
        'new_due_date',
        'sent_at',
        'paid_at',
        'pdf_path',
        'notes',
    ];

    protected $casts = [
        'original_amount'   => 'decimal:2',
        'mahnung_fee'       => 'decimal:2',
        'interest_rate'     => 'decimal:2',
        'interest_amount'   => 'decimal:2',
        'total_amount'      => 'decimal:2',
        'original_due_date' => 'date',
        'new_due_date'      => 'date',
        'sent_at'           => 'datetime',
        'paid_at'           => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Mahnung $mahnung) {
            if (empty($mahnung->uuid)) {
                $mahnung->uuid = Str::uuid();
            }
        });
    }

    // ── Relationships ──

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Accessors ──

    public function getLevelLabelAttribute(): string
    {
        return match ($this->level) {
            1 => '1. Mahnung',
            2 => '2. Mahnung',
            3 => '3. Mahnung (Letzte)',
            default => "Mahnung {$this->level}",
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'     => 'Entwurf',
            'sent'      => 'Versendet',
            'paid'      => 'Bezahlt',
            'cancelled' => 'Storniert',
            default     => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft'     => 'grey',
            'sent'      => 'orange',
            'paid'      => 'green',
            'cancelled' => 'red',
            default     => 'grey',
        };
    }
}
