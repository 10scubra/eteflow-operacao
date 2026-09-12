<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_value_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_value_id')->constrained()->cascadeOnDelete();
            $table->json('previous_value')->nullable();
            $table->json('new_value');
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('changed_at')->useCurrent();
            $table->string('reason', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_value_revisions');
    }
};
