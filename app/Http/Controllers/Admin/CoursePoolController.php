<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseRole;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoursePoolController extends Controller
{
    public function index(): View
    {
        $courses = Course::with(['curriculum', 'department', 'headInstructor', 'assignedStaff', 'instructors'])
            ->withCount(['instructors', 'assignedStaff'])
            ->orderBy('course_code')
            ->get();

        $activeRole  = session('active_role');
        $isAdmin     = $activeRole === 'admin';
        $routePrefix = $isAdmin ? 'admin' : 'staff';

        return view('shared.course_pool.index', compact('courses', 'isAdmin', 'routePrefix'));
    }

    public function show(Course $course): View
    {
        $course->load(['curriculum', 'department', 'headInstructor.instructorProfile.department', 'assignedStaff', 'instructors.instructorProfile.department']);

        $availableInstructors = User::query()
            ->with('instructorProfile.department')
            ->where('is_active', true)
            ->whereHas('instructorProfile')
            ->whereHas('roles', fn ($q) => $q->whereIn('role', ['instructor', 'course_head']))
            ->orderBy('name')
            ->get();

        $availableStaff = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('role', 'staff'))
            ->orderBy('name')
            ->get();

        // Roles ที่เลือกได้ในชุดผู้สอน — ซ่อน "หัวหน้าวิชา" (มี head_instructor_id แล้ว)
        $courseRoles = CourseRole::orderBy('sort_order')
            ->where('name_th', '!=', 'หัวหน้าวิชา')
            ->get();

        $activeRole  = session('active_role');
        $isAdmin     = $activeRole === 'admin';
        $routePrefix = $isAdmin ? 'admin' : 'staff';

        return view('shared.course_pool.show', compact(
            'course', 'availableInstructors', 'availableStaff', 'courseRoles', 'isAdmin', 'routePrefix'
        ));
    }

    public function updateHead(Request $request, Course $course): RedirectResponse
    {
        $validated = $request->validate([
            'head_instructor_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $newHeadId = $validated['head_instructor_id'] ?? null;
        if ($newHeadId && $course->assignedStaff()->where('users.id', $newHeadId)->exists()) {
            return back()->withErrors(['head_instructor_id' => 'ไม่สามารถตั้งเจ้าหน้าที่ที่อยู่ในวิชานี้อยู่แล้วเป็นหัวหน้าวิชาได้'])->withInput();
        }

        $course->update(['head_instructor_id' => $newHeadId]);
        return back()->with('success', 'อัปเดตหัวหน้าวิชาเรียบร้อยแล้ว');
    }

    public function storeStaff(Request $request, Course $course): RedirectResponse|JsonResponse
    {
        $validated = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $user = User::find($validated['user_id']);

        if ($course->assignedStaff()->where('users.id', $user->id)->exists()) {
            return $this->fail($request, 'เจ้าหน้าที่คนนี้ถูกเพิ่มแล้ว');
        }
        if ($course->head_instructor_id === $user->id) {
            return $this->fail($request, 'ไม่สามารถเพิ่มหัวหน้าวิชาเป็นเจ้าหน้าที่ในวิชาเดียวกันได้');
        }
        $course->assignedStaff()->attach($user->id);

        if ($request->expectsJson()) {
            return response()->json(['id' => $user->id, 'name' => $user->formatted_name]);
        }
        return back()->with('success', 'เพิ่มเจ้าหน้าที่เรียบร้อยแล้ว');
    }

    public function destroyStaff(Request $request, Course $course, User $user): RedirectResponse|JsonResponse
    {
        $course->assignedStaff()->detach($user->id);
        if ($request->expectsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'ลบเจ้าหน้าที่ออกจากรายวิชาแล้ว');
    }

    public function storeInstructor(Request $request, Course $course): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'user_id'        => ['required', 'integer', 'exists:users,id'],
            'course_role_id' => ['nullable', 'integer', 'exists:course_roles,id'],
        ]);
        $user = User::with('instructorProfile.department')->find($validated['user_id']);

        if (! $user->instructorProfile) {
            return $this->fail($request, 'เลือกได้เฉพาะอาจารย์ที่มีโปรไฟล์อาจารย์');
        }
        if ($course->instructors()->where('users.id', $user->id)->exists()) {
            return $this->fail($request, 'อาจารย์คนนี้อยู่ในชุดผู้สอนของรายวิชานี้แล้ว');
        }

        // Default = "อาจารย์ผู้สอน"
        $roleId = $validated['course_role_id'] ?? CourseRole::where('name_th', 'อาจารย์ผู้สอน')->value('id');

        $course->instructors()->attach($user->id, ['course_role_id' => $roleId]);

        if ($request->expectsJson()) {
            $role = $roleId ? CourseRole::find($roleId) : null;
            return response()->json([
                'id'             => $user->id,
                'name'           => $user->formatted_name,
                'department'     => $user->instructorProfile?->department?->name ?? '-',
                'course_role_id' => $roleId,
                'role_name'      => $role?->name_th,
            ]);
        }
        return back()->with('success', 'เพิ่มอาจารย์ผู้สอนเรียบร้อยแล้ว');
    }

    public function updateInstructorRole(Request $request, Course $course, User $user): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'course_role_id' => ['nullable', 'integer', 'exists:course_roles,id'],
        ]);

        if (! $course->instructors()->where('users.id', $user->id)->exists()) {
            return $this->fail($request, 'อาจารย์คนนี้ไม่อยู่ในชุดผู้สอนของรายวิชานี้');
        }

        $course->instructors()->updateExistingPivot($user->id, [
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

    public function destroyInstructor(Request $request, Course $course, User $user): RedirectResponse|JsonResponse
    {
        $course->instructors()->detach($user->id);
        if ($request->expectsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'ลบอาจารย์ผู้สอนออกจากรายวิชาแล้ว');
    }

    private function fail(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) return response()->json(['message' => $message], 422);
        return back()->withErrors(['user_id' => $message])->withInput();
    }
}
