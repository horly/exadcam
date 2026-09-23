<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashcams', function (Blueprint $table) {
            $table->smallInteger('gps_timezone_minutes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dashcams', function (Blueprint $table) {
            $table->dropColumn('gps_timezone_minutes');
        });
    }
};
