<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60);
            $table->string('name', 120);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->timestamps();

            $table->unique(['code', 'version']);
            $table->index(['code', 'is_active', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_templates');
    }
};
