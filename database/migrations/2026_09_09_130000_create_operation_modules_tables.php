<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium')->index();
            $table->string('equipment', 150)->nullable();
            $table->dateTime('due_at')->nullable()->index();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending')->index();
            $table->boolean('requires_before_photo')->default(false);
            $table->boolean('requires_after_photo')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('observation')->nullable();
            $table->decimal('measured_value', 14, 4)->nullable();
            $table->string('measured_unit', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('action_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_action_id')->constrained()->cascadeOnDelete();
            $table->string('label', 255);
            $table->boolean('is_completed')->default(false);
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('action_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_action_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['reference', 'before', 'after']);
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('caption')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('taken_at');
            $table->timestamps();
        });

        Schema::create('occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30)->unique();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('location', 180)->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium')->index();
            $table->enum('status', ['open', 'monitoring', 'resolved'])->default('open')->index();
            $table->string('photo_path')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reported_at');
            $table->foreignId('operational_action_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('aspersions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->string('area', 120);
            $table->string('line', 120)->nullable();
            $table->enum('status', ['active', 'completed'])->default('active')->index();
            $table->decimal('initial_reading', 14, 3)->nullable();
            $table->decimal('final_reading', 14, 3)->nullable();
            $table->decimal('total_consumption', 14, 3)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shift_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('summary');
            $table->text('note')->nullable();
            $table->boolean('confirmed')->default(false);
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('equipment_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('location', 150)->nullable();
            $table->enum('status', ['operating', 'attention', 'stopped'])->default('operating')->index();
            $table->decimal('reading', 14, 3)->nullable();
            $table->string('unit', 30)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reported_at');
            $table->timestamps();
            $table->index(['shift_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_statuses');
        Schema::dropIfExists('shift_handovers');
        Schema::dropIfExists('aspersions');
        Schema::dropIfExists('occurrences');
        Schema::dropIfExists('action_evidences');
        Schema::dropIfExists('action_checklist_items');
        Schema::dropIfExists('operational_actions');
    }
};
