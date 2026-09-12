<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_round_id')->constrained()->cascadeOnDelete();
            $table->string('section_key', 60);
            $table->string('label', 120);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'reopened', 'not_applicable'])->default('pending')->index();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->unique(['reading_round_id', 'section_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_sections');
    }
};
