<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            // VOB-Unterpositionen: Eltern-Position ist reine Ueberschrift/Gruppierung
            // ohne eigenen Preis, max. 2 Ebenen (Praxis-Regel, nicht DB-erzwungen).
            $table->foreignId('parent_id')
                ->nullable()
                ->after('quote_id')
                ->constrained('quote_items')
                ->cascadeOnDelete();

            // Anfahrt/Baustelleneinrichtung: pro Angebotsposition umschaltbar,
            // ob der Betrag einzeln ausgewiesen oder in die Baustelleneinrichtung
            // eingerechnet wird (siehe Zusage an Testbetrieb, keine Firmeneinstellung).
            $table->boolean('include_in_setup_costs')->default(false)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('include_in_setup_costs');
        });
    }
};
