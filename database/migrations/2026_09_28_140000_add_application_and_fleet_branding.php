<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->json('values');
            $table->timestamps();
        });
        DB::table('application_settings')->insert(['id' => 1, 'values' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        Schema::table('fleets', function (Blueprint $table) {
            $table->string('logo_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fleets', fn (Blueprint $table) => $table->dropColumn('logo_path'));
        Schema::dropIfExists('application_settings');
    }
};
