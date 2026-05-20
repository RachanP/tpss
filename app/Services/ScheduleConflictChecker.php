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
}
