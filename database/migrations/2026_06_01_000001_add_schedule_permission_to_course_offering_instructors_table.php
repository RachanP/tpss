<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_offering_instructors', function (Blueprint $table) {
            if (! Schema::hasColumn('course_offering_instructors', 'schedule_permission')) {
                $table->string('schedule_permission', 20)->default('view')->after('course_role_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('course_offering_instructors', function (Blueprint $table) {
            if (Schema::hasColumn('course_offering_instructors', 'schedule_permission')) {
                $table->dropColumn('schedule_permission');
            }
        });
    }
};
