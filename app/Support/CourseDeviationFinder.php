<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseOffering;
use Illuminate\Support\Collection;

/**
 * หาวิชาที่ "ผู้สอน/รายละเอียดใน offering ต่างจากแม่แบบ (template)"
 * ใช้ร่วมระหว่างหน้าแจ้งเตือน (section รายงาน) และ badge สรุป
 */
class CourseDeviationFinder
{
    /**
     * @return Collection<int,Course> วิชาที่มี deviation (eager-load สำหรับแสดงผล)
     */
    public static function coursesWithDeviation(): Collection
    {
        $courses = Course::with([
            'instructors.instructorProfile.department',
            'curriculum',
            'department',
        ])
            ->whereHas('courseOfferings', fn ($q) => $q->whereHas(
                'academicYear',
                fn ($y) => $y->whereIn('phase', ['scheduling', 'published'])
            ))
            ->orderBy('course_code')
            ->get();

        if ($courses->isEmpty()) {
            return collect();
        }

        $offerings = CourseOffering::with(['instructorPool', 'academicYear'])
            ->whereIn('course_id', $courses->pluck('id'))
            ->whereHas('academicYear', fn ($q) => $q->whereIn('phase', ['scheduling', 'published']))
            ->get()
            ->groupBy('course_id');

        return $courses->filter(function (Course $course) use ($offerings) {
            foreach ($offerings[$course->id] ?? [] as $offering) {
                $instructorDiff = $course->instructorPoolDeviationFor($offering);
                $detailsDiff    = $course->offeringDetailsDeviationFor($offering);
                $hasAny = count($instructorDiff['added']) + count($instructorDiff['removed'])
                    + count($instructorDiff['role_changed']) + count($detailsDiff);
                if ($hasAny > 0) {
                    return true;
                }
            }
            return false;
        })->values();
    }
}
