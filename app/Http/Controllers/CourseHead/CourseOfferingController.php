<?php

namespace App\Http\Controllers\CourseHead;

use App\Exceptions\BulkDestroyBlockedException;
use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\CourseRole;
use App\Models\StudentGroup;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CourseOfferingController extends Controller
{
    private const AUDIT_CATEGORY = 'รายวิชาและผู้รับผิดชอบ';

    public function index(Request $request): View
    {
        $coordinatorId = Auth::id();

        $availableYears = \App\Models\AcademicYear::query()
            ->whereIn('id', CourseOffering::query()
                ->where('coordinator_id', $coordinatorId)
                ->select('academic_year_id'))
            ->orderByDesc('name')
            ->orderByDesc('semester')
            ->get();

        $activeYear = \App\Models\AcademicYear::where('is_active', true)->first();
        $schedulingYear = $availableYears->firstWhere('phase', 'scheduling');
        $defaultYearId = $request->integer('year')
            ?: ($schedulingYear?->id
                ?? ($activeYear && $availableYears->contains('id', $activeYear->id) ? $activeYear->id : $availableYears->first()?->id));

        $offerings = CourseOffering::query()
            ->select('course_offerings.*')
            ->distinct()
            ->with(['course.curriculum', 'course.department', 'academicYear'])
            ->withCount(['studentGroups', 'instructorPool'])
            ->withSum('studentGroups as allocated_student_count', 'student_count')
            ->where('coordinator_id', $coordinatorId)
            ->when($defaultYearId, fn ($q) => $q->where('academic_year_id', $defaultYearId))
            ->latest('updated_at')
            ->get();

        $summary = [
            'total'     => $offerings->count(),
            'draft'     => $offerings->where('approval_status', 'draft')->count(),
            'pending'   => $offerings->where('approval_status', 'pending')->count(),
            'published' => $offerings->where('approval_status', 'published')->count(),
            'rejected'  => $offerings->where('approval_status', 'rejected')->count(),
        ];

        return view('course_head.course_offerings.index', [
            'offerings'       => $offerings,
            'availableYears'  => $availableYears,
            'selectedYearId'  => $defaultYearId,
            'summary'         => $summary,
        ]);
    }

    public function show(CourseOffering $courseOffering): View
    {
        $this->authorizeCourseHeadOffering($courseOffering);

        $courseOffering->load([
            'course.curriculum',
            'course.department',
            'academicYear',
            'coordinator',
            'studentGroups' => fn ($query) => $query->orderBy('group_code'),
            'instructorPool.instructorProfile.department',
        ])->loadCount('schedules');

        $availableInstructors = User::query()
            ->with('instructorProfile.department')
            ->where('is_active', true)
            ->whereHas('instructorProfile')
            ->whereHas('roles', fn ($q) => $q->whereIn('role', ['instructor', 'course_head']))
            ->orderBy('name')
            ->get();

        // Roles ที่เลือกได้ในชุดผู้สอน — ซ่อน "หัวหน้าวิชา" (auto-assigned ให้ coordinator)
        $courseRoles = CourseRole::orderBy('sort_order')
            ->where('name_th', '!=', 'หัวหน้าวิชา')
            ->get();

        return view('course_head.course_offerings.show', [
            'courseOffering' => $courseOffering,
            'availableInstructors' => $availableInstructors,
            'courseRoles' => $courseRoles,
            'teachingWeeks' => (int) SystemSetting::get('teaching_load_weeks', 39),
        ]);
    }

    public function update(Request $request, CourseOffering $courseOffering): RedirectResponse|JsonResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'course-info')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'ยังไม่เปิดช่วงจัดตาราง'], 403);
            }
            return $redirect;
        }

        $courseOffering->loadMissing('course');
        $requestedRotation = $request->boolean('requires_practicum_rotation');
        $defaultRotation = (bool) $courseOffering->course?->requires_practicum_rotation;
        $isRotationOverride = $requestedRotation !== $defaultRotation;

        try {
            $request->validate([
                'requires_practicum_rotation' => ['nullable', 'boolean'],
                'practicum_note' => [
                    Rule::requiredIf($isRotationOverride),
                    'nullable',
                    'string',
                    'max:1000',
                ],
            ], [
                'practicum_note.required' => 'กรุณาระบุหมายเหตุเมื่อการหมุนเวียนแหล่งฝึกของรอบนี้ต่างจากค่าเริ่มต้นใน Master Data',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => collect($e->errors())->flatten()->first() ?? 'ข้อมูลไม่ถูกต้อง',
                    'errors'  => $e->errors(),
                ], 422);
            }
            throw $e;
        }

        $auditFields = ['requires_practicum_rotation', 'practicum_note'];
        $auditBefore = $this->auditModelSnapshot($courseOffering, $auditFields);

        $courseOffering->update([
            'requires_practicum_rotation' => $requestedRotation,
            'practicum_note' => $isRotationOverride ? trim((string) $request->input('practicum_note')) : null,
        ]);

        $auditAfter = $this->auditModelSnapshot($courseOffering->fresh(), $auditFields);
        $diff = AuditLogger::diff($auditBefore, $auditAfter);
        $this->logCourseManagementUpdate(
            table: 'course_offerings',
            recordId: $courseOffering->id,
            oldValues: $diff['old'],
            newValues: $diff['new'] + $this->offeringAuditContext($courseOffering),
            description: "แก้ไขข้อมูลฝึกปฏิบัติของ {$this->offeringCourseLabel($courseOffering)}",
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'บันทึกแล้ว',
                'requires_practicum_rotation' => $courseOffering->requires_practicum_rotation,
                'practicum_note' => $courseOffering->practicum_note,
            ]);
        }

        return redirect()
            ->to(route('maker.course_offerings.show', $courseOffering) . '#course-info')
            ->with('success', 'บันทึกข้อมูลรายวิชาเรียบร้อยแล้ว');
    }

    public function storeInstructor(Request $request, CourseOffering $courseOffering): RedirectResponse|JsonResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'instructors')) return $redirect;
        $courseOffering->loadMissing('course');

        $validated = $request->validate([
            'user_id'        => ['required', 'integer', 'exists:users,id'],
            'course_role_id' => ['nullable', 'integer', 'exists:course_roles,id'],
        ]);

        $user = User::with('instructorProfile.department')->find($validated['user_id']);

        if (! $user || ! $user->is_active || ! $user->instructorProfile) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'เลือกได้เฉพาะอาจารย์ที่ยังใช้งานอยู่และมีข้อมูลโปรไฟล์อาจารย์'], 422);
            }
            return $this->redirectToInstructors($courseOffering)
                ->withErrors(['user_id' => 'เลือกได้เฉพาะอาจารย์ที่ยังใช้งานอยู่และมีข้อมูลโปรไฟล์อาจารย์'])
                ->withInput();
        }

        if (
            $courseOffering->course?->department_id
            && (int) $user->instructorProfile->department_id !== (int) $courseOffering->course->department_id
        ) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'เลือกได้เฉพาะอาจารย์ในภาควิชาของรายวิชานี้'], 422);
            }

            return $this->redirectToInstructors($courseOffering)
                ->withErrors(['user_id' => 'เลือกได้เฉพาะอาจารย์ในภาควิชาของรายวิชานี้'])
                ->withInput();
        }

        if ($courseOffering->instructorPool()->where('users.id', $user->id)->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'อาจารย์คนนี้อยู่ในชุดผู้สอนของรายวิชานี้แล้ว'], 422);
            }
            return $this->redirectToInstructors($courseOffering)
                ->withErrors(['user_id' => 'อาจารย์คนนี้อยู่ในชุดผู้สอนของรายวิชานี้แล้ว'])
                ->withInput();
        }

        $roleId = $validated['course_role_id'] ?? CourseRole::where('name_th', 'อาจารย์ผู้สอน')->value('id');

        $courseOffering->instructorPool()->attach($user->id, [
            'role_in_course' => 'instructor',
            'course_role_id' => $roleId,
        ]);

        $this->logCourseManagementCreate(
            table: 'course_offering_instructors',
            recordId: $courseOffering->id,
            newValues: $this->offeringInstructorAuditValues($courseOffering, $user, $roleId, 'instructor'),
            description: "เพิ่มผู้สอนในรายวิชา {$this->offeringCourseLabel($courseOffering)}",
        );

        if ($request->expectsJson()) {
            $role = $roleId ? CourseRole::find($roleId) : null;
            return response()->json([
                'id'             => $user->id,
                'name'           => $user->formatted_name,
                'department'     => $user->instructorProfile?->department?->name ?? '-',
                'course_role_id' => $roleId,
                'role_name'      => $role?->name_th,
                'is_coordinator' => false,
            ]);
        }

        return back()->with('success', 'เพิ่มอาจารย์ในรายวิชาเรียบร้อยแล้ว');
    }

    public function updateInstructorRole(Request $request, CourseOffering $courseOffering, User $user): JsonResponse|RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'instructors')) return $redirect;

        $validated = $request->validate([
            'course_role_id' => ['nullable', 'integer', 'exists:course_roles,id'],
        ]);

        if (! $courseOffering->instructorPool()->where('users.id', $user->id)->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'อาจารย์คนนี้ไม่อยู่ในชุดผู้สอน'], 422);
            }
            return $this->redirectToInstructors($courseOffering)
                ->withErrors(['user_id' => 'อาจารย์คนนี้ไม่อยู่ในชุดผู้สอน']);
        }

        $currentInstructor = $courseOffering->instructorPool()
            ->where('users.id', $user->id)
            ->first();
        $oldRoleId = $currentInstructor?->pivot?->course_role_id ? (int) $currentInstructor->pivot->course_role_id : null;
        $newRoleId = isset($validated['course_role_id']) ? (int) $validated['course_role_id'] : null;

        $courseOffering->instructorPool()->updateExistingPivot($user->id, [
            'course_role_id' => $newRoleId,
        ]);

        if ($oldRoleId !== $newRoleId) {
            $this->logCourseManagementUpdate(
                table: 'course_offering_instructors',
                recordId: $courseOffering->id,
                oldValues: $this->offeringInstructorAuditValues($courseOffering, $user, $oldRoleId, 'instructor'),
                newValues: $this->offeringInstructorAuditValues($courseOffering, $user, $newRoleId, 'instructor'),
                description: "เปลี่ยนบทบาทผู้สอนในรายวิชา {$this->offeringCourseLabel($courseOffering)}",
            );
        }

        if ($request->expectsJson()) {
            $role = $newRoleId ? CourseRole::find($newRoleId) : null;
            return response()->json([
                'ok'             => true,
                'course_role_id' => $newRoleId,
                'role_name'      => $role?->name_th,
            ]);
        }

        return back()->with('success', 'อัปเดตบทบาทเรียบร้อยแล้ว');
    }

    public function destroyInstructor(Request $request, CourseOffering $courseOffering, User $user): RedirectResponse|JsonResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'instructors')) return $redirect;

        if ((int) $courseOffering->coordinator_id === (int) $user->id) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'ไม่สามารถนำหัวหน้าวิชาหลักออกจากชุดผู้สอนได้'], 422);
            }
            return $this->redirectToInstructors($courseOffering)
                ->withErrors(['instructor_pool' => 'ไม่สามารถนำหัวหน้าวิชาหลักออกจากชุดผู้สอนได้']);
        }

        $currentInstructor = $courseOffering->instructorPool()
            ->where('users.id', $user->id)
            ->first();
        $oldRoleId = $currentInstructor?->pivot?->course_role_id ? (int) $currentInstructor->pivot->course_role_id : null;

        $detached = $courseOffering->instructorPool()->detach($user->id);

        if ($detached > 0) {
            $this->logCourseManagementDelete(
                table: 'course_offering_instructors',
                recordId: $courseOffering->id,
                oldValues: $this->offeringInstructorAuditValues($courseOffering, $user, $oldRoleId, $currentInstructor?->pivot?->role_in_course ?? 'instructor'),
                newValues: $this->offeringAuditContext($courseOffering),
                description: "ลบผู้สอนออกจากรายวิชา {$this->offeringCourseLabel($courseOffering)}",
            );
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'ลบอาจารย์ออกจากชุดผู้สอนเรียบร้อยแล้ว');
    }

    public function storeStudentGroup(Request $request, CourseOffering $courseOffering): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'student-groups')) return $redirect;

        $validated = $request->validate([
            'group_code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('student_groups', 'group_code')
                    ->where(fn ($query) => $query->where('course_offering_id', $courseOffering->id)),
            ],
            'student_count' => ['required', 'integer', 'min:1', 'max:9999'],
            'color_code' => ['nullable', 'string', 'max:10'],
        ]);

        if ($message = $this->studentCountLimitError($courseOffering, (int) $validated['student_count'])) {
            return $this->redirectToStudentGroups($courseOffering)
                ->withErrors(['student_count' => $message])
                ->withInput();
        }

        $studentGroup = $courseOffering->studentGroups()->create($validated);

        $this->logCourseManagementCreate(
            table: 'student_groups',
            recordId: $studentGroup->id,
            newValues: $this->studentGroupAuditValues($courseOffering, $studentGroup),
            description: "สร้างกลุ่มนักศึกษา {$studentGroup->group_code} ใน {$this->offeringCourseLabel($courseOffering)}",
        );

        return $this->redirectToStudentGroups($courseOffering)
            ->with('success', 'เพิ่มกลุ่มนักศึกษาเรียบร้อยแล้ว');
    }

    public function bulkStoreStudentGroups(Request $request, CourseOffering $courseOffering): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'student-groups')) return $redirect;

        $validated = $request->validate([
            'group_prefix'   => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9ก-๙_-]+$/u'],
            'start_number'   => ['required', 'integer', 'min:0', 'max:999'],
            'group_count'    => ['required', 'integer', 'min:1', 'max:100'],
            'group_counts'   => ['nullable', 'array'],
            'group_counts.*' => ['required_with:group_counts', 'integer', 'min:1', 'max:9999'],
        ], [
            'group_prefix.regex' => 'รหัสนำหน้ากลุ่มใช้ได้เฉพาะตัวอักษร ตัวเลข ขีดกลาง และขีดล่าง',
        ]);

        $groupCount = (int) $validated['group_count'];
        $customCounts = collect($validated['group_counts'] ?? [])
            ->take($groupCount)
            ->map(fn ($count) => (int) $count)
            ->values();

        $remainingStudents = $this->remainingStudentCount($courseOffering);
        if ($remainingStudents < 1) {
            return $this->redirectToStudentGroups($courseOffering)
                ->withErrors(['group_count' => 'จัดกลุ่มครบตามจำนวนนักศึกษาที่เปิดรับแล้ว'])
                ->withInput();
        }

        if ($customCounts->isNotEmpty() && $customCounts->count() !== $groupCount) {
            return $this->redirectToStudentGroups($courseOffering)
                ->withErrors(['group_counts' => 'จำนวนช่องนักศึกษาต่อกลุ่มต้องตรงกับจำนวนกลุ่ม'])
                ->withInput();
        }

        $totalStudents = $customCounts->isNotEmpty()
            ? $customCounts->sum()
            : $remainingStudents;

        if ($totalStudents < $groupCount) {
            return $this->redirectToStudentGroups($courseOffering)
                ->withErrors(['total_students' => 'จำนวนนักศึกษารวมต้องไม่น้อยกว่าจำนวนกลุ่ม'])
                ->withInput();
        }

        if ($message = $this->studentCountLimitError($courseOffering, $totalStudents)) {
            return $this->redirectToStudentGroups($courseOffering)
                ->withErrors(['total_students' => $message])
                ->withInput();
        }

        $prefix = trim($validated['group_prefix']);
        $startNumber = (int) $validated['start_number'];
        $groupCodes = collect(range(0, $groupCount - 1))
            ->map(fn ($offset) => $prefix . ($startNumber + $offset));

        $existingCodes = StudentGroup::query()
            ->where('course_offering_id', $courseOffering->id)
            ->whereIn('group_code', $groupCodes)
            ->pluck('group_code')
            ->all();

        if (!empty($existingCodes)) {
            return $this->redirectToStudentGroups($courseOffering)
                ->withErrors(['group_prefix' => 'มีรหัสกลุ่มซ้ำแล้ว: ' . implode(', ', $existingCodes)])
                ->withInput();
        }

        $baseCount = intdiv($totalStudents, $groupCount);
        $remainder = $totalStudents % $groupCount;
        $colors = ['#2563eb', '#16a34a', '#ca8a04', '#dc2626', '#7c3aed', '#0891b2', '#db2777', '#4f46e5', '#65a30d', '#ea580c'];

        $createdGroups = [];
        DB::transaction(function () use ($courseOffering, $groupCodes, $baseCount, $remainder, $customCounts, $colors, &$createdGroups) {
            foreach ($groupCodes->values() as $index => $groupCode) {
                $createdGroups[] = $courseOffering->studentGroups()->create([
                    'group_code' => $groupCode,
                    'student_count' => $customCounts->isNotEmpty()
                        ? $customCounts[$index]
                        : $baseCount + ($index < $remainder ? 1 : 0),
                    'color_code' => $colors[$index % count($colors)],
                ]);
            }
        });

        $this->logCourseManagementCreate(
            table: 'student_groups',
            recordId: $courseOffering->id,
            newValues: $this->bulkStudentGroupAuditValues($courseOffering, collect($createdGroups)),
            description: "สร้างกลุ่มนักศึกษาอัตโนมัติ {$groupCount} กลุ่ม ใน {$this->offeringCourseLabel($courseOffering)}",
        );

        return $this->redirectToStudentGroups($courseOffering)
            ->with('success', "สร้างกลุ่มนักศึกษา {$groupCount} กลุ่มเรียบร้อยแล้ว");
    }

    public function updateStudentGroup(
        Request $request,
        CourseOffering $courseOffering,
        StudentGroup $studentGroup
    ): RedirectResponse|JsonResponse {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'student-groups')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'ยังไม่เปิดช่วงจัดตาราง'], 403);
            }
            return $redirect;
        }
        $this->assertStudentGroupBelongsToOffering($courseOffering, $studentGroup);

        try {
            $validated = $request->validate([
                'group_code' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('student_groups', 'group_code')
                        ->where(fn ($query) => $query->where('course_offering_id', $courseOffering->id))
                        ->ignore($studentGroup->id),
                ],
                'student_count' => ['required', 'integer', 'min:1', 'max:9999'],
                'color_code' => ['nullable', 'string', 'max:10'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => collect($e->errors())->flatten()->first() ?? 'ข้อมูลไม่ถูกต้อง',
                    'errors'  => $e->errors(),
                ], 422);
            }
            throw $e;
        }

        if ($message = $this->studentCountLimitError(
            $courseOffering,
            (int) $validated['student_count'],
            $studentGroup
        )) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'errors' => ['student_count' => [$message]]], 422);
            }
            return $this->redirectToStudentGroups($courseOffering)
                ->withErrors(['student_count' => $message])
                ->withInput();
        }

        $auditFields = ['group_code', 'student_count', 'color_code'];
        $auditBefore = $this->auditModelSnapshot($studentGroup, $auditFields);

        $studentGroup->update($validated);

        $auditAfter = $this->auditModelSnapshot($studentGroup->fresh(), $auditFields);
        $diff = AuditLogger::diff($auditBefore, $auditAfter);
        $this->logCourseManagementUpdate(
            table: 'student_groups',
            recordId: $studentGroup->id,
            oldValues: $diff['old'],
            newValues: $diff['new'] + $this->offeringAuditContext($courseOffering),
            description: "แก้ไขกลุ่มนักศึกษา {$studentGroup->group_code} ใน {$this->offeringCourseLabel($courseOffering)}",
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'บันทึกแล้ว',
                'group' => [
                    'id' => $studentGroup->id,
                    'group_code' => $studentGroup->group_code,
                    'student_count' => $studentGroup->student_count,
                    'color_code' => $studentGroup->color_code,
                ],
            ]);
        }

        return $this->redirectToStudentGroups($courseOffering)
            ->with('success', 'อัปเดตกลุ่มนักศึกษาเรียบร้อยแล้ว');
    }

    public function bulkDestroyStudentGroups(Request $request, CourseOffering $courseOffering): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'student-groups')) return $redirect;

        $validated = $request->validate([
            'group_ids' => ['required', 'array', 'min:1'],
            'group_ids.*' => ['integer', 'exists:student_groups,id'],
        ], [
            'group_ids.required' => 'กรุณาเลือกกลุ่มที่ต้องการลบ',
            'group_ids.min' => 'กรุณาเลือกกลุ่มที่ต้องการลบ',
        ]);

        $requestedIds = array_values(array_unique(array_map('intval', $validated['group_ids'])));

        $groups = StudentGroup::query()
            ->where('course_offering_id', $courseOffering->id)
            ->whereIn('id', $requestedIds)
            ->get();

        // If any requested id doesn't belong to this offering → forbidden, not "not found"
        if ($groups->count() !== count($requestedIds)) {
            return $this->redirectToStudentGroups($courseOffering)->withErrors([
                'student_groups' => 'พบกลุ่มนักศึกษาที่ไม่ได้อยู่ในรายวิชานี้ กรุณาโหลดหน้าใหม่และลองอีกครั้ง',
            ]);
        }

        // Batch query downstream references once, then re-check inside a transaction
        // (resolves N+1 + race between check and delete)
        $deletedCount = 0;
        $deletedGroupValues = $groups
            ->sortBy('id')
            ->map(fn (StudentGroup $group) => $this->studentGroupAuditValues($courseOffering, $group))
            ->values();
        try {
            DB::transaction(function () use ($groups, &$deletedCount) {
                $ids = $groups->pluck('id')->all();
                $blockedIds = $this->studentGroupsWithDownstreamReferences($ids);

                if (! empty($blockedIds)) {
                    $blockedCodes = $groups->whereIn('id', $blockedIds)->pluck('group_code')->all();
                    throw new BulkDestroyBlockedException(
                        'ไม่สามารถลบกลุ่มที่ถูกอ้างอิงในตารางสอนได้: ' . implode(', ', $blockedCodes)
                    );
                }

                StudentGroup::whereIn('id', $ids)->delete();
                $deletedCount = count($ids);
            });
        } catch (BulkDestroyBlockedException $e) {
            return $this->redirectToStudentGroups($courseOffering)
                ->withErrors(['student_groups' => $e->getMessage()]);
        }

        $this->logCourseManagementDelete(
            table: 'student_groups',
            recordId: $courseOffering->id,
            oldValues: $this->bulkStudentGroupAuditValues($courseOffering, $deletedGroupValues),
            newValues: [
                'affected_count' => $deletedCount,
            ] + $this->offeringAuditContext($courseOffering),
            description: "ลบกลุ่มนักศึกษา {$deletedCount} กลุ่ม ใน {$this->offeringCourseLabel($courseOffering)}",
        );

        return $this->redirectToStudentGroups($courseOffering)
            ->with('warning', "ลบกลุ่มนักศึกษา {$deletedCount} กลุ่มเรียบร้อยแล้ว");
    }

    /**
     * Batch-check which student groups have schedule references (single query per table).
     * Returns IDs of groups that cannot be safely deleted.
     *
     * @param  array<int>  $ids
     * @return array<int>
     */
    private function studentGroupsWithDownstreamReferences(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $blocked = collect();

        if (Schema::hasTable('schedule_student_groups')) {
            $blocked = $blocked->merge(
                DB::table('schedule_student_groups')
                    ->whereIn('student_group_id', $ids)
                    ->pluck('student_group_id')
            );
        }

        if (Schema::hasTable('schedules') && Schema::hasColumn('schedules', 'student_group_id')) {
            $blocked = $blocked->merge(
                DB::table('schedules')
                    ->whereIn('student_group_id', $ids)
                    ->pluck('student_group_id')
            );
        }

        return $blocked->unique()->values()->all();
    }

    public function destroyStudentGroup(Request $request, CourseOffering $courseOffering, StudentGroup $studentGroup): RedirectResponse|JsonResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'student-groups')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'ยังไม่เปิดช่วงจัดตาราง'], 403);
            }
            return $redirect;
        }
        $this->assertStudentGroupBelongsToOffering($courseOffering, $studentGroup);

        if ($this->studentGroupHasDownstreamReferences($studentGroup)) {
            $msg = 'ไม่สามารถลบกลุ่มที่ถูกอ้างอิงในตารางสอนได้ กรุณาจัดการตารางสอนที่เกี่ยวข้องก่อน';
            if ($request->expectsJson()) {
                return response()->json(['message' => $msg], 422);
            }
            return $this->redirectToStudentGroups($courseOffering)->withErrors(['student_groups' => $msg]);
        }

        $auditValues = $this->studentGroupAuditValues($courseOffering, $studentGroup);
        $studentGroup->delete();

        $this->logCourseManagementDelete(
            table: 'student_groups',
            recordId: $studentGroup->id,
            oldValues: $auditValues,
            newValues: $this->offeringAuditContext($courseOffering),
            description: "ลบกลุ่มนักศึกษา {$auditValues['group_code']} ใน {$this->offeringCourseLabel($courseOffering)}",
        );

        if ($request->expectsJson()) {
            return response()->json(['message' => 'ลบกลุ่มแล้ว']);
        }

        return $this->redirectToStudentGroups($courseOffering)
            ->with('warning', 'ลบกลุ่มนักศึกษาเรียบร้อยแล้ว');
    }

    private function authorizeCourseHeadOffering(CourseOffering $courseOffering): void
    {
        abort_unless((int) $courseOffering->coordinator_id === (int) Auth::id(), 403);
    }

    private function requireSchedulingPhase(CourseOffering $courseOffering, string $section = 'course-info'): ?RedirectResponse
    {
        $courseOffering->loadMissing('academicYear');
        if ($courseOffering->academicYear?->phase !== 'scheduling') {
            return redirect()
                ->to(route('maker.course_offerings.show', $courseOffering) . '#' . $section)
                ->with('error', 'ยังไม่เปิดช่วงจัดตาราง — Admin ต้องเปิดช่วงจัดตารางก่อนจึงจะแก้ไขข้อมูลรายวิชาได้')
                ->with('error_section', $section);
        }
        return null;
    }

    private function assertStudentGroupBelongsToOffering(CourseOffering $courseOffering, StudentGroup $studentGroup): void
    {
        abort_unless((int) $studentGroup->course_offering_id === (int) $courseOffering->id, 404);
    }

    private function redirectToStudentGroups(CourseOffering $courseOffering): RedirectResponse
    {
        return redirect()->to(route('maker.course_offerings.show', $courseOffering) . '#student-groups');
    }

    private function redirectToInstructors(CourseOffering $courseOffering): RedirectResponse
    {
        return redirect()->to(route('maker.course_offerings.show', $courseOffering) . '#instructors');
    }

    private function studentGroupHasDownstreamReferences(StudentGroup $studentGroup): bool
    {
        if (Schema::hasTable('schedule_student_groups') &&
            DB::table('schedule_student_groups')->where('student_group_id', $studentGroup->id)->exists()) {
            return true;
        }

        return Schema::hasTable('schedules') &&
            Schema::hasColumn('schedules', 'student_group_id') &&
            DB::table('schedules')->where('student_group_id', $studentGroup->id)->exists();
    }

    private function studentCountLimitError(
        CourseOffering $courseOffering,
        int $newStudentCount,
        ?StudentGroup $ignoreGroup = null
    ): ?string {
        $limit = $this->studentCountLimit($courseOffering);

        if (! $limit || $limit < 1) {
            return 'กรุณากำหนดจำนวนนักศึกษารวมของรายวิชาก่อนเพิ่มกลุ่มนักศึกษา';
        }

        $currentTotal = $courseOffering->studentGroups()
            ->when($ignoreGroup, fn ($query) => $query->where('id', '!=', $ignoreGroup->id))
            ->sum('student_count');

        if ($currentTotal + $newStudentCount > $limit) {
            return 'จำนวนนักศึกษารวมของทุกกลุ่มต้องไม่เกินจำนวนนักศึกษารวมของรายวิชา';
        }

        return null;
    }

    private function remainingStudentCount(CourseOffering $courseOffering): int
    {
        $limit = $this->studentCountLimit($courseOffering);
        if (! $limit || $limit < 1) {
            return 0;
        }

        return max(0, $limit - (int) $courseOffering->studentGroups()->sum('student_count'));
    }

    private function studentCountLimit(CourseOffering $courseOffering): int
    {
        $courseOffering->loadMissing('course');

        return (int) ($courseOffering->total_student_count ?: $courseOffering->course?->capacity ?: 0);
    }

    private function auditModelSnapshot(object $model, array $fields): array
    {
        return collect($fields)
            ->mapWithKeys(fn (string $field) => [$field => $model->{$field}])
            ->all();
    }

    private function offeringAuditContext(CourseOffering $courseOffering): array
    {
        $courseOffering->loadMissing(['course', 'academicYear']);

        return [
            'course_offering_id' => $courseOffering->id,
            'course_id' => $courseOffering->course_id,
            'course_code' => $courseOffering->course?->course_code,
            'course_name' => $courseOffering->course?->name_th,
            'academic_year' => $courseOffering->academicYear?->name,
            'semester' => $courseOffering->academicYear?->semester,
        ];
    }

    private function offeringCourseLabel(CourseOffering $courseOffering): string
    {
        $courseOffering->loadMissing('course');

        return trim(($courseOffering->course?->course_code ?? 'รายวิชา') . ' ' . ($courseOffering->course?->name_th ?? ''));
    }

    private function offeringInstructorAuditValues(
        CourseOffering $courseOffering,
        User $user,
        ?int $courseRoleId,
        string $roleInCourse
    ): array {
        return [
            'user_id' => $user->id,
            'instructor_name' => $user->formatted_name ?? $user->name,
            'course_role_id' => $courseRoleId,
            'course_role_name' => $courseRoleId ? CourseRole::find($courseRoleId)?->name_th : null,
            'role_in_course' => $roleInCourse,
        ] + $this->offeringAuditContext($courseOffering);
    }

    private function studentGroupAuditValues(CourseOffering $courseOffering, StudentGroup $studentGroup): array
    {
        return [
            'student_group_id' => $studentGroup->id,
            'group_code' => $studentGroup->group_code,
            'student_count' => $studentGroup->student_count,
            'color_code' => $studentGroup->color_code,
        ] + $this->offeringAuditContext($courseOffering);
    }

    private function bulkStudentGroupAuditValues(CourseOffering $courseOffering, iterable $groups): array
    {
        $collection = collect($groups)->values();

        return [
            'affected_count' => $collection->count(),
            'sample_group_ids' => $collection->pluck('student_group_id')->filter()->take(5)->values()->all()
                ?: $collection->pluck('id')->filter()->take(5)->values()->all(),
            'sample_group_codes' => $collection->pluck('group_code')->take(5)->values()->all(),
            'total_student_count' => $collection->sum('student_count'),
        ] + $this->offeringAuditContext($courseOffering);
    }

    private function logCourseManagementCreate(string $table, int $recordId, array $newValues, string $description): void
    {
        AuditLogger::log(
            action: self::AUDIT_CATEGORY . '.สร้าง',
            table: $table,
            recordId: $recordId,
            oldValues: null,
            newValues: $newValues,
            category: self::AUDIT_CATEGORY,
            description: $description,
        );
    }

    private function logCourseManagementUpdate(string $table, int $recordId, array $oldValues, array $newValues, string $description): void
    {
        if (empty($oldValues)) {
            return;
        }

        AuditLogger::log(
            action: self::AUDIT_CATEGORY . '.แก้ไข',
            table: $table,
            recordId: $recordId,
            oldValues: $oldValues,
            newValues: $newValues,
            category: self::AUDIT_CATEGORY,
            description: $description,
        );
    }

    private function logCourseManagementDelete(string $table, int $recordId, array $oldValues, ?array $newValues, string $description): void
    {
        AuditLogger::log(
            action: self::AUDIT_CATEGORY . '.ลบ',
            table: $table,
            recordId: $recordId,
            oldValues: $oldValues,
            newValues: $newValues,
            category: self::AUDIT_CATEGORY,
            description: $description,
        );
    }
}
