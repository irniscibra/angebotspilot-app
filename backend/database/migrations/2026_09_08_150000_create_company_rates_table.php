<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->string('unit')->default('Std');
            // Freitext-Hinweis, z.B. "auch für Bagger bis ca. 12-14 Tonnen" —
            // hilft der KI, unterschiedliche Formulierungen des Kunden richtig
            // zuzuordnen, da hier (anders als beim Materialkatalog) kein exakter
            // Artikelabgleich möglich ist, sondern die KI selbst zuordnen muss.
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_rates');
    }
};
