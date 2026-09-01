<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // invited_at: wann wurde die Einladung verschickt (nur bei
            // role=admin/employee gesetzt, beim Owner immer null).
            // accepted_at: wann hat der Eingeladene sein Passwort gesetzt
            // und die Einladung damit angenommen - solange das null ist,
            // gilt der Account als "eingeladen, aber inaktiv".
            $table->timestamp('invited_at')->nullable()->after('role');
            $table->timestamp('accepted_at')->nullable()->after('invited_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['invited_at', 'accepted_at']);
        });
    }
};
