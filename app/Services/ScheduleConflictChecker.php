<?php

namespace App\Services;

use App\Models\ActivityType;
use App\Models\CourseOffering;
use App\Models\Schedule;
use App\Models\StudentGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ScheduleConflictChecker
{
    public function __construct(private ScheduleConflictPolicy $policy)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int>  $instructorIds
     * @param  array<int>  $studentGroupIds
     * @return array<int, array{type:string,message:string,schedule_id:int,course_offering_id:int,schedule_label?:string,resource_label?:string}>
     */
    public function check(array $data, array $instructorIds, array $studentGroupIds, ?int $ignoreScheduleId = null): array
    {
        $incoming = $this->incomingSchedule($data, $studentGroupIds);

        return collect()
            ->merge($this->instructorConflicts($data, $instructorIds, $ignoreScheduleId, $incoming))
            ->merge($this->roomConflicts($data, $ignoreScheduleId))
            ->merge($this->studentGroupConflicts($data, $studentGroupIds, $ignoreScheduleId, $incoming))
            ->unique(fn (array $conflict) => $conflict['type'] . ':' . $conflict['schedule_id'] . ':' . ($conflict['resource_label'] ?? $conflict['message']))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int>  $instructorIds
     * @return Collection<int, array{type:string,message:string,schedule_id:int,course_offering_id:int,schedule_label?:string,resource_label?:string}>
     */
    private function instructorConflicts(array $data, array $instructorIds, ?int $ignoreScheduleId, ?Schedule $incoming): Collection
    {
        if (empty($instructorIds)) {
            return collect();
        }

        return $this->overlappingSchedules($data, $ignoreScheduleId)
            ->whereHas('instructors', fn (Builder $query) => $query->whereIn('users.id', $instructorIds))
            ->with([
                'activityType',
                'courseOffering.course',
                'studentGroups',
                'instructors' => fn ($query) => $query->whereIn('users.id', $instructorIds),
            ])
            ->get()
            ->flatMap(function (Schedule $schedule) use ($incoming) {
                if ($incoming && $this->policy->suppresses('instructor_overlap', $incoming, $schedule)) {
                    return collect();
                }

                $names = $schedule->instructors
                    ->map(fn ($instructor) => $instructor->formatted_name ?? $instructor->name)
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');
                $scheduleLabel = $this->scheduleLabel($schedule);

                return collect([[
                    'type' => 'instructor_overlap',
                    'schedule_id' => $schedule->id,
                    'course_offering_id' => (int) $schedule->course_offering_id,
                    'schedule_label' => $scheduleLabel,
                    'resource_label' => $names,
                    'message' => 'อาจารย์: ' . $names . ' ชนกับ ' . $scheduleLabel,
                ]]);
            });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, array{type:string,message:string,schedule_id:int,course_offering_id:int,schedule_label?:string,resource_label?:string}>
     */
    private function roomConflicts(array $data, ?int $ignoreScheduleId): Collection
    {
        $roomId = $data['room_id'] ?? null;
        if (! $roomId) {
            return collect();
        }

        return $this->overlappingSchedules($data, $ignoreScheduleId)
            ->where('room_id', $roomId)
            ->with(['courseOffering.course', 'room.locationType'])
            ->get()
            ->filter(function (Schedule $schedule) {
                // ข้ามถ้า room ประเภทนี้ "ใช้ร่วมกันได้" — ไม่ถือว่าชนกัน
                return ! ($schedule->room?->locationType?->is_shared ?? false);
            })
            ->map(function (Schedule $schedule) {
                $roomLabel = $schedule->room?->room_name ?? $schedule->room?->room_code ?? 'ที่เลือก';
                $scheduleLabel = $this->scheduleLabel($schedule);

                return [
                    'type' => 'room_overlap',
                    'schedule_id' => $schedule->id,
                    'course_offering_id' => (int) $schedule->course_offering_id,
                    'schedule_label' => $scheduleLabel,
                    'resource_label' => $roomLabel,
                    'message' => 'ห้อง/สถานที่: ' . $roomLabel . ' ชนกับ ' . $scheduleLabel,
                ];
            });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int>  $studentGroupIds
     * @return Collection<int, array{type:string,message:string,schedule_id:int,course_offering_id:int,schedule_label?:string,resource_label?:string}>
     */
    private function studentGroupConflicts(array $data, array $studentGroupIds, ?int $ignoreScheduleId, ?Schedule $incoming): Collection
    {
        if (empty($studentGroupIds)) {
            return collect();
        }

        $incomingGroups = $incoming?->studentGroups ?? collect();
        $incomingGroupIds = $incomingGroups->pluck('id')->map(fn ($id) => (int) $id)->all();
        $incomingRootIds = $incomingGroups
            ->map(fn (StudentGroup $group) => $this->studentGroupRootId($group))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $this->overlappingSchedules($data, $ignoreScheduleId)
            ->whereHas('studentGroups', function (Builder $query) use ($studentGroupIds, $incomingRootIds): void {
                $query->whereIn('student_groups.id', $studentGroupIds);

                if ($incomingRootIds !== []) {
                    $query->orWhereIn('student_groups.cohort_group_id', $incomingRootIds)
                        ->orWhereHas('cohortGroup', fn (Builder $query) => $query->whereIn('parent_id', $incomingRootIds));
                }
            })
            ->with([
                'activityType',
                'courseOffering.course',
                'room',
                'studentGroups.cohortGroup.parent',
            ])
            ->get()
            ->flatMap(function (Schedule $schedule) use ($incoming, $incomingGroups, $incomingGroupIds, $incomingRootIds) {
                if ($incoming && $this->policy->suppresses('group_overlap', $incoming, $schedule)) {
                    return collect();
                }

                $matches = $this->studentGroupConflictLabels(
                    $incoming,
                    $schedule,
                    $incomingGroups,
                    $incomingGroupIds,
                    $incomingRootIds
                );

                if ($matches === []) {
                    return collect();
                }

                $groupCodes = collect($matches)
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');
                $scheduleLabel = $this->scheduleLabel($schedule);

                return collect([[
                    'type' => 'group_overlap',
                    'schedule_id' => $schedule->id,
                    'course_offering_id' => (int) $schedule->course_offering_id,
                    'schedule_label' => $scheduleLabel,
                    'resource_label' => $groupCodes,
                    'message' => 'กลุ่มนักศึกษา: ' . $groupCodes . ' ชนกับ ' . $scheduleLabel,
                ]]);
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function overlappingSchedules(array $data, ?int $ignoreScheduleId): Builder
    {
        $query = Schedule::query()
            ->whereHas('courseOffering', fn (Builder $query) => $query->withActiveCourse())
            ->when($ignoreScheduleId, fn (Builder $query) => $query->whereKeyNot($ignoreScheduleId));

        if (Schema::hasColumn('schedules', 'start_date') && Schema::hasColumn('schedules', 'end_date')) {
            $query->where('start_date', '<=', $data['end_date'])
                ->where('end_date', '>=', $data['start_date']);
        } else {
            $query->whereDate('teaching_date', '>=', $data['start_date'])
                ->whereDate('teaching_date', '<=', $data['end_date']);
        }

        return $query
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int>  $studentGroupIds
     */
    private function incomingSchedule(array $data, array $studentGroupIds): ?Schedule
    {
        if (empty($data['course_offering_id']) || empty($data['activity_type_id'])) {
            return null;
        }

        $schedule = new Schedule([
            'course_offering_id' => (int) $data['course_offering_id'],
            'activity_type_id' => (int) $data['activity_type_id'],
            'room_id' => $data['room_id'] ?? null,
            'practicum_series_id' => $data['practicum_series_id'] ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'teaching_date' => $data['teaching_date'] ?? ($data['start_date'] ?? null),
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'sub_group_label' => $data['sub_group_label'] ?? null,
        ]);

        $schedule->setRelation('activityType', ActivityType::query()
            ->select(['id', 'name', 'category'])
            ->find($data['activity_type_id']));
        $schedule->setRelation('courseOffering', CourseOffering::query()
            ->select(['id', 'course_id', 'requires_practicum_rotation', 'planned_practicum_hours'])
            ->with('course:id,course_code,name_th,name_en,requires_practicum_rotation')
            ->find($data['course_offering_id']));
        $schedule->setRelation('studentGroups', StudentGroup::query()
            ->select(['id', 'course_offering_id', 'cohort_group_id', 'group_code'])
            ->whereIn('id', array_map('intval', $studentGroupIds))
            ->with('cohortGroup.parent')
            ->get());

        return $schedule;
    }

    /**
     * @return array<int, string>
     */
    private function studentGroupConflictLabels(
        ?Schedule $incoming,
        Schedule $candidate,
        Collection $incomingGroups,
        array $incomingGroupIds,
        array $incomingRootIds
    ): array {
        if (! $incoming) {
            return $candidate->studentGroups->pluck('group_code')->filter()->values()->all();
        }

        $sameCourseOffering = (int) $incoming->course_offering_id === (int) $candidate->course_offering_id;

        if ($sameCourseOffering) {
            return $candidate->studentGroups
                ->filter(fn (StudentGroup $group) => in_array((int) $group->id, $incomingGroupIds, true))
                ->pluck('group_code')
                ->filter()
                ->values()
                ->all();
        }

        return $candidate->studentGroups
            ->filter(fn (StudentGroup $group) => in_array($this->studentGroupRootId($group), $incomingRootIds, true))
            ->map(fn (StudentGroup $group) => $this->studentGroupRootLabel($group, $incomingGroups) ?: $group->group_code)
            ->filter()
            ->values()
            ->all();
    }

    private function studentGroupRootId(StudentGroup $group): int
    {
        return (int) ($group->cohortGroup?->parent_id ?? $group->cohort_group_id ?? $group->id);
    }

    private function studentGroupRootLabel(StudentGroup $candidateGroup, Collection $incomingGroups): string
    {
        $rootId = $this->studentGroupRootId($candidateGroup);
        $source = $incomingGroups->first(fn (StudentGroup $group) => $this->studentGroupRootId($group) === $rootId);
        $rootCode = $candidateGroup->cohortGroup?->parent?->code
            ?? $candidateGroup->cohortGroup?->code
            ?? $source?->cohortGroup?->parent?->code
            ?? $source?->cohortGroup?->code
            ?? '';

        return $rootCode !== '' ? "กลุ่มต้นทาง {$rootCode}" : '';
    }

    private function scheduleLabel(Schedule $schedule): string
    {
        $course = $schedule->courseOffering?->course;
        $courseLabel = trim(($course?->course_code ?? 'รายวิชา') . ' ' . ($course?->name_th ?? ''));
        $dateLabel = optional($schedule->start_date ?? $schedule->teaching_date)->format('d/m/Y') ?? '-';
        $timeLabel = substr((string) $schedule->start_time, 0, 5) . '-' . substr((string) $schedule->end_time, 0, 5);

        return "{$courseLabel} ({$dateLabel} {$timeLabel})";
    }

    /**
     * Bulk in-memory conflict detection — replaces N×3 SQL calls of check().
     * Requires schedules pre-loaded with relations: courseOffering.course, instructors, studentGroups, room.
     *
     * @param Collection<int, Schedule> $schedules
     * @return Collection<int, Collection<int, array{type:string,message:string,schedule_id:int,course_offering_id:int}>>
     */
    public function bulkConflictMap(Collection $schedules): Collection
    {
        // Pre-extract data once per schedule — avoid repeated relation traversal
        $rows = $schedules->map(fn (Schedule $s) => [
            'schedule'   => $s,
            'id'         => (int) $s->id,
            'course_offering_id' => (int) $s->course_offering_id,
            'start_date' => $s->start_date?->toDateString(),
            'end_date'   => $s->end_date?->toDateString(),
            'start_time' => substr((string) $s->start_time, 0, 5),
            'end_time'   => substr((string) $s->end_time, 0, 5),
            'room_id'    => $s->room_id,
            'is_shared'  => (bool) ($s->room?->locationType?->is_shared ?? false),
            'inst_ids'   => $s->relationLoaded('instructors')
                ? $s->instructors->pluck('id')->map(fn ($v) => (int) $v)->all()
                : [],
            'group_ids'  => $s->relationLoaded('studentGroups')
                ? $s->studentGroups->pluck('id')->map(fn ($v) => (int) $v)->all()
                : [],
            'group_roots' => $s->relationLoaded('studentGroups')
                ? $s->studentGroups
                    ->map(fn (StudentGroup $group) => $this->studentGroupRootId($group))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all()
                : [],
        ])->values()->all();

        $conflictMap = [];
        $count = count($rows);

        for ($i = 0; $i < $count; $i++) {
            $a = $rows[$i];
            $conflictMap[$a['id']] ??= [];

            for ($j = $i + 1; $j < $count; $j++) {
                $b = $rows[$j];

                // Time overlap (same predicate as overlappingSchedules SQL)
                if (
                    $a['start_date'] === null || $a['end_date'] === null
                    || $b['start_date'] === null || $b['end_date'] === null
                    || $a['start_date'] > $b['end_date']
                    || $a['end_date'] < $b['start_date']
                    || $a['start_time'] >= $b['end_time']
                    || $a['end_time'] <= $b['start_time']
                ) {
                    continue;
                }

                $conflictMap[$b['id']] ??= [];
                $labelA = $this->scheduleLabel($a['schedule']);
                $labelB = $this->scheduleLabel($b['schedule']);

                // Instructor overlap — emit one entry per shared instructor (both directions)
                $sharedInstructors = array_intersect($a['inst_ids'], $b['inst_ids']);
                if (! empty($sharedInstructors)) {
                    $instructorsById = $a['schedule']->instructors->keyBy('id');
                    foreach ($sharedInstructors as $instructorId) {
                        $name = ($instructorsById[$instructorId]->formatted_name ?? $instructorsById[$instructorId]->name ?? "#{$instructorId}");
                        $conflictMap[$a['id']][] = [
                            'type'        => 'instructor_overlap',
                            'schedule_id' => $b['id'],
                            'course_offering_id' => $b['course_offering_id'],
                            'message'     => "อาจารย์ {$name} มีตารางซ้อนกับ {$labelB}",
                        ];
                        $conflictMap[$b['id']][] = [
                            'type'        => 'instructor_overlap',
                            'schedule_id' => $a['id'],
                            'course_offering_id' => $a['course_offering_id'],
                            'message'     => "อาจารย์ {$name} มีตารางซ้อนกับ {$labelA}",
                        ];
                    }
                }

                // Room overlap — single entry per pair (room is scalar)
                // ข้ามถ้า room ประเภท is_shared (ห้องใช้ร่วมกันได้ข้ามตาราง)
                if ($a['room_id'] && $a['room_id'] === $b['room_id'] && ! $a['is_shared']) {
                    $roomName = $a['schedule']->room?->room_name ?? $a['schedule']->room?->room_code ?? 'ที่เลือก';
                    $conflictMap[$a['id']][] = [
                        'type'        => 'room_overlap',
                        'schedule_id' => $b['id'],
                        'course_offering_id' => $b['course_offering_id'],
                        'message'     => "ห้อง/สถานที่ {$roomName} มีตารางซ้อนกับ {$labelB}",
                    ];
                    $conflictMap[$b['id']][] = [
                        'type'        => 'room_overlap',
                        'schedule_id' => $a['id'],
                        'course_offering_id' => $a['course_offering_id'],
                        'message'     => "ห้อง/สถานที่ {$roomName} มีตารางซ้อนกับ {$labelA}",
                    ];
                }

                // Student group overlap — same course uses exact groups, cross-course uses same root cohort.
                $sharedGroups = array_intersect($a['group_ids'], $b['group_ids']);
                $sharedRootGroups = ((int) $a['course_offering_id'] === (int) $b['course_offering_id'])
                    ? []
                    : array_intersect($a['group_roots'], $b['group_roots']);

                if (! empty($sharedGroups) || ! empty($sharedRootGroups)) {
                    $groupsById = $a['schedule']->studentGroups->keyBy('id');
                    foreach ($sharedGroups as $groupId) {
                        $code = $groupsById[$groupId]->group_code ?? "#{$groupId}";
                        $conflictMap[$a['id']][] = [
                            'type'        => 'group_overlap',
                            'schedule_id' => $b['id'],
                            'course_offering_id' => $b['course_offering_id'],
                            'message'     => "กลุ่มนักศึกษา {$code} มีตารางซ้อนกับ {$labelB}",
                        ];
                        $conflictMap[$b['id']][] = [
                            'type'        => 'group_overlap',
                            'schedule_id' => $a['id'],
                            'course_offering_id' => $a['course_offering_id'],
                            'message'     => "กลุ่มนักศึกษา {$code} มีตารางซ้อนกับ {$labelA}",
                        ];
                    }

                    foreach ($sharedRootGroups as $rootId) {
                        $sourceGroup = $a['schedule']->studentGroups
                            ->first(fn (StudentGroup $group) => $this->studentGroupRootId($group) === (int) $rootId);
                        $candidateGroup = $b['schedule']->studentGroups
                            ->first(fn (StudentGroup $group) => $this->studentGroupRootId($group) === (int) $rootId);
                        $label = $sourceGroup
                            ? ($this->studentGroupRootLabel($sourceGroup, $b['schedule']->studentGroups) ?: ($sourceGroup->group_code ?? "กลุ่ม {$rootId}"))
                            : "กลุ่มต้นทาง {$rootId}";

                        $conflictMap[$a['id']][] = [
                            'type'        => 'group_overlap',
                            'schedule_id' => $b['id'],
                            'course_offering_id' => $b['course_offering_id'],
                            'message'     => "{$label} มีตารางซ้อนกับ {$labelB}",
                        ];
                        $conflictMap[$b['id']][] = [
                            'type'        => 'group_overlap',
                            'schedule_id' => $a['id'],
                            'course_offering_id' => $a['course_offering_id'],
                            'message'     => "{$label} มีตารางซ้อนกับ {$labelA}",
                        ];
                    }
                }
            }
        }

        // Convert inner arrays to Collections + de-duplicate (matches check() behavior)
        return collect($conflictMap)->map(
            fn (array $items) => collect($items)
                ->unique(fn (array $c) => $c['type'] . ':' . $c['schedule_id'] . ':' . $c['message'])
                ->values()
        );
    }
}
