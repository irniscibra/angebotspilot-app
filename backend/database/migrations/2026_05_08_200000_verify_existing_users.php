<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Bestehende User (vor Einführung der E-Mail-Verifikation) automatisch verifizieren,
 * damit ihr Zugang nicht gesperrt wird.
 */
return new class extends Migration
{
    public function up(): void
    {
        User::whereNull('email_verified_at')->update([
            'email_verified_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Nicht rückgängig machen – das wäre destruktiv
    }
};
