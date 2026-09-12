<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->date('shift_date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->enum('status', ['planned', 'active', 'closed'])->default('planned')->index();
            $table->text('notes')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['shift_date', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
