<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('course_offering_instructors', 'schedule_permission')) {
            Schema::table('course_offering_instructors', function (Blueprint $table) {
                $table->string('schedule_permission', 20)->default('view')->after('course_role_id');
            });
        }
    }

    public function down(): void
    {
        // Compatibility migration for databases migrated before this column existed.
        // Keep the column on rollback because fresh installs define it in the base table migration.
    }
};
