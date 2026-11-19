<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('reference', 40)->unique('maint_sched_ref_uq');
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->enum('frequency', ['monthly', 'quarterly', 'semiannual', 'annual']);
            $table->string('provider_name', 180)->nullable();
            $table->date('next_due_on')->index('maint_sched_due_ix');
            $table->date('last_completed_on')->nullable();
            $table->boolean('is_active')->default(true)->index('maint_sched_active_ix');
            $table->timestamps();
        });

        Schema::create('maintenance_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('maintenance_schedules')->restrictOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference', 40)->unique('maint_req_ref_uq');
            $table->enum('type', ['corrective', 'preventive'])->index('maint_req_type_ix');
            $table->string('title', 180);
            $table->text('description');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal')->index('maint_req_priority_ix');
            $table->enum('status', ['new', 'pending_validation', 'approved', 'planned', 'in_progress', 'suspended', 'pending_closure', 'completed', 'rejected', 'cancelled'])->default('new')->index('maint_req_status_ix');
            $table->dateTime('reported_at');
            $table->dateTime('planned_at')->nullable();
            $table->string('provider_name', 180)->nullable();
            $table->text('technical_diagnosis')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->text('observations')->nullable();
            $table->dateTime('validated_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['asset_id', 'status'], 'maint_req_asset_status_ix');
            $table->index(['reported_at', 'type'], 'maint_req_date_type_ix');
        });

        Schema::create('maintenance_interventions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->nullable()->constrained('maintenance_requests')->restrictOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('maintenance_schedules')->restrictOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('agents')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('reference', 40)->unique('maint_int_ref_uq');
            $table->enum('status', ['planned', 'in_progress', 'suspended', 'completed', 'cancelled'])->default('planned')->index('maint_int_status_ix');
            $table->dateTime('planned_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('work_performed')->nullable();
            $table->text('parts_used')->nullable();
            $table->decimal('labor_cost', 18, 2)->default(0);
            $table->decimal('parts_cost', 18, 2)->default(0);
            $table->text('observations')->nullable();
            $table->text('report')->nullable();
            $table->timestamps();
            $table->unique('request_id', 'maint_int_request_uq');
            $table->index(['schedule_id', 'completed_at'], 'maint_int_schedule_done_ix');
        });

        Schema::create('maintenance_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->nullable()->constrained('maintenance_requests')->restrictOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('maintenance_schedules')->restrictOnDelete();
            $table->foreignId('intervention_id')->nullable()->constrained('maintenance_interventions')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 50);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->text('description')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent()->index('maint_history_created_ix');
            $table->index(['request_id', 'created_at'], 'maint_history_request_ix');
            $table->index(['intervention_id', 'created_at'], 'maint_history_intervention_ix');
        });

        Schema::create('maintenance_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->nullable()->constrained('maintenance_requests')->restrictOnDelete();
            $table->foreignId('intervention_id')->nullable()->constrained('maintenance_interventions')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['request_id', 'created_at'], 'maint_doc_request_ix');
            $table->index(['intervention_id', 'created_at'], 'maint_doc_intervention_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_documents');
        Schema::dropIfExists('maintenance_histories');
        Schema::dropIfExists('maintenance_interventions');
        Schema::dropIfExists('maintenance_requests');
        Schema::dropIfExists('maintenance_schedules');
    }
};
