<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Rechnungs-Einstellungen
            $table->string('invoice_prefix', 10)->default('RE')->after('next_quote_number');
            $table->integer('next_invoice_number')->default(1)->after('invoice_prefix');
            $table->integer('default_payment_days')->default(14)->after('next_invoice_number');

            // Bankverbindung
            $table->string('bank_name', 100)->nullable()->after('default_payment_days');
            $table->string('bank_iban', 34)->nullable()->after('bank_name');
            $table->string('bank_bic', 11)->nullable()->after('bank_iban');
            $table->string('bank_account_holder')->nullable()->after('bank_bic');

            // Kleinunternehmer-Regelung
            $table->boolean('is_small_business')->default(false)->after('bank_account_holder');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'invoice_prefix',
                'next_invoice_number',
                'default_payment_days',
                'bank_name',
                'bank_iban',
                'bank_bic',
                'bank_account_holder',
                'is_small_business',
            ]);
        });
    }
};