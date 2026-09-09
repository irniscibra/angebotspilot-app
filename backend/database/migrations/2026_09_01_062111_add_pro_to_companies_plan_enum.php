<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite (genutzt in Tests, siehe phpunit.xml) kennt kein
        // "ALTER TABLE ... MODIFY" und emuliert ENUM ohnehin nur als
        // CHECK-Constraint ohne feste Werteliste zum Nachtraeglich-Aendern -
        // dort ist nichts zu tun. Auf echtem MySQL (lokal/Test-Server/Prod)
        // laeuft die urspruengliche Anweisung unveraendert.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE companies MODIFY plan ENUM('trial','starter','professional','enterprise','pro') NOT NULL DEFAULT 'trial'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE companies MODIFY plan ENUM('trial','starter','professional','enterprise') NOT NULL DEFAULT 'trial'");
        }
    }
};
