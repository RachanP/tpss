<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRole;
use App\Models\Department;
use App\Models\InstructorProfile;
use App\Models\SystemSetting;
use App\Services\AuditLogger;
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
        $firstGroup = !empty($paCriteria) ? reset($paCriteria) : null;
        $firstField = $firstGroup ? reset($firstGroup) : null;
        if (empty($paCriteria) || !is_array($firstField)) {
            $paCriteria = \App\Http\Controllers\AdminSettingController::defaultPaCriteria();
        }

        $systemSettings = [
            'teaching_quota_hours' => SystemSetting::get('teaching_quota_hours', 1610)
        ];

        $departmentCount = $departments->count();

        return view('admin.users.index', compact('users', 'stats', 'departments', 'systemSettings', 'paCriteria', 'departmentCount'));
    }

    public function store(Request $request)
    {
        $roles        = $request->input('roles', []);
        $isInstructor = in_array('instructor', $roles);
        $needsDept    = $isInstructor || in_array('course_head', $roles);
        $needsProfile = $needsDept || in_array('executive', $roles);

        $reqTitle  = $needsProfile ? 'required' : 'nullable';
        $reqDept   = $needsDept    ? 'required' : 'nullable';
        $reqDegree = $needsProfile ? 'required' : 'nullable'; // degree needed for name display
        $reqEmpl   = $isInstructor ? 'required' : 'nullable';

        $validated = $request->validate([
            'username'     => 'required|string|max:100|unique:users',
            'prefix'       => 'nullable|string|max:50',
            'name'         => 'required|string|max:255',
            'email'        => 'required|string|email|max:255|unique:users',
            'password'     => 'required|string|min:8',
            'employee_id'  => "$reqEmpl|string|max:50|unique:users,employee_id",
            'roles'        => 'required|array|min:1',
            'primary_role' => ['required', 'string', Rule::in($roles)],
            'is_active'    => 'boolean',
            // profile fields — required level depends on role
            'instructor_title'           => "$reqTitle|string|max:100",
            'instructor_department_id'   => "$reqDept|integer|exists:departments,id",
            'instructor_academic_degree' => "$reqDegree|string|max:100",
            'instructor_employment_type' => "$reqEmpl|string|max:100",
            'instructor_hired_at'        => "$reqEmpl|date",
            'instructor_teaching_pct'    => "$reqEmpl|integer|min:0|max:100",
            'instructor_research_pct'    => "$reqEmpl|integer|min:0|max:100",
            'instructor_service_pct'     => "$reqEmpl|integer|min:0|max:100",
            'instructor_culture_pct'     => "$reqEmpl|integer|min:0|max:100",
            'instructor_other_pct'       => "$reqEmpl|integer|min:0|max:100",
            'instructor_teaching_quota'  => 'nullable|integer|min:0',
            'instructor_is_english_passed' => 'nullable|boolean',
            'instructor_department_position' => 'nullable|string|in:head,secretary',
        ], [
            'username.required'   => 'กรุณากรอกชื่อผู้ใช้งาน',
            'username.unique'     => 'ชื่อผู้ใช้งานนี้มีอยู่ในระบบแล้ว กรุณาใช้ชื่ออื่น',
            'name.required'       => 'กรุณากรอกชื่อ-นามสกุล',
            'email.required'      => 'กรุณากรอกอีเมล',
            'email.email'         => 'รูปแบบอีเมลไม่ถูกต้อง',
            'email.unique'        => 'อีเมลนี้มีอยู่ในระบบแล้ว กรุณาใช้อีเมลอื่น',
            'password.required'   => 'กรุณากรอกรหัสผ่าน',
            'password.min'        => 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร',
            'employee_id.unique'  => 'รหัสพนักงานนี้มีอยู่ในระบบแล้ว กรุณาตรวจสอบข้อมูล',
            'roles.required'      => 'กรุณาเลือกสิทธิ์อย่างน้อย 1 บทบาท',
        ]);

        if ($isInstructor) {
            $totalPct = (int)$request->instructor_teaching_pct + (int)$request->instructor_research_pct + (int)$request->instructor_service_pct + (int)$request->instructor_culture_pct + (int)$request->instructor_other_pct;
            if ($totalPct !== 100) {
                return back()->withErrors([
                    'instructor_teaching_pct' => "สัดส่วนภาระงานรวมต้องเท่ากับ 100% (ปัจจุบัน {$totalPct}%)"
                ])->withInput();
            }
        }

        $createdUser = null;
        DB::transaction(function () use ($validated, $request, $needsProfile, $isInstructor, &$createdUser) {
            $user = User::create([
                'username'    => $validated['username'],
                'prefix'      => $validated['prefix'] ?? null,
                'name'        => $validated['name'],
                'email'       => $validated['email'],
                'password'    => $validated['password'],
                'employee_id' => $validated['employee_id'] ?? null,
                'is_active'   => $validated['is_active'] ?? true,
            ]);
            $createdUser = $user;

            foreach ($validated['roles'] as $role) {
                UserRole::create([
                    'user_id'    => $user->id,
                    'role'       => $role,
                    'is_primary' => $role === $validated['primary_role'],
                ]);
            }

            // Save profile for instructor/course_head/executive
            if ($needsProfile) {
                $profileData = [
                    'user_id'         => $user->id,
                    'title'           => $validated['instructor_title'] ?? null,
                    'department_id'   => $validated['instructor_department_id'] ?? null,
                    'academic_degree' => $validated['instructor_academic_degree'] ?? null,
                ];
                if ($isInstructor) {
                    $profileData += [
                        'employment_type'   => $validated['instructor_employment_type'] ?? null,
                        'hired_at'          => $validated['instructor_hired_at'] ?? null,
                        'teaching_pct'      => $validated['instructor_teaching_pct'] ?? 0,
                        'research_pct'      => $validated['instructor_research_pct'] ?? 0,
                        'service_pct'       => $validated['instructor_service_pct'] ?? 0,
                        'culture_pct'       => $validated['instructor_culture_pct'] ?? 0,
                        'other_pct'         => $validated['instructor_other_pct'] ?? 0,
                        'teaching_quota'    => $validated['instructor_teaching_quota'] ?? 0,
                        'is_english_passed' => $request->boolean('instructor_is_english_passed'),
                    ];
                }
                InstructorProfile::create($profileData);

                // Handle department positions — clear old holder first, then assign new
                if ($request->filled('instructor_department_position') && $request->filled('instructor_department_id')) {
                    $deptId = $validated['instructor_department_id'];
                    if ($validated['instructor_department_position'] === 'head') {
                        Department::where('head_user_id', $user->id)->update(['head_user_id' => null]);
                        Department::where('id', $deptId)->update(['head_user_id' => $user->id]);
                    } else if ($validated['instructor_department_position'] === 'secretary') {
                        Department::where('secretary_user_id', $user->id)->update(['secretary_user_id' => null]);
                        Department::where('id', $deptId)->update(['secretary_user_id' => $user->id]);
                    }
                }
            }
        });

        \App\Http\Controllers\Admin\AlertController::flushCache();

        if ($createdUser) {
            AuditLogger::log(
                action:      'ผู้ใช้และสิทธิ์.สร้าง',
                table:       'users',
                recordId:    $createdUser->id,
                oldValues:   null,
                newValues:   [
                    'name'         => $createdUser->name,
                    'username'     => $createdUser->username,
                    'email'        => $createdUser->email,
                    'roles'        => $validated['roles'],
                    'primary_role' => $validated['primary_role'],
                    'is_active'    => $createdUser->is_active,
                ],
                description: "สร้างผู้ใช้ {$createdUser->name} ({$createdUser->username})",
            );
        }

        return redirect()->route('admin.users')->with('success', 'เพิ่มผู้ใช้เรียบร้อยแล้ว');
    }

    public function update(Request $request, User $user)
    {
        $roles        = $request->input('roles', []);
        $isInstructor = in_array('instructor', $roles);
        $needsDept    = $isInstructor || in_array('course_head', $roles);
        $needsProfile = $needsDept || in_array('executive', $roles);

        $reqTitle  = $needsProfile ? 'required' : 'nullable';
        $reqDept   = $needsDept    ? 'required' : 'nullable';
        $reqDegree = $needsProfile ? 'required' : 'nullable';
        $reqEmpl   = $isInstructor ? 'required' : 'nullable';

        $validated = $request->validate([
            'username'     => 'required|string|max:100|unique:users,username,' . $user->id,
            'prefix'       => 'nullable|string|max:50',
            'name'         => 'required|string|max:255',
            'email'        => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password'     => 'nullable|string|min:8',
            'employee_id'  => "$reqEmpl|string|max:50|unique:users,employee_id," . $user->id,
            'roles'        => 'required|array|min:1',
            'primary_role' => ['required', 'string', Rule::in($roles)],
            'is_active'    => 'required|boolean',
            // profile fields — required level depends on role
            'instructor_title'           => "$reqTitle|string|max:100",
            'instructor_department_id'   => "$reqDept|integer|exists:departments,id",
            'instructor_academic_degree' => "$reqDegree|string|max:100",
            'instructor_employment_type' => "$reqEmpl|string|max:100",
            'instructor_hired_at'        => "$reqEmpl|date",
            'instructor_teaching_pct'    => "$reqEmpl|integer|min:0|max:100",
            'instructor_research_pct'    => "$reqEmpl|integer|min:0|max:100",
            'instructor_service_pct'     => "$reqEmpl|integer|min:0|max:100",
            'instructor_culture_pct'     => "$reqEmpl|integer|min:0|max:100",
            'instructor_other_pct'       => "$reqEmpl|integer|min:0|max:100",
            'instructor_teaching_quota'  => 'nullable|integer|min:0',
            'instructor_is_english_passed' => 'nullable|boolean',
            'instructor_department_position' => 'nullable|string|in:head,secretary',
        ], [
            'username.required'   => 'กรุณากรอกชื่อผู้ใช้งาน',
            'username.unique'     => 'ชื่อผู้ใช้งานนี้มีอยู่ในระบบแล้ว กรุณาใช้ชื่ออื่น',
            'name.required'       => 'กรุณากรอกชื่อ-นามสกุล',
            'email.required'      => 'กรุณากรอกอีเมล',
            'email.email'         => 'รูปแบบอีเมลไม่ถูกต้อง',
            'email.unique'        => 'อีเมลนี้มีอยู่ในระบบแล้ว กรุณาใช้อีเมลอื่น',
            'password.min'        => 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร',
            'employee_id.unique'  => 'รหัสพนักงานนี้มีอยู่ในระบบแล้ว กรุณาตรวจสอบข้อมูล',
            'roles.required'      => 'กรุณาเลือกสิทธิ์อย่างน้อย 1 บทบาท',
        ]);

        if ($isInstructor) {
            $totalPct = (int)$request->instructor_teaching_pct + (int)$request->instructor_research_pct + (int)$request->instructor_service_pct + (int)$request->instructor_culture_pct + (int)$request->instructor_other_pct;
            if ($totalPct !== 100) {
                return back()->withErrors([
                    'instructor_teaching_pct' => "สัดส่วนภาระงานรวมต้องเท่ากับ 100% (ปัจจุบัน {$totalPct}%)"
                ])->withInput();
            }
        }

        // Snapshot for audit diff — captured before the transaction mutates the record
        $auditBefore = $user->only(['name', 'username', 'email', 'is_active']);
        $beforeRoles  = $user->roles()->orderBy('role')->pluck('role')->all();

        DB::transaction(function () use ($validated, $user, $request, $needsProfile, $isInstructor) {
            $user->update([
                'username'    => $validated['username'],
                'prefix'      => $validated['prefix'] ?? null,
                'name'        => $validated['name'],
                'email'       => $validated['email'],
                'employee_id' => $validated['employee_id'] ?? null,
                'is_active'   => $validated['is_active'],
            ]);

            if ($request->filled('password')) {
                $user->update(['password' => $validated['password']]);
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

            // Sync profile for instructor/course_head/executive
            if ($needsProfile) {
                $profileData = [
                    'title'           => $validated['instructor_title'] ?? null,
                    'department_id'   => $validated['instructor_department_id'] ?? null,
                    'academic_degree' => $validated['instructor_academic_degree'] ?? null,
                ];
                if ($isInstructor) {
                    $profileData += [
                        'employment_type'   => $validated['instructor_employment_type'] ?? null,
                        'hired_at'          => $validated['instructor_hired_at'] ?? null,
                        'teaching_pct'      => $validated['instructor_teaching_pct'] ?? 0,
                        'research_pct'      => $validated['instructor_research_pct'] ?? 0,
                        'service_pct'       => $validated['instructor_service_pct'] ?? 0,
                        'culture_pct'       => $validated['instructor_culture_pct'] ?? 0,
                        'other_pct'         => $validated['instructor_other_pct'] ?? 0,
                        'teaching_quota'    => $validated['instructor_teaching_quota'] ?? 0,
                        'is_english_passed' => $request->boolean('instructor_is_english_passed'),
                    ];
                }
                InstructorProfile::updateOrCreate(['user_id' => $user->id], $profileData);

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
                // Remove profile when neither instructor nor course_head
                InstructorProfile::where('user_id', $user->id)->delete();
            }
        });

        \App\Http\Controllers\Admin\AlertController::flushCache();

        // Diff user fields + roles; skip audit if truly nothing changed
        $user->refresh();
        $auditAfter = $user->only(['name', 'username', 'email', 'is_active']);
        $afterRoles  = collect($validated['roles'])->sort()->values()->all();
        $diff = AuditLogger::diff($auditBefore, $auditAfter);
        if ($beforeRoles !== $afterRoles) {
            $diff['old']['roles'] = $beforeRoles;
            $diff['new']['roles'] = $afterRoles;
        }
        if (!empty($diff['old']) || !empty($diff['new'])) {
            AuditLogger::log(
                action:      'ผู้ใช้และสิทธิ์.แก้ไข',
                table:       'users',
                recordId:    $user->id,
                oldValues:   $diff['old'] ?: null,
                newValues:   $diff['new'] ?: null,
                description: "แก้ไขข้อมูลผู้ใช้ {$user->name}",
            );
        }

        return redirect()->route('admin.users')->with('success', 'อัปเดตข้อมูลผู้ใช้เรียบร้อยแล้ว');
    }

    public function toggleStatus(User $user)
    {
        $wasActive = (bool) $user->is_active;
        $user->update(['is_active' => !$user->is_active]);
        $user->refresh();

        $verb = $user->is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน';
        AuditLogger::log(
            action:      'ผู้ใช้และสิทธิ์.เปลี่ยนสถานะ',
            table:       'users',
            recordId:    $user->id,
            oldValues:   ['is_active' => $wasActive],
            newValues:   ['is_active' => $user->is_active],
            description: "{$verb}บัญชีผู้ใช้ {$user->name}",
        );

        return response()->json(['success' => true, 'is_active' => $user->is_active]);
    }

    public function destroy(User $user)
    {
        $snapshot = ['name' => $user->name, 'username' => $user->username, 'email' => $user->email];
        $userId   = $user->id;

        try {
            DB::transaction(function () use ($user) {
                // Release department positions held by this user (FK has no cascade)
                Department::where('head_user_id', $user->id)->update(['head_user_id' => null]);
                Department::where('secretary_user_id', $user->id)->update(['secretary_user_id' => null]);
                // Remove instructor profile (FK has no cascade)
                InstructorProfile::where('user_id', $user->id)->delete();
                // user_roles cascades on delete
                $user->delete();
            });

            AuditLogger::log(
                action:      'ผู้ใช้และสิทธิ์.ลบ',
                table:       'users',
                recordId:    $userId,
                oldValues:   $snapshot,
                newValues:   null,
                description: "ลบบัญชีผู้ใช้ {$snapshot['name']} ({$snapshot['username']})",
            );

            \App\Http\Controllers\Admin\AlertController::flushCache();
            return redirect()->route('admin.users')->with('success', 'ลบผู้ใช้เรียบร้อยแล้ว');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('admin.users')->with('error', 'ไม่สามารถลบผู้ใช้นี้ได้ เนื่องจากมีข้อมูลผูกพันอยู่ในระบบ (เช่น เป็นหัวหน้าวิชา หรือมีตารางสอน)');
        }
    }

    public function importUsers(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt|max:5120']);

        $file   = $request->file('csv_file');
        $handle = $this->openCsvHandle($file);

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return back()->with('error', 'ไฟล์ CSV ว่างเปล่า');
        }
        $header = $this->normalizeCsvHeader($header);
        $missing = $this->missingCsvHeaders($header, ['username', 'email', 'name', 'password', 'roles', 'primary_role']);
        if ($missing) {
            fclose($handle);
            return back()->with('error', 'หัวไฟล์ CSV ไม่ครบ: ' . implode(', ', $missing));
        }

        $departments     = Department::pluck('id', 'name')->toArray();
        $validRoles      = ['admin', 'staff', 'course_head', 'executive', 'instructor'];
        $updateOnDup     = $request->boolean('update_on_duplicate');
        $paCriteria      = json_decode(\App\Models\SystemSetting::get('pa_criteria_config', '{}'), true);

        $successCount = 0;
        $errors       = [];
        $warnings     = [];
        $row          = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if (!$this->csvRowHasData($data)) continue;

            $csv = $this->combineCsvRow($header, $data, $row, $errors);
            if ($csv === null) continue;
            $username    = trim($csv['username'] ?? '');
            $email       = trim($csv['email'] ?? '');
            $name        = trim($csv['name'] ?? '');
            $password    = trim($csv['password'] ?? '');
            $rolesStr    = trim($csv['roles'] ?? '');
            $primaryRole = trim($csv['primary_role'] ?? '');

            if (!$username || !$email || !$name || !$password || !$rolesStr || !$primaryRole) {
                $errors[] = "แถว {$row}: ข้อมูลบังคับไม่ครบ (username, email, name, password, roles, primary_role)";
                continue;
            }

            $roles = array_values(array_filter(array_map('trim', explode('|', $rolesStr))));
            $invalid = array_diff($roles, $validRoles);
            if ($invalid) {
                $errors[] = "แถว {$row}: role ไม่ถูกต้อง: " . implode(', ', $invalid);
                continue;
            }
            if (!in_array($primaryRole, $roles)) {
                $errors[] = "แถว {$row}: primary_role '{$primaryRole}' ต้องอยู่ใน roles";
                continue;
            }

            $isInstructor = in_array('instructor', $roles);
            $needsDept    = $isInstructor || in_array('course_head', $roles);
            $needsProfile = $needsDept || in_array('executive', $roles);

            if ($needsProfile) {
                $missingFields = [];
                if (empty(trim($csv['title'] ?? '')))           $missingFields[] = 'title';
                if (empty(trim($csv['academic_degree'] ?? ''))) $missingFields[] = 'academic_degree';
                if ($needsDept && empty(trim($csv['department_name'] ?? ''))) $missingFields[] = 'department_name';
                if ($isInstructor) {
                    if (empty(trim($csv['employee_id'] ?? '')))    $missingFields[] = 'employee_id';
                    if (empty(trim($csv['employment_type'] ?? ''))) $missingFields[] = 'employment_type';
                    if (empty(trim($csv['hired_date'] ?? '')))      $missingFields[] = 'hired_date';
                }
                if ($missingFields) {
                    $errors[] = "แถว {$row}: ข้อมูลโปรไฟล์ไม่ครบ (" . implode(', ', $missingFields) . ")";
                    continue;
                }

                if ($isInstructor) {
                    $t = max(0, min(100, (int)(trim($csv['teaching_pct'] ?? '0') ?: '0')));
                    $r = max(0, min(100, (int)(trim($csv['research_pct'] ?? '0') ?: '0')));
                    $s = max(0, min(100, (int)(trim($csv['service_pct'] ?? '0') ?: '0')));
                    $c = max(0, min(100, (int)(trim($csv['culture_pct'] ?? '0') ?: '0')));
                    $o = max(0, min(100, (int)(trim($csv['other_pct'] ?? '0') ?: '0')));
                    $total = $t + $r + $s + $c + $o;
                    if ($total !== 100) {
                        $errors[] = "แถว {$row}: สัดส่วนภาระงานรวมของอาจารย์ต้องเท่ากับ 100% (ปัจจุบัน {$total}%)";
                        continue;
                    }
                }
            }

            // Check for existing user by email or username
            $existingByEmail    = User::where('email', $email)->first();
            $existingByUsername = User::where('username', $username)->first();
            $existing = $existingByEmail ?? $existingByUsername;

            if ($existing && !$updateOnDup) {
                $key = $existingByEmail ? "email '{$email}'" : "username '{$username}'";
                $errors[] = "แถว {$row}: {$key} มีในระบบแล้ว — ข้ามแถวนี้";
                continue;
            }

            try {
                DB::transaction(function () use ($csv, $username, $email, $name, $roles, $primaryRole, $departments, $existing, $password, &$warnings, $paCriteria, $row, $isInstructor, $needsProfile) {
                    $profileData = $this->buildProfileData($csv, $departments, $isInstructor);

                    if ($existing) {
                        // Update: name, prefix, employee_id, roles, profile — no password change
                        $existing->update([
                            'prefix'      => trim($csv['prefix'] ?? '') ?: null,
                            'name'        => $name,
                            'employee_id' => trim($csv['employee_id'] ?? '') ?: null,
                        ]);

                        UserRole::where('user_id', $existing->id)->delete();
                        foreach ($roles as $role) {
                            UserRole::create([
                                'user_id'    => $existing->id,
                                'role'       => $role,
                                'is_primary' => $role === $primaryRole,
                            ]);
                        }

                        if ($needsProfile && $profileData !== null) {
                            $profile = InstructorProfile::updateOrCreate(
                                ['user_id' => $existing->id],
                                $profileData
                            );
                            if ($isInstructor) {
                                $profileWarnings = $profile->getProfileWarnings($paCriteria);
                                if (!empty($profileWarnings)) {
                                    $warnings[] = "แถว {$row} ({$name}): " . implode(', ', $profileWarnings);
                                }
                            }
                        } else {
                            InstructorProfile::where('user_id', $existing->id)->delete();
                        }
                    } else {
                        $user = User::create([
                            'username'    => $username,
                            'prefix'      => trim($csv['prefix'] ?? '') ?: null,
                            'name'        => $name,
                            'email'       => $email,
                            'password'    => Hash::make($password),
                            'employee_id' => trim($csv['employee_id'] ?? '') ?: null,
                            'is_active'   => true,
                        ]);

                        foreach ($roles as $role) {
                            UserRole::create([
                                'user_id'    => $user->id,
                                'role'       => $role,
                                'is_primary' => $role === $primaryRole,
                            ]);
                        }

                        if ($needsProfile && $profileData !== null) {
                            $profile = InstructorProfile::create(array_merge(['user_id' => $user->id], $profileData));
                            if ($isInstructor) {
                                $profileWarnings = $profile->getProfileWarnings($paCriteria);
                                if (!empty($profileWarnings)) {
                                    $warnings[] = "แถว {$row} ({$name}): " . implode(', ', $profileWarnings);
                                }
                            }
                        }
                    }
                });
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = "แถว {$row}: เกิดข้อผิดพลาด — " . $e->getMessage();
            }
        }

        fclose($handle);

        \App\Http\Controllers\Admin\AlertController::flushCache();
        $msg = "นำเข้าสำเร็จ {$successCount} รายการ";
        $redirect = redirect()->route('admin.users')->with('success', $msg);
        
        if ($errors) {
            $redirect->with('import_errors', $errors);
        }
        if ($warnings) {
            $redirect->with('import_warnings', $warnings);
        }
        
        return $redirect;
    }

    private function buildProfileData(array $csv, array $departments, bool $isInstructor = false): ?array
    {
        $title    = trim($csv['title'] ?? '');
        $deptName = trim($csv['department_name'] ?? '');
        $hiredAt  = trim($csv['hired_date'] ?? '');

        if (!$title && !$deptName && !$hiredAt) {
            return null;
        }

        $deptId = null;
        if ($deptName) {
            $deptId = $departments[$deptName] ?? null;
            if (!$deptId) {
                foreach ($departments as $dbName => $id) {
                    if (str_replace('ภาควิชา', '', $dbName) === str_replace('ภาควิชา', '', $deptName) || str_contains($dbName, $deptName)) {
                        $deptId = $id;
                        break;
                    }
                }
            }
        }

        $profile = [
            'title'           => $title ?: null,
            'department_id'   => $deptId,
            'academic_degree' => trim($csv['academic_degree'] ?? '') ?: null,
        ];

        if ($isInstructor) {
            $parsedHiredAt = null;
            if ($hiredAt) {
                try {
                    $parsedHiredAt = str_contains($hiredAt, '/')
                        ? \Carbon\Carbon::createFromFormat('d/m/Y', $hiredAt)->format('Y-m-d')
                        : \Carbon\Carbon::parse($hiredAt)->format('Y-m-d');
                } catch (\Exception $e) {
                    try { $parsedHiredAt = \Carbon\Carbon::parse(str_replace('/', '-', $hiredAt))->format('Y-m-d'); }
                    catch (\Exception $e2) { $parsedHiredAt = null; }
                }
            }
            $profile += [
                'employment_type' => trim($csv['employment_type'] ?? '') ?: null,
                'hired_at'        => $parsedHiredAt,
                'teaching_pct'    => max(0, min(100, (int)(trim($csv['teaching_pct'] ?? '0') ?: '0'))),
                'research_pct'    => max(0, min(100, (int)(trim($csv['research_pct'] ?? '0') ?: '0'))),
                'service_pct'     => max(0, min(100, (int)(trim($csv['service_pct'] ?? '0') ?: '0'))),
                'culture_pct'     => max(0, min(100, (int)(trim($csv['culture_pct'] ?? '0') ?: '0'))),
                'other_pct'       => max(0, min(100, (int)(trim($csv['other_pct'] ?? '0') ?: '0'))),
                'teaching_quota'  => 0,
            ];
        }

        return $profile;
    }

    public function settings()
    {
        return view('admin.settings');
    }
}
