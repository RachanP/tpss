<?php

namespace App\Http\Controllers;

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
        return view('dashboard.admin', ['title' => 'Dashboard — ผู้ดูแลระบบ']);
    }

    public function staff()
    {
        return view('dashboard.staff', ['title' => 'ภาพรวม — เจ้าหน้าที่']);
    }

    public function maker()
    {
        return view('dashboard.maker', ['title' => 'ภาพรวม — หัวหน้าวิชา']);
    }

    public function approver()
    {
        return view('dashboard.approver', ['title' => 'รออนุมัติ — ผู้บริหาร']);
    }

    public function lecturer()
    {
        return view('dashboard.lecturer', ['title' => 'ตารางสอน — อาจารย์']);
    }

    /**
     * Switch the active_role stored in session.
     */
    public function switchRole(Request $request)
    {
        $request->validate(['role' => 'required|string']);

        $user = Auth::user();
        $hasRole = UserRole::where('user_id', $user->id)
            ->where('role', $request->role)
            ->exists();

        if ($hasRole) {
            $request->session()->put('active_role', $request->role);
        }

        return redirect()->route('dashboard');
    }
}
