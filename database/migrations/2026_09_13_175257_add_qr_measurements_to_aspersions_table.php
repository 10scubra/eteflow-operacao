<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aspersions', function (Blueprint $table) {
            $table->foreignId('aspersion_point_id')->nullable()->after('shift_id')->constrained()->nullOnDelete();
            $table->string('source', 20)->default('manual')->after('status')->index();
            $table->decimal('initial_flow_rate', 12, 3)->nullable()->after('initial_reading');
            $table->unsignedSmallInteger('initial_active_cannons')->nullable()->after('initial_flow_rate');
            $table->decimal('final_flow_rate', 12, 3)->nullable()->after('final_reading');
            $table->unsignedSmallInteger('final_active_cannons')->nullable()->after('final_flow_rate');
            $table->index(['aspersion_point_id', 'status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::table('aspersions', function (Blueprint $table) {
            $table->dropIndex(['aspersion_point_id', 'status', 'started_at']);
            $table->dropConstrainedForeignId('aspersion_point_id');
            $table->dropColumn([
                'source',
                'initial_flow_rate',
                'initial_active_cannons',
                'final_flow_rate',
                'final_active_cannons',
            ]);
        });
    }
};
