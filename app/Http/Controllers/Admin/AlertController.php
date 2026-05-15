<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AdminSettingController;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ActivityType;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\SystemSetting;
use App\Models\User;

class AlertController extends Controller
{
    public function index()
    {
        $criticals    = self::getCriticals();
        $paViolations = self::getPaViolations();

        // ── Warnings ────────────────────────────────────────────────
        $departmentsWithIssues = Department::where(function ($q) {
                $q->whereNull('head_user_id')->orWhereNull('secretary_user_id');
            })
            ->with(['head', 'secretary'])
            ->get()
            ->map(function ($dept) {
                $missing = [];
                if (empty($dept->head_user_id))      $missing[] = 'ไม่มีหัวหน้าภาค';
                if (empty($dept->secretary_user_id)) $missing[] = 'ไม่มีเลขานุการ';
                return ['dept' => $dept, 'missing' => $missing];
            });

        $instructorsWithIssues = User::whereHas('roles', fn($q) => $q->where('role', 'instructor'))
            ->with(['instructorProfile.department'])
            ->get()
            ->map(function ($user) {
                $profile = $user->instructorProfile;
                $missing = [];
                if (empty($user->email))                        $missing[] = 'ไม่มี Email';
                if (empty($user->employee_id))                  $missing[] = 'ไม่มีรหัสพนักงาน';
                if (!$profile)                                  $missing[] = 'ไม่มีข้อมูลโปรไฟล์';
                elseif (empty($profile->title))                 $missing[] = 'ไม่มีตำแหน่งทางวิชาการ';
                if ($profile && empty($profile->department_id)) $missing[] = 'ไม่ระบุภาควิชา';
                return $missing ? ['user' => $user, 'missing' => $missing] : null;
            })
            ->filter()->values();

        $roomsWithIssues = Room::where(function ($q) {
                $q->whereNull('capacity')->orWhere('capacity', 0)
                  ->orWhereNull('room_name')->orWhere('room_name', '');
            })
            ->whereHas('locationType', fn($q) => $q->where('requires_capacity', true))
            ->with('locationType')->get();

        $coursesWithoutCoordinator = Course::whereNull('head_instructor_id')
            ->with(['curriculum', 'department'])->get();

        $coursesWithoutStaff = Course::doesntHave('assignedStaff')
            ->with(['curriculum', 'department'])->get();

        return view('admin.alerts.index', compact(
            'criticals',
            'paViolations',
            'departmentsWithIssues',
            'instructorsWithIssues',
            'roomsWithIssues',
            'coursesWithoutCoordinator',
            'coursesWithoutStaff',
        ));
    }

    public static function getCriticals(): array
    {
        $criticals = [];
        if (!AcademicYear::where('is_active', true)->exists())
            $criticals[] = ['key' => 'no_active_year',   'label' => 'ไม่มีปีการศึกษาที่ใช้งานอยู่',  'link' => route('admin.settings') . '?tab=academic',         'linkTxt' => 'ตั้งค่าปีการศึกษา'];
        if (!Department::exists())
            $criticals[] = ['key' => 'no_department',    'label' => 'ยังไม่มีภาควิชาในระบบ',         'link' => route('admin.master_data') . '?tab=departments',   'linkTxt' => 'เพิ่มภาควิชา'];
        if (!Curriculum::exists())
            $criticals[] = ['key' => 'no_curriculum',    'label' => 'ยังไม่มีหลักสูตรในระบบ',        'link' => route('admin.master_data') . '?tab=curriculums',   'linkTxt' => 'เพิ่มหลักสูตร'];
        if (!ActivityType::exists())
            $criticals[] = ['key' => 'no_activity_type', 'label' => 'ยังไม่มีประเภทกิจกรรมในระบบ',  'link' => route('admin.master_data') . '?tab=activity_types','linkTxt' => 'เพิ่มประเภทกิจกรรม'];
        if (!LocationType::exists())
            $criticals[] = ['key' => 'no_location_type', 'label' => 'ยังไม่มีประเภทสถานที่ในระบบ',  'link' => route('admin.master_data') . '?tab=rooms',         'linkTxt' => 'เพิ่มประเภทสถานที่'];

        $paViolations = self::getPaViolations();
        if (!empty($paViolations)) {
            $criticals[] = [
                'key'     => 'pa_violations',
                'label'   => 'สัดส่วน PA ไม่อยู่ในเกณฑ์ (' . count($paViolations) . ' ท่าน)',
                'link'    => '#pa-violations',
                'linkTxt' => 'ดูรายละเอียด',
            ];
        }
        return $criticals;
    }

    public static function flushCache(): void
    {
        cache()->forget('tpss_alert_summary');
    }

