<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('instructor_profiles', 'employee_id')) {
                $table->dropUnique(['employee_id']);
                $table->dropColumn('employee_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('instructor_profiles', function (Blueprint $table) {
            $table->string('employee_id', 50)->nullable()->unique()->comment('รหัสพนักงาน/อาจารย์');
        });
    }
};
