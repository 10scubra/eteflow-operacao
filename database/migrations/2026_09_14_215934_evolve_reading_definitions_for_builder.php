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
        Schema::table('reading_section_definitions', function (Blueprint $table) {
            $table->dropUnique('reading_section_definitions_section_key_version_unique');
            $table->foreignId('reading_template_version_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->text('description')->nullable()->after('label');
            $table->index('reading_template_version_id', 'reading_section_definitions_template_fk_index');
            $table->unique(['reading_template_version_id', 'section_key'], 'reading_section_definitions_template_section_unique');
        });
        Schema::table('parameter_rules', function (Blueprint $table) {
            $table->string('data_type', 30)->nullable()->after('label');
            $table->text('description')->nullable()->after('data_type');
            $table->unsignedTinyInteger('decimal_places')->nullable()->after('unit');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('maximum_value');
            $table->string('condition_operator', 40)->nullable()->after('sort_order');
            $table->decimal('reference_value', 18, 6)->nullable()->after('condition_operator');
            $table->json('options')->nullable()->after('reference_value');
            $table->string('frequency_type', 30)->nullable()->after('options');
            $table->json('frequency_config')->nullable()->after('frequency_type');
            $table->text('operational_rule')->nullable()->after('frequency_config');
        });
        Schema::table('reading_rounds', function (Blueprint $table) {
            $table->foreignId('reading_template_version_id')->nullable()->after('shift_id')->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('reading_rounds', 'reading_template_version_id')) {
            Schema::table('reading_rounds', function (Blueprint $table) {
                $table->dropConstrainedForeignId('reading_template_version_id');
            });
        }
        if (Schema::hasColumn('parameter_rules', 'data_type')) {
            Schema::table('parameter_rules', function (Blueprint $table) {
                $table->dropColumn(['data_type', 'description', 'decimal_places', 'sort_order', 'condition_operator', 'reference_value', 'options', 'frequency_type', 'frequency_config', 'operational_rule']);
            });
        }
        if (Schema::hasColumn('reading_section_definitions', 'reading_template_version_id')) {
            Schema::table('reading_section_definitions', function (Blueprint $table) {
                $table->index('reading_template_version_id', 'reading_section_definitions_rollback_fk_index');
            });
            Schema::table('reading_section_definitions', function (Blueprint $table) {
                $table->dropUnique('reading_section_definitions_template_section_unique');
                $table->dropConstrainedForeignId('reading_template_version_id');
                $table->dropColumn('description');
                $table->unique(['section_key', 'version']);
            });
        }
    }
};
