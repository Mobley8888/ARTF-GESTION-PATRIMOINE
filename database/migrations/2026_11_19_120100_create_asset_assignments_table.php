<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('assigned_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('reason', 120);
            $table->string('end_reason', 120)->nullable();
            $table->text('observations')->nullable();
            $table->unsignedBigInteger('active_asset_id')
                ->nullable()
                ->storedAs('CASE WHEN ended_at IS NULL THEN asset_id ELSE NULL END');
            $table->timestamps();

            $table->unique('active_asset_id');
            $table->index(['asset_id', 'ended_at']);
            $table->index(['agent_id', 'assigned_at']);
            $table->index(['service_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
    }
};
