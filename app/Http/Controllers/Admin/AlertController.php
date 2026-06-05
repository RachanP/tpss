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
use Illuminate\Http\Request;

class AlertController extends Controller
{
    private const PA_VIOLATIONS_INSTANCE_KEY = 'tpss.pa_violations';

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

        $roomsWithIssues = Room::where(function ($q) {
                $q->where(function ($cap) {
                    // capacity: เฉพาะ location_type ที่ไม่ใช่สถานที่ประเภทเปิด (is_shared = false)
                    $cap->whereHas('locationType', fn($q) => $q->where('is_shared', false))
                        ->where(fn($c) => $c->whereNull('capacity')->orWhere('capacity', 0));
                })->orWhere(function ($name) {
                    // room_name: บังคับทุก location_type
                    $name->whereNull('room_name')->orWhere('room_name', '');
                });
            })
            ->with('locationType')->get();

        $coursesWithoutStaff = Course::doesntHave('assignedStaff')
            ->with(['curriculum', 'department'])->get();

        $activeCoursesMissingHead = Course::where('status', 'active')
            ->whereNull('head_instructor_id')
            ->with(['curriculum', 'department'])
            ->orderBy('course_code')
            ->get();

        $dismissedWarnings = self::getDismissedWarnings();

        $viewData = compact(
            'criticals',
            'paViolations',
            'departmentsWithIssues',
            'roomsWithIssues',
            'coursesWithoutStaff',
            'activeCoursesMissingHead',
            'dismissedWarnings',
        );

