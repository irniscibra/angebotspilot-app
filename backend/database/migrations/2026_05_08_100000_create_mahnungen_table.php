<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mahnungen', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->string('mahnung_number')->unique(); // MN-2026-0001
            $table->unsignedTinyInteger('level');       // 1, 2, 3

            $table->enum('status', ['draft', 'sent', 'paid', 'cancelled'])->default('draft');

            // Beträge
            $table->decimal('original_amount', 12, 2);   // Ursprünglicher Rechnungsbetrag
            $table->decimal('mahnung_fee', 10, 2)->default(0);    // Mahngebühr
            $table->decimal('interest_rate', 5, 2)->default(9.00); // Verzugszinssatz %
            $table->integer('interest_days')->default(0);          // Tage im Verzug
            $table->decimal('interest_amount', 10, 2)->default(0); // Zinsbetrag
            $table->decimal('total_amount', 12, 2);                // Gesamtbetrag

            // Fristen
            $table->date('original_due_date');   // Ursprüngliche Fälligkeit der Rechnung
            $table->date('new_due_date');         // Neue Zahlungsfrist in der Mahnung

            // Tracking
            $table->timestamp('sent_at')->nullable();
            $table->date('paid_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mahnungen');
    }
};
