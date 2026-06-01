<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schedules')
            || ! Schema::hasTable('schedule_instructors')
            || ! Schema::hasTable('schedule_student_groups')
            || ! Schema::hasColumn('schedules', 'schedule_template_id')) {
            return;
        }

        DB::table('schedules')
            ->whereNotNull('schedule_template_id')
            ->distinct()
            ->pluck('schedule_template_id')
            ->each(function ($templateId): void {
                $this->copyRoomToEmptySeriesInstances((int) $templateId);
                $this->copyInstructorsToEmptySeriesInstances((int) $templateId);
                $this->copyGroupsToEmptySeriesInstances((int) $templateId);
            });
    }

    public function down(): void
    {
        // Data backfill only. Do not remove user-edited schedule resources on rollback.
    }

    private function copyRoomToEmptySeriesInstances(int $templateId): void
    {
        $roomId = DB::table('schedules')
            ->where('schedule_template_id', $templateId)
            ->whereNotNull('room_id')
            ->orderBy('series_week_number')
            ->orderBy('id')
            ->value('room_id');

        if (! $roomId) {
            return;
        }

        DB::table('schedules')
            ->where('schedule_template_id', $templateId)
            ->whereNull('room_id')
            ->update(['room_id' => $roomId]);
    }

    private function copyInstructorsToEmptySeriesInstances(int $templateId): void
    {
        $sourceScheduleId = DB::table('schedules as s')
            ->join('schedule_instructors as si', 'si.schedule_id', '=', 's.id')
            ->where('s.schedule_template_id', $templateId)
            ->orderBy('s.series_week_number')
            ->orderBy('s.id')
            ->value('s.id');

        if (! $sourceScheduleId) {
            return;
        }

        $sourceRows = DB::table('schedule_instructors')
            ->where('schedule_id', $sourceScheduleId)
            ->get(['user_id', 'is_lead']);

        if ($sourceRows->isEmpty()) {
            return;
        }

        $targetIds = DB::table('schedules as s')
            ->where('s.schedule_template_id', $templateId)
            ->whereNotIn('s.id', DB::table('schedule_instructors')->select('schedule_id'))
            ->pluck('s.id');

        foreach ($targetIds as $scheduleId) {
            DB::table('schedule_instructors')->insert($sourceRows->map(fn ($row) => [
                'schedule_id' => $scheduleId,
                'user_id' => $row->user_id,
                'is_lead' => $row->is_lead,
            ])->all());
        }
    }

    private function copyGroupsToEmptySeriesInstances(int $templateId): void
    {
        $sourceScheduleId = DB::table('schedules as s')
            ->join('schedule_student_groups as ssg', 'ssg.schedule_id', '=', 's.id')
            ->where('s.schedule_template_id', $templateId)
            ->orderBy('s.series_week_number')
            ->orderBy('s.id')
            ->value('s.id');

        if (! $sourceScheduleId) {
            return;
        }

        $sourceRows = DB::table('schedule_student_groups')
            ->where('schedule_id', $sourceScheduleId)
            ->get(['student_group_id']);

        if ($sourceRows->isEmpty()) {
            return;
        }

        $targetIds = DB::table('schedules as s')
            ->where('s.schedule_template_id', $templateId)
            ->whereNotIn('s.id', DB::table('schedule_student_groups')->select('schedule_id'))
            ->pluck('s.id');

        foreach ($targetIds as $scheduleId) {
            DB::table('schedule_student_groups')->insert($sourceRows->map(fn ($row) => [
                'schedule_id' => $scheduleId,
                'student_group_id' => $row->student_group_id,
            ])->all());
        }
    }
};
