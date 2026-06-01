<?php

namespace App\Services;

use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ScheduleConflictIndex
{
    public function __construct(private ScheduleConflictPolicy $policy)
    {
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     * @return Collection<int, Collection<int, array{type:string,message:string,schedule_id:int}>>
     */
    public function conflictsFor(Collection $schedules): Collection
    {
        if ($schedules->isEmpty()) {
            return collect();
        }

        $this->loadResourceRelations($schedules);
        $candidates = $this->candidateSchedulesFor($schedules);
        $candidateBuckets = $this->resourceBuckets($candidates);

        return $schedules->mapWithKeys(function (Schedule $schedule) use ($candidateBuckets) {
            $conflicts = collect();
            $seen = [];

            foreach ($this->resourceEntries($schedule) as $resource) {
                foreach ($candidateBuckets[$resource['key']] ?? [] as $candidateResource) {
                    /** @var Schedule $candidate */
                    $candidate = $candidateResource['schedule'];
                    $seenKey = $candidateResource['type'] . ':' . $candidate->id . ':' . $candidateResource['key'];

                    if (
                        isset($seen[$seenKey])
                        || (int) $candidate->id === (int) $schedule->id
                        || ! $this->schedulesOverlap($schedule, $candidate)
                        || $this->policy->suppresses($candidateResource['type'], $schedule, $candidate)
                    ) {
                        continue;
                    }

                    $seen[$seenKey] = true;
                    $conflicts->push($this->messageFor($candidateResource['type'], $candidate, $candidateResource['resource']));
                }
            }

            return [
                $schedule->id => $conflicts
                    ->unique(fn (array $conflict) => $conflict['type'] . ':' . $conflict['schedule_id'] . ':' . $conflict['message'])
                    ->values(),
            ];
        });
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     */
    private function loadResourceRelations(Collection $schedules): void
    {
        if (! method_exists($schedules, 'loadMissing')) {
            return;
        }

        $schedules->loadMissing([
            'activityType',
            'courseOffering.course',
            'room.locationType:id,is_shared',
            'instructors.instructorProfile',
            'studentGroups',
        ]);
    }

    public function countForCoordinator(int $userId, ?int $academicYearId = null): int
    {
        return $this->conflictsForCoordinator($userId, $academicYearId)['total'];
    }

    /**
     * @return array{
     *     schedules: Collection<int, Schedule>,
     *     conflictMap: Collection<int, Collection<int, array{type:string,message:string,schedule_id:int}>>,
     *     total: int
     * }
     */
    public function conflictsForCoordinator(int $userId, ?int $academicYearId = null): array
    {
        $schedules = $this->orderSchedulesByDate(
            $this->baseScheduleQuery()
                ->whereHas('courseOffering', fn (Builder $query) => $query
                    ->withActiveCourse()
                    ->where('coordinator_id', $userId)
                    ->when($academicYearId, fn (Builder $query) => $query->where('academic_year_id', $academicYearId)))
        )->get();

        $conflictMap = $this->conflictsFor($schedules);

        return [
            'schedules' => $schedules->filter(
                fn (Schedule $schedule) => $conflictMap->get($schedule->id, collect())->isNotEmpty()
            )->values(),
            'conflictMap' => $conflictMap,
            'total' => $conflictMap->sum(fn (Collection $conflicts) => $conflicts->count()),
        ];
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     * @return Collection<int, Schedule>
     */
    private function candidateSchedulesFor(Collection $schedules): Collection
    {
        $startDate = $schedules
            ->map(fn (Schedule $schedule) => $this->scheduleStartDate($schedule)?->toDateString())
            ->filter()
            ->min();
        $endDate = $schedules
            ->map(fn (Schedule $schedule) => $this->scheduleEndDate($schedule)?->toDateString())
            ->filter()
            ->max();

        $roomIds = $schedules->pluck('room_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $instructorIds = $schedules
            ->flatMap(fn (Schedule $schedule) => $schedule->instructors->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $groupIds = $schedules
            ->flatMap(fn (Schedule $schedule) => $schedule->studentGroups->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (! $startDate || ! $endDate || (empty($roomIds) && empty($instructorIds) && empty($groupIds))) {
            return collect();
        }

        $query = $this->filterSchedulesByDateRange($this->baseScheduleQuery(), $startDate, $endDate);

        $query->where(function (Builder $query) use ($roomIds, $instructorIds, $groupIds): void {
            $hasClause = false;

            if (! empty($roomIds)) {
                $query->whereIn('room_id', $roomIds);
                $hasClause = true;
            }

            if (! empty($instructorIds)) {
                $method = $hasClause ? 'orWhereHas' : 'whereHas';
                $query->{$method}('instructors', fn (Builder $query) => $query->whereIn('users.id', $instructorIds));
                $hasClause = true;
            }

            if (! empty($groupIds)) {
                $method = $hasClause ? 'orWhereHas' : 'whereHas';
                $query->{$method}('studentGroups', fn (Builder $query) => $query->whereIn('student_groups.id', $groupIds));
            }
        });

        return $this->orderSchedulesByDate($query)->get();
    }

    private function baseScheduleQuery(): Builder
    {
        return Schedule::query()
            ->select($this->scheduleSelectColumns())
            ->with([
                'activityType:id,name,color_code,category',
                'room:id,room_code,room_name,location_type_id',
                'room.locationType:id,is_shared',
                'courseOffering:id,course_id,academic_year_id,coordinator_id,requires_practicum_rotation,planned_practicum_hours',
                'courseOffering.course:id,course_code,name_th,name_en,requires_practicum_rotation',
                'instructors:id,name,prefix',
                'instructors.instructorProfile:id,user_id,title,academic_degree',
                'studentGroups:id,course_offering_id,group_code,color_code',
            ])
            ->whereHas('courseOffering', fn (Builder $query) => $query->withActiveCourse());
    }

    /**
     * @return array<int, string>
     */
    private function scheduleSelectColumns(): array
    {
        $columns = [
            'id',
            'course_offering_id',
            'activity_type_id',
            'room_id',
            'practicum_series_id',
            'teaching_date',
            'start_time',
            'end_time',
            'topic',
            'sub_group_label',
            'status',
        ];

        if ($this->hasScheduleBlockDates()) {
            $columns[] = 'start_date';
            $columns[] = 'end_date';
        }

        return $columns;
    }

    /**
     * @param  Collection<int, Schedule>  $schedules
     * @return array<string, array<int, array{type:string,key:string,schedule:Schedule,resource:mixed}>>
     */
    private function resourceBuckets(Collection $schedules): array
    {
        $buckets = [];

        foreach ($schedules as $schedule) {
            foreach ($this->resourceEntries($schedule) as $entry) {
                $buckets[$entry['key']][] = $entry;
            }
        }

        return $buckets;
    }

    /**
     * @return array<int, array{type:string,key:string,schedule:Schedule,resource:mixed}>
     */
    private function resourceEntries(Schedule $schedule): array
    {
        $entries = [];
        $dateKeys = $this->scheduleDateKeys($schedule);

        if ($dateKeys === []) {
            return $entries;
        }

        // ข้ามห้อง "ใช้ร่วมกันได้" (is_shared) — หลายวิชาใช้สถานที่เดียวกันพร้อมกันได้ ไม่ถือว่าชน
        // ต้องตรงกับ ScheduleConflictChecker::bulkConflictMap() + check() ที่ skip is_shared เช่นกัน
        if ($schedule->room_id && ! ($schedule->room?->locationType?->is_shared ?? false)) {
            foreach ($dateKeys as $dateKey) {
                $entries[] = [
                    'type' => 'room_overlap',
                    'key' => 'room:' . (int) $schedule->room_id . ':' . $dateKey,
                    'schedule' => $schedule,
                    'resource' => $schedule->room,
                ];
            }
        }

        foreach ($schedule->instructors as $instructor) {
            foreach ($dateKeys as $dateKey) {
                $entries[] = [
                    'type' => 'instructor_overlap',
                    'key' => 'instructor:' . (int) $instructor->id . ':' . $dateKey,
                    'schedule' => $schedule,
                    'resource' => $instructor,
                ];
            }
        }

        foreach ($schedule->studentGroups as $group) {
            foreach ($dateKeys as $dateKey) {
                $entries[] = [
                    'type' => 'group_overlap',
                    'key' => 'group:' . (int) $group->id . ':' . $dateKey,
                    'schedule' => $schedule,
                    'resource' => $group,
                ];
            }
        }

        return $entries;
    }

    /**
     * @return array<int, string>
     */
    private function scheduleDateKeys(Schedule $schedule): array
    {
        $start = $this->scheduleStartDate($schedule);
        $end = $this->scheduleEndDate($schedule);

        if (! $start || ! $end || $start->gt($end)) {
            return [];
        }

        return collect(CarbonPeriod::create($start, $end))
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->all();
    }

    private function messageFor(string $type, Schedule $candidate, mixed $resource): array
    {
        $scheduleLabel = $this->scheduleLabel($candidate);

        return match ($type) {
            'room_overlap' => [
                'type' => $type,
                'schedule_id' => $candidate->id,
                'schedule_label' => $scheduleLabel,
                'resource_label' => $candidate->room?->room_name ?? $candidate->room?->room_code ?? 'ที่เลือก',
                'message' => 'ห้อง/สถานที่ ' . ($candidate->room?->room_name ?? $candidate->room?->room_code ?? 'ที่เลือก') . ' มีตารางซ้อนกับ ' . $scheduleLabel,
            ],
            'instructor_overlap' => [
                'type' => $type,
                'schedule_id' => $candidate->id,
                'schedule_label' => $scheduleLabel,
                'resource_label' => $resource->formatted_name ?? $resource->name,
                'message' => 'อาจารย์ ' . ($resource->formatted_name ?? $resource->name) . ' มีตารางซ้อนกับ ' . $scheduleLabel,
            ],
            'group_overlap' => [
                'type' => $type,
                'schedule_id' => $candidate->id,
                'schedule_label' => $scheduleLabel,
                'resource_label' => $resource->group_code,
                'message' => 'กลุ่มนักศึกษา ' . $resource->group_code . ' มีตารางซ้อนกับ ' . $scheduleLabel,
            ],
            default => [
                'type' => $type,
                'schedule_id' => $candidate->id,
                'schedule_label' => $scheduleLabel,
                'resource_label' => '',
                'message' => 'ตารางซ้อนกับ ' . $scheduleLabel,
            ],
        };
    }

    private function schedulesOverlap(Schedule $left, Schedule $right): bool
    {
        $leftStart = $this->scheduleStartDate($left);
        $leftEnd = $this->scheduleEndDate($left);
        $rightStart = $this->scheduleStartDate($right);
        $rightEnd = $this->scheduleEndDate($right);

        if (! $leftStart || ! $leftEnd || ! $rightStart || ! $rightEnd) {
            return false;
        }

        return $leftStart->lte($rightEnd)
            && $leftEnd->gte($rightStart)
            && $this->minutesFromTime($left->start_time) < $this->minutesFromTime($right->end_time)
            && $this->minutesFromTime($left->end_time) > $this->minutesFromTime($right->start_time);
    }

    private function scheduleStartDate(Schedule $schedule): ?CarbonImmutable
    {
        $date = $schedule->start_date ?? $schedule->teaching_date;

        return $date ? CarbonImmutable::parse($date)->startOfDay() : null;
    }

    private function scheduleEndDate(Schedule $schedule): ?CarbonImmutable
    {
        $date = $schedule->end_date ?? $schedule->teaching_date;

        return $date ? CarbonImmutable::parse($date)->startOfDay() : null;
    }

    private function minutesFromTime($time): int
    {
        $time = substr((string) $time, 0, 5);

        return ((int) substr($time, 0, 2) * 60) + (int) substr($time, 3, 2);
    }

    private function scheduleLabel(Schedule $schedule): string
    {
        $course = $schedule->courseOffering?->course;
        $courseLabel = trim(($course?->course_code ?? 'รายวิชา') . ' ' . ($course?->name_th ?? ''));
        $startDate = $this->scheduleStartDate($schedule);
        $dateLabel = $startDate ? \App\Support\ThaiDate::date($startDate) : '-';
        $timeLabel = substr((string) $schedule->start_time, 0, 5) . '-' . substr((string) $schedule->end_time, 0, 5);

        return "{$courseLabel} ({$dateLabel} {$timeLabel})";
    }

    private function filterSchedulesByDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        if ($this->hasScheduleBlockDates()) {
            return $query
                ->whereDate('start_date', '<=', $endDate)
                ->whereDate('end_date', '>=', $startDate);
        }

        return $query
            ->whereDate('teaching_date', '>=', $startDate)
            ->whereDate('teaching_date', '<=', $endDate);
    }

    private function orderSchedulesByDate(Builder $query): Builder
    {
        if ($this->hasScheduleBlockDates()) {
            return $query
                ->orderBy('start_date')
                ->orderBy('end_date')
                ->orderBy('start_time');
        }

        return $query
            ->orderBy('teaching_date')
            ->orderBy('start_time');
    }

    private function hasScheduleBlockDates(): bool
    {
        return Schema::hasColumn('schedules', 'start_date') && Schema::hasColumn('schedules', 'end_date');
    }
}
