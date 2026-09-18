<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('permission_id')->constrained()->restrictOnDelete();
            $table->string('effect', 10);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'permission_id', 'effective_from'], 'user_permission_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_overrides');
    }
};
