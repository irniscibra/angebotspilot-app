<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'customer_id',
        'title',
        'description',
        'project_address',
        'status',
        'planned_start',
        'planned_end',
    ];

    protected $casts = [
        'planned_start' => 'date',
        'planned_end' => 'date',
    ];

    // ---- Relationships ----

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    // Ab Phase 2 nutzbar, sobald quotes.project_id / invoices.project_id existieren.
    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function expenses()
    {
        return $this->hasMany(ProjectExpense::class);
    }

    public function photos()
    {
        return $this->hasMany(ProjectPhoto::class);
    }

    public function reports()
    {
        return $this->hasMany(ProjectReport::class);
    }

    public function assignments()
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'project_assignments')->withTimestamps();
    }

    // ---- Business Logic ----

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'angefragt' => 'Angefragt',
            'kalkuliert' => 'Kalkuliert',
            'beauftragt' => 'Beauftragt',
            'in_ausfuehrung' => 'In Ausführung',
            'abgeschlossen' => 'Abgeschlossen',
            'storniert' => 'Storniert',
            default => $this->status,
        };
    }
}
