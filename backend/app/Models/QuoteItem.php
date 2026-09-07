<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_id',
        'parent_id',
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
        'service_id',
        'include_in_setup_costs',
        'is_ai_generated',
        'ai_confidence',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'ai_confidence' => 'decimal:2',
        'is_ai_generated' => 'boolean',
        'include_in_setup_costs' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Automatisch total_price berechnen
        static::saving(function (QuoteItem $item) {
            $item->total_price = $item->quantity * $item->unit_price;
        });

        // Nach Änderung: Angebot neu kalkulieren
        static::saved(function (QuoteItem $item) {
            $item->quote->recalculate();
        });

        static::deleted(function (QuoteItem $item) {
            $item->quote->recalculate();
        });
    }

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * VOB-Unterpositionen: Eltern-Position (reine Ueberschrift ohne eigenen Preis).
     */
    public function parent()
    {
        return $this->belongsTo(QuoteItem::class, 'parent_id');
    }

    /**
     * VOB-Unterpositionen: Kind-Positionen unterhalb dieser Position.
     */
    public function children()
    {
        return $this->hasMany(QuoteItem::class, 'parent_id')->orderBy('sort_order');
    }
}