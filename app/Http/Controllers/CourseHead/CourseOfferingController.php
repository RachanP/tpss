<?php

namespace App\Http\Controllers\CourseHead;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\CourseRole;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NavigationBadgeService;
use App\Services\ReferenceDataCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
                ->withActiveCourse()
                ->where('coordinator_id', $coordinatorId)
                ->select('academic_year_id'))
            ->orderByDesc('name')
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
            ->withCount(['instructorPool'])
            ->withActiveCourse()
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
            'coordinatorEmptyStateKey' => \App\Support\CoordinatorEmptyState::forCoordinator((int) $coordinatorId),
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
        $courseRoles = app(ReferenceDataCache::class)
            ->courseRoles()
            ->filter(fn ($role) => $role->name_th !== 'หัวหน้าวิชา')
            ->values();

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
        NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);

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
        NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);

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

    /**
     * V2 delegation: หัวหน้าวิชา toggle สิทธิ์ "ให้ช่วยจัดตาราง" ของอาจารย์ในชุดผู้สอน
     * (schedule_permission: 'view' = ดูอย่างเดียว · 'schedule' = ช่วยจัดตาราง offering นี้ได้)
     */
    public function updateInstructorPermission(Request $request, CourseOffering $courseOffering, User $user): JsonResponse|RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, 'instructors')) return $redirect;

        $validated = $request->validate([
            'schedule_permission' => ['required', 'in:view,schedule'],
        ]);

        if ((int) $courseOffering->coordinator_id === (int) $user->id) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'หัวหน้าวิชาจัดตารางได้อยู่แล้ว ไม่ต้องมอบหมาย'], 422);
            }
            return $this->redirectToInstructors($courseOffering)
                ->withErrors(['instructor_pool' => 'หัวหน้าวิชาจัดตารางได้อยู่แล้ว ไม่ต้องมอบหมาย']);
        }

        $currentInstructor = $courseOffering->instructorPool()->where('users.id', $user->id)->first();
        if (! $currentInstructor) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'อาจารย์คนนี้ไม่อยู่ในชุดผู้สอน'], 422);
            }
            return $this->redirectToInstructors($courseOffering)
                ->withErrors(['user_id' => 'อาจารย์คนนี้ไม่อยู่ในชุดผู้สอน']);
        }

        $oldPermission = $currentInstructor->pivot->schedule_permission ?? 'view';
        $newPermission = $validated['schedule_permission'];

        $courseOffering->instructorPool()->updateExistingPivot($user->id, [
            'schedule_permission' => $newPermission,
        ]);
        NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);

        if ($oldPermission !== $newPermission) {
            $label = $newPermission === 'schedule' ? 'มอบหมายให้ช่วยจัดตาราง' : 'ยกเลิกสิทธิ์ช่วยจัดตาราง';
            $this->logCourseManagementUpdate(
                table: 'course_offering_instructors',
                recordId: $courseOffering->id,
                oldValues: ['schedule_permission' => $oldPermission],
                newValues: ['schedule_permission' => $newPermission],
                description: "{$label} ({$user->name}) ในรายวิชา {$this->offeringCourseLabel($courseOffering)}",
            );
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'schedule_permission' => $newPermission]);
        }

        return back()->with('success', 'อัปเดตสิทธิ์ช่วยจัดตารางเรียบร้อยแล้ว');
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
        NavigationBadgeService::flushCourseHead((int) $courseOffering->coordinator_id);

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

    private function authorizeCourseHeadOffering(CourseOffering $courseOffering): void
    {
        $courseOffering->loadMissing('course');
        abort_unless((int) $courseOffering->coordinator_id === (int) Auth::id(), 403);
        abort_unless($courseOffering->course?->status === 'active', 403);
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

    private function redirectToInstructors(CourseOffering $courseOffering): RedirectResponse
    {
        return redirect()->to(route('maker.course_offerings.show', $courseOffering) . '#instructors');
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
