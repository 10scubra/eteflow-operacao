<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reading_sections', function (Blueprint $table) {
            $table->foreignId('editing_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('editing_started_at')->nullable()->after('editing_by');
        });
    }

    public function down(): void
    {
        Schema::table('reading_sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('editing_by');
            $table->dropColumn('editing_started_at');
        });
    }
};
