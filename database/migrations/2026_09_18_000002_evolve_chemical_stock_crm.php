<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chemical_units', function (Blueprint $table) {
            $table->boolean('is_closed_package')->default(false)->after('decimal_places');
            $table->decimal('equivalent_quantity', 18, 4)->nullable()->after('is_closed_package');
            $table->foreignId('equivalent_unit_id')->nullable()->after('equivalent_quantity')->constrained('chemical_units')->restrictOnDelete();
        });
        Schema::table('chemical_products', function (Blueprint $table) {
            $table->string('handling_mode', 30)->default('BULK')->after('decimal_places');
            $table->decimal('package_content_quantity', 18, 4)->nullable()->after('handling_mode');
            $table->foreignId('package_content_unit_id')->nullable()->after('package_content_quantity')->constrained('chemical_units')->restrictOnDelete();
        });
        Schema::create('chemical_inventory_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chemical_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('chemical_storage_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('chemical_units')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('expected_quantity', 18, 4);
            $table->decimal('physical_quantity', 18, 4);
            $table->decimal('difference', 18, 4);
            $table->text('reason')->nullable();
            $table->text('observation')->nullable();
            $table->foreignId('checked_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('checked_at');
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('adjustment_movement_id')->nullable()->constrained('chemical_stock_movements')->restrictOnDelete();
            $table->json('configuration_snapshot');
            $table->timestamps();
            $table->index(['chemical_storage_location_id', 'checked_at'], 'chem_inventory_location_time');
        });
        Schema::create('chemical_open_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chemical_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_location_id')->constrained('chemical_storage_locations')->restrictOnDelete();
            $table->foreignId('content_unit_id')->constrained('chemical_units')->restrictOnDelete();
            $table->string('usage_location', 150)->default('UAP');
            $table->decimal('initial_content', 18, 4);
            $table->decimal('remaining_content', 18, 4);
            $table->string('status', 20)->default('OPEN')->index();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->foreignId('last_weighed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('last_weighed_at')->nullable();
            $table->text('observation')->nullable();
            $table->foreignId('issue_movement_id')->constrained('chemical_stock_movements')->restrictOnDelete();
            $table->json('configuration_snapshot');
            $table->timestamps();
        });
        Schema::create('chemical_open_package_weighings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chemical_open_package_id')->constrained()->restrictOnDelete();
            $table->decimal('previous_remaining', 18, 4);
            $table->decimal('remaining', 18, 4);
            $table->decimal('consumed', 18, 4);
            $table->foreignId('weighed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('weighed_at');
            $table->text('observation')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chemical_open_package_weighings');
        Schema::dropIfExists('chemical_open_packages');
        Schema::dropIfExists('chemical_inventory_checks');
        Schema::table('chemical_products', function (Blueprint $table) {
            $table->dropForeign(['package_content_unit_id']);
            $table->dropColumn(['handling_mode', 'package_content_quantity', 'package_content_unit_id']);
        });
        Schema::table('chemical_units', function (Blueprint $table) {
            $table->dropForeign(['equivalent_unit_id']);
            $table->dropColumn(['is_closed_package', 'equivalent_quantity', 'equivalent_unit_id']);
        });
    }
};
