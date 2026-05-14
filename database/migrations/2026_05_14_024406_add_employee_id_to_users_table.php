<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id', 50)->nullable()->unique()->after('username')->comment('รหัสพนักงาน');
        });

        // Migrate existing employee_id from instructor_profiles → users
        if (Schema::hasColumn('instructor_profiles', 'employee_id')) {
            DB::statement('
                UPDATE users u
                JOIN instructor_profiles ip ON ip.user_id = u.id
                SET u.employee_id = ip.employee_id
                WHERE ip.employee_id IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('employee_id');
        });
    }
};
