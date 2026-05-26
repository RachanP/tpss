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
     * @return array<int, array{type:string,message:string,schedule_id:int}>
     */
    public function check(array $data, array $instructorIds, array $studentGroupIds, ?int $ignoreScheduleId = null): array
    {
        $incoming = $this->incomingSchedule($data, $studentGroupIds);

        return collect()
            ->merge($this->instructorConflicts($data, $instructorIds, $ignoreScheduleId, $incoming))
            ->merge($this->roomConflicts($data, $ignoreScheduleId))
            ->merge($this->studentGroupConflicts($data, $studentGroupIds, $ignoreScheduleId, $incoming))
            ->unique(fn (array $conflict) => $conflict['type'] . ':' . $conflict['schedule_id'] . ':' . $conflict['message'])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int>  $instructorIds
     * @return Collection<int, array{type:string,message:string,schedule_id:int}>
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
    private function studentGroupConflicts(array $data, array $studentGroupIds, ?int $ignoreScheduleId, ?Schedule $incoming): Collection
    {
        if (empty($studentGroupIds)) {
            return collect();
        }

        return $this->overlappingSchedules($data, $ignoreScheduleId)
            ->whereHas('studentGroups', fn (Builder $query) => $query->whereIn('student_groups.id', $studentGroupIds))
            ->with([
                'activityType',
                'courseOffering.course',
                'room',
                'studentGroups' => fn ($query) => $query->whereIn('student_groups.id', $studentGroupIds),
            ])
            ->get()
            ->flatMap(function (Schedule $schedule) use ($incoming) {
                if ($incoming && $this->policy->suppresses('group_overlap', $incoming, $schedule)) {
                    return collect();
                }

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
        $query = Schedule::query()
            ->whereHas('courseOffering')
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
            ->select(['id', 'course_offering_id', 'group_code'])
            ->whereIn('id', array_map('intval', $studentGroupIds))
            ->get());

        return $schedule;
    }

    private function scheduleLabel(Schedule $schedule): string
    {
        $course = $schedule->courseOffering?->course;
        $courseLabel = trim(($course?->course_code ?? 'รายวิชา') . ' ' . ($course?->name_th ?? ''));
        $dateLabel = optional($schedule->start_date ?? $schedule->teaching_date)->format('d/m/Y') ?? '-';
        $timeLabel = substr((string) $schedule->start_time, 0, 5) . '-' . substr((string) $schedule->end_time, 0, 5);

        return "{$courseLabel} ({$dateLabel} {$timeLabel})";
    }
}
