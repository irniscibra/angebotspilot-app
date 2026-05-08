<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('mahnung_prefix')->default('MN')->after('invoice_prefix');
            $table->unsignedInteger('next_mahnung_number')->default(1)->after('mahnung_prefix');
            $table->decimal('mahnung_fee_level1', 8, 2)->default(0)->after('next_mahnung_number');
            $table->decimal('mahnung_fee_level2', 8, 2)->default(5)->after('mahnung_fee_level1');
            $table->decimal('mahnung_fee_level3', 8, 2)->default(15)->after('mahnung_fee_level2');
            $table->decimal('mahnung_interest_rate', 5, 2)->default(9.00)->after('mahnung_fee_level3');
            $table->unsignedInteger('mahnung_days_level1')->default(7)->after('mahnung_interest_rate');
            $table->unsignedInteger('mahnung_days_level2')->default(14)->after('mahnung_days_level1');
            $table->unsignedInteger('mahnung_days_level3')->default(21)->after('mahnung_days_level2');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'mahnung_prefix', 'next_mahnung_number',
                'mahnung_fee_level1', 'mahnung_fee_level2', 'mahnung_fee_level3',
                'mahnung_interest_rate',
                'mahnung_days_level1', 'mahnung_days_level2', 'mahnung_days_level3',
            ]);
        });
    }
};
