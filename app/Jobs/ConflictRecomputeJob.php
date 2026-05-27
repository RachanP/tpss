<?php

namespace App\Jobs;

use App\Models\Schedule;
use App\Models\ScheduleConflictResult;
use App\Models\ScheduleConflictRun;
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
            $sourceSchedules = $this->sourceSchedules();
            $conflictMap = $conflictIndex->conflictsFor($sourceSchedules);
            $scheduleMap = $this->scheduleMapWithCandidates($sourceSchedules, $conflictMap);
            $resultCount = $this->storeResults($run, $sourceSchedules, $scheduleMap, $conflictMap);

            $run->forceFill([
                'status' => 'ready',
                'finished_at' => now(),
                'result_count' => $resultCount,
            ])->save();
            $invalidation->clearDirty($this->academicYearId);
        } catch (Throwable $exception) {
            ScheduleConflictRun::query()
                ->whereKey($this->runId)
                ->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'error_message' => $exception->getMessage(),
                ]);
            $invalidation->clearDirty($this->academicYearId);

            throw $exception;
        }
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
            ->whereHas('courseOffering');
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

            $resultCount = 0;
            $seen = [];

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

                    $result = ScheduleConflictResult::query()->create([
                        'run_id' => $run->id,
                        'academic_year_id' => $this->academicYearId,
                        'schedule_id' => $sourceSchedule->id,
                        'conflicting_schedule_id' => $conflictingScheduleId,
                        'conflict_type' => $conflict['type'],
                        'resource_type' => $resource['type'],
                        'resource_id' => $resource['id'],
                        'message' => mb_substr($conflict['message'], 0, 255),
                        'pair_key' => $pairKey,
                    ]);

                    $result->scopes()->createMany($this->scopeRows($run, $result, $sourceSchedule));
                    $resultCount++;
                }
            }

            return $resultCount;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scopeRows(
        ScheduleConflictRun $run,
        ScheduleConflictResult $result,
        Schedule $sourceSchedule
    ): array {
        $courseOfferingId = (int) $sourceSchedule->course_offering_id;
        $coordinatorId = $sourceSchedule->courseOffering?->coordinator_id
            ? (int) $sourceSchedule->courseOffering->coordinator_id
            : null;

        $rows = [
            [
                'run_id' => $run->id,
                'academic_year_id' => $this->academicYearId,
                'scope_type' => 'admin_global',
                'user_id' => null,
                'role' => 'admin',
                'course_offering_id' => $courseOfferingId,
            ],
            [
                'run_id' => $run->id,
                'academic_year_id' => $this->academicYearId,
                'scope_type' => 'executive_academic_year',
                'user_id' => null,
                'role' => 'executive',
                'course_offering_id' => $courseOfferingId,
            ],
        ];

        if ($coordinatorId) {
            $rows[] = [
                'run_id' => $run->id,
                'academic_year_id' => $this->academicYearId,
                'scope_type' => 'course_head_user',
                'user_id' => $coordinatorId,
                'role' => 'course_head',
                'course_offering_id' => $courseOfferingId,
            ];
        }

        return $rows;
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
