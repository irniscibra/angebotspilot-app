<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'project_id',
        'created_by',
        'description',
        'amount',
        'category',
        'expense_date',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    // ---- Relationships ----

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ---- Business Logic ----

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'material' => 'Material',
            'lohn' => 'Lohn',
            'fremdleistung' => 'Fremdleistung',
            'sonstiges' => 'Sonstiges',
            default => $this->category,
        };
    }
}
