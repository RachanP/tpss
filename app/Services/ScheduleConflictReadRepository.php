<?php

namespace App\Services;

use App\Models\CourseOffering;
use App\Models\Schedule;
use App\Models\ScheduleConflictResult;
use App\Models\ScheduleConflictRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ScheduleConflictReadRepository
{
    private const CACHE_TTL_SECONDS = 120;
    private const PAGE_SIZE = 50;
    private const SUMMARY_PAGE_SIZE = 25;
    private const PREVIEW_SIZE = 3;

    /**
     * @return array{status:string,generation:?int,run_id:?int,result_count:?int,updated_at:mixed,has_scope:bool}
     */
    public function getStatusForUser(int $userId, ?int $academicYearId): array
    {
        if (! $academicYearId || ! $this->userHasAcademicYearScope($userId, $academicYearId)) {
            return $this->missingStatus();
        }

        $run = $this->latestRun($academicYearId);

        if (! $run) {
            return $this->missingStatus();
        }

        return [
            'status' => $run->status,
            'generation' => (int) $run->generation,
            'run_id' => (int) $run->id,
            'result_count' => (int) $run->result_count,
            'updated_at' => $run->finished_at ?? $run->started_at ?? $run->requested_at,
            'has_scope' => true,
        ];
    }

    public function getCountForUser(int $userId, ?int $academicYearId): ?int
    {
        if (! $academicYearId || ! $this->userHasAcademicYearScope($userId, $academicYearId)) {
            return null;
        }

        $run = $this->latestReadyRun((int) $academicYearId);

        if (! $run) {
            return null;
        }

        return Cache::remember(
            $this->badgeCacheKey($userId, (int) $academicYearId, (int) $run->generation),
            self::CACHE_TTL_SECONDS,
            fn () => $this->scopedResultQuery((int) $run->id, $userId, (int) $academicYearId)
                ->count()
        );
    }

    /**
     * Total conflict count for a course_head user, broken down by conflict_type.
     *
     * @return array<string, int>  e.g. ['instructor_overlap' => 30, 'room_overlap' => 25, 'group_overlap' => 22]
     */
    public function getTypeCountsForUser(int $userId, ?int $academicYearId): array
    {
        if (! $academicYearId || ! $this->userHasAcademicYearScope($userId, $academicYearId)) {
            return [];
        }

        $run = $this->latestReadyRun((int) $academicYearId);

        if (! $run) {
            return [];
        }

        return $this->scopedResultQuery((int) $run->id, $userId, (int) $academicYearId)
            ->select('schedule_conflict_results.conflict_type', DB::raw('count(*) as total'))
            ->groupBy('schedule_conflict_results.conflict_type')
            ->pluck('total', 'conflict_type')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    public function getPageForUser(int $userId, ?int $academicYearId, int $page): LengthAwarePaginator
    {
        if (! $academicYearId || ! $this->userHasAcademicYearScope($userId, $academicYearId)) {
            return new Paginator(collect(), 0, self::PAGE_SIZE, max(1, $page));
        }

        $run = $this->latestReadyRun((int) $academicYearId);

        if (! $run) {
            return new Paginator(collect(), 0, self::PAGE_SIZE, max(1, $page));
        }

        $paginator = $this->scopedResultQuery((int) $run->id, $userId, (int) $academicYearId)
            ->orderBy('schedule_conflict_results.schedule_id')
            ->orderBy('schedule_conflict_results.conflict_type')
            ->paginate(self::PAGE_SIZE, ['schedule_conflict_results.*'], 'page', max(1, $page));

        $this->loadSchedulesForPaginator($paginator);

        return $paginator;
    }

    /**
     * Return a conflict map (keyed by schedule ID) from the pre-computed read model.
     * Drop-in replacement for ScheduleConflictIndex::conflictsFor() on the schedules page.
     *
     * @param  array<int, int>  $scheduleIds
     * @return Collection<int, Collection<int, array{type:string,message:string,schedule_id:int,schedule_label:string,resource_label:string}>>
     */
    public function getConflictMapForSchedules(array $scheduleIds, int $userId, int $academicYearId): Collection
    {
        if (empty($scheduleIds) || ! $academicYearId) {
            return collect();
        }

        $run = $this->latestReadyRun($academicYearId);

        if (! $run) {
            return collect();
        }

        $results = $this->scopedResultQuery((int) $run->id, $userId, $academicYearId)
            ->whereIn('schedule_conflict_results.schedule_id', $scheduleIds)
            ->orderBy('schedule_conflict_results.schedule_id')
            ->orderBy('schedule_conflict_results.id')
            ->get();

        if ($results->isEmpty()) {
            return collect();
        }

        $this->loadSchedulesForResults($results);

        return $results
            ->groupBy(fn (ScheduleConflictResult $result) => (int) $result->schedule_id)
            ->map(fn (Collection $items) => $items
                ->map(fn (ScheduleConflictResult $result) => $this->displayConflict($result))
                ->values()
            );
    }

    public function getScheduleSummaryPageForUser(
        int $userId,
        ?int $academicYearId,
        int $page,
        int $perPage = self::SUMMARY_PAGE_SIZE
    ): LengthAwarePaginator {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, self::SUMMARY_PAGE_SIZE));

        if (! $academicYearId || ! $this->userHasAcademicYearScope($userId, $academicYearId)) {
            return new Paginator(collect(), 0, $perPage, $page);
        }

        $run = $this->latestReadyRun((int) $academicYearId);

        if (! $run) {
            return new Paginator(collect(), 0, $perPage, $page);
        }

        $paginator = $this->scopedResultQuery((int) $run->id, $userId, (int) $academicYearId)
            ->select([
                'schedule_conflict_results.schedule_id',
                DB::raw('MIN(schedule_conflict_results.id) as first_result_id'),
                DB::raw('COUNT(*) as conflict_count'),
            ])
            ->groupBy('schedule_conflict_results.schedule_id')
            ->orderBy('first_result_id')
            ->paginate($perPage, ['*'], 'page', $page);

        $summaries = $this->buildScheduleSummaries(
            $paginator->getCollection(),
            (int) $run->id,
            $userId,
            (int) $academicYearId
        );

        $paginator->setCollection($summaries);

        return $paginator;
    }

    /**
     * @return Collection<int, array{type:string,message:string,schedule_id:int,schedule_label:string,resource_label:string}>
     */
    public function getDetailsForSchedule(
        Schedule $schedule,
        int $userId,
        int $academicYearId,
        string $role
    ): Collection {
        $run = $this->latestReadyRun($academicYearId);

        if (! $run) {
            return collect();
        }

        $query = $role === 'admin'
            ? $this->adminScopedResultQuery((int) $run->id, $academicYearId)
            : $this->scopedResultQuery((int) $run->id, $userId, $academicYearId);

        $results = $query
            ->where('schedule_conflict_results.schedule_id', $schedule->id)
            ->orderBy('schedule_conflict_results.id')
            ->get();

        $this->loadSchedulesForResults($results);

        return $results
            ->map(fn (ScheduleConflictResult $result) => $this->displayConflict($result))
            ->values();
    }

    public function canReadScheduleDetails(
        Schedule $schedule,
        int $userId,
        int $academicYearId,
        string $role
    ): bool {
        $run = $this->latestReadyRun($academicYearId);

        if (! $run) {
            return false;
        }

        $query = $role === 'admin'
            ? $this->adminScopedResultQuery((int) $run->id, $academicYearId)
            : $this->scopedResultQuery((int) $run->id, $userId, $academicYearId);

        return $query
            ->where('schedule_conflict_results.schedule_id', $schedule->id)
            ->exists();
    }

    /**
     * @return array{status:string,generation:?int,total:?int,by_type:array<string,int>}
     */
    public function getGlobalSummary(?int $academicYearId): array
    {
        abort_unless(auth()->check() && session('active_role') === 'admin', 403);

        $run = $academicYearId ? $this->latestReadyRun($academicYearId) : null;

        if (! $run || $run->status !== 'ready') {
            return [
                'status' => $run?->status ?? 'missing',
                'generation' => $run?->generation ? (int) $run->generation : null,
                'total' => null,
                'by_type' => [],
            ];
        }

        $rows = ScheduleConflictResult::query()
            ->select('schedule_conflict_results.conflict_type', DB::raw('count(*) as total'))
            ->join('schedule_conflict_result_scopes as scopes', 'scopes.result_id', '=', 'schedule_conflict_results.id')
            ->where('schedule_conflict_results.run_id', $run->id)
            ->where('schedule_conflict_results.academic_year_id', $academicYearId)
            ->where('scopes.run_id', $run->id)
            ->where('scopes.academic_year_id', $academicYearId)
            ->where('scopes.scope_type', 'admin_global')
            ->groupBy('schedule_conflict_results.conflict_type')
            ->pluck('total', 'conflict_type')
            ->map(fn ($count) => (int) $count)
            ->all();

        return [
            'status' => 'ready',
            'generation' => (int) $run->generation,
            'total' => array_sum($rows),
            'by_type' => $rows,
        ];
    }

    /**
     * @return array{status:string,generation:?int,total:?int,by_type:array<string,int>}
     */
    public function getExecutiveSummary(?int $academicYearId): array
    {
        abort_unless(auth()->check() && session('active_role') === 'executive', 403);

        $run = $academicYearId ? $this->latestReadyRun($academicYearId) : null;

        if (! $run || $run->status !== 'ready') {
            return [
                'status' => $run?->status ?? 'missing',
                'generation' => $run?->generation ? (int) $run->generation : null,
                'total' => null,
                'by_type' => [],
            ];
        }

        $rows = ScheduleConflictResult::query()
            ->select('schedule_conflict_results.conflict_type', DB::raw('count(*) as total'))
            ->join('schedule_conflict_result_scopes as scopes', 'scopes.result_id', '=', 'schedule_conflict_results.id')
            ->where('schedule_conflict_results.run_id', $run->id)
            ->where('schedule_conflict_results.academic_year_id', $academicYearId)
            ->where('scopes.run_id', $run->id)
            ->where('scopes.academic_year_id', $academicYearId)
            ->where('scopes.scope_type', 'executive_academic_year')
            ->groupBy('schedule_conflict_results.conflict_type')
            ->pluck('total', 'conflict_type')
            ->map(fn ($count) => (int) $count)
            ->all();

        return [
            'status' => 'ready',
            'generation' => (int) $run->generation,
            'total' => array_sum($rows),
            'by_type' => $rows,
        ];
    }

    private function latestRun(int $academicYearId): ?ScheduleConflictRun
    {
        return ScheduleConflictRun::query()
            ->where('academic_year_id', $academicYearId)
            ->orderByDesc('generation')
            ->first();
    }

    private function latestReadyRun(int $academicYearId): ?ScheduleConflictRun
    {
        return ScheduleConflictRun::query()
            ->where('academic_year_id', $academicYearId)
            ->where('status', 'ready')
            ->orderByDesc('generation')
            ->first();
    }

    /**
     * @return array{status:string,generation:?int,run_id:?int,result_count:?int,updated_at:null,has_scope:bool}
     */
    private function missingStatus(): array
    {
        return [
            'status' => 'missing',
            'generation' => null,
            'run_id' => null,
            'result_count' => null,
            'updated_at' => null,
            'has_scope' => false,
        ];
    }

    private function userHasAcademicYearScope(int $userId, int $academicYearId): bool
    {
        return CourseOffering::query()
            ->withActiveCourse()
            ->where('coordinator_id', $userId)
            ->where('academic_year_id', $academicYearId)
            ->exists();
    }

    private function scopedResultQuery(int $runId, int $userId, int $academicYearId)
    {
        return ScheduleConflictResult::query()
            ->select('schedule_conflict_results.*')
            ->join('schedule_conflict_result_scopes as scopes', 'scopes.result_id', '=', 'schedule_conflict_results.id')
            ->where('schedule_conflict_results.run_id', $runId)
            ->where('schedule_conflict_results.academic_year_id', $academicYearId)
            ->where('scopes.run_id', $runId)
            ->where('scopes.academic_year_id', $academicYearId)
            ->where('scopes.scope_type', 'course_head_user')
            ->where('scopes.user_id', $userId);
    }

    private function adminScopedResultQuery(int $runId, int $academicYearId)
    {
        return ScheduleConflictResult::query()
            ->select('schedule_conflict_results.*')
            ->join('schedule_conflict_result_scopes as scopes', 'scopes.result_id', '=', 'schedule_conflict_results.id')
            ->where('schedule_conflict_results.run_id', $runId)
            ->where('schedule_conflict_results.academic_year_id', $academicYearId)
            ->where('scopes.run_id', $runId)
            ->where('scopes.academic_year_id', $academicYearId)
            ->where('scopes.scope_type', 'admin_global');
    }

    private function buildScheduleSummaries(Collection $rows, int $runId, int $userId, int $academicYearId): Collection
    {
        $scheduleIds = $rows
            ->pluck('schedule_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($scheduleIds->isEmpty()) {
            return collect();
        }

        $schedules = Schedule::query()
            ->with($this->scheduleRelations())
            ->whereIn('id', $scheduleIds->all())
            ->get()
            ->keyBy('id');

        $typeCounts = $this->scopedResultQuery($runId, $userId, $academicYearId)
            ->whereIn('schedule_conflict_results.schedule_id', $scheduleIds->all())
            ->select([
                'schedule_conflict_results.schedule_id',
                'schedule_conflict_results.conflict_type',
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('schedule_conflict_results.schedule_id', 'schedule_conflict_results.conflict_type')
            ->get()
            ->groupBy(fn ($row) => (int) $row->schedule_id)
            ->map(fn (Collection $items) => $items
                ->mapWithKeys(fn ($item) => [$item->conflict_type => (int) $item->total])
                ->all());

        $distinctConflictCounts = $this->scopedResultQuery($runId, $userId, $academicYearId)
            ->whereIn('schedule_conflict_results.schedule_id', $scheduleIds->all())
            ->select([
                'schedule_conflict_results.schedule_id',
                DB::raw('COUNT(DISTINCT schedule_conflict_results.conflicting_schedule_id) as distinct_total'),
            ])
            ->groupBy('schedule_conflict_results.schedule_id')
            ->pluck('distinct_total', 'schedule_id')
            ->map(fn ($count) => (int) $count);

        $previewIds = $this->previewResultIds($runId, $userId, $academicYearId, $scheduleIds->all());
        $previewResults = $previewIds->isEmpty()
            ? collect()
            : ScheduleConflictResult::query()
                ->whereIn('id', $previewIds->all())
                ->orderBy('schedule_id')
                ->orderBy('id')
                ->get();
        $this->loadSchedulesForResults($previewResults);
        $previews = $previewResults
            ->groupBy(fn (ScheduleConflictResult $result) => (int) $result->schedule_id)
            ->map(fn (Collection $items) => $items
                ->map(fn (ScheduleConflictResult $result) => $this->displayConflict($result))
                ->values());

        return $rows->map(function ($row) use ($schedules, $typeCounts, $previews, $distinctConflictCounts) {
            $scheduleId = (int) $row->schedule_id;
            $conflictCount = (int) $row->conflict_count;
            $distinctCount = (int) ($distinctConflictCounts->get($scheduleId) ?? 0);
            $previewItems = $previews->get($scheduleId, collect());
            $previewDistinct = $previewItems->pluck('schedule_id')->unique()->count();

            return [
                'schedule' => $schedules->get($scheduleId),
                'schedule_id' => $scheduleId,
                'conflict_count' => $conflictCount,
                'distinct_conflict_count' => $distinctCount,
                'preview_distinct_count' => $previewDistinct,
                'type_counts' => $typeCounts->get($scheduleId, []),
                'preview_conflicts' => $previewItems,
                'has_more' => $distinctCount > $previewDistinct,
            ];
        })->filter(fn (array $summary) => $summary['schedule'])->values();
    }

    private function previewResultIds(int $runId, int $userId, int $academicYearId, array $scheduleIds): Collection
    {
        if ($scheduleIds === []) {
            return collect();
        }

        // Fetch all conflict records for these source schedules — group by source then pick
        // top N DISTINCT conflicting schedules (not top N records) so preview shows the
        // SAME complete info per card as the full expanded view.
        $rows = $this->scopedResultQuery($runId, $userId, $academicYearId)
            ->whereIn('schedule_conflict_results.schedule_id', $scheduleIds)
            ->orderBy('schedule_conflict_results.schedule_id')
            ->orderBy('schedule_conflict_results.id')
            ->get([
                'schedule_conflict_results.id',
                'schedule_conflict_results.schedule_id',
                'schedule_conflict_results.conflicting_schedule_id',
            ]);

        return $rows
            ->groupBy(fn ($row) => (int) $row->schedule_id)
            ->flatMap(function (Collection $items) {
                $allowedConflictIds = $items
                    ->pluck('conflicting_schedule_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->take(self::PREVIEW_SIZE)
                    ->values()
                    ->all();

                return $items
                    ->filter(fn ($row) => in_array((int) $row->conflicting_schedule_id, $allowedConflictIds, true))
                    ->pluck('id');
            })
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    private function loadSchedulesForResults(Collection $results): void
    {
        $scheduleIds = $results
            ->flatMap(fn (ScheduleConflictResult $result) => [
                (int) $result->schedule_id,
                (int) $result->conflicting_schedule_id,
            ])
            ->unique()
            ->values();

        if ($scheduleIds->isEmpty()) {
            return;
        }

        $schedules = Schedule::query()
            ->with($this->scheduleRelations())
            ->whereIn('id', $scheduleIds->all())
            ->get()
            ->keyBy('id');

        $results->each(function (ScheduleConflictResult $result) use ($schedules): void {
            $result->setRelation('sourceSchedule', $schedules->get((int) $result->schedule_id));
            $result->setRelation('conflictingSchedule', $schedules->get((int) $result->conflicting_schedule_id));
        });
    }

    /**
     * @return array<int, string>
     */
    private function scheduleRelations(): array
    {
        return [
            'activityType:id,name,color_code,category',
            'room:id,room_code,room_name',
            'courseOffering:id,course_id,academic_year_id,coordinator_id',
            'courseOffering.course:id,course_code,name_th,name_en',
            'instructors:id,name,prefix',
            'instructors.instructorProfile:id,user_id,title,academic_degree',
            'studentGroups:id,course_offering_id,cohort_group_id,group_code,color_code',
            'studentGroups.cohortGroup:id,parent_id,code',
            'studentGroups.cohortGroup.parent:id,code',
        ];
    }

    /**
     * @return array{type:string,message:string,schedule_id:int,schedule_label:string,resource_label:string}
     */
    private function displayConflict(ScheduleConflictResult $result): array
    {
        $conflicting = $result->getRelation('conflictingSchedule');

        return [
            'type' => $result->conflict_type,
            'message' => $result->message,
            'schedule_id' => (int) $result->conflicting_schedule_id,
            'schedule_label' => $conflicting ? $this->scheduleLabel($conflicting) : '',
            'resource_label' => $this->resourceLabel($result),
        ];
    }

    private function resourceLabel(ScheduleConflictResult $result): string
    {
        $source = $result->getRelation('sourceSchedule');
        $conflicting = $result->getRelation('conflictingSchedule');
        $resourceId = (int) $result->resource_id;

        return match ($result->resource_type) {
            'room' => $source?->room?->room_name
                ?? $source?->room?->room_code
                ?? $conflicting?->room?->room_name
                ?? $conflicting?->room?->room_code
                ?? '',
            'instructor' => $this->namedRelationLabel($source?->instructors, $resourceId)
                ?: $this->namedRelationLabel($conflicting?->instructors, $resourceId),
            'student_group' => $this->rootGroupLabelFromMessage((string) $result->message)
                ?: $this->groupRelationLabel($source?->studentGroups, $resourceId)
                ?: $this->groupRelationLabel($conflicting?->studentGroups, $resourceId),
            default => '',
        };
    }

    private function rootGroupLabelFromMessage(string $message): string
    {
        if (preg_match('/กลุ่มต้นทาง\s+([^\s,·]+)/u', $message, $matches)) {
            return 'กลุ่มต้นทาง ' . trim($matches[1]);
        }

        return '';
    }

    private function namedRelationLabel(?Collection $items, int $id): string
    {
        $item = $items?->firstWhere('id', $id);

        return $item ? (string) ($item->formatted_name ?? $item->name ?? '') : '';
    }

    private function groupRelationLabel(?Collection $items, int $id): string
    {
        $item = $items?->firstWhere('id', $id);

        return $item ? (string) ($item->group_code ?? '') : '';
    }

    private function scheduleLabel(Schedule $schedule): string
    {
        $course = $schedule->courseOffering?->course;
        $courseLabel = trim(($course?->course_code ?? 'รายวิชา') . ' ' . ($course?->name_th ?? $course?->name_en ?? ''));
        $rawDate = $schedule->start_date ?? $schedule->teaching_date;
        $dateLabel = $rawDate ? \App\Support\ThaiDate::date($rawDate) : '-';
        $timeLabel = substr((string) $schedule->start_time, 0, 5) . '-' . substr((string) $schedule->end_time, 0, 5);

        return "{$courseLabel} ({$dateLabel} {$timeLabel})";
    }

    private function loadSchedulesForPaginator(LengthAwarePaginator $paginator): void
    {
        $items = collect($paginator->items());
        $scheduleIds = $items
            ->flatMap(fn (ScheduleConflictResult $result) => [
                (int) $result->schedule_id,
                (int) $result->conflicting_schedule_id,
            ])
            ->unique()
            ->values();

        if ($scheduleIds->isEmpty()) {
            return;
        }

        $schedules = Schedule::query()
            ->with([
                'activityType:id,name,color_code,category',
                'room:id,room_code,room_name',
                'courseOffering:id,course_id,academic_year_id,coordinator_id',
                'courseOffering.course:id,course_code,name_th,name_en',
                'instructors:id,name,prefix',
                'studentGroups:id,course_offering_id,cohort_group_id,group_code,color_code',
                'studentGroups.cohortGroup:id,parent_id,code',
                'studentGroups.cohortGroup.parent:id,code',
            ])
            ->whereIn('id', $scheduleIds->all())
            ->get()
            ->keyBy('id');

        $paginator->setCollection($items->map(function (ScheduleConflictResult $result) use ($schedules) {
            $result->setRelation('sourceSchedule', $schedules->get((int) $result->schedule_id));
            $result->setRelation('conflictingSchedule', $schedules->get((int) $result->conflicting_schedule_id));

            return $result;
        }));
    }

    private function badgeCacheKey(int $userId, int $academicYearId, int $generation): string
    {
        return "conflicts:badge:course_head:{$userId}:ay:{$academicYearId}:gen:{$generation}";
    }
}
