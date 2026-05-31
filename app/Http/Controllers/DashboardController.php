<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\AlertController;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Curriculum;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ScheduleConflictReadRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Redirect to the role-appropriate dashboard.
     */
    public function index()
    {
        $role = session('active_role');

        return match($role) {
            'admin'       => redirect()->route('admin.dashboard'),
            'staff'       => redirect()->route('staff.dashboard'),
            'course_head' => redirect()->route('maker.dashboard'),
            'executive'   => redirect()->route('approver.dashboard'),
            'instructor'  => redirect()->route('lecturer.dashboard'),
            default       => redirect()->route('staff.dashboard'),
        };
    }

    // ── Per-role placeholders ──────────────────────────────────────

    public function admin()
    {
        ['instructors' => $instructors, 'teachingWeeks' => $teachingWeeks, 'hoursPerWeek' => $hoursPerWeek]
            = $this->instructorWorkloadData();

        $criticals = AlertController::getCriticals();
        $alerts    = AlertController::getSummary();
        $currentAcademicYear = AcademicYear::where('is_active', true)
            ->orderByDesc('name')
            ->orderByDesc('semester')
            ->first();

        $roomsByType = \App\Models\LocationType::withCount('rooms')
            ->orderByDesc('rooms_count')
            ->get()
            ->map(fn($lt) => ['label' => $lt->name, 'count' => $lt->rooms_count]);

        $curriculumsByLevel = Curriculum::select('education_level', DB::raw('COUNT(*) as cnt'))
            ->groupBy('education_level')
            ->pluck('cnt', 'education_level')
            ->toArray();

        $stats = [
            'users' => [
                'active' => User::where('is_active', true)->count(),
                'total'  => User::count(),
            ],
            'courses' => [
                'active' => Course::where('status', 'active')->count(),
                'total'  => Course::count(),
            ],
            'rooms' => [
                'total'    => Room::count(),
                'by_type'  => $roomsByType,
            ],
            'curriculums' => [
                'total'     => Curriculum::count(),
                'by_level'  => [
                    'bachelor'  => $curriculumsByLevel['bachelor']  ?? 0,
                    'master'    => $curriculumsByLevel['master']    ?? 0,
                    'doctorate' => $curriculumsByLevel['doctorate'] ?? 0,
                ],
            ],
        ];

        $pipelineCounts = $currentAcademicYear
            ? CourseOffering::where('academic_year_id', $currentAcademicYear->id)
                ->select('approval_status', DB::raw('COUNT(*) as count'))
                ->groupBy('approval_status')
                ->pluck('count', 'approval_status')
                ->toArray()
            : [];

        $pipeline = [
            'draft'     => $pipelineCounts['draft']     ?? 0,
            'pending'   => $pipelineCounts['pending']   ?? 0,
            'published' => $pipelineCounts['published'] ?? 0,
            'rejected'  => $pipelineCounts['rejected']  ?? 0,
        ];

        $conflictSummary = config('conflicts.async_reads') && $currentAcademicYear
            ? app(ScheduleConflictReadRepository::class)->getGlobalSummary((int) $currentAcademicYear->id)
            : ['status' => config('conflicts.async_reads') ? 'missing' : 'disabled', 'generation' => null, 'total' => null, 'by_type' => []];

        return view('admin.dashboard', compact('instructors', 'teachingWeeks', 'hoursPerWeek', 'alerts', 'criticals', 'currentAcademicYear', 'stats', 'pipeline', 'conflictSummary'));
    }

    public function staff()
    {
        ['instructors' => $instructors, 'teachingWeeks' => $teachingWeeks, 'hoursPerWeek' => $hoursPerWeek]
            = $this->instructorWorkloadData();

        $currentAcademicYear = AcademicYear::where('is_active', true)
            ->orderByDesc('name')
            ->orderByDesc('semester')
            ->first();

        $staffId = (int) Auth::id();
        $assignedCourseCount = Course::whereHas('assignedStaff', fn ($query) => $query->where('users.id', $staffId))
            ->count();

        $staffOfferings = CourseOffering::query()
            ->with(['course.curriculum', 'course.department', 'academicYear'])
            ->withCount(['schedules', 'studentGroups', 'instructorPool'])
            ->withActiveCourse()
            ->whereHas('course.assignedStaff', fn ($query) => $query->where('users.id', $staffId))
            ->get();

        $currentYearStaffOfferings = $currentAcademicYear
            ? $staffOfferings->where('academic_year_id', $currentAcademicYear->id)->values()
            : collect();

        $offeringPipelineCounts = $currentAcademicYear
            ? CourseOffering::where('academic_year_id', $currentAcademicYear->id)
                ->select('approval_status', DB::raw('COUNT(*) as count'))
                ->groupBy('approval_status')
                ->pluck('count', 'approval_status')
                ->toArray()
            : [];

        $staffScheduleCount = $currentAcademicYear
            ? Schedule::whereHas('courseOffering', fn ($query) => $query
                ->where('academic_year_id', $currentAcademicYear->id)
                ->whereHas('course.assignedStaff', fn ($courseQuery) => $courseQuery->where('users.id', $staffId)))
                ->count()
            : 0;

        $roomUsage = $currentAcademicYear
            ? Schedule::query()
                ->whereHas('courseOffering', fn ($query) => $query->where('academic_year_id', $currentAcademicYear->id))
                ->whereNotNull('room_id')
                ->distinct('room_id')
                ->count('room_id')
            : 0;

        $criticals = AlertController::getCriticals();
        $alerts = AlertController::getSummary();
        $staffActionGroups = $this->staffActionGroups($criticals, $alerts);

        $conflictSummary = config('conflicts.async_reads') && $currentAcademicYear
            ? app(ScheduleConflictReadRepository::class)->getStaffSummary($staffId, (int) $currentAcademicYear->id)
            : ['status' => config('conflicts.async_reads') ? 'missing' : 'disabled', 'generation' => null, 'total' => null, 'by_type' => []];

        $masterStats = [
            'courses' => Course::where('status', 'active')->count(),
            'rooms' => Room::count(),
            'active_rooms' => Room::where('status', 'active')->count(),
            'location_types' => LocationType::count(),
            'course_offerings' => array_sum($offeringPipelineCounts),
            'assigned_courses' => $assignedCourseCount,
            'assigned_offerings' => $currentYearStaffOfferings->count(),
            'staff_schedules' => $staffScheduleCount,
            'room_usage' => $roomUsage,
        ];

        $pipeline = [
            'draft' => $offeringPipelineCounts['draft'] ?? 0,
            'pending' => $offeringPipelineCounts['pending'] ?? 0,
            'published' => $offeringPipelineCounts['published'] ?? 0,
            'rejected' => $offeringPipelineCounts['rejected'] ?? 0,
        ];

        $readiness = $this->staffReadinessItems(
            $currentAcademicYear,
            $masterStats,
            $alerts,
            $conflictSummary
        );

        $recentAuditLogs = AuditLog::with('user')
            ->orderedForAudit()
            ->limit(5)
            ->get();

        return view('staff.dashboard', compact(
            'instructors',
            'teachingWeeks',
            'hoursPerWeek',
            'recentAuditLogs',
            'currentAcademicYear',
            'currentYearStaffOfferings',
            'masterStats',
            'pipeline',
            'readiness',
            'conflictSummary',
            'staffActionGroups',
            'alerts',
        ));
    }

    private function staffReadinessItems(?AcademicYear $currentAcademicYear, array $masterStats, array $alerts, array $conflictSummary): array
    {
        $conflictTotal = $conflictSummary['total'];
        $conflictStatus = $conflictSummary['status'] ?? 'missing';

        return [
            [
                'label' => 'ปีการศึกษาปัจจุบัน',
                'value' => $currentAcademicYear
                    ? "{$currentAcademicYear->name} / เทอม {$currentAcademicYear->semester}"
                    : 'ยังไม่มีปีใช้งาน',
                'status' => $currentAcademicYear ? 'ready' : 'critical',
                'hint' => $currentAcademicYear
                    ? 'Staff แก้ข้อมูลปีและวันที่ได้ แต่การเปิดช่วงจัดตารางเป็นสิทธิ์ Admin'
                    : 'เพิ่มหรือเลือกปีการศึกษาที่ใช้งานอยู่',
                'href' => route('staff.settings') . '?tab=academic',
            ],
            [
                'label' => 'Phase การจัดตาราง',
                'value' => $currentAcademicYear?->phase ?? 'ไม่ระบุ',
                'status' => $currentAcademicYear?->phase === 'scheduling' ? 'ready' : 'watch',
                'hint' => $currentAcademicYear?->phase === 'scheduling'
                    ? 'เปิดให้ Staff บันทึกรายการสอนได้ตามรายวิชาที่ได้รับมอบหมาย'
                    : 'ช่วงนี้เป็นโหมดดูอย่างเดียวสำหรับตารางสอน',
                'href' => route('staff.schedules.index'),
            ],
            [
                'label' => 'ข้อมูลหลักที่ Staff ดูแล',
                'value' => number_format($masterStats['courses']) . ' วิชา / ' . number_format($masterStats['active_rooms']) . ' ห้อง',
                'status' => $masterStats['courses'] > 0 && $masterStats['location_types'] > 0 ? 'ready' : 'critical',
                'hint' => 'แก้ได้เฉพาะรายวิชา ห้อง และประเภทสถานที่',
                'href' => route('staff.master_data') . '?tab=courses',
            ],
            [
                'label' => 'Course Offering รอบปัจจุบัน',
                'value' => number_format($masterStats['course_offerings']) . ' รายวิชาเปิดสอน',
                'status' => $masterStats['course_offerings'] > 0 ? 'ready' : 'watch',
                'hint' => 'Staff เห็นเฉพาะ offering ที่ผูกผ่าน course_staff ในหน้าตาราง',
                'href' => route('staff.schedules.index'),
            ],
            [
                'label' => 'ตารางที่ Staff บันทึกแล้ว',
                'value' => number_format($masterStats['staff_schedules']) . ' รายการ',
                'status' => $masterStats['staff_schedules'] > 0 ? 'ready' : 'watch',
                'hint' => 'นับเฉพาะรอบปีการศึกษาปัจจุบันและรายวิชาที่ได้รับมอบหมาย',
                'href' => route('staff.schedules.index'),
            ],
            [
                'label' => 'Conflict / Warning',
                'value' => $conflictTotal === null ? 'รอข้อมูล' : number_format((int) $conflictTotal) . ' รายการ',
                'status' => $conflictTotal === null
                    ? 'watch'
                    : ((int) $conflictTotal > 0 || ($alerts['warnings'] ?? 0) > 0 ? 'critical' : 'ready'),
                'hint' => $conflictStatus === 'disabled'
                    ? 'ยังไม่ได้เปิด async conflict read จึงใช้ summary แบบจำกัด'
                    : 'สรุปรายการชนและ warning จากข้อมูลล่าสุด',
                'href' => route('staff.dashboard') . '#staff-report-summary',
            ],
        ];
    }

    private function staffActionGroups(array $criticals, array $alerts): array
    {
        $staffKeys = [
            'no_active_year' => route('staff.settings') . '?tab=academic',
            'no_location_type' => route('staff.master_data') . '?tab=location_types',
            'no_active_course' => route('staff.master_data') . '?tab=courses',
            'active_courses_missing_head' => route('staff.master_data') . '?tab=courses',
        ];

        $adminKeys = [
            'no_department',
            'no_curriculum',
            'no_activity_type',
            'pa_violations',
        ];

        $staff = [];
        $admin = [];

        foreach ($criticals as $critical) {
            $key = $critical['key'] ?? '';
            $item = [
                'label' => $critical['label'] ?? $key,
                'href' => $staffKeys[$key] ?? null,
                'link_text' => $critical['linkTxt'] ?? 'ตรวจสอบ',
                'tone' => 'critical',
            ];

            if (array_key_exists($key, $staffKeys)) {
                $staff[] = $item;
            } elseif (in_array($key, $adminKeys, true)) {
                $admin[] = [
                    ...$item,
                    'href' => null,
                    'link_text' => 'ให้ Admin แก้ไข',
                ];
            }
        }

        if (($alerts['rooms'] ?? 0) > 0) {
            $staff[] = [
                'label' => 'ห้อง/สถานที่ยังมีข้อมูลไม่ครบ ' . number_format((int) $alerts['rooms']) . ' รายการ',
                'href' => route('staff.master_data') . '?tab=location_types',
                'link_text' => 'ตรวจสอบห้อง',
                'tone' => 'warning',
            ];
        }

        if (($alerts['course_staff'] ?? 0) > 0) {
            $staff[] = [
                'label' => 'รายวิชายังไม่มีเจ้าหน้าที่ดูแล ' . number_format((int) $alerts['course_staff']) . ' รายการ',
                'href' => route('staff.master_data') . '?tab=courses',
                'link_text' => 'กำหนด Staff',
                'tone' => 'warning',
            ];
        }

        if (($alerts['departments'] ?? 0) > 0) {
            $admin[] = [
                'label' => 'ภาควิชายังมีข้อมูลหัวหน้า/เลขานุการไม่ครบ ' . number_format((int) $alerts['departments']) . ' รายการ',
                'href' => null,
                'link_text' => 'ให้ Admin แก้ไข',
                'tone' => 'warning',
            ];
        }

        return [
            'staff' => collect($staff)->values(),
            'admin' => collect($admin)->values(),
        ];
    }

    private function instructorWorkloadData(): array
    {
        return [
            'instructors'   => User::whereHas('roles', fn($q) => $q->where('role', 'instructor'))
                                   ->with(['instructorProfile.department'])->get(),
            'teachingWeeks' => SystemSetting::get('teaching_load_weeks', 39),
            'hoursPerWeek'  => SystemSetting::get('teaching_quota_hours_per_week', 35),
        ];
    }

    public function maker()
    {
        return view('course_head.dashboard');
    }

    public function approver()
    {
        $currentAcademicYear = AcademicYear::where('is_active', true)
            ->orWhere('phase', 'scheduling')
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->orderByDesc('semester')
            ->first();
        $conflictSummary = config('conflicts.async_reads') && $currentAcademicYear
            ? app(ScheduleConflictReadRepository::class)->getExecutiveSummary((int) $currentAcademicYear->id)
            : ['status' => config('conflicts.async_reads') ? 'missing' : 'disabled', 'generation' => null, 'total' => null, 'by_type' => []];

        return view('executive.dashboard', compact('currentAcademicYear', 'conflictSummary'));
    }

    public function lecturer()
    {
        $user = Auth::user()->load('instructorProfile');
        $quota = null;
        $period = null;

        if ($user->instructorProfile && $user->instructorProfile->teaching_pct) {
            $isGov = ($user->instructorProfile->employment_type === 'ข้าราชการ');
            $teachingWeeks = \App\Models\SystemSetting::get('teaching_load_weeks', 39);
            $hoursPerWeek = \App\Models\SystemSetting::get('teaching_quota_hours_per_week', 35);
            $base = $isGov ? ($teachingWeeks * $hoursPerWeek / 2) : ($teachingWeeks * $hoursPerWeek);
            $period = $isGov ? '6 เดือน' : 'ปี';
            $quota = ($base * $user->instructorProfile->teaching_pct) / 100;
        }

        return view('instructor.dashboard', compact('user', 'quota', 'period'));
    }

    /**
     * Switch the active_role stored in session.
     */
    public function switchRole(Request $request)
    {
        $request->validate(['role' => 'required|string']);

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['username' => 'บัญชีผู้ใช้นี้ถูกระงับการใช้งาน']);
        }

        $hasRole = UserRole::where('user_id', $user->id)
            ->where('role', $request->role)
            ->exists();

        if ($hasRole) {
            $request->session()->put('active_role', $request->role);
        }

        return redirect()->route('dashboard');
    }
}
