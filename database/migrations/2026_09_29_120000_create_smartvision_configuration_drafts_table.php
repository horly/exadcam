<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smartvision_configuration_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashcam_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->string('context_hash', 64);
            $table->json('settings');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smartvision_configuration_drafts');
    }
};
