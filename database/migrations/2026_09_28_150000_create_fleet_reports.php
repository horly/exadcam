<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_report_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('queued');
            $table->json('filters');
            $table->json('vehicle_ids');
            $table->string('scope_hash', 64);
            $table->string('error_code', 32)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
        Schema::create('fleet_report_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('filters');
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_report_presets');
        Schema::dropIfExists('fleet_report_runs');
    }
};
