<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 180);
            $table->string('display_name', 120);
            $table->string('registration_number', 60)->nullable()->unique();
            $table->string('job_title', 120)->nullable();
            $table->string('operational_function', 120)->nullable();
            $table->string('department', 120)->nullable();
            $table->string('unit', 120)->nullable();
            $table->string('corporate_email')->nullable();
            $table->string('personal_email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->date('hired_at')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->string('photo_path')->nullable();
            $table->text('administrative_notes')->nullable();
            $table->date('terminated_at')->nullable();
            $table->text('termination_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
