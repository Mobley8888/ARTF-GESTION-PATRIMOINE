<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->string('manufacturer', 160)->nullable();
            $table->string('brand', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('manufacturer_reference', 120)->nullable();
            $table->date('commissioned_on')->nullable();
            $table->string('acquisition_mode', 80)->nullable();
            $table->string('supplier', 180)->nullable();
            $table->string('invoice_reference', 120)->nullable();
            $table->string('delivery_reference', 120)->nullable();
            $table->text('acquisition_notes')->nullable();
            $table->string('usage_status', 32)->nullable();
            $table->index(['brand', 'model']);
            $table->index('usage_status');
        });

        Schema::create('asset_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('type', 40);
            $table->string('original_name', 255);
            $table->string('disk', 40)->default('private');
            $table->string('path', 500)->unique();
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['asset_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_documents');
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropIndex(['brand', 'model']);
            $table->dropIndex(['usage_status']);
            $table->dropColumn([
                'manufacturer', 'brand', 'model', 'manufacturer_reference', 'commissioned_on',
                'acquisition_mode', 'supplier', 'invoice_reference', 'delivery_reference',
                'acquisition_notes', 'usage_status',
            ]);
        });
    }
};
