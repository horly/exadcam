<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashcams', function (Blueprint $table) {
            $table->boolean('normalize_video_timestamps')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('dashcams', function (Blueprint $table) {
            $table->dropColumn('normalize_video_timestamps');
        });
    }
};
