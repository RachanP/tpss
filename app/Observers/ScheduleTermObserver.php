<?php

namespace App\Observers;

use App\Models\AcademicYear;
use App\Models\CourseOffering;
use App\Models\Schedule;
use App\Services\AcademicCalendar;

/**
 * V2: ติดป้าย term_id ให้ทุก slot อัตโนมัติจาก start_date — ครอบทุกทางสร้าง/แก้
 * (store, update, storeSeries, copyWeek) โดยไม่ต้องไปแก้ทีละจุด
 *
 * term_id = เทอมที่ช่วงวันคลุมวันเริ่มของ slot · null ถ้าวันตกช่วงปิดภาคเรียน
 * (validation บล็อกการบันทึก slot ที่ตกช่วงปิดภาคเรียน/สัปดาห์สอบแยกต่างหาก)
 *
 * cache calendar ต่อ "ปี" + offering→year ภายใน request (observer = singleton ต่อ request)
 */
class ScheduleTermObserver
{
    /** @var array<int, AcademicCalendar> */
    private array $calendars = [];

    /** @var array<int, int|null> */
    private array $offeringYear = [];

    public function saving(Schedule $schedule): void
    {
        $start = $schedule->start_date ?? $schedule->teaching_date;
        if (! $start || ! $schedule->course_offering_id) {
            return;
        }

        $yearId = $this->yearIdForOffering((int) $schedule->course_offering_id);
        if (! $yearId) {
            $schedule->term_id = null;
            return;
        }

        $schedule->term_id = $this->calendarForYear($yearId)->termIdForDate($start);
    }

    private function yearIdForOffering(int $offeringId): ?int
    {
        if (! array_key_exists($offeringId, $this->offeringYear)) {
            $this->offeringYear[$offeringId] = CourseOffering::whereKey($offeringId)->value('academic_year_id');
        }

        return $this->offeringYear[$offeringId];
    }

    private function calendarForYear(int $yearId): AcademicCalendar
    {
        return $this->calendars[$yearId] ??= AcademicCalendar::forYear(
            AcademicYear::with('terms')->find($yearId)
        );
    }
}
