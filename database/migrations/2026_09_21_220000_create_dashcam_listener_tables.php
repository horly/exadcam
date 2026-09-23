<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashcams', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('imei', 15)->unique();
            $table->string('terminal_id_2013', 12)->unique();
            $table->string('terminal_id_2019', 20)->unique();
            $table->string('video_terminal_id', 12)->unique();
            $table->text('auth_token');
            $table->boolean('enabled')->default(true);
            $table->unsignedTinyInteger('channels')->default(2);
            $table->unsignedTinyInteger('frame_rate')->default(15);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_protocol', 4)->nullable();
            $table->ipAddress('last_ip')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('speed', 6, 1)->nullable();
            $table->timestamp('position_at')->nullable();
            $table->timestamps();
        });
        Schema::create('dashcam_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashcam_id')->constrained()->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed', 6, 1);
            $table->unsignedInteger('alarm')->default(0);
            $table->unsignedInteger('status')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['dashcam_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashcam_positions');
        Schema::dropIfExists('dashcams');
    }
};
