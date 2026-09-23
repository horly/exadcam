<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', fn (Blueprint $table) => $table->timestamp('fleet_assigned_at')->nullable());
        Schema::table('dashcams', fn (Blueprint $table) => $table->timestamp('vehicle_assigned_at')->nullable());
        // Existing assignments have no audit timestamp. Start conservatively at rollout.
        DB::table('vehicles')->update(['fleet_assigned_at' => now()]);
        DB::table('dashcams')->whereNotNull('vehicle_id')->update(['vehicle_assigned_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('dashcams', fn (Blueprint $table) => $table->dropColumn('vehicle_assigned_at'));
        Schema::table('vehicles', fn (Blueprint $table) => $table->dropColumn('fleet_assigned_at'));
    }
};
