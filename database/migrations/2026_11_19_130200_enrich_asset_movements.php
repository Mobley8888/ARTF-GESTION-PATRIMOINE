<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table): void {
            $table->string('movement_type', 24)->default('assignment');
            $table->string('received_by_name', 160)->nullable();
            $table->string('return_condition', 24)->nullable();
            $table->text('return_observations')->nullable();
            $table->index(['movement_type', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table): void {
            $table->dropIndex(['movement_type', 'assigned_at']);
            $table->dropColumn(['movement_type', 'received_by_name', 'return_condition', 'return_observations']);
        });
    }
};
