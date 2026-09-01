<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // user_id: wessen Arbeitszeit das ist. logged_by: wer den Eintrag
            // angelegt hat (bei Selbst-Erfassung identisch mit user_id, bei
            // "fuer Kollegen erfassen" durch Admin/Owner unterschiedlich).
            // Diese Trennung ist bewusst von Anfang an da - sie liesse sich
            // nur schwer nachruesten, falls spaeter ein Vorarbeiter-Flag
            // dazukommt.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('logged_by')->constrained('users')->cascadeOnDelete();
            $table->date('entry_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('break_minutes')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'entry_date']);
            $table->index(['user_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