    public static function getSummary(): array
    {
        return cache()->remember('tpss_alert_summary', 300, function () {
            $criticalCount = 0;
            if (!AcademicYear::where('is_active', true)->exists()) $criticalCount++;
            if (!Curriculum::exists())                             $criticalCount++;
            if (!Department::exists())                             $criticalCount++;
            if (!ActivityType::exists())                           $criticalCount++;
            if (!LocationType::exists())                           $criticalCount++;
            $criticalCount += count(self::getPaViolations());

            $deptCount = Department::where(function ($q) {
                $q->whereNull('head_user_id')->orWhereNull('secretary_user_id');
            })->count();

            $instructorCount = User::whereHas('roles', fn($q) => $q->where('role', 'instructor'))
                ->where(function ($q) {
                    $q->whereNull('email')->orWhere('email', '')
                      ->orWhereNull('employee_id')->orWhere('employee_id', '')
                      ->orWhereDoesntHave('instructorProfile')
                      ->orWhereHas('instructorProfile', fn($pq) =>
                          $pq->whereNull('title')->orWhere('title', '')->orWhereNull('department_id')
                      );
                })
                ->count();

            $roomCount = Room::where(function ($q) {
                $q->whereNull('capacity')->orWhere('capacity', 0)
                  ->orWhereNull('room_name')->orWhere('room_name', '');
            })->whereHas('locationType', fn($q) => $q->where('requires_capacity', true))->count();

            $courseCount      = Course::whereNull('head_instructor_id')->count();
            $courseStaffCount = Course::doesntHave('assignedStaff')->count();

            $warningCount = $deptCount + $instructorCount + $roomCount + $courseCount + $courseStaffCount;

            return [
                'critical'      => $criticalCount,
                'warnings'      => $warningCount,
                'departments'   => $deptCount,
                'instructors'   => $instructorCount,
                'rooms'         => $roomCount,
                'courses'       => $courseCount,
                'course_staff'  => $courseStaffCount,
                'total'         => $criticalCount + $warningCount,
            ];
        });
    }

    public static function getPaViolations(): array
    {
        $criteria = json_decode(SystemSetting::get('pa_criteria_config', '{}'), true);
        $firstGroup = !empty($criteria) ? reset($criteria) : null;
        $firstField = $firstGroup ? reset($firstGroup) : null;
        if (empty($criteria) || !is_array($firstField)) {
            $criteria = AdminSettingController::defaultPaCriteria();
        }

        $fieldMap = ['t' => 'สอน', 'r' => 'วิจัย', 's' => 'บริการฯ', 'c' => 'ศิลปะฯ', 'o' => 'มอบหมาย'];

        $violations = [];

        User::whereHas('roles', fn($q) => $q->where('role', 'instructor'))
            ->with('instructorProfile')
            ->get()
            ->each(function ($user) use ($criteria, $fieldMap, &$violations) {
                $profile = $user->instructorProfile;
                if (!$profile) return;

                $pcts = [
                    't' => (int) $profile->teaching_pct,
                    'r' => (int) $profile->research_pct,
                    's' => (int) $profile->service_pct,
                    'c' => (int) $profile->culture_pct,
                    'o' => (int) $profile->other_pct,
                ];

                $issues = [];

                // sum check
                $sum = array_sum($pcts);
                if ($sum !== 100) {
                    $issues[] = 'รวม ' . $sum . '% (ต้องเท่ากับ 100%)';
                }

                // range check
                $group = self::paGroup($profile->title ?? '', $profile->academic_degree ?? '');
                if (isset($criteria[$group])) {
                    foreach ($pcts as $f => $val) {
                        $range = $criteria[$group][$f] ?? null;
                        if (!$range) continue;
                        if ($val < $range['min'] || $val > $range['max']) {
                            $issues[] = $fieldMap[$f] . ' ' . $val . '% (เกณฑ์ ' . $range['min'] . '–' . $range['max'] . '%)';
                        }
                    }
                }

                if ($issues) {
                    $violations[] = ['user' => $user, 'issues' => $issues, 'group' => $group];
                }
            });

        return $violations;
    }

    private static function paGroup(string $title, string $degree): string
    {
        if (str_contains($title, 'คลินิก'))            return 'ผู้ช่วยอาจารย์_คลินิก';
        if (str_contains($title, 'สอนภาคปฏิบัติ'))     return 'ผู้ช่วยอาจารย์_ปฏิบัติ';
        if (str_contains($title, 'ผู้ช่วยอาจารย์')) {
            if ($degree === 'ปริญญาตรี')               return 'ผู้ช่วยอาจารย์_ปตรี';
            return 'ผู้ช่วยอาจารย์';
        }
        return 'อาจารย์';
    }
}
