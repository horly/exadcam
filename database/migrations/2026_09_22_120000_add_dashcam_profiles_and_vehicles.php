<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('registration_number', 40)->nullable();
            $table->string('brand', 80)->nullable();
            $table->string('model', 80)->nullable();
            $table->timestamps();
            $table->unique(['fleet_id', 'registration_number']);
        });
        Schema::table('dashcams', function (Blueprint $table) {
            // Nullable only for already commissioned devices awaiting assignment.
            $table->foreignId('vehicle_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('model', 30)->nullable();
            $table->string('transport', 3)->default('TCP');
            $table->string('protocol_version', 4)->nullable();
        });
        // Classify the two existing named models without altering identities, secrets or telemetry.
        DB::table('dashcams')->where('name', 'ES500-603')->update(['model' => 'ES500-603', 'protocol_version' => '2013']);
        DB::table('dashcams')->where('name', 'JK114')->update(['model' => 'JK114', 'protocol_version' => '2019']);
        DB::table('dashcams')->where('model', 'JK114')->where('last_protocol', '2013')->update(['protocol_version' => '2013']);
    }

    public function down(): void
    {
        Schema::table('dashcams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vehicle_id');
            $table->dropColumn(['model', 'transport', 'protocol_version']);
        });
        Schema::dropIfExists('vehicles');
    }
};
