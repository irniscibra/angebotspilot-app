<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'project_id',
        'user_id',
        'logged_by',
        'entry_date',
        'start_time',
        'end_time',
        'break_minutes',
        'description',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    protected $appends = ['duration_minutes'];

    // ---- Relationships ----

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function loggedBy()
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // ---- Business Logic ----

    /**
     * Netto-Arbeitszeit in Minuten (Ende - Start - Pause). start_time/
     * end_time sind reine Uhrzeiten (kein Datum) - beide werden auf
     * denselben Referenztag gemappt, ein Schichtende nach Mitternacht wird
     * bewusst NICHT unterstuetzt (Validierung im Controller verlangt
     * end_time > start_time).
     */
    public function getDurationMinutesAttribute(): int
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);

        return max(0, $start->diffInMinutes($end) - (int) $this->break_minutes);
    }
}
