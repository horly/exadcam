<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashcams', function (Blueprint $table) {
            $table->string('video_terminal_id', 20)->change();
        });
    }

    public function down(): void
    {
        // Do not truncate registered extended identities when reverting code.
    }
};
