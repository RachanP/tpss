<?php

namespace App\Services;

use App\Models\Schedule;
use App\Support\ThaiDate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * แหล่งคำนวณ "warning" (ไม่ใช่การชน) ของหัวหน้าวิชา — แหล่งเดียวที่ทั้ง
 * หน้าแจ้งเตือน (CourseHead\ScheduleController::alerts) และ sidebar badge
 * (NavigationBadgeService) ใช้ร่วมกัน เพื่อให้เลขรวมตรงกันเสมอ
 *
 * warning ที่นับ: incomplete (ข้อมูลไม่ครบ), no_role (ไม่กำหนดบทบาทผู้สอน),
 * dept_mismatch (ผู้สอนต่างภาควิชา), holiday (ตรงวันหยุด)
 * — การชน (conflict) คำนวณแยกผ่าน ScheduleConflictIndex
 */
class CoordinatorAlertService
{
    /**
     * รายการ warning (เรียงตามวันที่) ของ coordinator ในปีการศึกษาที่ระบุ
     *
     * @return Collection<int, array{type:string,schedule:Schedule,label:string,message:string}>
     */
    public function warningItems(int $userId, int $academicYearId): Collection
    {
        $year = \App\Models\AcademicYear::find($academicYearId);
        if (! $year) {
            return collect();
        }

        $schedules = $this->orderByDate(
            Schedule::query()
                ->with([
                    'courseOffering.course.department',
                    'courseOffering.instructorPool.instructorProfile.department',
                    'activityType', 'room', 'term',
                    'instructors.instructorProfile.department',
                ])
                ->whereHas('courseOffering', function ($q) use ($userId, $academicYearId) {
                    // คง delegation scope: หัวหน้าวิชา + อาจารย์/เจ้าหน้าที่ที่ถูกมอบหมายช่วยจัดตาราง
                    $q->schedulableBy($userId)
                        ->where('academic_year_id', $academicYearId)
                        ->withActiveCourse();
                })
        )->get();

        $calendar = AcademicCalendar::forYear($year);

        return $schedules->flatMap(function (Schedule $schedule) use ($calendar) {
            $items = [];
            $label = $this->scheduleLabel($schedule);

            // 1. ข้อมูลไม่ครบ — V2: ไม่เช็คกลุ่มนักศึกษา (กลุ่มจัดหลังอนุมัติ = Phase B)
            $missingParts = [];
            if (! $schedule->topic)                $missingParts[] = 'หัวข้อ';
            if (! $schedule->room_id)              $missingParts[] = 'ห้อง/สถานที่';
            if ($schedule->instructors->isEmpty()) $missingParts[] = 'ผู้สอน';
            if (! empty($missingParts)) {
                $items[] = ['type' => 'incomplete', 'schedule' => $schedule, 'label' => $label, 'message' => 'ข้อมูลไม่ครบ: ' . implode(', ', $missingParts)];
            }

            // 2. ไม่กำหนดบทบาทผู้สอน
            $poolMap = $schedule->courseOffering?->instructorPool->keyBy('id') ?? collect();
            $noRole = $schedule->instructors->filter(fn ($i) => is_null($poolMap->get($i->id)?->pivot?->course_role_id ?? null));
            if ($noRole->isNotEmpty()) {
                $names = $noRole->map(fn ($i) => $i->formatted_name ?? $i->name)->implode(', ');
                $items[] = ['type' => 'no_role', 'schedule' => $schedule, 'label' => $label, 'message' => "ไม่กำหนดบทบาทผู้สอน: {$names}"];
            }

            // 3. ผู้สอนต่างภาควิชา
            $deptId = $schedule->courseOffering?->course?->department_id;
            if ($deptId) {
                $outside = $schedule->instructors->filter(fn ($i) => (int) ($i->instructorProfile?->department_id) !== (int) $deptId);
                if ($outside->isNotEmpty()) {
                    $names = $outside->map(fn ($i) => $i->formatted_name ?? $i->name)->implode(', ');
                    $dept = $outside->first()?->instructorProfile?->department?->name ?? 'ภาควิชาอื่น';
                    $items[] = ['type' => 'dept_mismatch', 'schedule' => $schedule, 'label' => $label, 'message' => "ผู้สอนจากภาควิชาอื่น ({$dept}): {$names}"];
                }
            }

            // 4. กิจกรรมตรงวันหยุดราชการ (เตือน ไม่บล็อก)
            $di = $calendar->classifyDay($schedule->start_date);
            if (($di['kind'] ?? null) === 'holiday') {
                $items[] = ['type' => 'holiday', 'schedule' => $schedule, 'label' => $label, 'message' => 'ตรงวันหยุด: ' . ($di['label'] ?? '') . ' — งดการเรียนการสอน'];
            }

            return $items;
        });
    }

    /** จำนวน warning (ไม่รวมการชน) — ใช้บวกกับ conflict count ใน sidebar badge */
    public function warningCount(int $userId, int $academicYearId): int
    {
        return $this->warningItems($userId, $academicYearId)->count();
    }

    private function orderByDate($query)
    {
        if (Schema::hasColumn('schedules', 'start_date') && Schema::hasColumn('schedules', 'end_date')) {
            return $query->orderBy('start_date')->orderBy('end_date')->orderBy('start_time');
        }

        return $query->orderBy('teaching_date')->orderBy('start_time');
    }

    private function scheduleLabel(Schedule $schedule): string
    {
        $date = $schedule->start_date ? ThaiDate::date($schedule->start_date) : '-';
        $start = substr((string) $schedule->start_time, 0, 5);
        $end = substr((string) $schedule->end_time, 0, 5);
        $activity = $schedule->activityType?->name ?? 'กิจกรรม';

        return trim("{$activity} · {$date} {$start}-{$end}");
    }
}
