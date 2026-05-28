<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ScheduleSeriesGenerator
{
    public function __construct(private ScheduleConflictChecker $conflictChecker)
    {
    }

    /**
     * @param  array{
     *     room_id?: int|null,
     *     instructor_ids?: array<int>,
     *     lead_instructor_id?: int|null,
     *     student_group_ids?: array<int>,
     *     status?: string,
     *     remark?: string|null,
     *     check_conflicts?: bool,
     *     skip_existing_weeks?: bool,
     *     populate_resources?: 'all'|'first'
     * }  $options
     * @return Collection<int, Schedule>
     */
    public function generateFromTemplate(ScheduleTemplate $template, array $options = []): Collection
    {
        $template->loadMissing('courseOffering.academicYear');

        $instructorIds = array_values(array_unique(array_map('intval', $options['instructor_ids'] ?? [])));
        $studentGroupIds = array_values(array_unique(array_map('intval', $options['student_group_ids'] ?? [])));
        $leadInstructorId = isset($options['lead_instructor_id']) ? (int) $options['lead_instructor_id'] : null;
        $checkConflicts = (bool) ($options['check_conflicts'] ?? true);
        $skipExistingWeeks = (bool) ($options['skip_existing_weeks'] ?? true);
        $populateResources = ($options['populate_resources'] ?? 'all') === 'first' ? 'first' : 'all';
        $roomId = $options['room_id'] ?? null;

        $datesByWeek = $this->instanceDates($template);
        $firstGeneratedWeek = $datesByWeek->keys()->map(fn ($week) => (int) $week)->min();
        $existingWeeks = $skipExistingWeeks
            ? $template->schedules()->pluck('series_week_number')->map(fn ($week) => (int) $week)->all()
            : [];

        $payloads = $datesByWeek
            ->reject(fn (CarbonImmutable $date, int $week) => in_array($week, $existingWeeks, true))
            ->map(function (CarbonImmutable $date, int $week) use ($template, $roomId, $options, $populateResources, $firstGeneratedWeek, $instructorIds, $studentGroupIds, $leadInstructorId): array {
                $shouldPopulateResources = $populateResources === 'all' || (int) $week === (int) $firstGeneratedWeek;

                return [
                    'course_offering_id' => $template->course_offering_id,
                    'activity_type_id' => $template->activity_type_id,
                    'room_id' => $shouldPopulateResources ? $roomId : null,
                    'practicum_series_id' => null,
                    'schedule_template_id' => $template->id,
                    'series_week_number' => $week,
                    ...$this->scheduleDatePayload($date),
                    'start_time' => $template->start_time,
                    'end_time' => $template->end_time,
                    'topic' => $template->topic,
                    'capacity_required' => $template->capacity_required,
                    'sub_group_label' => $template->sub_group_label,
                    'status' => $options['status'] ?? 'draft',
                    'remark' => $shouldPopulateResources ? ($options['remark'] ?? null) : null,
                    '_resource_instructor_ids' => $shouldPopulateResources ? $instructorIds : [],
                    '_resource_student_group_ids' => $shouldPopulateResources ? $studentGroupIds : [],
                    '_resource_lead_instructor_id' => $shouldPopulateResources ? $leadInstructorId : null,
                ];
            });

        if ($checkConflicts) {
            $this->assertNoConflicts($payloads);
        }

        return DB::transaction(function () use ($payloads): Collection {
            return $payloads
                ->map(function (array $payload): Schedule {
                    $instructorIds = $payload['_resource_instructor_ids'] ?? [];
                    $studentGroupIds = $payload['_resource_student_group_ids'] ?? [];
                    $leadInstructorId = $payload['_resource_lead_instructor_id'] ?? null;
                    unset($payload['_resource_instructor_ids'], $payload['_resource_student_group_ids'], $payload['_resource_lead_instructor_id']);

                    $schedule = Schedule::create($payload);
                    $this->syncInstructors($schedule, $instructorIds, $leadInstructorId);
                    $schedule->studentGroups()->sync($studentGroupIds);

                    return $schedule;
                })
                ->values();
        });
    }

    /**
     * Sync template-owned fields to existing instances. Room and student groups
     * stay untouched so weekly practicum adjustments are preserved.
     *
     * @return Collection<int, Schedule>
     */
    public function syncInstancesFromTemplate(ScheduleTemplate $template, bool $checkConflicts = true): Collection
    {
        $template->loadMissing('courseOffering.academicYear');
        $datesByWeek = $this->instanceDates($template);
        $instances = $template->schedules()
            ->with(['instructors', 'studentGroups'])
            ->orderBy('series_week_number')
            ->get();

        if ($checkConflicts) {
            $payloads = $instances
                ->filter(fn (Schedule $schedule) => $datesByWeek->has((int) $schedule->series_week_number))
                ->mapWithKeys(function (Schedule $schedule) use ($template, $datesByWeek) {
                    $date = $datesByWeek->get((int) $schedule->series_week_number);

                    return [
                        $schedule->id => [
                            'course_offering_id' => $template->course_offering_id,
                            'activity_type_id' => $template->activity_type_id,
                            'room_id' => $schedule->room_id,
                            ...$this->scheduleDatePayload($date),
                            'start_time' => $template->start_time,
                            'end_time' => $template->end_time,
                            'sub_group_label' => $template->sub_group_label,
                        ],
                    ];
                });

            $this->assertNoConflictsForExisting($payloads, $instances);
        }

        return DB::transaction(function () use ($template, $datesByWeek, $instances): Collection {
            $instances->each(function (Schedule $schedule) use ($template, $datesByWeek): void {
                $date = $datesByWeek->get((int) $schedule->series_week_number);

                if (! $date) {
                    return;
                }

                $schedule->update([
                    'activity_type_id' => $template->activity_type_id,
                    ...$this->scheduleDatePayload($date),
                    'start_time' => $template->start_time,
                    'end_time' => $template->end_time,
                    'topic' => $template->topic,
                    'capacity_required' => $template->capacity_required,
                    'sub_group_label' => $template->sub_group_label,
                ]);
            });

            return $instances->fresh(['instructors', 'studentGroups'])->values();
        });
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    public function instanceDates(ScheduleTemplate $template): Collection
    {
        $template->loadMissing('courseOffering.academicYear');

        $academicStart = $template->courseOffering?->academicYear?->start_date
            ? CarbonImmutable::parse($template->courseOffering->academicYear->start_date)->startOfDay()
            : null;
        $academicEnd = $template->courseOffering?->academicYear?->end_date
            ? CarbonImmutable::parse($template->courseOffering->academicYear->end_date)->startOfDay()
            : null;

        $anchor = $template->starts_on
            ? CarbonImmutable::parse($template->starts_on)->startOfDay()
            : ($academicStart ?? CarbonImmutable::parse(now())->startOfDay());
        if ($academicStart && $anchor->lt($academicStart)) {
            $anchor = $academicStart;
        }

        $seriesEnd = $template->ends_on
            ? CarbonImmutable::parse($template->ends_on)->startOfDay()
            : null;
        if ($academicEnd && (! $seriesEnd || $academicEnd->lt($seriesEnd))) {
            $seriesEnd = $academicEnd;
        }

        $weekday = max(1, min(7, (int) $template->weekday));
        $firstOccurrence = $this->firstWeekdayOnOrAfter($anchor, $weekday);

        return collect(range((int) $template->start_week, (int) $template->end_week))
            ->mapWithKeys(function (int $week) use ($firstOccurrence, $academicStart, $seriesEnd): array {
                $date = $firstOccurrence->addWeeks($week - 1);

                if ($academicStart && $date->lt($academicStart)) {
                    return [];
                }

                if ($seriesEnd && $date->gt($seriesEnd)) {
                    return [];
                }

                return [$week => $date];
            });
    }

    /**
     * @param  iterable<string|\DateTimeInterface>  $holidayDates
     * @return Collection<int, array{schedule_id:int|null,series_week_number:int|null,date:string}>
     */
    public function holidayWarnings(iterable $schedules, iterable $holidayDates): Collection
    {
        $holidaySet = collect($holidayDates)
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->flip();

        return collect($schedules)
            ->map(function (Schedule $schedule) use ($holidaySet) {
                $date = $schedule->start_date ?? $schedule->teaching_date;

                if (! $date) {
                    return null;
                }

                $dateString = CarbonImmutable::parse($date)->toDateString();

                if (! $holidaySet->has($dateString)) {
                    return null;
                }

                return [
                    'schedule_id' => $schedule->id ? (int) $schedule->id : null,
                    'series_week_number' => $schedule->series_week_number ? (int) $schedule->series_week_number : null,
                    'date' => $dateString,
                ];
            })
            ->filter()
            ->values();
    }

    private function firstWeekdayOnOrAfter(CarbonImmutable $date, int $weekday): CarbonImmutable
    {
        $daysToAdd = ($weekday - $date->dayOfWeekIso + 7) % 7;

        return $date->addDays($daysToAdd);
    }

    private function scheduleDatePayload(CarbonImmutable $date): array
    {
        if (Schema::hasColumn('schedules', 'start_date') && Schema::hasColumn('schedules', 'end_date')) {
            return [
                'start_date' => $date->toDateString(),
                'end_date' => $date->toDateString(),
                'teaching_date' => null,
            ];
        }

        return [
            'teaching_date' => $date->toDateString(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $payloads
     */
    private function assertNoConflicts(Collection $payloads): void
    {
        $conflicts = $payloads
            ->flatMap(function (array $payload) {
                $instructorIds = $payload['_resource_instructor_ids'] ?? [];
                $studentGroupIds = $payload['_resource_student_group_ids'] ?? [];
                unset($payload['_resource_instructor_ids'], $payload['_resource_student_group_ids'], $payload['_resource_lead_instructor_id']);

                return $this->conflictChecker->check($payload, $instructorIds, $studentGroupIds);
            })
            ->unique(fn (array $conflict) => $conflict['type'] . ':' . $conflict['schedule_id'] . ':' . $conflict['message'])
            ->values();

        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages([
                'schedule' => $conflicts->pluck('message')->all(),
            ]);
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $payloadsByScheduleId
     * @param  Collection<int, Schedule>  $instances
     */
    private function assertNoConflictsForExisting(Collection $payloadsByScheduleId, Collection $instances): void
    {
        $instancesById = $instances->keyBy('id');
        $conflicts = $payloadsByScheduleId
            ->flatMap(function (array $payload, int $scheduleId) use ($instancesById) {
                $schedule = $instancesById->get($scheduleId);

                if (! $schedule) {
                    return [];
                }

                return $this->conflictChecker->check(
                    $payload,
                    $schedule->instructors->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    $schedule->studentGroups->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    $scheduleId
                );
            })
            ->unique(fn (array $conflict) => $conflict['type'] . ':' . $conflict['schedule_id'] . ':' . $conflict['message'])
            ->values();

        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages([
                'schedule' => $conflicts->pluck('message')->all(),
            ]);
        }
    }

    /**
     * @param  array<int>  $instructorIds
     */
    private function syncInstructors(Schedule $schedule, array $instructorIds, ?int $leadInstructorId): void
    {
        $payload = collect($instructorIds)
            ->mapWithKeys(fn (int $id) => [
                $id => ['is_lead' => $leadInstructorId ? $id === $leadInstructorId : false],
            ])
            ->all();

        $schedule->instructors()->sync($payload);
    }
}
