<?php

namespace App\Services;

use App\Models\Schedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ScheduleConflictChecker
{
    /**
     * @param  array{start_date:string,end_date:string,start_time:string,end_time:string,room_id?:int|null}  $data
     * @param  array<int>  $instructorIds
     * @param  array<int>  $studentGroupIds
     * @return array<int, array{type:string,message:string,schedule_id:int}>
     */
    public function check(array $data, array $instructorIds, array $studentGroupIds, ?int $ignoreScheduleId = null): array
    {
        return collect()
            ->merge($this->instructorConflicts($data, $instructorIds, $ignoreScheduleId))
            ->merge($this->roomConflicts($data, $ignoreScheduleId))
            ->merge($this->studentGroupConflicts($data, $studentGroupIds, $ignoreScheduleId))
            ->unique(fn (array $conflict) => $conflict['type'] . ':' . $conflict['schedule_id'] . ':' . $conflict['message'])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int>  $instructorIds
     * @return Collection<int, array{type:string,message:string,schedule_id:int}>
     */
    private function instructorConflicts(array $data, array $instructorIds, ?int $ignoreScheduleId): Collection
    {
        if (empty($instructorIds)) {
            return collect();
        }

        return $this->overlappingSchedules($data, $ignoreScheduleId)
            ->whereHas('instructors', fn (Builder $query) => $query->whereIn('users.id', $instructorIds))
            ->with(['courseOffering.course', 'instructors' => fn ($query) => $query->whereIn('users.id', $instructorIds)])
            ->get()
            ->flatMap(function (Schedule $schedule) {
                return $schedule->instructors->map(fn ($instructor) => [
                    'type' => 'instructor_overlap',
                    'schedule_id' => $schedule->id,
                    'message' => 'อาจารย์ ' . ($instructor->formatted_name ?? $instructor->name) . ' มีตารางซ้อนกับ ' . $this->scheduleLabel($schedule),
                ]);
            });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, array{type:string,message:string,schedule_id:int}>
     */
    private function roomConflicts(array $data, ?int $ignoreScheduleId): Collection
    {
        $roomId = $data['room_id'] ?? null;
        if (! $roomId) {
            return collect();
        }

        return $this->overlappingSchedules($data, $ignoreScheduleId)
            ->where('room_id', $roomId)
            ->with(['courseOffering.course', 'room'])
            ->get()
            ->map(fn (Schedule $schedule) => [
                'type' => 'room_overlap',
                'schedule_id' => $schedule->id,
                'message' => 'ห้อง/สถานที่ ' . ($schedule->room?->room_name ?? $schedule->room?->room_code ?? 'ที่เลือก') . ' มีตารางซ้อนกับ ' . $this->scheduleLabel($schedule),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int>  $studentGroupIds
     * @return Collection<int, array{type:string,message:string,schedule_id:int}>
     */
    private function studentGroupConflicts(array $data, array $studentGroupIds, ?int $ignoreScheduleId): Collection
    {
        if (empty($studentGroupIds)) {
            return collect();
        }

        return $this->overlappingSchedules($data, $ignoreScheduleId)
            ->whereHas('studentGroups', fn (Builder $query) => $query->whereIn('student_groups.id', $studentGroupIds))
            ->with(['courseOffering.course', 'studentGroups' => fn ($query) => $query->whereIn('student_groups.id', $studentGroupIds)])
            ->get()
            ->flatMap(function (Schedule $schedule) {
                return $schedule->studentGroups->map(fn ($group) => [
                    'type' => 'group_overlap',
                    'schedule_id' => $schedule->id,
                    'message' => 'กลุ่มนักศึกษา ' . $group->group_code . ' มีตารางซ้อนกับ ' . $this->scheduleLabel($schedule),
                ]);
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function overlappingSchedules(array $data, ?int $ignoreScheduleId): Builder
    {
        return Schedule::query()
            ->whereHas('courseOffering')
            ->when($ignoreScheduleId, fn (Builder $query) => $query->whereKeyNot($ignoreScheduleId))
            ->where('start_date', '<=', $data['end_date'])
            ->where('end_date', '>=', $data['start_date'])
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time']);
    }

    private function scheduleLabel(Schedule $schedule): string
    {
        $course = $schedule->courseOffering?->course;
        $courseLabel = trim(($course?->course_code ?? 'รายวิชา') . ' ' . ($course?->name_th ?? ''));
        $dateLabel = optional($schedule->start_date)->format('d/m/Y') ?? '-';
        $timeLabel = substr((string) $schedule->start_time, 0, 5) . '-' . substr((string) $schedule->end_time, 0, 5);

        return "{$courseLabel} ({$dateLabel} {$timeLabel})";
    }

    /**
     * Bulk in-memory conflict detection — replaces N×3 SQL calls of check().
     * Requires schedules pre-loaded with relations: courseOffering.course, instructors, studentGroups, room.
     *
     * @param Collection<int, Schedule> $schedules
     * @return Collection<int, Collection<int, array{type:string,message:string,schedule_id:int}>>
     */
    public function bulkConflictMap(Collection $schedules): Collection
    {
        // Pre-extract data once per schedule — avoid repeated relation traversal
        $rows = $schedules->map(fn (Schedule $s) => [
            'schedule'   => $s,
            'id'         => (int) $s->id,
            'start_date' => $s->start_date?->toDateString(),
            'end_date'   => $s->end_date?->toDateString(),
            'start_time' => substr((string) $s->start_time, 0, 5),
            'end_time'   => substr((string) $s->end_time, 0, 5),
            'room_id'    => $s->room_id,
            'inst_ids'   => $s->relationLoaded('instructors')
                ? $s->instructors->pluck('id')->map(fn ($v) => (int) $v)->all()
                : [],
            'group_ids'  => $s->relationLoaded('studentGroups')
                ? $s->studentGroups->pluck('id')->map(fn ($v) => (int) $v)->all()
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
                            'message'     => "อาจารย์ {$name} มีตารางซ้อนกับ {$labelB}",
                        ];
                        $conflictMap[$b['id']][] = [
                            'type'        => 'instructor_overlap',
                            'schedule_id' => $a['id'],
                            'message'     => "อาจารย์ {$name} มีตารางซ้อนกับ {$labelA}",
                        ];
                    }
                }

                // Room overlap — single entry per pair (room is scalar)
                if ($a['room_id'] && $a['room_id'] === $b['room_id']) {
                    $roomName = $a['schedule']->room?->room_name ?? $a['schedule']->room?->room_code ?? 'ที่เลือก';
                    $conflictMap[$a['id']][] = [
                        'type'        => 'room_overlap',
                        'schedule_id' => $b['id'],
                        'message'     => "ห้อง/สถานที่ {$roomName} มีตารางซ้อนกับ {$labelB}",
                    ];
                    $conflictMap[$b['id']][] = [
                        'type'        => 'room_overlap',
                        'schedule_id' => $a['id'],
                        'message'     => "ห้อง/สถานที่ {$roomName} มีตารางซ้อนกับ {$labelA}",
                    ];
                }

                // Student group overlap — one entry per shared group
                $sharedGroups = array_intersect($a['group_ids'], $b['group_ids']);
                if (! empty($sharedGroups)) {
                    $groupsById = $a['schedule']->studentGroups->keyBy('id');
                    foreach ($sharedGroups as $groupId) {
                        $code = $groupsById[$groupId]->group_code ?? "#{$groupId}";
                        $conflictMap[$a['id']][] = [
                            'type'        => 'group_overlap',
                            'schedule_id' => $b['id'],
                            'message'     => "กลุ่มนักศึกษา {$code} มีตารางซ้อนกับ {$labelB}",
                        ];
                        $conflictMap[$b['id']][] = [
                            'type'        => 'group_overlap',
                            'schedule_id' => $a['id'],
                            'message'     => "กลุ่มนักศึกษา {$code} มีตารางซ้อนกับ {$labelA}",
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
