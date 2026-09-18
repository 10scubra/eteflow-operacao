<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_template_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_template_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('offset_minutes');
            $table->unsignedSmallInteger('sort_order');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['shift_template_id', 'offset_minutes']);
            $table->unique(['shift_template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_template_rounds');
    }
};
