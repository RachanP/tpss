<?php

namespace App\Support;

use App\Models\AcademicYear;
use App\Models\CourseOffering;

/**
 * คำนวณ empty-state key สำหรับหน้าฝั่งหัวหน้าวิชา (course_head)
 * ให้สื่อสารชัดเจนว่าอยู่ในสถานะไหน — ไม่ให้ผู้ใช้สับสนคิดว่าระบบ error
 *
 * keys:
 * - 'preparation'  : ระบบยังไม่เปิด scheduling phase ที่ไหนเลย
 * - 'no_offerings' : เปิด scheduling แล้ว แต่ผู้ใช้ไม่มี offering ในรอบนั้น
 * - 'ready'        : มี offering ในรอบ scheduling แล้ว — ใช้แสดงข้อมูลตามปกติ
 */
class CoordinatorEmptyState
{
    public const PREPARATION = 'preparation';
    public const NO_OFFERINGS = 'no_offerings';
    public const READY = 'ready';

    /**
     * @param  int  $userId  หัวหน้าวิชา หรือผู้ที่ถูกมอบหมายให้ช่วยจัดตาราง (อาจารย์/เจ้าหน้าที่)
     *   — ใช้ scopeSchedulableBy เพื่อให้ empty-state/redirect ของ delegated user ตรงกับหัวหน้าวิชา
     */
    public static function forCoordinator(int $userId): string
    {
        $systemHasScheduling = AcademicYear::query()
            ->where('phase', 'scheduling')
            ->exists();

        if (! $systemHasScheduling) {
            return self::PREPARATION;
        }

        $userHasSchedulingOffering = CourseOffering::query()
            ->withActiveCourse()
            ->schedulableBy($userId)
            ->whereHas('academicYear', fn ($q) => $q->where('phase', 'scheduling'))
            ->exists();

        return $userHasSchedulingOffering ? self::READY : self::NO_OFFERINGS;
    }
}
