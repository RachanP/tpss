<?php

namespace App\Services;

use App\Models\CourseOffering;
use App\Models\Schedule;
use App\Models\ScheduleConflictResult;
use App\Models\ScheduleConflictRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ScheduleConflictReadRepository
{
    private const CACHE_TTL_SECONDS = 120;
    private const PAGE_SIZE = 50;

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
        $status = $this->getStatusForUser($userId, $academicYearId);

        if ($status['status'] !== 'ready' || ! $status['run_id'] || ! $status['generation']) {
            return null;
        }

        return Cache::remember(
            $this->badgeCacheKey($userId, (int) $academicYearId, $status['generation']),
            self::CACHE_TTL_SECONDS,
            fn () => $this->scopedResultQuery((int) $status['run_id'], $userId, (int) $academicYearId)
                ->count()
        );
    }

    public function getPageForUser(int $userId, ?int $academicYearId, int $page): LengthAwarePaginator
    {
        $status = $this->getStatusForUser($userId, $academicYearId);

        if ($status['status'] !== 'ready' || ! $status['run_id'] || ! $academicYearId) {
            return new Paginator(collect(), 0, self::PAGE_SIZE, max(1, $page));
        }

        $paginator = $this->scopedResultQuery((int) $status['run_id'], $userId, (int) $academicYearId)
            ->orderBy('schedule_conflict_results.schedule_id')
            ->orderBy('schedule_conflict_results.conflict_type')
            ->paginate(self::PAGE_SIZE, ['schedule_conflict_results.*'], 'page', max(1, $page));

        $this->loadSchedulesForPaginator($paginator);

        return $paginator;
    }

    /**
     * @return array{status:string,generation:?int,total:?int,by_type:array<string,int>}
     */
    public function getGlobalSummary(?int $academicYearId): array
    {
        abort_unless(auth()->check() && session('active_role') === 'admin', 403);

        $run = $academicYearId ? $this->latestRun($academicYearId) : null;

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

        $run = $academicYearId ? $this->latestRun($academicYearId) : null;

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
                'studentGroups:id,course_offering_id,group_code,color_code',
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
