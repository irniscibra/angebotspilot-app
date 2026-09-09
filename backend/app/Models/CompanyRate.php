<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'price',
        'unit',
        'note',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
