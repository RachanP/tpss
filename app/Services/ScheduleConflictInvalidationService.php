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
    private const DEBOUNCE_SECONDS = 10;
    private const DIRTY_TTL_SECONDS = 300;

    /**
     * @param  array<int, int>|null  $scheduleIds  null means the whole academic year is dirty.
     */
    public function markDirty(?int $academicYearId, string $source = 'observer', ?array $scheduleIds = null): void
    {
        if (! config('conflicts.async_reads')) {
            return;
        }

        if (! $academicYearId) {
            return;
        }

        $existing = Cache::get($this->dirtyKey($academicYearId), []);
        $normalizedScheduleIds = $this->normalizeScheduleIds($scheduleIds);
        $existingIsFull = is_array($existing)
            && $existing !== []
            && (($existing['full'] ?? false) || ! array_key_exists('schedule_ids', $existing));
        $isFull = $scheduleIds === null || $existingIsFull;
        $mergedScheduleIds = $isFull
            ? null
            : collect($existing['schedule_ids'] ?? [])
                ->merge($normalizedScheduleIds)
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();

        Cache::put($this->dirtyKey($academicYearId), [
            'academic_year_id' => $academicYearId,
            'source' => $source,
            'full' => $isFull,
            'schedule_ids' => $mergedScheduleIds,
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

        $this->markDirty(
            $academicYearId ? (int) $academicYearId : null,
            $source,
            $schedule->getKey() ? [(int) $schedule->getKey()] : null
        );
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
            $dirtyScope = $this->dirtyScope($academicYearId);
            $scheduledRunId = Cache::get($this->scheduledKey($academicYearId));

            if ($scheduledRunId) {
                $this->mergePendingRunScope((int) $scheduledRunId, $dirtyScope);
                return;
            }

            $run = $this->createPendingRun($academicYearId, $source, $dirtyScope);
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

    /**
     * @return array{full:bool,schedule_ids:array<int, int>}
     */
    public function dirtyScope(int $academicYearId): array
    {
        $dirty = Cache::get($this->dirtyKey($academicYearId));

        if (! is_array($dirty)) {
            return ['full' => false, 'schedule_ids' => []];
        }

        if (($dirty['full'] ?? false) || ! array_key_exists('schedule_ids', $dirty)) {
            return ['full' => true, 'schedule_ids' => []];
        }

        return [
            'full' => false,
            'schedule_ids' => $this->normalizeScheduleIds($dirty['schedule_ids'] ?? []),
        ];
    }

    public function clearDirty(int $academicYearId): void
    {
        Cache::forget($this->dirtyKey($academicYearId));
        Cache::forget($this->scheduledKey($academicYearId));
    }

    /**
     * @param  array{full:bool,schedule_ids:array<int, int>}  $dirtyScope
     */
    private function createPendingRun(int $academicYearId, string $source, array $dirtyScope): ScheduleConflictRun
    {
        return DB::transaction(function () use ($academicYearId, $source, $dirtyScope): ScheduleConflictRun {
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
                'metadata' => $this->runMetadataForScope($dirtyScope),
            ]);
        });
    }

    /**
     * @param  array{full:bool,schedule_ids:array<int, int>}  $dirtyScope
     */
    private function mergePendingRunScope(int $runId, array $dirtyScope): void
    {
        $run = ScheduleConflictRun::query()
            ->whereKey($runId)
            ->where('status', 'pending')
            ->first();

        if (! $run) {
            return;
        }

        if ($dirtyScope['full']) {
            $run->forceFill(['metadata' => null])->save();
            return;
        }

        $existingIds = $this->normalizeScheduleIds($run->metadata['affected_schedule_ids'] ?? []);
        $scheduleIds = collect($existingIds)
            ->merge($dirtyScope['schedule_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $run->forceFill([
            'metadata' => $scheduleIds === []
                ? null
                : ['affected_schedule_ids' => $scheduleIds],
        ])->save();
    }

    /**
     * @param  array{full:bool,schedule_ids:array<int, int>}  $dirtyScope
     * @return array<string, mixed>|null
     */
    private function runMetadataForScope(array $dirtyScope): ?array
    {
        if ($dirtyScope['full'] || $dirtyScope['schedule_ids'] === []) {
            return null;
        }

        return ['affected_schedule_ids' => $dirtyScope['schedule_ids']];
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
