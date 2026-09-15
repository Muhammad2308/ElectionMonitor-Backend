<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('state_id')->nullable()->after('id')->constrained('states')->nullOnDelete();
            $table->string('device_id')->nullable()->after('email');
            $table->string('phone')->nullable()->after('email');
            $table->enum('status', ['active', 'suspended', 'pending'])->default('active')->after('password');
            $table->timestamp('last_login_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['state_id']);
            $table->dropColumn(['state_id', 'device_id', 'phone', 'status', 'last_login_at']);
        });
    }
};
