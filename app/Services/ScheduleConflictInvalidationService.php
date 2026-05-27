<?php

namespace App\Services;

use App\Jobs\ConflictRecomputeJob;
use App\Models\CourseOffering;
use App\Models\Schedule;
use App\Models\ScheduleConflictRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ScheduleConflictInvalidationService
{
    private const DEBOUNCE_SECONDS = 45;
    private const DIRTY_TTL_SECONDS = 300;

    public function markDirty(?int $academicYearId, string $source = 'observer'): void
    {
        if (! config('conflicts.async_reads')) {
            return;
        }

        if (! $academicYearId) {
            return;
        }

        Cache::put($this->dirtyKey($academicYearId), [
            'academic_year_id' => $academicYearId,
            'source' => $source,
            'dirty_at' => now()->toISOString(),
            'dispatch_after' => now()->addSeconds(self::DEBOUNCE_SECONDS)->toISOString(),
        ], self::DIRTY_TTL_SECONDS);

        $this->dispatchIfDirty($academicYearId, $source);
    }

    /**
     * @param  iterable<int|null>  $academicYearIds
     */
    public function markDirtyMany(iterable $academicYearIds, string $source = 'bulk_import'): void
    {
        collect($academicYearIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->each(fn (int $academicYearId) => $this->markDirty($academicYearId, $source));
    }

    /**
     * @param  iterable<int, Schedule|int>  $schedules
     */
    public function markImportedSchedulesDirty(iterable $schedules): void
    {
        $scheduleIds = [];
        $courseOfferingIds = [];
        $academicYearIds = [];

        foreach ($schedules as $schedule) {
            if ($schedule instanceof Schedule) {
                if ($schedule->courseOffering?->academic_year_id) {
                    $academicYearIds[] = (int) $schedule->courseOffering->academic_year_id;
                } elseif ($schedule->course_offering_id) {
                    $courseOfferingIds[] = (int) $schedule->course_offering_id;
                }

                continue;
            }

            if (is_numeric($schedule)) {
                $scheduleIds[] = (int) $schedule;
            }
        }

        if ($scheduleIds) {
            $courseOfferingIds = array_merge(
                $courseOfferingIds,
                Schedule::query()
                    ->whereIn('id', array_unique($scheduleIds))
                    ->pluck('course_offering_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->all()
            );
        }

        if ($courseOfferingIds) {
            $academicYearIds = array_merge(
                $academicYearIds,
                CourseOffering::query()
                    ->whereIn('id', array_unique($courseOfferingIds))
                    ->pluck('academic_year_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->all()
            );
        }

        $this->markDirtyMany($academicYearIds, 'bulk_import');
    }

    public function markScheduleDirty(Schedule $schedule, string $source = 'observer'): void
    {
        $academicYearId = $schedule->courseOffering?->academic_year_id
            ?: CourseOffering::query()
                ->whereKey($schedule->course_offering_id)
                ->value('academic_year_id');

        $this->markDirty($academicYearId ? (int) $academicYearId : null, $source);
    }

    public function dispatchIfDirty(int $academicYearId, string $source = 'observer'): void
    {
        if (! config('conflicts.async_reads') || ! Cache::has($this->dirtyKey($academicYearId))) {
            return;
        }

        $lock = Cache::lock($this->dispatchLockKey($academicYearId), 10);

        if (! $lock->get()) {
            return;
        }

        try {
            if (Cache::has($this->scheduledKey($academicYearId))) {
                return;
            }

            $run = $this->createPendingRun($academicYearId, $source);
            Cache::put($this->scheduledKey($academicYearId), $run->id, self::DIRTY_TTL_SECONDS);

            ConflictRecomputeJob::dispatch(
                (int) $academicYearId,
                (int) $run->id,
                (int) $run->generation,
                $source
            )->delay(now()->addSeconds(self::DEBOUNCE_SECONDS));
        } finally {
            optional($lock)->release();
        }
    }

    public function clearDirty(int $academicYearId): void
    {
        Cache::forget($this->dirtyKey($academicYearId));
        Cache::forget($this->scheduledKey($academicYearId));
    }

    private function createPendingRun(int $academicYearId, string $source): ScheduleConflictRun
    {
        return DB::transaction(function () use ($academicYearId, $source): ScheduleConflictRun {
            $latestGeneration = (int) ScheduleConflictRun::query()
                ->where('academic_year_id', $academicYearId)
                ->lockForUpdate()
                ->max('generation');

            return ScheduleConflictRun::query()->create([
                'academic_year_id' => $academicYearId,
                'status' => 'pending',
                'generation' => $latestGeneration + 1,
                'source' => $this->normalizedSource($source),
                'requested_at' => now(),
                'result_count' => 0,
            ]);
        });
    }

    private function normalizedSource(string $source): string
    {
        return in_array($source, ['observer', 'pivot', 'manual', 'scheduled', 'bulk_import'], true)
            ? $source
            : 'observer';
    }

    private function dirtyKey(int $academicYearId): string
    {
        return "conflicts:dirty:academic_year:{$academicYearId}";
    }

    private function scheduledKey(int $academicYearId): string
    {
        return "conflicts:scheduled:academic_year:{$academicYearId}";
    }

    private function dispatchLockKey(int $academicYearId): string
    {
        return "conflicts:dispatch-lock:academic_year:{$academicYearId}";
    }
}
