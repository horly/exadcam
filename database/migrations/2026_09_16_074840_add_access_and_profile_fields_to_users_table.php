<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('fleet_id')->nullable()->after('subscription_id')->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('fleet_id')->constrained('users')->nullOnDelete();
            $table->string('role', 24)->default('user')->after('email_verified_at')->index();
            $table->string('status', 24)->default('active')->after('role')->index();
            $table->timestamp('disabled_at')->nullable()->after('status');
            $table->json('permissions')->nullable()->after('disabled_at');
            $table->string('phone', 40)->nullable()->after('permissions');
            $table->string('address')->nullable()->after('phone');
            $table->string('profile_photo_path')->nullable()->after('address');
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->index(['subscription_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('fleet_id');
            $table->dropIndex(['subscription_id', 'role']);
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
            $table->dropColumn(['role', 'status', 'disabled_at', 'permissions', 'phone', 'address', 'profile_photo_path', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
