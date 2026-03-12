<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'position_number',
        'group_name',
        'type',
        'title',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'total_price',
        'material_id',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Automatisch total_price berechnen
        static::saving(function (InvoiceItem $item) {
            $item->total_price = $item->quantity * $item->unit_price;
        });

        // Nach Änderung: Rechnung neu kalkulieren
        static::saved(function (InvoiceItem $item) {
            $item->invoice->recalculate();
        });

        static::deleted(function (InvoiceItem $item) {
            $item->invoice->recalculate();
        });
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}