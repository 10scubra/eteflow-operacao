<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->foreignId('shift_template_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        Schema::table('reading_sections', function (Blueprint $table) {
            $table->foreignId('reading_section_definition_id')->nullable()->after('reading_round_id')->constrained()->restrictOnDelete();
        });

        Schema::table('parameter_rules', function (Blueprint $table) {
            $table->foreignId('reading_section_definition_id')->nullable()->after('id')->constrained()->restrictOnDelete();
        });

        Schema::table('equipment_statuses', function (Blueprint $table) {
            $table->foreignId('equipment_id')->nullable()->after('shift_id')->constrained('equipment')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_statuses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_id');
        });

        Schema::table('parameter_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reading_section_definition_id');
        });

        Schema::table('reading_sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reading_section_definition_id');
        });

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_template_id');
        });
    }
};
