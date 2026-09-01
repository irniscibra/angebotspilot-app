<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Team-Mitglieder werden beim "Entfernen" nicht mehr hart geloescht,
 * sondern deaktiviert (Soft-Delete-Pattern, Standard bei HR-/Lohn-Software
 * wie Personio/BambooHR): der User-Datensatz bleibt bestehen, damit
 * bereits erfasste Zeiten, Fotos und Bautagesberichte weiterhin korrekt
 * seinem Namen zugeordnet bleiben - wichtig fuer die Nachvollziehbarkeit
 * von Lohn-relevanten Daten. Der User selbst kann sich danach nicht mehr
 * einloggen und taucht nicht mehr in der Team-Liste auf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable()->after('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deactivated_at');
        });
    }
};
