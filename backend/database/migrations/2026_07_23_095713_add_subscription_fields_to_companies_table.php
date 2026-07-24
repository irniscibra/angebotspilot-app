<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Wann das bezahlte Abo (Starter/Professional) begonnen hat
            $table->timestamp('subscription_started_at')->nullable()->after('trial_ends_at');
            // Wann der Nutzer die Kündigung ausgelöst hat (Nachweis-Zeitpunkt)
            $table->timestamp('cancelled_at')->nullable()->after('subscription_started_at');
            // Bis wann der Zugriff noch besteht (Laufzeitende) - NICHT sofort sperren
            $table->timestamp('access_until')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['subscription_started_at', 'cancelled_at', 'access_until']);
        });
    }
};