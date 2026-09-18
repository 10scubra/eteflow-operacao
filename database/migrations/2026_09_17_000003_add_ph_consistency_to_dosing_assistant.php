<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dosing_rules', function (Blueprint $table) {
            $table->decimal('consistency_max_spread', 8, 4)->nullable()->after('stop_value');
            $table->unsignedInteger('recurrence_window')->nullable()->after('consistency_max_spread');
            $table->unsignedInteger('recurrence_count')->nullable()->after('recurrence_window');
        });

        Schema::create('dosing_consistency_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dosing_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('reading_section_id')->constrained('reading_sections')->restrictOnDelete();
            $table->foreignId('control_point_id')->constrained('parameter_rule_points')->restrictOnDelete();
            $table->foreignId('suspected_point_id')->nullable()->constrained('parameter_rule_points')->restrictOnDelete();
            $table->decimal('minimum_value', 14, 4);
            $table->decimal('maximum_value', 14, 4);
            $table->decimal('average_value', 14, 4);
            $table->decimal('spread_value', 14, 4);
            $table->decimal('configured_max_spread', 8, 4)->nullable();
            $table->boolean('is_divergent')->default(false);
            $table->json('values_snapshot');
            $table->string('values_hash', 64);
            $table->timestamp('evaluated_at');
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['dosing_rule_id', 'reading_section_id', 'values_hash'], 'dosing_consistency_snapshot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dosing_consistency_checks');
        Schema::table('dosing_rules', function (Blueprint $table) {
            $table->dropColumn(['consistency_max_spread', 'recurrence_window', 'recurrence_count']);
        });
    }
};
