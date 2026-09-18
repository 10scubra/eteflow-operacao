<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parameter_rules', function (Blueprint $table) {
            $table->foreignId('equipment_id')->nullable()->after('reading_section_definition_id')->constrained('equipment')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('parameter_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_id');
        });
    }
};
