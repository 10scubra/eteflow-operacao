<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->nullable()->after('role')->constrained()->restrictOnDelete();
            $table->timestamp('blocked_at')->nullable()->after('is_active')->index();
            $table->timestamp('last_login_at')->nullable()->after('blocked_at');
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->unique('employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['role_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['employee_id']);
            $table->dropColumn(['employee_id', 'role_id', 'blocked_at', 'last_login_at', 'must_change_password']);
        });
    }
};
