<?php

namespace App\Services;

use App\Http\Controllers\Admin\AlertController;
use Illuminate\Support\Facades\Cache;

class NavigationBadgeService
{
    private const ADMIN_CACHE_KEY = 'sidebar.badges.admin';
    private const ADMIN_TTL = 120;
    private const COURSE_HEAD_TTL = 120;

    public function __construct(private ScheduleConflictIndex $conflictIndex)
    {
    }

    public function forRole(?string $role, ?int $userId): array
    {
        return match ($role) {
            'admin' => [
                'admin_alert_summary' => $this->adminAlertSummary(),
            ],
            'course_head' => [
                'maker_conflict_count' => $userId ? $this->courseHeadConflictCount($userId) : 0,
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
        return Cache::remember(
            self::courseHeadCacheKey($userId),
            self::COURSE_HEAD_TTL,
            fn () => $this->conflictIndex->countForCoordinator($userId)
        );
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
