<?php

namespace App\Services;

use App\Http\Controllers\Admin\AlertController;
use App\Models\AcademicYear;
use App\Models\CourseOffering;
use Illuminate\Support\Facades\Cache;

class NavigationBadgeService
{
    private const ADMIN_CACHE_KEY = 'sidebar.badges.admin';
    private const ADMIN_TTL = 120;
    private const COURSE_HEAD_TTL = 300;

    public function __construct(
        private ScheduleConflictIndex $conflictIndex,
        private ScheduleConflictReadRepository $conflictReadRepository
    ) {
    }

    public function forRole(?string $role, ?int $userId): array
    {
        return match ($role) {
            'admin' => [
                'admin_alert_summary' => $this->adminAlertSummary(),
            ],
            'course_head' => [
                ...($userId ? $this->courseHeadConflictBadge($userId) : $this->emptyCourseHeadBadge()),
            ],
            default => [],
        };
    }

    public function adminAlertSummary(): array
    {
        return Cache::remember(self::ADMIN_CACHE_KEY, self::ADMIN_TTL, fn () => AlertController::getSummary());
    }

    public function courseHeadConflictCount(int $userId): int
    {
        return (int) Cache::get(self::courseHeadCacheKey($userId), 0);
    }

    public function courseHeadConflictBadge(int $userId): array
    {
        if (! config('conflicts.async_reads')) {
            return [
                'maker_conflict_count' => $this->courseHeadConflictCount($userId),
                'maker_conflict_status' => 'ready',
                'maker_conflict_pending' => false,
            ];
        }

        $academicYearId = $this->defaultAcademicYearIdForCourseHead($userId);
        $status = $this->conflictReadRepository->getStatusForUser($userId, $academicYearId);
        $count = $this->conflictReadRepository->getCountForUser($userId, $academicYearId);

        return [
            'maker_conflict_count' => $count,
            'maker_conflict_status' => $status['status'],
            'maker_conflict_pending' => $status['status'] !== 'ready',
            'maker_conflict_academic_year_id' => $academicYearId,
        ];
    }

    public function refreshCourseHeadConflictCount(int $userId, ?int $academicYearId = null): int
    {
        $count = $this->conflictIndex->countForCoordinator($userId, $academicYearId);
        $this->putCourseHeadConflictCount($userId, $count);

        return $count;
    }

    public function putCourseHeadConflictCount(int $userId, int $count): void
    {
        Cache::put(self::courseHeadCacheKey($userId), $count, self::COURSE_HEAD_TTL);
    }

    private function emptyCourseHeadBadge(): array
    {
        return [
            'maker_conflict_count' => 0,
            'maker_conflict_status' => 'missing',
            'maker_conflict_pending' => true,
        ];
    }

    private function defaultAcademicYearIdForCourseHead(int $userId): ?int
    {
        $availableYearIds = CourseOffering::query()
            ->where('coordinator_id', $userId)
            ->pluck('academic_year_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($availableYearIds->isEmpty()) {
            return null;
        }

        $academicYearId = AcademicYear::query()
            ->whereIn('id', $availableYearIds->all())
            ->orderByRaw("CASE WHEN phase = 'scheduling' THEN 0 WHEN is_active = 1 THEN 1 ELSE 2 END")
            ->orderByDesc('start_date')
            ->orderByDesc('semester')
            ->orderByDesc('id')
            ->value('id');

        return $academicYearId ? (int) $academicYearId : null;
    }

    public static function flushAdmin(): void
    {
        Cache::forget(self::ADMIN_CACHE_KEY);
    }

    public static function flushCourseHead(?int $userId = null): void
    {
        if ($userId) {
            Cache::forget(self::courseHeadCacheKey($userId));
        }
    }

    private static function courseHeadCacheKey(int $userId): string
    {
        return "sidebar.badges.course_head.{$userId}";
    }
}
