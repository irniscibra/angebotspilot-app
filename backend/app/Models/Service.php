<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'category',
        'name',
        'description',
        'estimated_hours',
        'hourly_rate',
        'flat_price',
        'pricing_type',
        'is_active',
    ];

    protected $casts = [
        'estimated_hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'flat_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // ---- Relationships ----

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function quoteItems()
    {
        return $this->hasMany(QuoteItem::class);
    }
}
