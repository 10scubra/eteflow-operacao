<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chemical_units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->string('symbol', 20);
            $table->unsignedTinyInteger('decimal_places')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('chemical_products', function (Blueprint $table) {
            $table->id();
            $table->string('stable_key', 100)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->foreignId('unit_id')->constrained('chemical_units')->restrictOnDelete();
            $table->unsignedTinyInteger('decimal_places')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('chemical_storage_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chemical_product_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('chemical_units')->restrictOnDelete();
            $table->string('stable_key', 100);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('capacity', 18, 4)->nullable();
            $table->string('low_threshold_type', 20)->nullable();
            $table->decimal('low_threshold_value', 18, 4)->nullable();
            $table->string('critical_threshold_type', 20)->nullable();
            $table->decimal('critical_threshold_value', 18, 4)->nullable();
            $table->boolean('participates_in_shift_count')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['chemical_product_id', 'stable_key'], 'chemical_location_stable_unique');
        });
        Schema::create('chemical_stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('counted_by')->constrained('users')->restrictOnDelete();
            $table->string('origin_context', 40)->default('SHIFT_START');
            $table->text('observation')->nullable();
            $table->timestamp('counted_at');
            $table->timestamps();
        });
        Schema::create('chemical_stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chemical_stock_count_id')->constrained()->restrictOnDelete();
            $table->foreignId('chemical_product_id');
            $table->foreignId('chemical_storage_location_id');
            $table->foreignId('unit_id');
            $table->decimal('quantity', 18, 4);
            $table->decimal('expected_quantity_snapshot', 18, 4)->nullable();
            $table->decimal('difference_snapshot', 18, 4)->nullable();
            $table->json('configuration_snapshot');
            $table->timestamps();
            $table->unique(['chemical_stock_count_id', 'chemical_storage_location_id'], 'chemical_count_location_unique');
            $table->foreign('chemical_product_id', 'chem_count_product_fk')->references('id')->on('chemical_products')->restrictOnDelete();
            $table->foreign('chemical_storage_location_id', 'chem_count_location_fk')->references('id')->on('chemical_storage_locations')->restrictOnDelete();
            $table->foreign('unit_id', 'chem_count_unit_fk')->references('id')->on('chemical_units')->restrictOnDelete();
        });
        Schema::create('chemical_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chemical_product_id');
            $table->foreignId('source_location_id')->nullable();
            $table->foreignId('destination_location_id')->nullable();
            $table->foreignId('unit_id');
            $table->foreignId('shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->string('direction', 10)->nullable();
            $table->decimal('quantity', 18, 4);
            $table->string('supplier')->nullable();
            $table->string('document')->nullable();
            $table->string('reason')->nullable();
            $table->text('observation')->nullable();
            $table->string('status', 20)->default('CONFIRMED');
            $table->uuid('idempotency_key')->unique();
            $table->timestamp('occurred_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->json('configuration_snapshot');
            $table->timestamps();
            $table->index(['chemical_product_id', 'occurred_at']);
            $table->foreign('chemical_product_id', 'chem_move_product_fk')->references('id')->on('chemical_products')->restrictOnDelete();
            $table->foreign('source_location_id', 'chem_move_source_fk')->references('id')->on('chemical_storage_locations')->restrictOnDelete();
            $table->foreign('destination_location_id', 'chem_move_destination_fk')->references('id')->on('chemical_storage_locations')->restrictOnDelete();
            $table->foreign('unit_id', 'chem_move_unit_fk')->references('id')->on('chemical_units')->restrictOnDelete();
        });
        Schema::create('chemical_stock_corrections', function (Blueprint $table) {
            $table->id();
            $table->string('record_type', 30);
            $table->unsignedBigInteger('record_id');
            $table->decimal('corrected_quantity', 18, 4);
            $table->text('reason');
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('corrected_at');
            $table->uuid('idempotency_key')->unique();
            $table->json('original_snapshot');
            $table->json('correction_snapshot');
            $table->timestamps();
            $table->index(['record_type', 'record_id']);
        });
        Schema::create('chemical_stock_balance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chemical_product_id');
            $table->foreignId('chemical_storage_location_id');
            $table->foreignId('reference_count_item_id')->nullable();
            $table->string('trigger_type', 30);
            $table->unsignedBigInteger('trigger_id')->nullable();
            $table->decimal('balance', 18, 4)->nullable();
            $table->decimal('percentage', 9, 4)->nullable();
            $table->string('status', 20);
            $table->json('configuration_snapshot');
            $table->timestamp('calculated_at');
            $table->timestamps();
            $table->index(['chemical_storage_location_id', 'calculated_at'], 'chemical_balance_location_time');
            $table->foreign('chemical_product_id', 'chem_balance_product_fk')->references('id')->on('chemical_products')->restrictOnDelete();
            $table->foreign('chemical_storage_location_id', 'chem_balance_location_fk')->references('id')->on('chemical_storage_locations')->restrictOnDelete();
            $table->foreign('reference_count_item_id', 'chem_balance_count_item_fk')->references('id')->on('chemical_stock_count_items')->restrictOnDelete();
        });
        Schema::create('chemical_stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chemical_product_id');
            $table->foreignId('chemical_storage_location_id');
            $table->foreignId('chemical_stock_balance_snapshot_id');
            $table->string('severity', 20);
            $table->string('status', 20)->default('OPEN');
            $table->decimal('balance_snapshot', 18, 4)->nullable();
            $table->json('threshold_snapshot');
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['chemical_storage_location_id', 'status']);
            $table->foreign('chemical_product_id', 'chem_alert_product_fk')->references('id')->on('chemical_products')->restrictOnDelete();
            $table->foreign('chemical_storage_location_id', 'chem_alert_location_fk')->references('id')->on('chemical_storage_locations')->restrictOnDelete();
            $table->foreign('chemical_stock_balance_snapshot_id', 'chem_alert_snapshot_fk')->references('id')->on('chemical_stock_balance_snapshots')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chemical_stock_alerts');
        Schema::dropIfExists('chemical_stock_balance_snapshots');
        Schema::dropIfExists('chemical_stock_corrections');
        Schema::dropIfExists('chemical_stock_movements');
        Schema::dropIfExists('chemical_stock_count_items');
        Schema::dropIfExists('chemical_stock_counts');
        Schema::dropIfExists('chemical_storage_locations');
        Schema::dropIfExists('chemical_products');
        Schema::dropIfExists('chemical_units');
    }
};
