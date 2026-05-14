<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('student_groups', function (Blueprint $table) {
            if (Schema::hasColumn('student_groups', 'academic_year_id')) {
                $table->dropForeign(['academic_year_id']);
                $table->dropColumn('academic_year_id');
            }
            if (Schema::hasColumn('student_groups', 'curriculum_id')) {
                $table->dropForeign(['curriculum_id']);
                $table->dropColumn('curriculum_id');
            }
            if (Schema::hasColumn('student_groups', 'year_level')) {
                $table->dropColumn('year_level');
            }
            if (!Schema::hasColumn('student_groups', 'course_offering_id')) {
                $table->unsignedBigInteger('course_offering_id')->after('id')->nullable();
                $table->foreign('course_offering_id')->references('id')->on('course_offerings')->nullOnDelete();
            }
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('student_groups', function (Blueprint $table) {
            if (Schema::hasColumn('student_groups', 'course_offering_id')) {
                $table->dropForeign(['course_offering_id']);
                $table->dropColumn('course_offering_id');
            }
            $table->unsignedBigInteger('curriculum_id')->nullable()->after('id');
            $table->foreign('curriculum_id')->references('id')->on('curriculums')->nullOnDelete();
            $table->unsignedBigInteger('academic_year_id')->nullable()->after('curriculum_id');
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->nullOnDelete();
        });

        Schema::enableForeignKeyConstraints();
    }
};
