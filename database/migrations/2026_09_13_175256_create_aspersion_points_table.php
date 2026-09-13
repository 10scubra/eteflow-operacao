<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aspersion_points', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('location', 150)->nullable();
            $table->string('public_token', 64)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aspersion_points');
    }
};
