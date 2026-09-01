<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('project_address')->nullable();
            $table->enum('status', [
                'angefragt', 'kalkuliert', 'beauftragt', 'in_ausfuehrung', 'abgeschlossen', 'storniert'
            ])->default('angefragt');
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
