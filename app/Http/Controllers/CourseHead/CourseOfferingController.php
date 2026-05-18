<?php

namespace App\Http\Controllers\CourseHead;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\CourseRole;
use App\Models\StudentGroup;
use App\Models\SystemSetting;
use App\Models\User;
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
    public function index(): View
    {
        $offerings = CourseOffering::query()
            ->select('course_offerings.*')
            ->distinct()
            ->with(['course.curriculum', 'course.department', 'academicYear'])
            ->withCount(['studentGroups', 'instructorPool'])
            ->withSum('studentGroups as allocated_student_count', 'student_count')
            ->where('coordinator_id', Auth::id())
            ->latest('updated_at')
            ->get();

        return view('course_head.course_offerings.index', [
            'offerings' => $offerings,
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
        ]);

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

    public function update(Request $request, CourseOffering $courseOffering): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        $courseOffering->loadMissing('course');
        $requestedRotation = $request->boolean('requires_practicum_rotation');
        $defaultRotation = (bool) $courseOffering->course?->requires_practicum_rotation;
        $isRotationOverride = $requestedRotation !== $defaultRotation;

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

        $courseOffering->update([
            'requires_practicum_rotation' => $requestedRotation,
            'practicum_note' => $isRotationOverride ? trim((string) $request->input('practicum_note')) : null,
        ]);

        return redirect()
            ->to(route('maker.course_offerings.show', $courseOffering) . '#course-info')
            ->with('success', 'บันทึกข้อมูลรายวิชาเรียบร้อยแล้ว');
    }

    public function storeInstructor(Request $request, CourseOffering $courseOffering): RedirectResponse|JsonResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        $validated = $request->validate([
            'user_id'        => ['required', 'integer', 'exists:users,id'],
            'course_role_id' => ['nullable', 'integer', 'exists:course_roles,id'],
        ]);

        $user = User::with('instructorProfile.department')->find($validated['user_id']);

        if (! $user || ! $user->is_active || ! $user->instructorProfile) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'เลือกได้เฉพาะอาจารย์ที่ยังใช้งานอยู่และมีข้อมูลโปรไฟล์อาจารย์'], 422);
            }
            return back()->withErrors(['user_id' => 'เลือกได้เฉพาะอาจารย์ที่ยังใช้งานอยู่และมีข้อมูลโปรไฟล์อาจารย์'])->withInput();
        }

        if ($courseOffering->instructorPool()->where('users.id', $user->id)->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'อาจารย์คนนี้อยู่ในชุดผู้สอนของรายวิชานี้แล้ว'], 422);
            }
            return back()->withErrors(['user_id' => 'อาจารย์คนนี้อยู่ในชุดผู้สอนของรายวิชานี้แล้ว'])->withInput();
        }

        $roleId = $validated['course_role_id'] ?? CourseRole::where('name_th', 'อาจารย์ผู้สอน')->value('id');

        $courseOffering->instructorPool()->attach($user->id, [
            'role_in_course' => 'instructor',
            'course_role_id' => $roleId,
        ]);

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
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        $validated = $request->validate([
            'course_role_id' => ['nullable', 'integer', 'exists:course_roles,id'],
        ]);

        if (! $courseOffering->instructorPool()->where('users.id', $user->id)->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'อาจารย์คนนี้ไม่อยู่ในชุดผู้สอน'], 422);
            }
            return back()->withErrors(['user_id' => 'อาจารย์คนนี้ไม่อยู่ในชุดผู้สอน']);
        }

        $courseOffering->instructorPool()->updateExistingPivot($user->id, [
            'course_role_id' => $validated['course_role_id'] ?? null,
        ]);

        if ($request->expectsJson()) {
            $role = $validated['course_role_id'] ? CourseRole::find($validated['course_role_id']) : null;
            return response()->json([
                'ok'             => true,
                'course_role_id' => $validated['course_role_id'] ?? null,
                'role_name'      => $role?->name_th,
            ]);
        }

        return back()->with('success', 'อัปเดตบทบาทเรียบร้อยแล้ว');
    }

    public function destroyInstructor(Request $request, CourseOffering $courseOffering, User $user): RedirectResponse|JsonResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        if ((int) $courseOffering->coordinator_id === (int) $user->id) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'ไม่สามารถนำหัวหน้าวิชาหลักออกจากชุดผู้สอนได้'], 422);
            }
            return back()->withErrors(['instructor_pool' => 'ไม่สามารถนำหัวหน้าวิชาหลักออกจากชุดผู้สอนได้']);
        }

        $courseOffering->instructorPool()->detach($user->id);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'ลบอาจารย์ออกจากชุดผู้สอนเรียบร้อยแล้ว');
    }

    public function storeStudentGroup(Request $request, CourseOffering $courseOffering): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

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

        $courseOffering->studentGroups()->create($validated);

        return $this->redirectToStudentGroups($courseOffering)
            ->with('success', 'เพิ่มกลุ่มนักศึกษาเรียบร้อยแล้ว');
    }

    public function bulkStoreStudentGroups(Request $request, CourseOffering $courseOffering): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

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

        DB::transaction(function () use ($courseOffering, $groupCodes, $baseCount, $remainder, $customCounts, $colors) {
            foreach ($groupCodes->values() as $index => $groupCode) {
                $courseOffering->studentGroups()->create([
                    'group_code' => $groupCode,
                    'student_count' => $customCounts->isNotEmpty()
                        ? $customCounts[$index]
                        : $baseCount + ($index < $remainder ? 1 : 0),
                    'color_code' => $colors[$index % count($colors)],
                ]);
            }
        });

        return $this->redirectToStudentGroups($courseOffering)
            ->with('success', "สร้างกลุ่มนักศึกษา {$groupCount} กลุ่มเรียบร้อยแล้ว");
    }

    public function updateStudentGroup(
        Request $request,
        CourseOffering $courseOffering,
        StudentGroup $studentGroup
    ): RedirectResponse {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;
        $this->assertStudentGroupBelongsToOffering($courseOffering, $studentGroup);

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

        if ($message = $this->studentCountLimitError(
            $courseOffering,
            (int) $validated['student_count'],
            $studentGroup
        )) {
            return $this->redirectToStudentGroups($courseOffering)
                ->withErrors(['student_count' => $message])
                ->withInput();
        }

        $studentGroup->update($validated);

        return $this->redirectToStudentGroups($courseOffering)
            ->with('success', 'อัปเดตกลุ่มนักศึกษาเรียบร้อยแล้ว');
    }

    public function bulkDestroyStudentGroups(Request $request, CourseOffering $courseOffering): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        $validated = $request->validate([
            'group_ids' => ['required', 'array', 'min:1'],
            'group_ids.*' => ['integer', 'exists:student_groups,id'],
        ], [
            'group_ids.required' => 'กรุณาเลือกกลุ่มที่ต้องการลบ',
            'group_ids.min' => 'กรุณาเลือกกลุ่มที่ต้องการลบ',
        ]);

        $groups = StudentGroup::query()
            ->where('course_offering_id', $courseOffering->id)
            ->whereIn('id', $validated['group_ids'])
            ->get();

        if ($groups->count() !== count(array_unique($validated['group_ids']))) {
            abort(404);
        }

        $blocked = $groups
            ->filter(fn (StudentGroup $group) => $this->studentGroupHasDownstreamReferences($group))
            ->pluck('group_code')
            ->all();

        if (! empty($blocked)) {
            return $this->redirectToStudentGroups($courseOffering)
                ->withErrors([
                    'student_groups' => 'ไม่สามารถลบกลุ่มที่ถูกอ้างอิงในตารางสอนได้: ' . implode(', ', $blocked),
                ]);
        }

        DB::transaction(fn () => $groups->each->delete());

        return $this->redirectToStudentGroups($courseOffering)
            ->with('warning', 'ลบกลุ่มนักศึกษา ' . $groups->count() . ' กลุ่มเรียบร้อยแล้ว');
    }

    public function destroyStudentGroup(CourseOffering $courseOffering, StudentGroup $studentGroup): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;
        $this->assertStudentGroupBelongsToOffering($courseOffering, $studentGroup);

        if ($this->studentGroupHasDownstreamReferences($studentGroup)) {
            return $this->redirectToStudentGroups($courseOffering)->withErrors([
                'student_groups' => 'ไม่สามารถลบกลุ่มที่ถูกอ้างอิงในตารางสอนได้ กรุณาจัดการตารางสอนที่เกี่ยวข้องก่อน',
            ]);
        }

        $studentGroup->delete();

        return $this->redirectToStudentGroups($courseOffering)
            ->with('warning', 'ลบกลุ่มนักศึกษาเรียบร้อยแล้ว');
    }

    private function authorizeCourseHeadOffering(CourseOffering $courseOffering): void
    {
        abort_unless((int) $courseOffering->coordinator_id === (int) Auth::id(), 403);
    }

    private function requireSchedulingPhase(CourseOffering $courseOffering): ?RedirectResponse
    {
        $courseOffering->loadMissing('academicYear');
        if ($courseOffering->academicYear?->phase !== 'scheduling') {
            return redirect()
                ->route('maker.course_offerings.show', $courseOffering)
                ->with('error', 'ยังไม่เปิดช่วงจัดตาราง — Admin ต้องเปิดช่วงจัดตารางก่อนจึงจะแก้ไขข้อมูลรายวิชาได้');
        }
        return null;
    }

    private function assertStudentGroupBelongsToOffering(CourseOffering $courseOffering, StudentGroup $studentGroup): void
    {
        abort_unless((int) $studentGroup->course_offering_id === (int) $courseOffering->id, 404);
    }

    private function redirectToStudentGroups(CourseOffering $courseOffering): RedirectResponse
    {
        return redirect()->route('maker.course_offerings.show', $courseOffering);
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
}
