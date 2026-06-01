<?php

namespace App\Jobs;

use App\Models\Schedule;
use App\Models\ScheduleConflictResult;
use App\Models\ScheduleConflictRun;
use App\Models\CourseOffering;
use App\Services\NavigationBadgeService;
use App\Services\ScheduleConflictInvalidationService;
use App\Services\ScheduleConflictIndex;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ConflictRecomputeJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $uniqueFor = 300;

    public function __construct(
        public int $academicYearId,
        public int $runId,
        public int $generation,
        public string $source = 'manual'
    ) {
    }

    public function uniqueId(): string
    {
        return "academic-year:{$this->academicYearId}";
    }

    public function handle(ScheduleConflictIndex $conflictIndex, ScheduleConflictInvalidationService $invalidation): void
    {
        $run = ScheduleConflictRun::query()->findOrFail($this->runId);

        $run->forceFill([
            'status' => 'processing',
            'started_at' => now(),
            'failed_at' => null,
            'error_message' => null,
        ])->save();

        try {
            $affectedScheduleIds = $this->affectedScheduleIds($run, $invalidation);
            $previousReadyRun = $affectedScheduleIds === null ? null : $this->previousReadyRun($run);
            [$sourceSchedules, $excludedScheduleIds] = $previousReadyRun
                ? $this->scopedSourceSchedules($affectedScheduleIds, $previousReadyRun, $conflictIndex)
                : [$this->sourceSchedules(), null];

            $conflictMap = $conflictIndex->conflictsFor($sourceSchedules);
            $scheduleMap = $this->scheduleMapWithCandidates($sourceSchedules, $conflictMap);
            $resultCount = $previousReadyRun
                ? $this->storeIncrementalResults($run, $previousReadyRun, $excludedScheduleIds ?? [], $sourceSchedules, $scheduleMap, $conflictMap)
                : $this->storeResults($run, $sourceSchedules, $scheduleMap, $conflictMap);

            $run->forceFill([
                'status' => 'ready',
                'finished_at' => now(),
                'result_count' => $resultCount,
            ])->save();
            $invalidation->clearDirty($this->academicYearId);
            $this->flushCourseHeadBadges($sourceSchedules);
        } catch (Throwable $exception) {
            $this->cleanupPartialResults();
            ScheduleConflictRun::query()
                ->whereKey($this->runId)
                ->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'error_message' => $exception->getMessage(),
                ]);
            $invalidation->clearDirty($this->academicYearId);
            $this->flushCourseHeadBadgesForAcademicYear();

            throw $exception;
        }
    }

    /**
     * @return array<int, int>|null  null means full recompute.
     */
    private function affectedScheduleIds(
        ScheduleConflictRun $run,
        ScheduleConflictInvalidationService $invalidation
    ): ?array {
        $dirtyScope = $invalidation->dirtyScope($this->academicYearId);

        if ($dirtyScope['full']) {
            return null;
        }

        $scheduleIds = collect($this->normalizeScheduleIds($run->metadata['affected_schedule_ids'] ?? []))
            ->merge($dirtyScope['schedule_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $scheduleIds === [] ? null : $scheduleIds;
    }

    private function previousReadyRun(ScheduleConflictRun $run): ?ScheduleConflictRun
    {
        return ScheduleConflictRun::query()
            ->where('academic_year_id', $this->academicYearId)
            ->where('status', 'ready')
            ->where('generation', '<', (int) $run->generation)
            ->orderByDesc('generation')
            ->first();
    }

    /**
     * @param  array<int, int>  $seedScheduleIds
     * @return array{0: Collection<int, Schedule>, 1: array<int, int>}
     */
    private function scopedSourceSchedules(
        array $seedScheduleIds,
        ScheduleConflictRun $previousReadyRun,
        ScheduleConflictIndex $conflictIndex
    ): array {
        $sourceIds = collect($seedScheduleIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        do {
            $before = $sourceIds->count();
            $ids = $sourceIds->all();

            $sourceIds = $sourceIds
                ->merge($this->previousConflictNeighborIds((int) $previousReadyRun->id, $ids))
                ->unique()
                ->values();

            $currentSchedules = $sourceIds->isEmpty()
                ? collect()
                : $this->baseScheduleQuery()
                    ->whereIn('id', $sourceIds->all())
                    ->get();

            if ($currentSchedules->isNotEmpty()) {
                $currentConflictMap = $conflictIndex->conflictsFor($currentSchedules);
                $sourceIds = $sourceIds
                    ->merge($currentConflictMap
                        ->flatMap(fn (Collection $conflicts) => $conflicts->pluck('schedule_id'))
                        ->map(fn ($id) => (int) $id))
                    ->unique()
                    ->values();
            }
        } while ($sourceIds->count() > $before);

        $schedules = $sourceIds->isEmpty()
            ? collect()
            : $this->baseScheduleQuery()
                ->whereIn('id', $sourceIds->all())
                ->orderBy('start_date')
                ->orderBy('end_date')
                ->orderBy('start_time')
                ->get();

        return [$schedules, $sourceIds->all()];
    }

    /**
     * @param  array<int, int>  $scheduleIds
     * @return array<int, int>
     */
    private function previousConflictNeighborIds(int $previousRunId, array $scheduleIds): array
    {
        $scheduleIds = $this->normalizeScheduleIds($scheduleIds);

        if ($scheduleIds === []) {
            return [];
        }

        return ScheduleConflictResult::query()
            ->where('run_id', $previousRunId)
            ->where(function (Builder $query) use ($scheduleIds): void {
                $query->whereIn('schedule_id', $scheduleIds)
                    ->orWhereIn('conflicting_schedule_id', $scheduleIds);
            })
            ->get(['schedule_id', 'conflicting_schedule_id'])
            ->flatMap(fn (ScheduleConflictResult $result) => [
                (int) $result->schedule_id,
                (int) $result->conflicting_schedule_id,
            ])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Schedule>
     */
    private function sourceSchedules(): Collection
    {
        return $this->baseScheduleQuery()
            ->whereHas('courseOffering', fn (Builder $query) => $query->where('academic_year_id', $this->academicYearId))
            ->orderBy('start_date')
            ->orderBy('end_date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * @param  Collection<int, Schedule>  $sourceSchedules
     * @param  Collection<int, Collection<int, array{type:string,message:string,schedule_id:int}>>  $conflictMap
     * @return Collection<int, Schedule>
     */
    private function scheduleMapWithCandidates(Collection $sourceSchedules, Collection $conflictMap): Collection
    {
        $sourceMap = $sourceSchedules->keyBy('id');
        $candidateIds = $conflictMap
            ->flatMap(fn (Collection $conflicts) => $conflicts->pluck('schedule_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => $sourceMap->has($id))
            ->values();

        if ($candidateIds->isEmpty()) {
            return $sourceMap;
        }

        $candidates = $this->baseScheduleQuery()
            ->whereIn('id', $candidateIds->all())
            ->get()
            ->keyBy('id');

        return $sourceMap->union($candidates);
    }

    private function baseScheduleQuery(): Builder
    {
        return Schedule::query()
            ->select([
                'id',
                'course_offering_id',
                'activity_type_id',
                'room_id',
                'practicum_series_id',
                'teaching_date',
                'start_date',
                'end_date',
                'start_time',
                'end_time',
                'topic',
                'sub_group_label',
                'status',
            ])
            ->with([
                'activityType:id,name,color_code,category',
                'room:id,room_code,room_name',
                'courseOffering:id,course_id,academic_year_id,coordinator_id',
                'courseOffering.course:id,course_code,name_th,name_en',
                'instructors:id,name,prefix',
                'instructors.instructorProfile:id,user_id,title,academic_degree',
                'studentGroups:id,course_offering_id,group_code,color_code',
            ])
            ->whereHas('courseOffering', fn (Builder $query) => $query->withActiveCourse());
    }

    /**
     * @param  Collection<int, Schedule>  $sourceSchedules
     * @param  Collection<int, Schedule>  $scheduleMap
     * @param  Collection<int, Collection<int, array{type:string,message:string,schedule_id:int}>>  $conflictMap
     */
    private function storeResults(
        ScheduleConflictRun $run,
        Collection $sourceSchedules,
        Collection $scheduleMap,
        Collection $conflictMap
    ): int {
        return DB::transaction(function () use ($run, $sourceSchedules, $scheduleMap, $conflictMap): int {
            $run->scopes()->delete();
            $run->results()->delete();

            return $this->insertComputedResultsWithScopes($run, $sourceSchedules, $scheduleMap, $conflictMap);
        });
    }

    /**
     * @param  array<int, int>  $excludedScheduleIds
     * @param  Collection<int, Schedule>  $sourceSchedules
     * @param  Collection<int, Schedule>  $scheduleMap
     * @param  Collection<int, Collection<int, array{type:string,message:string,schedule_id:int}>>  $conflictMap
     */
    private function storeIncrementalResults(
        ScheduleConflictRun $run,
        ScheduleConflictRun $previousReadyRun,
        array $excludedScheduleIds,
        Collection $sourceSchedules,
        Collection $scheduleMap,
        Collection $conflictMap
    ): int {
        return DB::transaction(function () use ($run, $previousReadyRun, $excludedScheduleIds, $sourceSchedules, $scheduleMap, $conflictMap): int {
            $run->scopes()->delete();
            $run->results()->delete();

            $copiedCount = $this->copyUnchangedResults($previousReadyRun, $run, $excludedScheduleIds);
            $computedCount = $this->insertComputedResultsWithScopes($run, $sourceSchedules, $scheduleMap, $conflictMap);

            return $copiedCount + $computedCount;
        });
    }

    /**
     * @param  Collection<int, Schedule>  $sourceSchedules
     * @param  Collection<int, Schedule>  $scheduleMap
     * @param  Collection<int, Collection<int, array{type:string,message:string,schedule_id:int}>>  $conflictMap
     */
    private function insertComputedResultsWithScopes(
        ScheduleConflictRun $run,
        Collection $sourceSchedules,
        Collection $scheduleMap,
        Collection $conflictMap
    ): int {
            $resultRows = [];
            $seen = [];
            $now = now();

            foreach ($sourceSchedules as $sourceSchedule) {
                $conflicts = $conflictMap->get($sourceSchedule->id, collect());

                foreach ($conflicts as $conflict) {
                    $conflictingScheduleId = (int) $conflict['schedule_id'];
                    $candidate = $scheduleMap->get($conflictingScheduleId);
                    $resource = $this->resourceFor($conflict['type'], $sourceSchedule, $candidate, $conflict['message']);
                    $pairKey = $this->pairKey(
                        $conflict['type'],
                        (int) $sourceSchedule->id,
                        $conflictingScheduleId,
                        $resource['type'],
                        $resource['id'],
                        $conflict['message']
                    );
                    $seenKey = $sourceSchedule->id . ':' . $pairKey;

                    if (isset($seen[$seenKey])) {
                        continue;
                    }

                    $seen[$seenKey] = true;

                    $resultRows[] = [
                        'run_id' => $run->id,
                        'academic_year_id' => $this->academicYearId,
                        'schedule_id' => $sourceSchedule->id,
                        'conflicting_schedule_id' => $conflictingScheduleId,
                        'conflict_type' => $conflict['type'],
                        'resource_type' => $resource['type'],
                        'resource_id' => $resource['id'],
                        'message' => mb_substr($conflict['message'], 0, 255),
                        'pair_key' => $pairKey,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            collect($resultRows)
                ->chunk(500)
                ->each(fn (Collection $rows) => ScheduleConflictResult::query()->insert($rows->all()));

            $sourceMap = $sourceSchedules->keyBy('id');
            $scopeRows = ScheduleConflictResult::query()
                ->where('run_id', $run->id)
                ->whereIn('schedule_id', $sourceMap->keys()->all())
                ->get(['id', 'schedule_id'])
                ->flatMap(function (ScheduleConflictResult $result) use ($run, $sourceMap) {
                    $sourceSchedule = $sourceMap->get((int) $result->schedule_id);

                    return $sourceSchedule
                        ? $this->scopeRows($run, (int) $result->id, $sourceSchedule)
                        : [];
                })
                ->values();

            $scopeRows
                ->chunk(500)
                ->each(fn (Collection $rows) => DB::table('schedule_conflict_result_scopes')->insert($rows->all()));

            return count($resultRows);
    }

    /**
     * @param  array<int, int>  $excludedScheduleIds
     */
    private function copyUnchangedResults(
        ScheduleConflictRun $previousReadyRun,
        ScheduleConflictRun $run,
        array $excludedScheduleIds
    ): int {
        $excludedScheduleIds = $this->normalizeScheduleIds($excludedScheduleIds);
        $copiedCount = 0;

        $query = ScheduleConflictResult::query()
            ->where('run_id', $previousReadyRun->id);

        if ($excludedScheduleIds !== []) {
            $query
                ->whereNotIn('schedule_id', $excludedScheduleIds)
                ->whereNotIn('conflicting_schedule_id', $excludedScheduleIds);
        }

        $query
            ->orderBy('id')
            ->chunkById(500, function (Collection $results) use ($run, &$copiedCount): void {
                if ($results->isEmpty()) {
                    return;
                }

                $now = now();
                $oldIds = $results->pluck('id')->map(fn ($id) => (int) $id)->all();
                $resultRows = $results->map(fn (ScheduleConflictResult $result) => [
                    'run_id' => $run->id,
                    'academic_year_id' => $this->academicYearId,
                    'schedule_id' => (int) $result->schedule_id,
                    'conflicting_schedule_id' => (int) $result->conflicting_schedule_id,
                    'conflict_type' => $result->conflict_type,
                    'resource_type' => $result->resource_type,
                    'resource_id' => $result->resource_id,
                    'message' => $result->message,
                    'pair_key' => $result->pair_key,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                ScheduleConflictResult::query()->insert($resultRows);

                $newResults = ScheduleConflictResult::query()
                    ->where('run_id', $run->id)
                    ->whereIn('pair_key', $results->pluck('pair_key')->unique()->values()->all())
                    ->whereIn('schedule_id', $results->pluck('schedule_id')->unique()->values()->all())
                    ->get(['id', 'pair_key', 'schedule_id'])
                    ->keyBy(fn (ScheduleConflictResult $result) => $this->resultIdentityKey(
                        (string) $result->pair_key,
                        (int) $result->schedule_id
                    ));

                $oldToNewResultIds = $results
                    ->mapWithKeys(function (ScheduleConflictResult $result) use ($newResults) {
                        $newResult = $newResults->get($this->resultIdentityKey(
                            (string) $result->pair_key,
                            (int) $result->schedule_id
                        ));

                        return $newResult ? [(int) $result->id => (int) $newResult->id] : [];
                    })
                    ->all();

                $scopeRows = DB::table('schedule_conflict_result_scopes')
                    ->whereIn('result_id', $oldIds)
                    ->orderBy('id')
                    ->get()
                    ->map(function ($scope) use ($run, $oldToNewResultIds, $now) {
                        $newResultId = $oldToNewResultIds[(int) $scope->result_id] ?? null;

                        if (! $newResultId) {
                            return null;
                        }

                        return [
                            'run_id' => $run->id,
                            'result_id' => $newResultId,
                            'academic_year_id' => $this->academicYearId,
                            'scope_type' => $scope->scope_type,
                            'user_id' => $scope->user_id,
                            'role' => $scope->role,
                            'course_offering_id' => $scope->course_offering_id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    })
                    ->filter()
                    ->values();

                $scopeRows
                    ->chunk(500)
                    ->each(fn (Collection $rows) => DB::table('schedule_conflict_result_scopes')->insert($rows->all()));

                $copiedCount += $results->count();
            });

        return $copiedCount;
    }

    private function resultIdentityKey(string $pairKey, int $scheduleId): string
    {
        return $pairKey . ':' . $scheduleId;
    }

    /**
     * @param  mixed  $scheduleIds
     * @return array<int, int>
     */
    private function normalizeScheduleIds(mixed $scheduleIds): array
    {
        if (! is_iterable($scheduleIds)) {
            return [];
        }

        return collect($scheduleIds)
            ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scopeRows(
        ScheduleConflictRun $run,
        int $resultId,
        Schedule $sourceSchedule
    ): array {
        $now = now();
        $courseOfferingId = (int) $sourceSchedule->course_offering_id;
        $coordinatorId = $sourceSchedule->courseOffering?->coordinator_id
            ? (int) $sourceSchedule->courseOffering->coordinator_id
            : null;

        $rows = [
            [
                'run_id' => $run->id,
                'result_id' => $resultId,
                'academic_year_id' => $this->academicYearId,
                'scope_type' => 'admin_global',
                'user_id' => null,
                'role' => 'admin',
                'course_offering_id' => $courseOfferingId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'run_id' => $run->id,
                'result_id' => $resultId,
                'academic_year_id' => $this->academicYearId,
                'scope_type' => 'executive_academic_year',
                'user_id' => null,
                'role' => 'executive',
                'course_offering_id' => $courseOfferingId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        if ($coordinatorId) {
            $rows[] = [
                'run_id' => $run->id,
                'result_id' => $resultId,
                'academic_year_id' => $this->academicYearId,
                'scope_type' => 'course_head_user',
                'user_id' => $coordinatorId,
                'role' => 'course_head',
                'course_offering_id' => $courseOfferingId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    private function cleanupPartialResults(): void
    {
        DB::transaction(function (): void {
            DB::table('schedule_conflict_result_scopes')
                ->where('run_id', $this->runId)
                ->delete();
            DB::table('schedule_conflict_results')
                ->where('run_id', $this->runId)
                ->delete();
        });
    }

    /**
     * @param  Collection<int, Schedule>  $sourceSchedules
     */
    private function flushCourseHeadBadges(Collection $sourceSchedules): void
    {
        $sourceSchedules
            ->map(fn (Schedule $schedule) => $schedule->courseOffering?->coordinator_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->each(fn (int $userId) => NavigationBadgeService::flushCourseHead($userId));
    }

    private function flushCourseHeadBadgesForAcademicYear(): void
    {
        CourseOffering::query()
            ->withActiveCourse()
            ->where('academic_year_id', $this->academicYearId)
            ->pluck('coordinator_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->each(fn (int $userId) => NavigationBadgeService::flushCourseHead($userId));
    }

    /**
     * @return array{type:?string,id:?int}
     */
    private function resourceFor(string $type, Schedule $source, ?Schedule $candidate, string $message): array
    {
        return match ($type) {
            'room_overlap' => [
                'type' => 'room',
                'id' => $source->room_id ? (int) $source->room_id : ($candidate?->room_id ? (int) $candidate->room_id : null),
            ],
            'instructor_overlap' => [
                'type' => 'instructor',
                'id' => $this->sharedInstructorId($source, $candidate, $message),
            ],
            'group_overlap' => [
                'type' => 'student_group',
                'id' => $this->sharedStudentGroupId($source, $candidate, $message),
            ],
            default => [
                'type' => null,
                'id' => null,
            ],
        };
    }

    private function sharedInstructorId(Schedule $source, ?Schedule $candidate, string $message): ?int
    {
        $sourceInstructors = $source->instructors->keyBy('id');
        $candidateIds = $candidate
            ? $candidate->instructors->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $sourceInstructors->keys()->map(fn ($id) => (int) $id)->all();

        foreach ($candidateIds as $id) {
            $instructor = $sourceInstructors->get($id);

            if (! $instructor) {
                continue;
            }

            $names = array_filter([
                (string) ($instructor->formatted_name ?? ''),
                (string) $instructor->name,
            ]);

            foreach ($names as $name) {
                if ($name !== '' && str_contains($message, $name)) {
                    return (int) $id;
                }
            }
        }

        return $sourceInstructors->keys()->map(fn ($id) => (int) $id)->first();
    }

    private function sharedStudentGroupId(Schedule $source, ?Schedule $candidate, string $message): ?int
    {
        $sourceGroups = $source->studentGroups->keyBy('id');
        $candidateIds = $candidate
            ? $candidate->studentGroups->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $sourceGroups->keys()->map(fn ($id) => (int) $id)->all();

        foreach ($candidateIds as $id) {
            $group = $sourceGroups->get($id);

            if ($group && $group->group_code && str_contains($message, (string) $group->group_code)) {
                return (int) $id;
            }
        }

        return $sourceGroups->keys()->map(fn ($id) => (int) $id)->first();
    }

    private function pairKey(
        string $type,
        int $sourceScheduleId,
        int $conflictingScheduleId,
        ?string $resourceType,
        ?int $resourceId,
        string $message
    ): string {
        $left = min($sourceScheduleId, $conflictingScheduleId);
        $right = max($sourceScheduleId, $conflictingScheduleId);
        $resource = ($resourceType ?? 'none') . ':' . ($resourceId ?? 'unknown');

        return implode(':', [
            $type,
            $left,
            $right,
            $resource,
            substr(sha1($message), 0, 12),
        ]);
    }
}
