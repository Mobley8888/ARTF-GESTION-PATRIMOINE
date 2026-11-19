<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_campaigns')) {
            Schema::create('inventory_campaigns', function (Blueprint $table): void {
                $table->id();
                $table->string('reference', 60);
                $table->string('title', 180);
                $table->text('description')->nullable();
                $table->date('starts_on');
                $table->date('ends_on');
                $table->string('status', 24)->default('draft');
                $table->foreignId('responsible_user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('validated_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->dateTime('launched_at')->nullable();
                $table->dateTime('validated_at')->nullable();
                $table->dateTime('closed_at')->nullable();
                $table->timestamps();
                $table->unique('reference', 'uq_inv_campaign_ref');
                $table->index('status', 'ix_inv_campaign_status');
                $table->index(['status', 'starts_on'], 'ix_inv_campaign_status_start');
            });
        }

        if (! Schema::hasTable('inventory_campaign_scopes')) {
            Schema::create('inventory_campaign_scopes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('campaign_id')->constrained('inventory_campaigns')->restrictOnDelete();
                $table->string('scope_type', 20);
                $table->foreignId('direction_id')->nullable()->constrained()->restrictOnDelete();
                $table->foreignId('service_id')->nullable()->constrained()->restrictOnDelete();
                $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
                $table->timestamps();
                $table->index(['campaign_id', 'scope_type'], 'ix_inv_scope_campaign_type');
                $table->index(['direction_id', 'service_id', 'location_id'], 'ix_inv_scope_location');
            });
        }

        if (! Schema::hasIndex('inventory_campaign_scopes', 'ix_inv_scope_location')) {
            Schema::table('inventory_campaign_scopes', function (Blueprint $table): void {
                $table->index(['direction_id', 'service_id', 'location_id'], 'ix_inv_scope_location');
            });
        }

        Schema::create('inventory_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('inventory_campaigns')->restrictOnDelete();
            $table->foreignId('scope_id')->constrained('inventory_campaign_scopes')->restrictOnDelete();
            $table->foreignId('agent_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('assigned');
            $table->date('due_on')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();
            $table->unique('scope_id', 'uq_inv_task_scope');
            $table->index(['campaign_id', 'status'], 'ix_inv_task_campaign_status');
            $table->index(['agent_id', 'status'], 'ix_inv_task_agent_status');
        });

        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('inventory_campaigns')->restrictOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('inventory_tasks')->restrictOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('expected_snapshot')->nullable();
            $table->string('observed_asset_code', 60)->nullable();
            $table->string('observed_designation', 180)->nullable();
            $table->string('observed_serial_number', 120)->nullable();
            $table->foreignId('observed_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('observed_agent_id')->nullable()->constrained('agents')->restrictOnDelete();
            $table->foreignId('observed_service_id')->nullable()->constrained('services')->restrictOnDelete();
            $table->string('physical_condition', 24)->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('physical_anomaly')->nullable();
            $table->text('administrative_anomaly')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('observed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('observed_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'asset_id'], 'uq_inv_item_campaign_asset');
            $table->index(['campaign_id', 'status'], 'ix_inv_item_campaign_status');
            $table->index(['observed_location_id', 'status'], 'ix_inv_item_location_status');
        });

        Schema::create('inventory_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('inventory_campaigns')->restrictOnDelete();
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->string('step', 24);
            $table->string('decision', 24);
            $table->text('observations')->nullable();
            $table->dateTime('reviewed_at');
            $table->timestamps();
            $table->index(['campaign_id', 'reviewed_at'], 'ix_inv_review_campaign_date');
        });

        Schema::create('inventory_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_name', 255);
            $table->string('disk', 40)->default('private');
            $table->string('path', 500);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->softDeletes();
            $table->unique('path', 'uq_inv_evidence_path');
            $table->index('inventory_item_id', 'ix_inv_evidence_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_evidence');
        Schema::dropIfExists('inventory_reviews');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_tasks');
        Schema::dropIfExists('inventory_campaign_scopes');
        Schema::dropIfExists('inventory_campaigns');
    }
};
