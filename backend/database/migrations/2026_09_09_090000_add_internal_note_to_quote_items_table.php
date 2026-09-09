<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trennt interne Sicherheitsnetz-Hinweise ("🔴 ACHTUNG...", "⚠...",
     * "✓... korrigiert") von der kundensichtbaren Positionsbeschreibung.
     *
     * Bisher wurden diese Hinweise direkt an `description` angehängt –
     * dieses Feld wird aber unveraendert im PDF (quote.blade.php) und in
     * der oeffentlichen Angebotsansicht (PublicQuoteView.vue) angezeigt,
     * die der Kunde sieht. Interne Warnungen gehoeren dort NICHT hin.
     *
     * `internal_note` ist ausschliesslich fuer die interne Bearbeitungs-
     * ansicht (QuoteCreatePage.vue, QuoteDetailPage.vue) gedacht.
     */
    public function up(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->text('internal_note')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropColumn('internal_note');
        });
    }
};
