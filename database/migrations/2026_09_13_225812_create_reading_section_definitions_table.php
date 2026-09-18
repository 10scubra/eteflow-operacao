<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_section_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('section_key', 60);
            $table->string('label', 120);
            $table->unsignedSmallInteger('sort_order');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->timestamps();

            $table->unique(['section_key', 'version']);
            $table->index(['section_key', 'is_active', 'effective_from'], 'reading_sections_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_section_definitions');
    }
};
