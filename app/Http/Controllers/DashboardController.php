<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\AlertController;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Curriculum;
use App\Models\Room;
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

        $recentAuditLogs = AuditLog::with('user')
            ->orderedForAudit()
            ->limit(5)
            ->get();

        return view('staff.dashboard', compact('instructors', 'teachingWeeks', 'hoursPerWeek', 'recentAuditLogs'));
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
