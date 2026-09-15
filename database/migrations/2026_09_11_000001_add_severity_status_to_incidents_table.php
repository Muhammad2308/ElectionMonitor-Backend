<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])
                ->default('medium')
                ->after('category_id');

            $table->enum('status', ['open', 'investigating', 'resolved', 'dismissed'])
                ->default('open')
                ->after('severity');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn(['severity', 'status']);
        });
    }
};
