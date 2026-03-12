<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // Rechnungsnummer (GoBD: fortlaufend, keine Lücken)
            $table->string('invoice_number')->index();
            $table->unique(['company_id', 'invoice_number']);

            // Typ
            $table->enum('type', [
                'standard',      // Normale Rechnung
                'partial',       // Abschlagsrechnung
                'final',         // Schlussrechnung
            ])->default('standard');

            // Projekt-Referenz
            $table->string('project_title');
            $table->text('project_description')->nullable();
            $table->string('project_address')->nullable();

            // Angebots-Referenz (Text, damit er auch ohne Angebot funktioniert)
            $table->string('quote_reference')->nullable();

            // Leistungszeitraum
            $table->date('service_date_from')->nullable();
            $table->date('service_date_to')->nullable();

            // Beträge
            $table->decimal('subtotal_materials', 12, 2)->default(0);
            $table->decimal('subtotal_labor', 12, 2)->default(0);
            $table->decimal('subtotal_net', 12, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(19);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total_gross', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);

            // Abschlagsrechnungen: bereits gezahlte Beträge
            $table->decimal('partial_payments_total', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2)->default(0);

            // Zahlung
            $table->date('due_date')->nullable();
            $table->date('paid_at')->nullable();
            $table->decimal('paid_amount', 12, 2)->default(0);

            // Status (GoBD: einmal erstellt, nicht löschbar – nur stornierbar)
            $table->enum('status', [
                'draft',        // Entwurf – noch editierbar
                'sent',         // Versendet – nicht mehr editierbar
                'paid',         // Bezahlt
                'partial_paid', // Teilweise bezahlt
                'overdue',      // Überfällig
                'cancelled',    // Storniert (GoBD: nicht löschen!)
            ])->default('draft');

            // Storno-Referenz
            $table->foreignId('cancelled_by_invoice_id')->nullable();
            $table->text('cancellation_reason')->nullable();

            // PDF
            $table->string('pdf_path')->nullable();
            $table->dateTime('pdf_generated_at')->nullable();

            // Texte
            $table->text('header_text')->nullable();
            $table->text('footer_text')->nullable();
            $table->text('terms_text')->nullable();
            $table->text('internal_notes')->nullable();

            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            // Indizes
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'created_at']);
            $table->index('customer_id');
            $table->index('quote_id');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->integer('position_number')->default(0);
            $table->string('group_name')->default('Positionen');
            $table->enum('type', ['material', 'labor', 'flat', 'text'])->default('material');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 20)->default('Stück');
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};