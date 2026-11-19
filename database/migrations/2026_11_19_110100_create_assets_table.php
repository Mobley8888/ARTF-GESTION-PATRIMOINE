<?php

use App\Enums\AssetStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('asset_code', 60)->unique();
            $table->string('designation', 180);
            $table->text('description')->nullable();
            $table->string('serial_number', 120)->nullable();
            $table->json('characteristics')->nullable();
            $table->date('acquired_on')->nullable();
            $table->decimal('acquisition_value', 18, 2)->nullable();
            $table->string('condition', 24)->nullable();
            $table->string('status', 24)->default(AssetStatus::InService->value);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category_id']);
            $table->index('location_id');
            $table->index('serial_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
