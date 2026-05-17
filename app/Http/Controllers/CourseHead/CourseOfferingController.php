<?php

namespace App\Http\Controllers\CourseHead;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\StudentGroup;
use App\Models\SystemSetting;
use App\Models\User;
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
            ->with(['course.curriculum', 'course.department', 'academicYear'])
            ->withCount(['studentGroups', 'instructorPool'])
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
            'course.prerequisites',
            'academicYear',
            'coordinator',
            'studentGroups' => fn ($query) => $query->orderBy('group_code'),
            'instructorPool.instructorProfile.department',
        ]);

        $availablePrerequisiteCourses = Course::query()
            ->with('curriculum')
            ->where('id', '!=', $courseOffering->course_id)
            ->orderBy('course_code')
            ->get();

        $availableInstructors = User::query()
            ->with('instructorProfile.department')
            ->where('is_active', true)
            ->whereHas('instructorProfile')
            ->orderBy('name')
            ->get();

        return view('course_head.course_offerings.show', [
            'courseOffering' => $courseOffering,
            'availableInstructors' => $availableInstructors,
            'availablePrerequisiteCourses' => $availablePrerequisiteCourses,
            'teachingWeeks' => (int) SystemSetting::get('teaching_load_weeks', 39),
        ]);
    }

    public function update(Request $request, CourseOffering $courseOffering): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        $request->validate([
            'requires_practicum_rotation' => ['nullable', 'boolean'],
        ]);

        $courseOffering->update([
            'requires_practicum_rotation' => $request->boolean('requires_practicum_rotation'),
        ]);

        return redirect()
            ->route('maker.course_offerings.show', $courseOffering)
            ->with('success', 'บันทึกข้อมูลรายวิชาเรียบร้อยแล้ว');
    }

    public function storeInstructor(Request $request, CourseOffering $courseOffering): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::with('instructorProfile')->find($validated['user_id']);

        if (! $user || ! $user->is_active || ! $user->instructorProfile) {
            return back()
                ->withErrors(['user_id' => 'เลือกได้เฉพาะอาจารย์ที่ยังใช้งานอยู่และมีข้อมูลโปรไฟล์อาจารย์'])
                ->withInput();
        }

        if ($courseOffering->instructorPool()->where('users.id', $user->id)->exists()) {
            return back()
                ->withErrors(['user_id' => 'อาจารย์คนนี้อยู่ในชุดผู้สอนของรายวิชานี้แล้ว'])
                ->withInput();
        }

        $courseOffering->instructorPool()->attach($user->id, [
            'role_in_course' => 'instructor',
        ]);

        return back()->with('success', 'เพิ่มอาจารย์ในรายวิชาเรียบร้อยแล้ว');
    }

    public function destroyInstructor(CourseOffering $courseOffering, User $user): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        if ((int) $courseOffering->coordinator_id === (int) $user->id) {
            return back()->withErrors([
                'instructor_pool' => 'ไม่สามารถนำหัวหน้าวิชาหลักออกจากชุดผู้สอนได้ กรุณาเปลี่ยนหัวหน้าวิชาผ่านหน้าตั้งค่ารายวิชาแยกต่างหาก',
            ]);
        }

        $courseOffering->instructorPool()->detach($user->id);

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
            return back()->withErrors(['student_count' => $message])->withInput();
        }

        $courseOffering->studentGroups()->create($validated);

        return back()->with('success', 'เพิ่มกลุ่มนักศึกษาเรียบร้อยแล้ว');
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
            return back()->withErrors(['student_count' => $message])->withInput();
        }

        $studentGroup->update($validated);

        return back()->with('success', 'อัปเดตกลุ่มนักศึกษาเรียบร้อยแล้ว');
    }

    public function destroyStudentGroup(CourseOffering $courseOffering, StudentGroup $studentGroup): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;
        $this->assertStudentGroupBelongsToOffering($courseOffering, $studentGroup);

        if (Schema::hasTable('schedule_student_groups') &&
            DB::table('schedule_student_groups')->where('student_group_id', $studentGroup->id)->exists()) {
            return back()->withErrors([
                'student_groups' => 'ไม่สามารถลบกลุ่มที่ถูกอ้างอิงในตารางสอนได้ กรุณาจัดการตารางสอนที่เกี่ยวข้องก่อน',
            ]);
        }

        if (Schema::hasTable('schedules') &&
            Schema::hasColumn('schedules', 'student_group_id') &&
            DB::table('schedules')->where('student_group_id', $studentGroup->id)->exists()) {
            return back()->withErrors([
                'student_groups' => 'ไม่สามารถลบกลุ่มที่ถูกอ้างอิงในตารางสอนได้ กรุณาจัดการตารางสอนที่เกี่ยวข้องก่อน',
            ]);
        }

        $studentGroup->delete();

        return back()->with('success', 'ลบกลุ่มนักศึกษาเรียบร้อยแล้ว');
    }

    public function storePrerequisite(Request $request, CourseOffering $courseOffering): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        $validated = $request->validate([
            'prerequisite_course_id' => ['required', 'integer', 'exists:courses,id'],
        ]);

        $course = $courseOffering->course;
        $prerequisiteCourseId = (int) $validated['prerequisite_course_id'];

        if (! $course) {
            return back()->withErrors([
                'prerequisite_course_id' => 'ไม่พบข้อมูลรายวิชาหลักของรายวิชานี้',
            ])->withInput();
        }

        if ((int) $course->id === $prerequisiteCourseId) {
            return back()->withErrors([
                'prerequisite_course_id' => 'ไม่สามารถเลือกรายวิชาเดียวกันเป็นรายวิชาที่ต้องเรียนมาก่อนได้',
            ])->withInput();
        }

        if ($course->prerequisites()->where('courses.id', $prerequisiteCourseId)->exists()) {
            return back()->withErrors([
                'prerequisite_course_id' => 'รายวิชานี้ถูกเพิ่มเป็นรายวิชาที่ต้องเรียนมาก่อนแล้ว',
            ])->withInput();
        }

        $course->prerequisites()->attach($prerequisiteCourseId);

        return back()->with('success', 'เพิ่มรายวิชาที่ต้องเรียนมาก่อนเรียบร้อยแล้ว');
    }

    public function destroyPrerequisite(CourseOffering $courseOffering, Course $course): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        $offeringCourse = $courseOffering->course;

        if (! $offeringCourse) {
            return back()->withErrors([
                'prerequisite_course_id' => 'ไม่พบข้อมูลรายวิชาหลักของรายวิชานี้',
            ]);
        }

        $offeringCourse->prerequisites()->detach($course->id);

        return back()->with('success', 'ลบรายวิชาที่ต้องเรียนมาก่อนเรียบร้อยแล้ว');
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

    private function studentCountLimitError(
        CourseOffering $courseOffering,
        int $newStudentCount,
        ?StudentGroup $ignoreGroup = null
    ): ?string {
        $limit = $courseOffering->total_student_count;

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
}
