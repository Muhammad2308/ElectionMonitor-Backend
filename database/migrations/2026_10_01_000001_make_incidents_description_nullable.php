<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * incidents.description was created NOT NULL, but ReportIncidentRequest
     * has always validated it as 'nullable' and IncidentReportingService
     * has always written `$data['description'] ?? null` — the schema never
     * matched the validation/service layer's own assumption that a report
     * can be filed with evidence/category only, no free-text description.
     *
     * Raw SQL rather than Schema::table(...)->change(), which needs
     * doctrine/dbal — not a dependency of this project.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE incidents MODIFY description TEXT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE incidents SET description = '' WHERE description IS NULL");
        DB::statement('ALTER TABLE incidents MODIFY description TEXT NOT NULL');
    }
};
