<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reading_values', function (Blueprint $table) {
            $table->index('reading_section_id', 'reading_values_section_fk_index');
        });

        Schema::table('reading_values', function (Blueprint $table) {
            $table->dropUnique('reading_values_reading_section_id_field_key_unique');
            $table->foreignId('parameter_rule_point_id')->nullable()->after('parameter_rule_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('parameter_point_slot')->nullable()->after('parameter_rule_point_id');
            $table->string('semantic_status', 30)->nullable()->after('field_key')->index();
            $table->boolean('value_boolean')->nullable()->after('value_numeric');
            $table->json('value_json')->nullable()->after('value_boolean');
            $table->string('equipment_state', 30)->nullable()->after('value_json');
            $table->text('justification')->nullable()->after('equipment_state');
            $table->json('definition_snapshot')->nullable()->after('maximum_at_time');
            $table->unique(['reading_section_id', 'parameter_rule_id', 'parameter_point_slot'], 'reading_values_definition_slot_unique');
            $table->index(['reading_section_id', 'field_key'], 'reading_values_legacy_field_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reading_values', function (Blueprint $table) {
            $table->dropIndex('reading_values_legacy_field_index');
            $table->dropUnique('reading_values_definition_slot_unique');
            $table->dropConstrainedForeignId('parameter_rule_point_id');
            $table->dropColumn(['parameter_point_slot', 'semantic_status', 'value_boolean', 'value_json', 'equipment_state', 'justification', 'definition_snapshot']);
            $table->unique(['reading_section_id', 'field_key']);
            $table->dropIndex('reading_values_section_fk_index');
        });
    }
};
