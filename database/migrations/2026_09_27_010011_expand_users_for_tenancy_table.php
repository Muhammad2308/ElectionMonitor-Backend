<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M11 — expand_users_for_tenancy
 *
 * Adds new columns for multi-tenancy and hierarchy to the existing `users` table.
 * All columns are added as nullable first so existing data isn't broken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // RESTRICT, not CASCADE: deleting an organisation/tenant must never
            // silently take its users with it. Removal only ever happens
            // through the tenant:purge command.
            $table->foreignId('organisation_id')->nullable()->after('state_id')->constrained('organisations')->restrictOnDelete();
            $table->foreignId('tenant_id')->nullable()->after('organisation_id')->constrained('tenants')->restrictOnDelete();
            
            $table->string('user_code', 30)->unique()->nullable()->after('tenant_id');
            $table->enum('role_type', [
                'cybernet_superadmin',
                'national_master_admin',
                'state_master_admin',
                'state_admin',
                'observer'
            ])->nullable()->after('user_code');
            
            $table->foreignId('supervisor_id')->nullable()->after('role_type')->constrained('users')->nullOnDelete();
            
            $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            
            $table->text('nin_encrypted')->nullable()->after('device_id');
            $table->char('nin_hash', 64)->nullable()->after('nin_encrypted');
            
            $table->string('profile_photo_path', 255)->nullable()->after('nin_hash');
            $table->enum('deployment_status', ['deployed', 'standby', 'off_duty'])->default('standby')->after('profile_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organisation_id']);
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['supervisor_id']);
            $table->dropForeign(['created_by']);
            
            $table->dropColumn([
                'organisation_id',
                'tenant_id',
                'user_code',
                'role_type',
                'supervisor_id',
                'created_by',
                'nin_encrypted',
                'nin_hash',
                'profile_photo_path',
                'deployment_status',
            ]);
        });
    }
};