        return view('admin.alerts.index', $viewData);
    }

    public function updateDismissed(Request $request)
    {
        $valid = ['departments', 'rooms', 'course_staff'];
        $dismissed = array_values(array_intersect($request->input('dismissed', []), $valid));
        SystemSetting::set('dismissed_warnings', json_encode($dismissed));
        self::flushCache();
        return redirect()->route('admin.alerts')->with('success', 'บันทึกการตั้งค่าแจ้งเตือนเรียบร้อยแล้ว');
    }

    public static function getDismissedWarnings(): array
    {
        return json_decode(SystemSetting::get('dismissed_warnings', '[]'), true) ?? [];
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
            $criticals[] = ['key' => 'no_location_type', 'label' => 'ยังไม่มีประเภทสถานที่ในระบบ',  'link' => route('admin.master_data') . '?tab=location_types', 'linkTxt' => 'เพิ่มประเภทสถานที่'];

        // V4 ข้อ 8: ปีปัจจุบันต้องมีเทอมในปฏิทินค่าเริ่มต้น (ทุกหลักสูตร) — ไม่งั้นระบบไม่รู้ช่วงสอบ/ปิดเทอม
        $activeYear = AcademicYear::where('is_active', true)->first();
        if ($activeYear) {
            $fallback = $activeYear->calendars()
                ->whereNull('curriculum_id')->whereNull('year_levels')->first();
            if (!$fallback || $fallback->terms()->doesntExist()) {
                $criticals[] = [
                    'key'     => 'active_year_missing_calendar_terms',
                    'label'   => 'ปีการศึกษาปัจจุบันยังไม่ได้กำหนดเทอม/ช่วงสอบในปฏิทินค่าเริ่มต้น (ทุกหลักสูตร)',
                    'link'    => route('admin.settings') . '?tab=academic',
                    'linkTxt' => 'ตั้งค่าปฏิทิน',
                ];
            }
        }

        $activeCoursesCount = Course::where('status', 'active')->count();
        if ($activeCoursesCount === 0) {
            $criticals[] = [
                'key'     => 'no_active_course',
                'label'   => 'ยังไม่มีรายวิชาที่เปิดสอนสำหรับรอบปัจจุบัน',
                'link'    => route('admin.master_data') . '?tab=courses',
                'linkTxt' => 'ตรวจสอบรายวิชา',
            ];
        }

        $coursesMissingHeadCount = Course::where('status', 'active')
            ->whereNull('head_instructor_id')
            ->count();
        if ($coursesMissingHeadCount > 0) {
            $criticals[] = [
                'key'     => 'active_courses_missing_head',
                'label'   => 'รายวิชาที่เปิดสอนยังไม่มีหัวหน้าวิชา (' . $coursesMissingHeadCount . ' วิชา)',
                'link'    => route('admin.alerts') . '#active-courses-missing-head',
                'linkTxt' => 'ดูรายละเอียด',
            ];
        }

        $paViolations = self::getPaViolations();
        if (!empty($paViolations)) {
            $criticals[] = [
                'key'     => 'pa_violations',
                'label'   => 'สัดส่วน PA ไม่อยู่ในเกณฑ์ (' . count($paViolations) . ' ท่าน)',
                'link'    => route('admin.users'),
                'linkTxt' => 'ดูรายละเอียด',
            ];
        }
        return $criticals;
    }

    public static function flushCache(): void
    {
        cache()->forget('tpss_alert_summary');
        \App\Services\NavigationBadgeService::flushAdmin();
        app()->forgetInstance(self::PA_VIOLATIONS_INSTANCE_KEY);
    }

    public static function getSummary(): array
    {
        return cache()->remember('tpss_alert_summary', 120, function () {
            $criticalCount = count(self::getCriticals());

            $deptCount = Department::where(function ($q) {
                $q->whereNull('head_user_id')->orWhereNull('secretary_user_id');
            })->count();

            $roomCount = Room::where(function ($q) {
                $q->where(function ($cap) {
                    $cap->whereHas('locationType', fn($q) => $q->where('is_shared', false))
                        ->where(fn($c) => $c->whereNull('capacity')->orWhere('capacity', 0));
                })->orWhere(function ($name) {
                    $name->whereNull('room_name')->orWhere('room_name', '');
                });
            })->count();

            $courseStaffCount = Course::doesntHave('assignedStaff')->count();

            $dismissed = self::getDismissedWarnings();
            $counts = [
                'departments'  => in_array('departments',  $dismissed) ? 0 : $deptCount,
                'rooms'        => in_array('rooms',        $dismissed) ? 0 : $roomCount,
                'course_staff' => in_array('course_staff', $dismissed) ? 0 : $courseStaffCount,
            ];
            $warningCount = array_sum($counts);

            return array_merge($counts, [
                'critical'  => $criticalCount,
                'warnings'  => $warningCount,
                'total'     => $criticalCount + $warningCount,
                'dismissed' => $dismissed,
            ]);
        });
    }

    public static function getPaViolations(): array
    {
        if (app()->bound(self::PA_VIOLATIONS_INSTANCE_KEY)) {
            return app(self::PA_VIOLATIONS_INSTANCE_KEY);
        }

        $criteria = json_decode(SystemSetting::get('pa_criteria_config', '{}'), true);
        $firstGroup = !empty($criteria) ? reset($criteria) : null;
        $firstField = $firstGroup ? reset($firstGroup) : null;
        if (empty($criteria) || !is_array($firstField)) {
            $criteria = AdminSettingController::defaultPaCriteria();
        }

        $fieldMap = ['t' => 'สอน', 'r' => 'วิจัย', 's' => 'บริการฯ', 'c' => 'ศิลปะฯ', 'o' => 'มอบหมาย'];

        $violations = [];

        User::whereHas('roles', fn($q) => $q->where('role', 'instructor'))
            ->with('instructorProfile.department')
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
                $group  = self::paGroup($profile->title ?? '', $profile->academic_degree ?? '');

                $sum = array_sum($pcts);
                if ($sum !== 100) {
                    $issues[] = 'รวม ' . $sum . '% (ต้องเท่ากับ 100%)';
                } elseif (isset($criteria[$group])) {
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

        app()->instance(self::PA_VIOLATIONS_INSTANCE_KEY, $violations);

        return $violations;
    }

    public static function paGroup(string $title, string $degree): string
    {
        $isAssistant = str_contains($title, 'ผู้ช่วยอาจารย์');
        if ($isAssistant && str_contains($title, 'คลินิก'))        return 'ผู้ช่วยอาจารย์_คลินิก';
        if ($isAssistant && str_contains($title, 'สอนภาคปฏิบัติ')) return 'ผู้ช่วยอาจารย์_ปฏิบัติ';
        if ($isAssistant) {
            if ($degree === 'ปริญญาตรี')                           return 'ผู้ช่วยอาจารย์_ปตรี';
            return 'ผู้ช่วยอาจารย์';
        }
        return 'อาจารย์';
    }
}
