<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'project_id',
        'uploaded_by',
        'path',
        'original_name',
        'caption',
    ];

    protected $appends = ['url'];

    // ---- Relationships ----

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ---- Business Logic ----

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }
}
