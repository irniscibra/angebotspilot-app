<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('trade', 100)
                  ->nullable()
                  ->default(null)
                  ->after('primary_color')
                  ->comment('Gewerk: shk, elektro, maler, trockenbau, fliesen, schreiner, dachdecker, sonstiges');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('trade');
        });
    }
};