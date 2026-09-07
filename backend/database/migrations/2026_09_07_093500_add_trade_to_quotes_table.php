<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            // Gewerk pro Angebot statt nur pro Firma (companies.trade bleibt als
            // Standard/Fallback bestehen) - wichtig fuer Betriebe mit mehreren
            // Taetigkeiten (z.B. Hochbau UND Tiefbau), die pro Angebot wechseln.
            $table->string('trade', 50)->nullable()->after('project_address');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('trade');
        });
    }
};
