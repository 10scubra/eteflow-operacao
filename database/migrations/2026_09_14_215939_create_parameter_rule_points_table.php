<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parameter_rule_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parameter_rule_id')->constrained()->restrictOnDelete();
            $table->string('stable_key', 80);
            $table->string('label', 140);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->text('instruction')->nullable();
            $table->timestamps();
            $table->unique(['parameter_rule_id', 'stable_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parameter_rule_points');
    }
};
