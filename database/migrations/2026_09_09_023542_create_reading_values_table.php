<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parameter_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('field_key', 80);
            $table->text('value_text')->nullable();
            $table->decimal('value_numeric', 14, 4)->nullable();
            $table->string('unit', 30)->nullable();
            $table->decimal('minimum_at_time', 14, 4)->nullable();
            $table->decimal('maximum_at_time', 14, 4)->nullable();
            $table->boolean('is_out_of_range')->default(false)->index();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['reading_section_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_values');
    }
};
