<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dosing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('stable_key', 100);
            $table->unsignedInteger('version')->default(1);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->foreignId('parameter_rule_id')->constrained('parameter_rules')->restrictOnDelete();
            $table->foreignId('parameter_rule_point_id')->nullable()->constrained('parameter_rule_points')->restrictOnDelete();
            $table->string('product_name');
            $table->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->string('start_operator', 30);
            $table->decimal('start_value', 14, 4);
            $table->string('stop_operator', 30);
            $table->decimal('stop_value', 14, 4);
            $table->unsignedInteger('reminder_after_minutes');
            $table->unsignedInteger('reminder_interval_minutes')->nullable();
            $table->unsignedInteger('recheck_after_minutes')->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['stable_key', 'version']);
        });

        Schema::create('dosing_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dosing_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('trigger_reading_value_id')->constrained('reading_values')->restrictOnDelete();
            $table->foreignId('resolved_reading_value_id')->nullable()->constrained('reading_values')->restrictOnDelete();
            $table->string('status', 40)->default('OPEN');
            $table->decimal('trigger_value', 14, 4);
            $table->json('rule_snapshot');
            $table->timestamp('detected_at');
            $table->timestamp('actioned_at')->nullable();
            $table->foreignId('actioned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('impediment_code', 60)->nullable();
            $table->text('justification')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['dosing_rule_id', 'status']);
        });

        Schema::create('dosing_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dosing_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('dosing_alert_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->unsignedBigInteger('active_lock_key')->nullable()->unique();
            $table->foreignId('trigger_reading_value_id')->constrained('reading_values')->restrictOnDelete();
            $table->string('product_name');
            $table->string('process_name')->nullable();
            $table->timestamp('started_at');
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->decimal('initial_value', 14, 4);
            $table->decimal('initial_percentage', 7, 2);
            $table->string('status', 30)->default('ACTIVE');
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->decimal('final_value', 14, 4)->nullable();
            $table->string('end_reason', 80)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('dosing_cycle_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dosing_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->string('type', 50);
            $table->decimal('percentage', 7, 2)->nullable();
            $table->decimal('ph_value', 14, 4)->nullable();
            $table->text('justification')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('event_occurred_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['dosing_cycle_id', 'event_occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dosing_cycle_events');
        Schema::dropIfExists('dosing_cycles');
        Schema::dropIfExists('dosing_alerts');
        Schema::dropIfExists('dosing_rules');
    }
};
