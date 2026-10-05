<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M20 — add_tenant_to_check_ins
 *
 * Renames `observer_check_ins` to `check_ins` and adds multi-tenancy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('observer_check_ins', 'check_ins');

        Schema::table('check_ins', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->nullable()->after('id');
            // RESTRICT: tenant removal only happens via tenant:purge, never
            // as a side effect of deleting something else.
            $table->foreignId('tenant_id')->nullable()->after('uuid')->constrained('tenants')->restrictOnDelete();
            
            $table->renameColumn('user_id', 'observer_id');
            
            $table->decimal('accuracy_m', 8, 2)->nullable()->after('longitude');
            $table->renameColumn('check_in_time', 'captured_at');
            $table->dateTime('synced_at')->nullable()->after('captured_at');
        });

        // Backfill tenant_id
        DB::statement("
            UPDATE check_ins c
            JOIN users u ON c.observer_id = u.id
            SET c.tenant_id = u.tenant_id
            WHERE u.tenant_id IS NOT NULL
        ");

        // Enforce constraints
        Schema::table('check_ins', function (Blueprint $table) {
            $table->dropForeign('observer_check_ins_user_id_foreign');
        });
        
        DB::statement("
            ALTER TABLE check_ins
            ADD CONSTRAINT fk_check_ins_observer
            FOREIGN KEY (tenant_id, observer_id) REFERENCES users(tenant_id, id)
            ON DELETE CASCADE
        ");
    }

    public function down(): void
    {
        Schema::table('check_ins', function (Blueprint $table) {
            $table->dropForeign('fk_check_ins_observer');

            // Drop the FK on tenant_id before dropping the column — MySQL
            // refuses to drop a column while a foreign key still depends on it.
            $table->dropForeign(['tenant_id']);

            // captured_at was renamed from check_in_time in up(); reverse
            // that rename, then drop synced_at as its own column (it was
            // never a rename target, so it must not appear in both places).
            $table->renameColumn('captured_at', 'check_in_time');
            $table->dropColumn(['uuid', 'tenant_id', 'accuracy_m', 'synced_at']);

            $table->renameColumn('observer_id', 'user_id');
        });

        // Rename back before re-adding the FK: Laravel names an unnamed FK
        // after the table it's added to, so adding it while the table was
        // still called 'check_ins' would name it 'check_ins_user_id_foreign'
        // instead of the 'observer_check_ins_user_id_foreign' that up() expects.
        Schema::rename('check_ins', 'observer_check_ins');

        Schema::table('observer_check_ins', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
