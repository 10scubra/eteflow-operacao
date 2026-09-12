<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parameter_rules', function (Blueprint $table) {
            $table->id();
            $table->string('section_key', 60);
            $table->string('field_key', 80);
            $table->string('label', 140);
            $table->string('unit', 30)->nullable();
            $table->decimal('minimum_value', 14, 4)->nullable();
            $table->decimal('maximum_value', 14, 4)->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_until')->nullable();
            $table->timestamps();
            $table->index(['section_key', 'field_key', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parameter_rules');
    }
};
