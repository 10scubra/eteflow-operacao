<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->dateTime('scheduled_at')->index();
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending')->index();
            $table->timestamps();
            $table->unique(['shift_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_rounds');
    }
};
