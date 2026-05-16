<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_groups') ||
            ! Schema::hasColumn('student_groups', 'course_offering_id')) {
            throw new RuntimeException('Cannot scope student_groups: course_offering_id column is missing.');
        }

        if (DB::table('student_groups')->whereNull('course_offering_id')->exists()) {
            throw new RuntimeException(
                'Cannot enforce student_groups.course_offering_id NOT NULL while existing null rows remain.'
            );
        }

        $duplicate = DB::table('student_groups')
            ->select('course_offering_id', 'group_code')
            ->groupBy('course_offering_id', 'group_code')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate) {
            throw new RuntimeException(
                'Cannot add unique(course_offering_id, group_code) while duplicate student group codes exist.'
            );
        }

        Schema::table('student_groups', function (Blueprint $table) {
            try {
                $table->dropForeign(['course_offering_id']);
            } catch (Throwable) {
                // The column may already be detached in a partially hardened environment.
            }
        });

        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE student_groups MODIFY course_offering_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('student_groups', function (Blueprint $table) {
                $table->unsignedBigInteger('course_offering_id')->nullable(false)->change();
            });
        }

        Schema::table('student_groups', function (Blueprint $table) {
            $table->foreign('course_offering_id')
                ->references('id')
                ->on('course_offerings')
                ->restrictOnDelete();

            if (! Schema::hasIndex('student_groups', 'student_groups_offering_group_code_unique')) {
                $table->unique(['course_offering_id', 'group_code'], 'student_groups_offering_group_code_unique');
            }
        });
    }

    public function down(): void
    {
        // Forward-only hardening migration. Do not relax scoped group constraints automatically.
    }
};
