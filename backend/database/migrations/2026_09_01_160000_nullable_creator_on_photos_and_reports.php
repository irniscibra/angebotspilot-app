<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bisher blockierte die DB das Entfernen eines Team-Mitglieds, sobald er
 * mind. ein Foto hochgeladen oder einen Bautagesbericht geschrieben hatte
 * (uploaded_by/created_by waren NOT NULL + RESTRICT-FK, kein onDelete
 * definiert -> MySQL-Standardverhalten ist RESTRICT). Gewuenschtes
 * Verhalten (User-Vorgabe): ein Mitarbeiter darf jederzeit aus dem Team
 * entfernt werden - seine Fotos/Berichte bleiben im Projekt erhalten,
 * er selbst sieht danach nur keine Projekte mehr. Dafuer muessen die
 * Spalten nullable werden und die FK auf nullOnDelete umgestellt werden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_photos', function (Blueprint $table) {
            $table->dropForeign(['uploaded_by']);
        });
        Schema::table('project_photos', function (Blueprint $table) {
            $table->foreignId('uploaded_by')->nullable()->change();
        });
        Schema::table('project_photos', function (Blueprint $table) {
            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('project_reports', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });
        Schema::table('project_reports', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->change();
        });
        Schema::table('project_reports', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_photos', function (Blueprint $table) {
            $table->dropForeign(['uploaded_by']);
        });
        Schema::table('project_photos', function (Blueprint $table) {
            $table->foreignId('uploaded_by')->nullable(false)->change();
        });
        Schema::table('project_photos', function (Blueprint $table) {
            $table->foreign('uploaded_by')->references('id')->on('users');
        });

        Schema::table('project_reports', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });
        Schema::table('project_reports', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable(false)->change();
        });
        Schema::table('project_reports', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users');
        });
    }
};
