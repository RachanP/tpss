<?php

namespace App\Http\Controllers\CourseHead;

use App\Http\Controllers\Controller;
use App\Models\ActivityType;
use App\Models\CourseOffering;
use App\Models\Room;
use App\Models\Schedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(CourseOffering $courseOffering): View
    {
        $this->authorizeCourseHeadOffering($courseOffering);

        $courseOffering->load(['course.curriculum', 'academicYear']);

        $schedules = $courseOffering->schedules()
            ->with(['activityType', 'room', 'instructors.instructorProfile.department', 'studentGroups'])
            ->orderBy('teaching_date')
            ->orderBy('start_time')
            ->get();

        return view('course_head.schedules.index', [
            'courseOffering' => $courseOffering,
            'schedules' => $schedules,
        ]);
    }

    public function create(CourseOffering $courseOffering): View|RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        $courseOffering->load('academicYear');

        if ($courseOffering->academicYear->phase !== 'scheduling') {
            return redirect()
                ->route('maker.course_offerings.schedules.index', $courseOffering)
                ->withErrors(['schedule' => 'ยังไม่เปิดช่วงจัดตาราง — Admin ต้องเปิดช่วงจัดตารางก่อน']);
        }

        return view('course_head.schedules.create', $this->formData($courseOffering));
    }

    public function store(Request $request, CourseOffering $courseOffering): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        $courseOffering->load('academicYear');

        if ($courseOffering->academicYear->phase !== 'scheduling') {
            return redirect()
                ->route('maker.course_offerings.schedules.index', $courseOffering)
                ->withErrors(['schedule' => 'ยังไม่เปิดช่วงจัดตาราง — Admin ต้องเปิดช่วงจัดตารางก่อน']);
        }

        $validated = $request->validate([
            'teaching_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'activity_type_id' => ['required', 'integer', 'exists:activity_types,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'topic' => ['nullable', 'string', 'max:255'],
            'remark' => ['nullable', 'string'],
            'capacity_required' => ['nullable', 'integer', 'min:1'],
            'sub_group_label' => ['nullable', 'string', 'max:20'],
            'instructor_ids' => ['required', 'array', 'min:1'],
            'instructor_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('course_offering_instructors', 'user_id')
                    ->where(fn ($query) => $query->where('course_offering_id', $courseOffering->id)),
            ],
            'student_group_ids' => ['required', 'array', 'min:1'],
            'student_group_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('student_groups', 'id')
                    ->where(fn ($query) => $query->where('course_offering_id', $courseOffering->id)),
            ],
        ], [], [
            'teaching_date' => 'วันที่สอน',
            'start_time' => 'เวลาเริ่ม',
            'end_time' => 'เวลาสิ้นสุด',
            'activity_type_id' => 'ประเภทกิจกรรม',
            'room_id' => 'ห้อง/สถานที่',
            'topic' => 'หัวข้อ',
            'remark' => 'หมายเหตุ',
            'capacity_required' => 'จำนวนรองรับ',
            'sub_group_label' => 'ป้ายกลุ่มย่อย',
            'instructor_ids' => 'ผู้สอน',
            'instructor_ids.*' => 'ผู้สอน',
            'student_group_ids' => 'กลุ่มนักศึกษา',
            'student_group_ids.*' => 'กลุ่มนักศึกษา',
        ]);

        DB::transaction(function () use ($courseOffering, $validated): void {
            $schedule = Schedule::create([
                'course_offering_id' => $courseOffering->id,
                'activity_type_id' => $validated['activity_type_id'],
                'room_id' => $validated['room_id'] ?? null,
                'practicum_series_id' => null,
                'teaching_date' => $validated['teaching_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'topic' => $validated['topic'] ?? null,
                'capacity_required' => $validated['capacity_required'] ?? null,
                'sub_group_label' => $validated['sub_group_label'] ?? null,
                'status' => 'draft',
                'remark' => $validated['remark'] ?? null,
            ]);

            $schedule->instructors()->syncWithPivotValues($validated['instructor_ids'], [
                'is_lead' => false,
            ]);
            $schedule->studentGroups()->sync($validated['student_group_ids']);
        });

        return redirect()
            ->route('maker.course_offerings.schedules.index', $courseOffering)
            ->with('success', 'เพิ่มรายการสอนเรียบร้อยแล้ว');
    }

    private function authorizeCourseHeadOffering(CourseOffering $courseOffering): void
    {
        abort_unless((int) $courseOffering->coordinator_id === (int) Auth::id(), 403);
    }

    private function formData(CourseOffering $courseOffering): array
    {
        $courseOffering->load([
            'course.curriculum',
            'academicYear',
            'instructorPool.instructorProfile.department',
            'studentGroups' => fn ($query) => $query->orderBy('group_code'),
        ]);

        return [
            'courseOffering' => $courseOffering,
            'activityTypes' => ActivityType::orderBy('name')->get(),
            'rooms' => Room::query()
                ->where('status', 'active')
                ->orderBy('room_code')
                ->get(),
        ];
    }
}
