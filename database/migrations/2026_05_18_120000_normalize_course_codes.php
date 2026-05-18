<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalize existing course_code values to uppercase + no whitespace.
 * Aligns DB with the input normalization done in MasterDataController::normalizeCourseInput().
 *
 * Safety: aborts if normalization would create new duplicates within a curriculum.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('courses')) {
            return;
        }

        // Step 1: detect courses whose normalized form would collide with another in same curriculum
        $collisions = DB::table('courses')
            ->whereNull('deleted_at')
            ->select(
                'curriculum_id',
                DB::raw("REPLACE(UPPER(course_code), ' ', '') as normalized"),
                DB::raw('COUNT(*) as cnt'),
                DB::raw("GROUP_CONCAT(course_code SEPARATOR ', ') as raw_codes")
            )
            ->groupBy('curriculum_id', 'normalized')
            ->having('cnt', '>', 1)
            ->get();

        if ($collisions->isNotEmpty()) {
            $report = $collisions->map(fn($c) => "curriculum {$c->curriculum_id}: {$c->raw_codes}")->implode(' | ');
            throw new RuntimeException(
                'Cannot normalize course codes — collisions would occur: ' . $report
                . '. Resolve duplicates manually before running this migration.'
            );
        }

        // Step 2: normalize in-place
        DB::statement("UPDATE courses SET course_code = REPLACE(UPPER(course_code), ' ', '') WHERE deleted_at IS NULL");
    }

    public function down(): void
    {
        // Normalization is idempotent and cannot be reversed (we don't know original spacing/case)
    }
};
