<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Zusaetzlich zu den im Plan inkludierten Mitarbeiter-Sitzplaetzen
            // (siehe Company::EMPLOYEE_SEATS_INCLUDED) dazugekaufte Sitzplaetze.
            // Wird aktuell manuell gepflegt; Anbindung an den Stripe-Checkout
            // (eigener, guenstigerer Preis pro Mitarbeiter-Sitzplatz) folgt,
            // sobald die Preisgestaltung dafuer feststeht.
            $table->unsignedInteger('employee_seats_purchased')->default(0)->after('plan');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('employee_seats_purchased');
        });
    }
};
