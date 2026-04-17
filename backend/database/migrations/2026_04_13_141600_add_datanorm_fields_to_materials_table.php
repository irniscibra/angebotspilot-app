<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            if (!Schema::hasColumn('materials', 'long_description')) {
                $table->text('long_description')->nullable()->after('description');
            }
            if (!Schema::hasColumn('materials', 'datanorm_article_number')) {
                $table->string('datanorm_article_number', 50)->nullable()->after('supplier_sku');
            }
            if (!Schema::hasColumn('materials', 'ean')) {
                $table->string('ean', 20)->nullable()->after('datanorm_article_number');
            }
            if (!Schema::hasColumn('materials', 'match_code')) {
                $table->string('match_code', 50)->nullable()->after('ean');
            }
            if (!Schema::hasColumn('materials', 'product_group')) {
                $table->string('product_group', 20)->nullable()->after('match_code');
            }
            if (!Schema::hasColumn('materials', 'discount_group')) {
                $table->string('discount_group', 20)->nullable()->after('product_group');
            }
            if (!Schema::hasColumn('materials', 'main_product_group')) {
                $table->string('main_product_group', 20)->nullable()->after('discount_group');
            }
            if (!Schema::hasColumn('materials', 'list_price')) {
                $table->decimal('list_price', 10, 2)->nullable()->after('main_product_group');
            }
            if (!Schema::hasColumn('materials', 'gross_price')) {
                $table->decimal('gross_price', 10, 2)->nullable()->after('list_price');
            }
            if (!Schema::hasColumn('materials', 'source')) {
                $table->string('source', 20)->nullable()->after('gross_price');
            }
            if (!Schema::hasColumn('materials', 'datanorm_import_id')) {
                $table->foreignId('datanorm_import_id')->nullable()->after('source')->constrained('datanorm_imports')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            if (Schema::hasColumn('materials', 'datanorm_import_id')) {
                $table->dropForeign(['datanorm_import_id']);
                $table->dropColumn('datanorm_import_id');
            }
            $columns = [
                'long_description', 'datanorm_article_number', 'ean', 'match_code',
                'product_group', 'discount_group', 'main_product_group',
                'list_price', 'gross_price', 'source',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('materials', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};