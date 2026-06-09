<?php

namespace App\Services;

use App\Http\Controllers\Admin\AlertController;
use App\Models\CourseOffering;
use Illuminate\Support\Facades\Cache;

class NavigationBadgeService
{
    private const ADMIN_CACHE_KEY = 'sidebar.badges.admin';
    private const ADMIN_TTL = 120;
    private const COURSE_HEAD_TTL = 300;
    private const ASYNC_READY_TTL = 90;
    private const ASYNC_PENDING_TTL = 10;

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
            $yearId = $this->schedulingAcademicYearIdForCourseHead($userId);
            $total = $this->courseHeadConflictCount($userId)
                + ($yearId ? app(CoordinatorAlertService::class)->warningCount($userId, $yearId) : 0);

            return [
                'maker_conflict_count' => $total,
                'maker_conflict_status' => 'ready',
                'maker_conflict_pending' => false,
                'maker_conflict_label' => null,
            ];
        }

        $academicYearId = $this->schedulingAcademicYearIdForCourseHead($userId);

        if (! $academicYearId) {
            Cache::forget(self::courseHeadAsyncCacheKey($userId));

            return $this->idleCourseHeadBadge(null);
        }

        $cached = Cache::get(self::courseHeadAsyncCacheKey($userId));

        if (is_array($cached)) {
            return $cached;
        }

        $badge = $this->uncachedAsyncCourseHeadConflictBadge($userId, $academicYearId);
        Cache::put(
            self::courseHeadAsyncCacheKey($userId),
            $badge,
            ($badge['maker_conflict_pending'] ?? true) ? self::ASYNC_PENDING_TTL : self::ASYNC_READY_TTL
        );

        return $badge;
    }

    /**
     * @return array{status:string,count:?int,pending:bool,label:?string,poll:bool}
     */
    public function courseHeadConflictBadgeStatusJson(int $userId): array
    {
        $badge = $this->courseHeadConflictBadge($userId);
        $status = (string) ($badge['maker_conflict_status'] ?? 'missing');
        $count = $badge['maker_conflict_count'] ?? null;

        return [
            'status' => $status,
            'count' => is_numeric($count) ? (int) $count : null,
            'pending' => (bool) ($badge['maker_conflict_pending'] ?? false),
            'label' => $badge['maker_conflict_label'] ?? null,
            'poll' => in_array($status, ['missing', 'pending', 'processing'], true),
        ];
    }

    private function uncachedAsyncCourseHeadConflictBadge(int $userId, int $academicYearId): array
    {
        $status = $this->conflictReadRepository->getStatusForUser($userId, $academicYearId);

        // เลขรวมใน badge = การชน + warning (incomplete/holiday/ฯลฯ) ให้ตรงกับยอดรวมในหน้าแจ้งเตือน
        // การชนนับเป็น distinct schedule (1 การ์ด/1 schedule) ไม่ใช่ edge — ให้ตรงกับ totalWarningCount
        $warningCount = app(CoordinatorAlertService::class)->warningCount($userId, $academicYearId);

        // read model พร้อมแล้ว → ใช้ค่าที่ pre-compute (เร็ว + scale ดี)
        if ($status['status'] === 'ready') {
            $count = (int) $this->conflictReadRepository->getDistinctScheduleCountForUser($userId, $academicYearId)
                + $warningCount;

            return [
                'maker_conflict_count' => $count,
                'maker_conflict_status' => 'ready',
                'maker_conflict_pending' => false,
                'maker_conflict_academic_year_id' => $academicYearId,
                'maker_conflict_label' => $this->courseHeadConflictLabel('ready', $count),
            ];
        }

        // read model ยังไม่พร้อม (missing/pending/processing/failed) — เดิม badge จะว่าง
        // จนกว่า recompute job จะเสร็จ ทำให้ขึ้น "ช้ามาก" (โดยเฉพาะถ้า queue worker ไม่ทำงาน)
        // แก้: คำนวณ sync เฉพาะ coordinator คนนี้ (engine เดียวกับหน้าแจ้งเตือน, ต้นทุนน้อย)
        // ให้ badge ขึ้นทันที + ตรงกับหน้าแจ้งเตือน · พร้อมสั่ง recompute เบื้องหลังไว้ให้รอบหน้าใช้ async
        if ($status['status'] === 'missing') {
            app(ScheduleConflictInvalidationService::class)->markDirty($academicYearId, 'manual');
        }

        $count = $this->conflictIndex->countForCoordinator($userId, $academicYearId) + $warningCount;

        return [
            'maker_conflict_count' => $count,
            'maker_conflict_status' => 'ready',
            'maker_conflict_pending' => false,
            'maker_conflict_academic_year_id' => $academicYearId,
            'maker_conflict_label' => $this->courseHeadConflictLabel('ready', $count),
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
            'maker_conflict_count' => null,
            'maker_conflict_status' => 'missing',
            'maker_conflict_pending' => true,
            'maker_conflict_label' => null,
        ];
    }

    private function courseHeadConflictLabel(string $status, ?int $count): ?string
    {
        if ($status === 'ready') {
            return $count && $count > 0 ? (string) $count : null;
        }

        if ($status === 'failed') {
            return 'ตรวจสอบไม่สำเร็จ';
        }

        return null;
    }

    private function idleCourseHeadBadge(?int $academicYearId): array
    {
        return [
            'maker_conflict_count' => null,
            'maker_conflict_status' => 'idle',
            'maker_conflict_pending' => false,
            'maker_conflict_academic_year_id' => $academicYearId,
            'maker_conflict_label' => null,
        ];
    }

    private function schedulingAcademicYearIdForCourseHead(int $userId): ?int
    {
        $academicYearId = CourseOffering::query()
            ->withActiveCourse()
            ->where('coordinator_id', $userId)
            ->whereHas('academicYear', fn ($query) => $query->where('phase', 'scheduling'))
            ->join('academic_years', 'academic_years.id', '=', 'course_offerings.academic_year_id')
            ->orderByDesc('academic_years.start_date')
            ->orderByDesc('academic_years.id')
            ->value('academic_years.id');

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
            Cache::forget(self::courseHeadAsyncCacheKey($userId));
        }
    }

    private static function courseHeadCacheKey(int $userId): string
    {
        return "sidebar.badges.course_head.{$userId}";
    }

    private static function courseHeadAsyncCacheKey(int $userId): string
    {
        return "sidebar.badges.course_head.async.{$userId}";
    }
}
