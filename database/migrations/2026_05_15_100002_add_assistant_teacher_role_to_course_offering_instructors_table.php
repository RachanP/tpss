<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('course_offering_instructors') ||
            ! Schema::hasColumn('course_offering_instructors', 'role_in_course')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                "ALTER TABLE course_offering_instructors MODIFY role_in_course ENUM('coordinator','secretary','instructor','group_advisor','assistant_teacher','preceptor') NULL"
            );
        }
    }

    public function down(): void
    {
        // Forward-only hardening migration. Do not risk dropping existing role values.
    }
};
