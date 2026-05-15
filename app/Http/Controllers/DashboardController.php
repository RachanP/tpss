<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $instructors = User::whereHas('roles', function($q) {
            $q->where('role', 'instructor');
        })->with(['instructorProfile.department'])->get();

        $teachingWeeks = \App\Models\SystemSetting::get('teaching_load_weeks', 39);
        $hoursPerWeek = \App\Models\SystemSetting::get('teaching_quota_hours_per_week', 35);

        return view('admin.dashboard', compact('instructors', 'teachingWeeks', 'hoursPerWeek'));
    }

    public function staff()
    {
        $instructors = User::whereHas('roles', function($q) {
            $q->where('role', 'instructor');
        })->with(['instructorProfile.department'])->get();

        $teachingWeeks = \App\Models\SystemSetting::get('teaching_load_weeks', 39);
        $hoursPerWeek = \App\Models\SystemSetting::get('teaching_quota_hours_per_week', 35);

        return view('staff.dashboard', compact('instructors', 'teachingWeeks', 'hoursPerWeek'));
    }

    public function maker()
    {
        return view('course_head.dashboard');
    }

    public function approver()
    {
        return view('executive.dashboard');
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
