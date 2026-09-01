<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'password',
        'role',
        'avatar_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // ---- Relationships ----

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class, 'created_by');
    }

    public function assignedProjects()
    {
        return $this->belongsToMany(Project::class, 'project_assignments')->withTimestamps();
    }

    // ---- Email Verification ----

    /**
     * Nutzt unsere eigene, professionell gestaltete Verifikations-E-Mail.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

        /**
     * Nutzt unsere eigene, professionell gestaltete Passwort-Reset-E-Mail
     * statt Laravels Standard-Benachrichtigung.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    // ---- Helpers ----

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['owner', 'admin']);
    }

    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    /**
     * Darf dieser User auf dieses Projekt zugreifen? Owner/Admin: immer
     * (Firmenzugehoerigkeit wird zusaetzlich in
     * AuthorizesProjectAccess::authorizeProjectAccess() geprueft).
     * Mitarbeiter: nur wenn explizit ueber project_assignments zugewiesen.
     */
    public function canAccessProject(Project $project): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->assignedProjects()->whereKey($project->id)->exists();
    }
}