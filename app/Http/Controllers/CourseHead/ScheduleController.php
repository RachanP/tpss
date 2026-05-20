<?php

namespace App\Http\Controllers\CourseHead;

use App\Http\Controllers\Controller;
use App\Models\ActivityType;
use App\Models\CourseOffering;
use App\Models\Room;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
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

        $courseOffering->load([
            'course.curriculum',
            'academicYear',
            'instructorPool',
            'studentGroups' => fn ($query) => $query->orderBy('group_code'),
        ]);

        $schedules = $courseOffering->schedules()
            ->with(['activityType', 'room', 'instructors.instructorProfile.department', 'studentGroups'])
            ->orderBy('start_date')
            ->orderBy('end_date')
            ->orderBy('start_time')
            ->get();

        $availableInstructors = $this->availableInstructors($courseOffering);

        return view('course_head.schedules.index', [
            'courseOffering' => $courseOffering,
            'schedules' => $schedules,
            'availableInstructors' => $availableInstructors,
            'scheduleWarnings' => $this->scheduleWarnings($schedules, $availableInstructors),
        ]);
    }

    public function create(CourseOffering $courseOffering): View|RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        $courseOffering->load('academicYear');

        if ($courseOffering->academicYear?->phase !== 'scheduling') {
            return redirect()
                ->route('maker.course_offerings.schedules.index', $courseOffering)
                ->withErrors(['schedule' => 'ยังไม่เปิดช่วงจัดตาราง — ผู้ดูแลระบบต้องเปิดช่วงจัดตารางก่อน']);
        }

        return view('course_head.schedules.create', $this->formData($courseOffering));
    }

    public function store(Request $request, CourseOffering $courseOffering): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        $courseOffering->load(['academicYear', 'course']);

        if ($courseOffering->academicYear?->phase !== 'scheduling') {
            return redirect()
                ->route('maker.course_offerings.schedules.index', $courseOffering)
                ->withErrors(['schedule' => 'ยังไม่เปิดช่วงจัดตาราง — ผู้ดูแลระบบต้องเปิดช่วงจัดตารางก่อน']);
        }

        $validated = $this->validateSchedule($request, $courseOffering);

        DB::transaction(function () use ($courseOffering, $validated): void {
            $schedule = Schedule::create([
                'course_offering_id' => $courseOffering->id,
                'activity_type_id' => $validated['activity_type_id'],
                'room_id' => $validated['room_id'] ?? null,
                'practicum_series_id' => null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
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

    public function edit(CourseOffering $courseOffering, Schedule $schedule): View|RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        $this->authorizeScheduleBelongsToOffering($courseOffering, $schedule);
        $courseOffering->load('academicYear');

        if ($courseOffering->academicYear?->phase !== 'scheduling') {
            return redirect()
                ->route('maker.course_offerings.schedules.index', $courseOffering)
                ->withErrors(['schedule' => 'ยังไม่เปิดช่วงจัดตาราง — ผู้ดูแลระบบต้องเปิดช่วงจัดตารางก่อน']);
        }

        $schedule->load(['instructors', 'studentGroups']);

        return view('course_head.schedules.create', $this->formData($courseOffering) + [
            'schedule' => $schedule,
        ]);
    }

    public function update(Request $request, CourseOffering $courseOffering, Schedule $schedule): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        $this->authorizeScheduleBelongsToOffering($courseOffering, $schedule);
        $courseOffering->load(['academicYear', 'course']);

        if ($courseOffering->academicYear?->phase !== 'scheduling') {
            return redirect()
                ->route('maker.course_offerings.schedules.index', $courseOffering)
                ->withErrors(['schedule' => 'ยังไม่เปิดช่วงจัดตาราง — ผู้ดูแลระบบต้องเปิดช่วงจัดตารางก่อน']);
        }

        $validated = $this->validateSchedule($request, $courseOffering);

        DB::transaction(function () use ($schedule, $validated): void {
            $schedule->update([
                'activity_type_id' => $validated['activity_type_id'],
                'room_id' => $validated['room_id'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'topic' => $validated['topic'] ?? null,
                'capacity_required' => $validated['capacity_required'] ?? null,
                'sub_group_label' => $validated['sub_group_label'] ?? null,
                'remark' => $validated['remark'] ?? null,
            ]);

            $schedule->instructors()->syncWithPivotValues($validated['instructor_ids'], [
                'is_lead' => false,
            ]);
            $schedule->studentGroups()->sync($validated['student_group_ids']);
        });

        return redirect()
            ->route('maker.course_offerings.schedules.index', $courseOffering)
            ->with('success', 'แก้ไขรายการสอนเรียบร้อยแล้ว');
    }

    public function checkConflicts(Request $request, CourseOffering $courseOffering): JsonResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        $courseOffering->load('course');

        $validated = $request->validate([
            'schedule_id' => ['nullable', 'integer', 'exists:schedules,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'capacity_required' => ['nullable', 'integer', 'min:1'],
            'instructor_ids' => ['nullable', 'array'],
            'instructor_ids.*' => ['integer'],
            'student_group_ids' => ['nullable', 'array'],
            'student_group_ids.*' => ['integer'],
        ], $this->validationMessages());

        $currentSchedule = null;
        if (! empty($validated['schedule_id'])) {
            $currentSchedule = Schedule::find($validated['schedule_id']);
            $this->authorizeScheduleBelongsToOffering($courseOffering, $currentSchedule);
        }

        $validated['instructor_ids'] = array_values(array_intersect(
            $validated['instructor_ids'] ?? [],
            $this->availableInstructors($courseOffering)->pluck('id')->map(fn ($id) => (int) $id)->all()
        ));
        $validated['student_group_ids'] = array_values(array_intersect(
            $validated['student_group_ids'] ?? [],
            $courseOffering->studentGroups()->pluck('id')->map(fn ($id) => (int) $id)->all()
        ));

        $studentCount = 0;
        $capacity = $validated['capacity_required'] ?? null;
        if (! empty($validated['student_group_ids'])) {
            $studentCount = (int) $courseOffering->studentGroups()
                ->whereIn('id', $validated['student_group_ids'])
                ->sum('student_count');
        }

        $groupConflictDetails = empty($validated['student_group_ids'])
            ? collect()
            : $this->conflictingStudentGroupDetails($courseOffering, $validated, $currentSchedule);
        $instructorConflicts = empty($validated['instructor_ids'])
            ? collect()
            : $this->conflictingInstructors($validated, $currentSchedule);
        $roomConflict = $this->conflictingRoom($validated, $currentSchedule);

        return response()->json([
            'conflicts' => [
                'groups' => $groupConflictDetails->pluck('code')->values(),
                'group_ids' => $groupConflictDetails->pluck('id')->map(fn ($id) => (string) $id)->values(),
                'instructors' => $instructorConflicts->values(),
                'room' => $roomConflict,
                'capacity' => $capacity && $studentCount > $capacity ? [
                    'selected' => $studentCount,
                    'limit' => (int) $capacity,
                ] : null,
            ],
        ]);
    }

    public function destroy(CourseOffering $courseOffering, Schedule $schedule): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        $this->authorizeScheduleBelongsToOffering($courseOffering, $schedule);
        $courseOffering->load('academicYear');

        if ($courseOffering->academicYear?->phase !== 'scheduling') {
            return redirect()
                ->route('maker.course_offerings.schedules.index', $courseOffering)
                ->withErrors(['schedule' => 'ยังไม่เปิดช่วงจัดตาราง ผู้ดูแลระบบต้องเปิดช่วงจัดตารางก่อน']);
        }

        DB::transaction(function () use ($schedule): void {
            $schedule->instructors()->detach();
            $schedule->studentGroups()->detach();
            $schedule->delete();
        });

        return redirect()
            ->route('maker.course_offerings.schedules.index', $courseOffering)
            ->with('success', 'ลบรายการสอนเรียบร้อยแล้ว');
    }

    private function authorizeCourseHeadOffering(CourseOffering $courseOffering): void
    {
        abort_unless((int) $courseOffering->coordinator_id === (int) Auth::id(), 403);
    }

    private function authorizeScheduleBelongsToOffering(CourseOffering $courseOffering, Schedule $schedule): void
    {
        abort_unless((int) $schedule->course_offering_id === (int) $courseOffering->id, 404);
    }

    private function validateSchedule(Request $request, CourseOffering $courseOffering): array
    {
        $availableInstructorIds = $this->availableInstructors($courseOffering)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
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
                Rule::in($availableInstructorIds),
            ],
            'student_group_ids' => ['required', 'array', 'min:1'],
            'student_group_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('student_groups', 'id')
                    ->where(fn ($query) => $query->where('course_offering_id', $courseOffering->id)),
            ],
        ], $this->validationMessages(), [
            'start_date' => 'วันที่เริ่มต้น',
            'end_date' => 'วันที่สิ้นสุด',
            'start_time' => 'เวลาเริ่ม',
            'end_time' => 'เวลาสิ้นสุด',
            'activity_type_id' => 'ประเภทกิจกรรม',
            'room_id' => 'ห้อง/สถานที่',
            'topic' => 'หัวข้อ',
            'remark' => 'หมายเหตุ',
            'capacity_required' => 'จำนวนที่รองรับ',
            'sub_group_label' => 'ป้ายกลุ่มย่อย',
            'instructor_ids' => 'ผู้สอน',
            'instructor_ids.*' => 'ผู้สอน',
            'student_group_ids' => 'กลุ่มนักศึกษา',
            'student_group_ids.*' => 'กลุ่มนักศึกษา',
        ]);

        $capacity = $validated['capacity_required'] ?? null;

        if ($capacity) {
            $studentCount = $courseOffering->studentGroups()
                ->whereIn('id', $validated['student_group_ids'])
                ->sum('student_count');

            if ($studentCount > $capacity) {
                back()
                    ->withInput()
                    ->withErrors([
                        'student_group_ids' => "จำนวนผู้เรียนของกลุ่มที่เลือก ({$studentCount} คน) เกินจำนวนรองรับที่ระบุ ({$capacity} คน)",
                    ])
                    ->throwResponse();
            }
        }

        $conflictingGroups = $this->conflictingStudentGroups($courseOffering, $validated, $request->route('schedule'));

        if ($conflictingGroups->isNotEmpty()) {
            back()
                ->withInput()
                ->withErrors([
                    'student_group_ids' => 'กลุ่มนักศึกษานี้มีรายการสอนในช่วงเวลาเดียวกันแล้ว: '.$conflictingGroups->join(', '),
                ])
                ->throwResponse();
        }

        $conflictingInstructors = $this->conflictingInstructors($validated, $request->route('schedule'));

        if ($conflictingInstructors->isNotEmpty()) {
            back()
                ->withInput()
                ->withErrors([
                    'instructor_ids' => 'ผู้สอนนี้มีรายการสอนในช่วงเวลาเดียวกันแล้ว: '.$conflictingInstructors->join(', '),
                ])
                ->throwResponse();
        }

        $conflictingRoom = $this->conflictingRoom($validated, $request->route('schedule'));

        if ($conflictingRoom) {
            back()
                ->withInput()
                ->withErrors([
                    'room_id' => 'ห้องหรือสถานที่นี้มีรายการสอนในช่วงเวลาเดียวกันแล้ว: '.$conflictingRoom,
                ])
                ->throwResponse();
        }

        return $validated;
    }

    private function validationMessages(): array
    {
        return [
            'required' => 'กรุณาระบุ:attribute',
            'date' => 'กรุณาระบุ:attributeให้เป็นวันที่ที่ถูกต้อง',
            'date_format' => 'กรุณาระบุ:attributeในรูปแบบ HH:mm เช่น 08:30',
            'after' => ':attributeต้องมากกว่า:date',
            'after_or_equal' => ':attributeต้องไม่ก่อน:date',
            'integer' => ':attributeต้องเป็นตัวเลขจำนวนเต็ม',
            'exists' => ':attributeที่เลือกไม่พบในระบบ',
            'max' => ':attributeต้องไม่เกิน :max ตัวอักษร',
            'min' => 'กรุณาเลือกหรือระบุ:attributeอย่างน้อย :min รายการ',
            'array' => 'กรุณาเลือก:attribute',
            'distinct' => ':attributeซ้ำกัน กรุณาเลือกใหม่',
            'in' => ':attributeที่เลือกไม่ตรงกับเงื่อนไขของรายวิชานี้',
        ];
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
            'availableInstructors' => $this->availableInstructors($courseOffering),
            'activityTypes' => ActivityType::orderBy('name')->get(),
            'rooms' => Room::query()
                ->where('status', 'active')
                ->orderBy('room_code')
                ->get(),
            'existingSchedules' => $courseOffering->schedules()
                ->with(['studentGroups:id,group_code'])
                ->orderBy('start_date')
                ->orderBy('start_time')
                ->get(),
        ];
    }

    private function conflictingStudentGroups(CourseOffering $courseOffering, array $validated, ?Schedule $currentSchedule = null)
    {
        return $this->conflictingStudentGroupDetails($courseOffering, $validated, $currentSchedule)
            ->pluck('code')
            ->values();
    }

    private function conflictingStudentGroupDetails(CourseOffering $courseOffering, array $validated, ?Schedule $currentSchedule = null)
    {
        return $this->overlappingSchedules($courseOffering->schedules(), $validated, $currentSchedule)
            ->whereHas('studentGroups', fn ($query) => $query->whereIn('student_groups.id', $validated['student_group_ids']))
            ->with(['studentGroups' => fn ($query) => $query->whereIn('student_groups.id', $validated['student_group_ids'])])
            ->get()
            ->flatMap(fn ($schedule) => $schedule->studentGroups->map(fn ($group) => [
                'id' => (int) $group->id,
                'code' => $group->group_code,
            ]))
            ->unique('id')
            ->values();
    }

    private function conflictingInstructors(array $validated, ?Schedule $currentSchedule = null)
    {
        return $this->overlappingSchedules(Schedule::query(), $validated, $currentSchedule)
            ->whereHas('instructors', fn ($query) => $query->whereIn('users.id', $validated['instructor_ids']))
            ->with(['instructors' => fn ($query) => $query->whereIn('users.id', $validated['instructor_ids'])])
            ->get()
            ->flatMap(fn ($schedule) => $schedule->instructors->pluck('formatted_name'))
            ->unique()
            ->values();
    }

    private function conflictingRoom(array $validated, ?Schedule $currentSchedule = null): ?string
    {
        if (empty($validated['room_id'])) {
            return null;
        }

        $schedule = $this->overlappingSchedules(Schedule::query(), $validated, $currentSchedule)
            ->where('room_id', $validated['room_id'])
            ->with('room')
            ->first();

        if (! $schedule) {
            return null;
        }

        return $schedule->room?->room_code
            ? trim($schedule->room->room_code.' '.$schedule->room->room_name)
            : 'ไม่ระบุชื่อห้อง';
    }

    private function overlappingSchedules($query, array $validated, ?Schedule $currentSchedule = null)
    {
        return $query
            ->when($currentSchedule, fn ($query) => $query->whereKeyNot($currentSchedule->id))
            ->whereDate('start_date', '<=', $validated['end_date'])
            ->whereDate('end_date', '>=', $validated['start_date'])
            ->where('start_time', '<', $validated['end_time'])
            ->where('end_time', '>', $validated['start_time']);
    }

    private function scheduleWarnings($schedules, $availableInstructors): array
    {
        $validInstructorIds = $availableInstructors->pluck('id')->map(fn ($id) => (int) $id);
        $warningsBySchedule = [];

        foreach ($schedules as $schedule) {
            $warnings = [];
            $validScheduleInstructorIds = $schedule->instructors
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->intersect($validInstructorIds)
                ->values();

            if ($validScheduleInstructorIds->isEmpty() || $schedule->studentGroups->isEmpty() || ! $schedule->activityType) {
                $warnings['missing_info'] = [
                    'label' => 'ข้อมูลไม่ครบ',
                    'class' => 'badge-warn',
                ];
            }

            $studentCount = (int) $schedule->studentGroups->sum('student_count');
            if ($schedule->capacity_required && $studentCount > $schedule->capacity_required) {
                $warnings['capacity_exceeded'] = [
                    'label' => 'จำนวนเกิน',
                    'class' => 'badge-warn',
                ];
            }

            $overlappingSchedules = $this->overlappingSchedules(Schedule::query(), [
                'start_date' => $schedule->start_date,
                'end_date' => $schedule->end_date,
                'start_time' => substr((string) $schedule->start_time, 0, 5),
                'end_time' => substr((string) $schedule->end_time, 0, 5),
            ], $schedule)->with(['instructors', 'studentGroups'])->get();

            foreach ($overlappingSchedules as $otherSchedule) {
                if (! $this->schedulesOverlap($schedule, $otherSchedule)) {
                    continue;
                }

                if ($schedule->studentGroups->pluck('id')->intersect($otherSchedule->studentGroups->pluck('id'))->isNotEmpty()) {
                    $warnings['group_overlap'] = [
                        'label' => 'กลุ่มชนเวลา',
                        'class' => 'badge-err',
                    ];
                }

                $otherInstructorIds = $otherSchedule->instructors
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->intersect($validInstructorIds);

                if ($validScheduleInstructorIds->intersect($otherInstructorIds)->isNotEmpty()) {
                    $warnings['instructor_overlap'] = [
                        'label' => 'ผู้สอนชนเวลา',
                        'class' => 'badge-err',
                    ];
                }

                if ($schedule->room_id && (int) $schedule->room_id === (int) $otherSchedule->room_id) {
                    $warnings['room_overlap'] = [
                        'label' => 'ห้องชนเวลา',
                        'class' => 'badge-err',
                    ];
                }
            }

            $warningsBySchedule[$schedule->id] = array_values($warnings);
        }

        return $warningsBySchedule;
    }

    private function schedulesOverlap(Schedule $first, Schedule $second): bool
    {
        return $first->start_date?->format('Y-m-d') <= $second->end_date?->format('Y-m-d')
            && $first->end_date?->format('Y-m-d') >= $second->start_date?->format('Y-m-d')
            && substr((string) $first->start_time, 0, 5) < substr((string) $second->end_time, 0, 5)
            && substr((string) $first->end_time, 0, 5) > substr((string) $second->start_time, 0, 5);
    }

    private function availableInstructors(CourseOffering $courseOffering)
    {
        $courseOffering->loadMissing([
            'course',
            'instructorPool.instructorProfile.department',
        ]);

        $departmentId = $courseOffering->course?->department_id;

        return $courseOffering->instructorPool
            ->when($departmentId, fn ($instructors) => $instructors
                ->filter(fn ($instructor) => (int) $instructor->instructorProfile?->department_id === (int) $departmentId))
            ->sortBy(fn ($instructor) => $instructor->formatted_name)
            ->values();
    }
}
