<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->string('sku', 60)->nullable()->unique('stock_article_sku_uq');
            $table->string('name', 180)->nullable();
            $table->text('description')->nullable();
            $table->string('unit', 32)->default('unité');
            $table->decimal('minimum_stock', 18, 3)->default(0);
            $table->boolean('is_active')->default(true)->index('stock_article_active_ix');
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->string('stock_state', 32)->nullable()->index('asset_stock_state_ix');
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('from_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('to_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('from_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('to_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('corrects_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->string('reference', 48)->unique('stock_move_ref_uq');
            $table->enum('type', ['receipt', 'issue', 'transfer', 'adjustment', 'asset_entry', 'asset_assignment', 'asset_transfer', 'asset_return', 'physical_transfer', 'repair_dispatch', 'repair_return', 'reform', 'loss'])->index('stock_move_type_ix');
            $table->enum('status', ['validated', 'reversal'])->default('validated')->index('stock_move_status_ix');
            $table->dateTime('occurred_at')->index('stock_move_date_ix');
            $table->string('source', 180)->nullable();
            $table->string('document_reference', 120)->nullable();
            $table->string('reason', 180);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('source_movement_id')->constrained('stock_movements')->restrictOnDelete();
            $table->string('reference', 48)->unique('stock_lot_ref_uq');
            $table->date('received_on')->index('stock_lot_date_ix');
            $table->decimal('quantity_received', 18, 3);
            $table->decimal('quantity_available', 18, 3);
            $table->decimal('unit_cost', 18, 2)->nullable();
            $table->string('source', 180)->nullable();
            $table->string('document_reference', 120)->nullable();
            $table->timestamps();
            $table->index(['article_id', 'location_id', 'quantity_available'], 'stock_lot_avail_ix');
        });

        Schema::create('stock_movement_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('movement_id')->constrained('stock_movements')->restrictOnDelete();
            $table->foreignId('article_id')->nullable()->constrained('articles')->restrictOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->restrictOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained('stock_lots')->restrictOnDelete();
            $table->foreignId('destination_lot_id')->nullable()->constrained('stock_lots')->restrictOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('from_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('to_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('from_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('to_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 18, 2)->nullable();
            $table->timestamps();
            $table->index(['article_id', 'asset_id'], 'stock_line_item_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement_lines');
        Schema::dropIfExists('stock_lots');
        Schema::dropIfExists('stock_movements');
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropIndex('asset_stock_state_ix');
            $table->dropColumn('stock_state');
        });
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropIndex('stock_article_active_ix');
            $table->dropUnique('stock_article_sku_uq');
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['sku', 'name', 'description', 'unit', 'minimum_stock', 'is_active']);
        });
    }
};
