<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRole;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::with(['roles', 'instructorProfile.department', 'headOfDepartments', 'secretaryOfDepartments'])
            ->orderBy('created_at', 'desc')
            ->get();

        $stats = [
            'total'    => User::count(),
            'active'   => User::where('is_active', true)->count(),
            'inactive' => User::where('is_active', false)->count(),
        ];

        $departments = Department::with(['head', 'secretary'])->orderBy('name')->get();
        
        $paCriteria = json_decode(SystemSetting::get('pa_criteria_config', '{}'), true);
        if (empty($paCriteria)) {
            $paCriteria = [
                'อาจารย์' => ['t' => '20-70%', 'r' => '20-70%', 's' => '5-20%', 'c' => '5-15%', 'o' => '0-20%'],
                'ผู้ช่วยอาจารย์' => ['t' => '≤ 70%', 'r' => '15-20%', 's' => '5-20%', 'c' => '5-20%', 'o' => '0-20%'],
                'ผู้ช่วยอาจารย์_ปตรี' => ['t' => '30-60%', 'r' => '0%', 's' => '10-30%', 'c' => '10-20%', 'o' => '0-30%'],
                'ผู้ช่วยอาจารย์_คลินิก' => ['t' => '≤ 10%', 'r' => '0-5%', 's' => '70-80%', 'c' => '0-5%', 'o' => '0-10%'],
                'ผู้ช่วยอาจารย์_ปฏิบัติ' => ['t' => '≤ 70%', 'r' => '0%', 's' => '5-20%', 'c' => '5-20%', 'o' => '0-20%'],
            ];
        }

        $systemSettings = [
            'teaching_quota_hours' => SystemSetting::get('teaching_quota_hours', 1610)
        ];

        return view('admin.users.index', compact('users', 'stats', 'departments', 'systemSettings', 'paCriteria'));
    }

    public function store(Request $request)
    {
        $roles = $request->input('roles', []);
        $reqInstructor = in_array('instructor', $roles) ? 'required' : 'nullable';

        $validated = $request->validate([
            'username'     => 'required|string|max:100|unique:users',
            'prefix'       => 'nullable|string|max:50',
            'name'         => 'required|string|max:255',
            'email'        => 'required|string|email|max:255|unique:users',
            'password'     => 'required|string|min:4',
            'roles'        => 'required|array|min:1',
            'primary_role' => ['required', 'string', Rule::in($roles)],
            'is_active'    => 'boolean',
            // instructor profile fields
            'instructor_title'          => "$reqInstructor|string|max:100",
            'instructor_employee_id'     => "$reqInstructor|string|max:50|unique:instructor_profiles,employee_id",
            'instructor_department_id'  => "$reqInstructor|integer|exists:departments,id",
            'instructor_employment_type' => "$reqInstructor|string|max:100",
            'instructor_hired_at'        => "$reqInstructor|date",
            'instructor_academic_degree' => "$reqInstructor|string|max:100",
            'instructor_teaching_pct'   => "$reqInstructor|integer|min:0|max:100",
            'instructor_research_pct'   => "$reqInstructor|integer|min:0|max:100",
            'instructor_service_pct'    => "$reqInstructor|integer|min:0|max:100",
            'instructor_culture_pct'    => "$reqInstructor|integer|min:0|max:100",
            'instructor_other_pct'      => "$reqInstructor|integer|min:0|max:100",
            'instructor_teaching_quota' => 'nullable|integer|min:0',
            'instructor_is_english_passed' => 'nullable|boolean',
            'instructor_department_position' => [
                'nullable', 'string', 'in:head,secretary',
                function ($attribute, $value, $fail) use ($request) {
                    if (!$value) return;
                    $deptId = $request->input('instructor_department_id');
                    if (!$deptId) return;
                    $dept = Department::find($deptId);
                    if (!$dept) return;
                    $conflictUserId = $value === 'head' ? $dept->head_user_id : $dept->secretary_user_id;
                    if ($conflictUserId) {
                        $conflictUser = User::find($conflictUserId);
                        $posLabel = $value === 'head' ? 'หัวหน้าภาควิชา' : 'เลขานุการภาควิชา';
                        $fail("ภาควิชา {$dept->name} มี{$posLabel}อยู่แล้วคือ {$conflictUser?->name} กรุณาถอดถอนท่านเดิมก่อน หรือเลือกภาควิชาอื่น");
                    }
                }
            ],
        ]);

        if ($reqInstructor === 'required') {
            $totalPct = (int)$request->instructor_teaching_pct + (int)$request->instructor_research_pct + (int)$request->instructor_service_pct + (int)$request->instructor_culture_pct + (int)$request->instructor_other_pct;
            if ($totalPct !== 100) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'instructor_teaching_pct' => "สัดส่วนภาระงานรวมต้องเท่ากับ 100% (ปัจจุบัน {$totalPct}%)"
                ]);
            }
        }

        DB::transaction(function () use ($validated, $request) {
            $user = User::create([
                'username'  => $validated['username'],
                'prefix'    => $validated['prefix'] ?? null,
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'password'  => Hash::make($validated['password']),
                'is_active' => $validated['is_active'] ?? true,
            ]);

            foreach ($validated['roles'] as $role) {
                UserRole::create([
                    'user_id'    => $user->id,
                    'role'       => $role,
                    'is_primary' => $role === $validated['primary_role'],
                ]);
            }

            // Save instructor profile if instructor role is included
            if (in_array('instructor', $validated['roles'])) {
                InstructorProfile::create([
                    'user_id'        => $user->id,
                    'title'          => $validated['instructor_title'] ?? null,
                    'employee_id'    => $validated['instructor_employee_id'] ?? null,
                    'department_id'  => $validated['instructor_department_id'] ?? null,
                    'employment_type' => $validated['instructor_employment_type'] ?? null,
                    'hired_at'       => $validated['instructor_hired_at'] ?? null,
                    'academic_degree' => $validated['instructor_academic_degree'] ?? null,
                    'teaching_pct'   => $validated['instructor_teaching_pct'] ?? 0,
                    'research_pct'   => $validated['instructor_research_pct'] ?? 0,
                    'service_pct'    => $validated['instructor_service_pct'] ?? 0,
                    'culture_pct'    => $validated['instructor_culture_pct'] ?? 0,
                    'other_pct'      => $validated['instructor_other_pct'] ?? 0,
                    'teaching_quota' => $validated['instructor_teaching_quota'] ?? 0,
                    'is_english_passed' => $request->boolean('instructor_is_english_passed'),
                ]);

                // Handle department positions
                if ($request->filled('instructor_department_position') && $request->filled('instructor_department_id')) {
                    if ($validated['instructor_department_position'] === 'head') {
                        Department::where('id', $validated['instructor_department_id'])->update(['head_user_id' => $user->id]);
                    } else if ($validated['instructor_department_position'] === 'secretary') {
                        Department::where('id', $validated['instructor_department_id'])->update(['secretary_user_id' => $user->id]);
                    }
                }
            }
        });

        return redirect()->route('admin.users')->with('success', 'เพิ่มผู้ใช้เรียบร้อยแล้ว');
    }

    public function update(Request $request, User $user)
    {
        $roles = $request->input('roles', []);
        $reqInstructor = in_array('instructor', $roles) ? 'required' : 'nullable';

        $validated = $request->validate([
            'prefix'       => 'nullable|string|max:50',
            'name'         => 'required|string|max:255',
            'email'        => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password'     => 'nullable|string|min:4',
            'roles'        => 'required|array|min:1',
            'primary_role' => ['required', 'string', Rule::in($roles)],
            'is_active'    => 'required|boolean',
            // instructor profile fields
            'instructor_title'          => "$reqInstructor|string|max:100",
            'instructor_employee_id'     => "$reqInstructor|string|max:50|unique:instructor_profiles,employee_id," . ($user->instructorProfile ? $user->instructorProfile->id : 'NULL'),
            'instructor_department_id'  => "$reqInstructor|integer|exists:departments,id",
            'instructor_employment_type' => "$reqInstructor|string|max:100",
            'instructor_hired_at'        => "$reqInstructor|date",
            'instructor_academic_degree' => "$reqInstructor|string|max:100",
            'instructor_teaching_pct'   => "$reqInstructor|integer|min:0|max:100",
            'instructor_research_pct'   => "$reqInstructor|integer|min:0|max:100",
            'instructor_service_pct'    => "$reqInstructor|integer|min:0|max:100",
            'instructor_culture_pct'    => "$reqInstructor|integer|min:0|max:100",
            'instructor_other_pct'      => "$reqInstructor|integer|min:0|max:100",
            'instructor_teaching_quota' => 'nullable|integer|min:0',
            'instructor_is_english_passed' => 'nullable|boolean',
            'instructor_department_position' => [
                'nullable', 'string', 'in:head,secretary',
                function ($attribute, $value, $fail) use ($request, $user) {
                    if (!$value) return;
                    $deptId = $request->input('instructor_department_id');
                    if (!$deptId) return;
                    $dept = Department::find($deptId);
                    if (!$dept) return;
                    $conflictUserId = $value === 'head' ? $dept->head_user_id : $dept->secretary_user_id;
                    if ($conflictUserId && (int)$conflictUserId !== (int)$user->id) {
                        $conflictUser = User::find($conflictUserId);
                        $posLabel = $value === 'head' ? 'หัวหน้าภาควิชา' : 'เลขานุการภาควิชา';
                        $fail("ภาควิชา {$dept->name} มี{$posLabel}อยู่แล้วคือ {$conflictUser?->name} กรุณาถอดถอนท่านเดิมก่อน หรือเลือกภาควิชาอื่น");
                    }
                }
            ],
        ]);

        if ($reqInstructor === 'required') {
            $totalPct = (int)$request->instructor_teaching_pct + (int)$request->instructor_research_pct + (int)$request->instructor_service_pct + (int)$request->instructor_culture_pct + (int)$request->instructor_other_pct;
            if ($totalPct !== 100) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'instructor_teaching_pct' => "สัดส่วนภาระงานรวมต้องเท่ากับ 100% (ปัจจุบัน {$totalPct}%)"
                ]);
            }
        }

        DB::transaction(function () use ($validated, $user, $request) {
            $user->update([
                'prefix'    => $validated['prefix'] ?? null,
                'name'      => $validated['name'],
                'email'     => $validated['email'],
                'is_active' => $validated['is_active'],
            ]);

            if ($request->filled('password')) {
                $user->update(['password' => Hash::make($validated['password'])]);
            }

            // Sync roles
            UserRole::where('user_id', $user->id)->delete();
            foreach ($validated['roles'] as $role) {
                UserRole::create([
                    'user_id'    => $user->id,
                    'role'       => $role,
                    'is_primary' => $role === $validated['primary_role'],
                ]);
            }

            // Sync instructor profile
            if (in_array('instructor', $validated['roles'])) {
                InstructorProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'title'          => $validated['instructor_title'] ?? null,
                        'employee_id'    => $validated['instructor_employee_id'] ?? null,
                        'department_id'  => $validated['instructor_department_id'] ?? null,
                        'employment_type' => $validated['instructor_employment_type'] ?? null,
                        'hired_at'       => $validated['instructor_hired_at'] ?? null,
                        'academic_degree' => $validated['instructor_academic_degree'] ?? null,
                        'teaching_pct'   => $validated['instructor_teaching_pct'] ?? 0,
                        'research_pct'   => $validated['instructor_research_pct'] ?? 0,
                        'service_pct'    => $validated['instructor_service_pct'] ?? 0,
                        'culture_pct'    => $validated['instructor_culture_pct'] ?? 0,
                        'other_pct'      => $validated['instructor_other_pct'] ?? 0,
                        'teaching_quota' => $validated['instructor_teaching_quota'] ?? 0,
                        'is_english_passed' => $request->boolean('instructor_is_english_passed'),
                    ]
                );

                // Handle department positions (clear old positions first)
                Department::where('head_user_id', $user->id)->update(['head_user_id' => null]);
                Department::where('secretary_user_id', $user->id)->update(['secretary_user_id' => null]);

                if ($request->filled('instructor_department_position') && $request->filled('instructor_department_id')) {
                    if ($validated['instructor_department_position'] === 'head') {
                        Department::where('id', $validated['instructor_department_id'])->update(['head_user_id' => $user->id]);
                    } else if ($validated['instructor_department_position'] === 'secretary') {
                        Department::where('id', $validated['instructor_department_id'])->update(['secretary_user_id' => $user->id]);
                    }
                }
            } else {
                // Remove profile if instructor role removed
                InstructorProfile::where('user_id', $user->id)->delete();
            }
        });

        return redirect()->route('admin.users')->with('success', 'อัปเดตข้อมูลผู้ใช้เรียบร้อยแล้ว');
    }

    public function toggleStatus(User $user)
    {
        $user->update(['is_active' => !$user->is_active]);
        return response()->json(['success' => true, 'is_active' => $user->is_active]);
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('admin.users')->with('success', 'ลบผู้ใช้เรียบร้อยแล้ว');
    }

    public function settings()
    {
        return view('admin.settings');
    }
}
