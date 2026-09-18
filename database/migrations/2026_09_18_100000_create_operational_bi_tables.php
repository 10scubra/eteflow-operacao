<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operational_units')) {
            Schema::create('operational_units', function (Blueprint $table) {
                $table->id();
                $table->string('stable_key', 80)->unique();
                $table->string('name', 150);
                $table->string('timezone', 60)->default('America/Sao_Paulo');
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('operational_unit_user')) {
            Schema::create('operational_unit_user', function (Blueprint $table) {
                $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
                $table->primary(['operational_unit_id', 'user_id']);
            });
        }
        if (! Schema::hasColumn('shifts', 'operational_unit_id')) {
            Schema::table('shifts', function (Blueprint $table) {
                $table->foreignId('operational_unit_id')->nullable()->after('id')->constrained()->restrictOnDelete();
                $table->index(['operational_unit_id', 'shift_date']);
            });
        }
        if (! Schema::hasTable('laboratory_parameters')) {
            Schema::create('laboratory_parameters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
                $table->string('stable_key', 100);
                $table->string('name', 150);
                $table->string('default_unit', 30);
                $table->text('description')->nullable();
                $table->unsignedTinyInteger('decimal_places')->default(2);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->json('configuration')->nullable();
                $table->timestamps();
                $table->unique(['operational_unit_id', 'stable_key']);
            });
        }
        if (! Schema::hasTable('sampling_points')) {
            Schema::create('sampling_points', function (Blueprint $table) {
                $table->id();
                $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
                $table->string('stable_key', 100);
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->foreignId('equipment_id')->nullable()->constrained()->restrictOnDelete();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['operational_unit_id', 'stable_key']);
            });
        }
        if (! Schema::hasTable('laboratory_collections')) {
            Schema::create('laboratory_collections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
                $table->foreignId('sampling_point_id')->constrained()->restrictOnDelete();
                $table->string('origin_type', 30)->index();
                $table->timestamp('collected_at')->index();
                $table->timestamp('result_received_at')->nullable();
                $table->string('external_laboratory', 180)->nullable();
                $table->string('document_reference', 255)->nullable();
                $table->text('observation')->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
                $table->index(['operational_unit_id', 'collected_at']);
            });
        }
        if (! Schema::hasTable('laboratory_results')) {
            Schema::create('laboratory_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('laboratory_collection_id')->constrained()->restrictOnDelete();
                $table->foreignId('laboratory_parameter_id')->constrained()->restrictOnDelete();
                $table->decimal('result_value', 18, 6);
                $table->string('unit', 30);
                $table->json('parameter_snapshot');
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
                $table->unique(['laboratory_collection_id', 'laboratory_parameter_id'], 'lab_result_collection_parameter_unique');
            });
        }
        if (! Schema::hasIndex('laboratory_results', 'lab_result_collection_parameter_unique')) {
            Schema::table('laboratory_results', fn (Blueprint $table) => $table->unique(['laboratory_collection_id', 'laboratory_parameter_id'], 'lab_result_collection_parameter_unique'));
        }
        Schema::create('laboratory_result_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laboratory_result_id')->constrained()->restrictOnDelete();
            $table->decimal('original_value', 18, 6);
            $table->decimal('corrected_value', 18, 6);
            $table->text('reason');
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('corrected_at');
            $table->timestamps();
        });
        Schema::create('dashboard_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
            $table->string('stable_key', 100);
            $table->string('name', 150);
            $table->string('slug', 120);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_shareable')->default(true);
            $table->timestamps();
            $table->unique(['operational_unit_id', 'stable_key']);
            $table->unique(['operational_unit_id', 'slug']);
        });
        Schema::create('indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('dashboard_page_id')->constrained()->restrictOnDelete();
            $table->string('stable_key', 120);
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('source_type', 40);
            $table->foreignId('parameter_rule_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('parameter_rule_point_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('laboratory_parameter_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('aggregation', 20);
            $table->string('visualization', 20);
            $table->string('unit', 30)->nullable();
            $table->string('periodicity', 30)->nullable();
            $table->json('visual_configuration')->nullable();
            $table->json('operational_configuration')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_shareable')->default(true);
            $table->timestamps();
            $table->unique(['operational_unit_id', 'stable_key']);
            $table->index(['dashboard_page_id', 'sort_order']);
        });
        Schema::create('public_dashboard_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
            $table->string('name', 180);
            $table->char('token_hash', 64)->unique();
            $table->char('token_last_four', 4);
            $table->timestamp('expires_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('allow_export')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('dashboard_page_public_share', function (Blueprint $table) {
            $table->foreignId('public_dashboard_share_id')->constrained()->restrictOnDelete();
            $table->foreignId('dashboard_page_id')->constrained()->restrictOnDelete();
            $table->primary(['public_dashboard_share_id', 'dashboard_page_id'], 'dashboard_share_page_primary');
        });
        Schema::create('chemical_product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('chemical_product_id')->constrained()->restrictOnDelete();
            $table->decimal('price', 18, 6);
            $table->foreignId('unit_id')->constrained('chemical_units')->restrictOnDelete();
            $table->char('currency', 3)->default('BRL');
            $table->timestamp('effective_from')->index();
            $table->timestamp('effective_until')->nullable()->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->json('configuration_snapshot')->nullable();
            $table->timestamps();
            $table->index(['operational_unit_id', 'chemical_product_id', 'effective_from'], 'chem_price_unit_product_effective');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chemical_product_prices');
        Schema::dropIfExists('dashboard_page_public_share');
        Schema::dropIfExists('public_dashboard_shares');
        Schema::dropIfExists('indicators');
        Schema::dropIfExists('dashboard_pages');
        Schema::dropIfExists('laboratory_result_corrections');
        Schema::dropIfExists('laboratory_results');
        Schema::dropIfExists('laboratory_collections');
        Schema::dropIfExists('sampling_points');
        Schema::dropIfExists('laboratory_parameters');
        Schema::table('shifts', fn (Blueprint $table) => $table->dropConstrainedForeignId('operational_unit_id'));
        Schema::dropIfExists('operational_unit_user');
        Schema::dropIfExists('operational_units');
    }
};
