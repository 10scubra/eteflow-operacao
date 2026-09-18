<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_members', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('shift_members', function (Blueprint $table) {
            $table->index('shift_id', 'shift_members_shift_idx');
        });

        Schema::table('shift_members', function (Blueprint $table) {
            $table->dropUnique('shift_members_shift_id_user_id_unique');
        });

        Schema::table('shift_members', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->after('user_id')->constrained()->restrictOnDelete();
            $table->index(['shift_id', 'user_id'], 'shift_members_shift_user_idx');
            $table->index(['shift_id', 'employee_id'], 'shift_members_shift_employee_idx');
        });
    }

    public function down(): void
    {
        $hasRepeatedUsers = DB::table('shift_members')
            ->whereNotNull('user_id')
            ->select(['shift_id', 'user_id'])
            ->groupBy(['shift_id', 'user_id'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasRepeatedUsers || DB::table('shift_members')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Rollback inseguro: existem múltiplos intervalos ou participações sem user_id.');
        }

        Schema::table('shift_members', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropConstrainedForeignId('employee_id');
        });

        Schema::table('shift_members', function (Blueprint $table) {
            $table->dropIndex('shift_members_shift_employee_idx');
            $table->dropIndex('shift_members_shift_user_idx');
        });

        Schema::table('shift_members', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['shift_id', 'user_id']);
        });

        Schema::table('shift_members', function (Blueprint $table) {
            $table->dropIndex('shift_members_shift_idx');
        });
    }
};
