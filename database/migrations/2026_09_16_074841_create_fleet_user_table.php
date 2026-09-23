<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_user', function (Blueprint $table) {
            $table->foreignId('fleet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 24)->default('viewer')->index();
            $table->timestamps();
            $table->primary(['fleet_id', 'user_id']);
            $table->index(['user_id', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_user');
    }
};
