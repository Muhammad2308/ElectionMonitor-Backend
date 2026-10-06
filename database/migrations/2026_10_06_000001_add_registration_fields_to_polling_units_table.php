<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('polling_units', function (Blueprint $table) {
            $table->string('image_path', 500)->nullable()->after('longitude');
            $table->boolean('is_registered')->default(false)->after('image_path');
            $table->dateTime('registered_at')->nullable()->after('is_registered');
            $table->foreignId('registered_by')->nullable()->after('registered_at')->constrained('users')->nullOnDelete();

            $table->index(['is_registered', 'ward_id']);
        });
    }

    public function down(): void
    {
        Schema::table('polling_units', function (Blueprint $table) {
            $table->dropForeign(['registered_by']);
            $table->dropIndex(['is_registered', 'ward_id']);
            $table->dropColumn(['image_path', 'is_registered', 'registered_at', 'registered_by']);
        });
    }
};
