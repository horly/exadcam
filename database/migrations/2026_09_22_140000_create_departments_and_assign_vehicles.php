<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fleet_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['fleet_id', 'name']);
            $table->unique(['fleet_id', 'code']);
            $table->unique(['id', 'fleet_id']);
            $table->index(['fleet_id', 'status']);
        });
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable();
            // A department is optional, but when present its fleet must match.
            $table->foreign(['department_id', 'fleet_id'])
                ->references(['id', 'fleet_id'])->on('departments')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropForeign(['department_id', 'fleet_id']);
            $table->dropColumn('department_id');
        });
        Schema::dropIfExists('departments');
    }
};
