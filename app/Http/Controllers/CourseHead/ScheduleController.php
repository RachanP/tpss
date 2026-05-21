<?php

namespace App\Http\Controllers\CourseHead;

use App\Http\Controllers\Controller;
use App\Models\ActivityType;
use App\Models\CourseOffering;
use App\Models\Room;
use App\Models\Schedule;
use App\Services\ScheduleConflictChecker;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function workspace(Request $request): View
    {
        $offerings = $this->coordinatorScheduleOfferings();

        return view('course_head.schedules.index', $this->schedulePageData(
            request: $request,
            courseOffering: null,
            isWorkspace: true,
            availableOfferings: $offerings,
        ));
    }

    public function index(Request $request, CourseOffering $courseOffering): View
    {
        $this->authorizeCourseHeadOffering($courseOffering);

        return view('course_head.schedules.index', $this->schedulePageData(
            request: $request,
            courseOffering: $courseOffering,
            isWorkspace: false,
            availableOfferings: $this->coordinatorScheduleOfferings(),
        ));
    }

    public function createGlobal(Request $request): View|RedirectResponse
    {
        $offerings = $this->coordinatorScheduleOfferings()
            ->filter(fn (CourseOffering $offering) => $offering->academicYear?->phase === 'scheduling')
            ->values();

        if ($offerings->isEmpty()) {
            return redirect()
                ->route('maker.schedules.index')
                ->withErrors(['schedule' => 'ยังไม่มีรายวิชาที่ต้องจัดตาราง']);
        }

        $selectedOfferingId = (int) old('course_offering_id', $request->query('course_offering_id'));

        return redirect()->route('maker.schedules.index', array_filter([
            'modal' => 'create',
            'week_start' => $request->query('week_start'),
            'course_offering_id' => $selectedOfferingId && $offerings->firstWhere('id', $selectedOfferingId) ? $selectedOfferingId : null,
        ]));
    }

    public function storeGlobal(
        Request $request,
        ScheduleConflictChecker $conflictChecker
    ): RedirectResponse {
        $offerings = $this->coordinatorScheduleOfferings();
        $courseOffering = $offerings->firstWhere('id', (int) $request->input('course_offering_id'));

        if (! $courseOffering) {
            throw ValidationException::withMessages([
                'course_offering_id' => 'เลือกรายวิชาที่คุณรับผิดชอบ',
            ]);
        }

        return $this->storeForOffering($request, $courseOffering, $conflictChecker, true);
    }

    private function schedulePageData(
        Request $request,
        ?CourseOffering $courseOffering,
        bool $isWorkspace,
        Collection $availableOfferings
    ): array {
        $courseOffering?->load([
            'course.curriculum',
            'course.department',
            'academicYear',
            'instructorPool.instructorProfile.department',
            'studentGroups' => fn ($query) => $query->orderBy('group_code'),
        ]);
        $offeringIds = $isWorkspace
            ? $availableOfferings->pluck('id')->all()
            : ($courseOffering ? [$courseOffering->id] : []);

        $firstScheduleDate = empty($offeringIds)
            ? null
            : Schedule::query()
                ->whereIn('course_offering_id', $offeringIds)
                ->whereNotNull('start_date')
                ->orderBy('start_date')
                ->value('start_date');

        $baseDate = $this->validWeekStart($request)
            ?? ($firstScheduleDate ? CarbonImmutable::parse($firstScheduleDate) : null)
            ?? ($courseOffering?->academicYear?->start_date ? CarbonImmutable::parse($courseOffering->academicYear->start_date) : null)
            ?? ($availableOfferings->first()?->academicYear?->start_date ? CarbonImmutable::parse($availableOfferings->first()->academicYear->start_date) : null)
            ?? CarbonImmutable::now();

        $weekStart = CarbonImmutable::parse($baseDate)->startOfWeek(CarbonInterface::MONDAY);
        $weekEnd = $weekStart->addDays(6);
        $gridEnd = $weekStart->addDays(4);

        $schedules = empty($offeringIds)
            ? collect()
            : Schedule::query()
                ->with([
                    'courseOffering.course.curriculum',
                    'courseOffering.course.department',
                    'courseOffering.academicYear',
                    'courseOffering.instructorPool.instructorProfile.department',
                    'courseOffering.studentGroups' => fn ($query) => $query->orderBy('group_code'),
                    'activityType',
                    'room.locationType',
                    'instructors.instructorProfile.department',
                    'studentGroups',
                ])
                ->whereIn('course_offering_id', $offeringIds)
                ->whereDate('start_date', '<=', $weekEnd->toDateString())
                ->whereDate('end_date', '>=', $weekStart->toDateString())
                ->orderBy('start_date')
                ->orderBy('end_date')
                ->orderBy('start_time')
                ->get();

        $weekDays = collect(range(0, 4))
            ->map(fn (int $offset) => $weekStart->addDays($offset))
            ->values();
        $occurrences = $this->scheduleOccurrences($schedules, $weekStart, $gridEnd);
        $timeSlots = $this->scheduleTimeSlots($occurrences);

        return [
            'courseOffering' => $courseOffering,
            'availableOfferings' => $availableOfferings,
            'isWorkspace' => $isWorkspace,
            'schedules' => $schedules,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'weekDays' => $weekDays,
            'occurrences' => $occurrences,
            'timeSlots' => $timeSlots,
            'activityTypes' => ActivityType::orderBy('name')->get(),
            'rooms' => Room::query()
                ->with('locationType')
                ->where('status', 'active')
                ->orderBy('room_code')
                ->get(),
            'previousWeekUrl' => $this->scheduleWeekUrl($courseOffering, $weekStart->subWeek(), $isWorkspace),
            'nextWeekUrl' => $this->scheduleWeekUrl($courseOffering, $weekStart->addWeek(), $isWorkspace),
        ];
    }

    private function scheduleTimeSlots(Collection $occurrences): array
    {
        return collect(range(6, 16))
            ->map(fn (int $hour) => sprintf('%02d:00', $hour))
            ->merge($occurrences->pluck('time_slot'))
            ->filter()
            ->unique()
            ->sortBy(fn (string $slot) => (int) substr($slot, 0, 2))
            ->values()
            ->all();
    }

    private function validWeekStart(Request $request): ?CarbonImmutable
    {
        $value = $request->query('week_start');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        return $date instanceof CarbonImmutable && $date->format('Y-m-d') === $value
            ? $date
            : null;
    }

    private function coordinatorScheduleOfferings(): Collection
    {
        return CourseOffering::query()
            ->with([
                'course.curriculum',
                'course.department',
                'academicYear',
                'instructorPool.instructorProfile.department',
                'studentGroups' => fn ($query) => $query->orderBy('group_code'),
            ])
            ->withCount(['schedules', 'studentGroups', 'instructorPool'])
            ->where('coordinator_id', Auth::id())
            ->latest('updated_at')
            ->get()
            ->sortByDesc(fn (CourseOffering $offering) => $offering->academicYear?->phase === 'scheduling')
            ->values();
    }

    private function scheduleWeekUrl(?CourseOffering $courseOffering, CarbonImmutable $weekStart, bool $isWorkspace): string
    {
        if ($isWorkspace || ! $courseOffering) {
            return route('maker.schedules.index', array_filter([
                'course_offering_id' => $courseOffering?->id,
                'week_start' => $weekStart->toDateString(),
            ]));
        }

        return route('maker.course_offerings.schedules.index', [
            $courseOffering,
            'week_start' => $weekStart->toDateString(),
        ]);
    }

    public function create(Request $request, CourseOffering $courseOffering): View|RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        $courseOffering->load('academicYear');

        if ($courseOffering->academicYear?->phase !== 'scheduling') {
            return redirect()
                ->route('maker.course_offerings.schedules.index', $courseOffering)
                ->withErrors(['schedule' => 'ยังไม่เปิดช่วงจัดตาราง — Admin ต้องเปิดช่วงจัดตารางก่อน']);
        }

        return redirect()->route('maker.course_offerings.schedules.index', array_filter([
            $courseOffering,
            'modal' => 'create',
            'week_start' => $request->query('week_start'),
        ]));
    }

    public function store(
        Request $request,
        CourseOffering $courseOffering,
        ScheduleConflictChecker $conflictChecker
    ): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);

        return $this->storeForOffering($request, $courseOffering, $conflictChecker);
    }

    private function storeForOffering(
        Request $request,
        CourseOffering $courseOffering,
        ScheduleConflictChecker $conflictChecker,
        bool $redirectToWorkspace = false
    ): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        if ($redirect = $this->requireSchedulingPhase($courseOffering, $redirectToWorkspace)) return $redirect;

        $validated = $this->validateSchedule($request, $courseOffering);
        $this->assertLeadInstructorSelected($validated);
        $this->assertNoConflicts($conflictChecker, $validated);

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

            $this->syncInstructors($schedule, $validated);
            $schedule->studentGroups()->sync($validated['student_group_ids']);
        });

        return redirect()
            ->to($redirectToWorkspace ? $this->workspaceRedirectUrl($validated['start_date']) : route('maker.course_offerings.schedules.index', $courseOffering))
            ->with('success', 'เพิ่มรายการสอนเรียบร้อยแล้ว');
    }

    public function edit(CourseOffering $courseOffering, Schedule $schedule): View|RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        $this->assertScheduleBelongsToOffering($courseOffering, $schedule);

        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        return redirect()->route('maker.course_offerings.schedules.index', [
            $courseOffering,
            'edit_schedule_id' => $schedule->id,
            'week_start' => $schedule->start_date?->toDateString(),
        ]);
    }

    public function update(
        Request $request,
        CourseOffering $courseOffering,
        Schedule $schedule,
        ScheduleConflictChecker $conflictChecker
    ): RedirectResponse {
        $this->authorizeCourseHeadOffering($courseOffering);
        $this->assertScheduleBelongsToOffering($courseOffering, $schedule);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        $validated = $this->validateSchedule($request, $courseOffering);
        $this->assertLeadInstructorSelected($validated);
        $this->assertNoConflicts($conflictChecker, $validated, $schedule->id);

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

            $this->syncInstructors($schedule, $validated);
            $schedule->studentGroups()->sync($validated['student_group_ids']);
        });

        return redirect()
            ->route('maker.course_offerings.schedules.index', $courseOffering)
            ->with('success', 'อัปเดตรายการสอนเรียบร้อยแล้ว');
    }

    public function destroy(CourseOffering $courseOffering, Schedule $schedule): RedirectResponse
    {
        $this->authorizeCourseHeadOffering($courseOffering);
        $this->assertScheduleBelongsToOffering($courseOffering, $schedule);
        if ($redirect = $this->requireSchedulingPhase($courseOffering)) return $redirect;

        $schedule->delete();

        return redirect()
            ->route('maker.course_offerings.schedules.index', $courseOffering)
            ->with('warning', 'ลบรายการสอนเรียบร้อยแล้ว');
    }

    private function authorizeCourseHeadOffering(CourseOffering $courseOffering): void
    {
        abort_unless((int) $courseOffering->coordinator_id === (int) Auth::id(), 403);
    }

    private function assertScheduleBelongsToOffering(CourseOffering $courseOffering, Schedule $schedule): void
    {
        abort_unless((int) $schedule->course_offering_id === (int) $courseOffering->id, 404);
    }

    private function requireSchedulingPhase(CourseOffering $courseOffering, bool $redirectToWorkspace = false): ?RedirectResponse
    {
        $courseOffering->loadMissing('academicYear');

        if ($courseOffering->academicYear?->phase !== 'scheduling') {
            return redirect()
                ->to($redirectToWorkspace ? route('maker.schedules.index') : route('maker.course_offerings.schedules.index', $courseOffering))
                ->withErrors(['schedule' => 'ยังไม่เปิดช่วงจัดตาราง — Admin ต้องเปิดช่วงจัดตารางก่อน']);
        }

        return null;
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
                ->with('locationType')
                ->where('status', 'active')
                ->orderBy('room_code')
                ->get(),
        ];
    }

    private function workspaceRedirectUrl(string $date): string
    {
        $weekStart = CarbonImmutable::parse($date)->startOfWeek(CarbonInterface::MONDAY);

        return route('maker.schedules.index', ['week_start' => $weekStart->toDateString()]);
    }

    private function scheduleOccurrences($schedules, CarbonImmutable $weekStart, CarbonImmutable $weekEnd)
    {
        return $schedules
            ->flatMap(function (Schedule $schedule) use ($weekStart, $weekEnd) {
                $startDate = CarbonImmutable::parse($schedule->start_date ?? $schedule->teaching_date);
                $endDate = CarbonImmutable::parse($schedule->end_date ?? $schedule->teaching_date);
                $rangeStart = $startDate->greaterThan($weekStart) ? $startDate : $weekStart;
                $rangeEnd = $endDate->lessThan($weekEnd) ? $endDate : $weekEnd;

                return collect(CarbonPeriod::create($rangeStart, $rangeEnd))
                    ->filter(fn ($date) => $date->dayOfWeekIso <= 5)
                    ->map(fn ($date) => [
                        'schedule' => $schedule,
                        'date' => CarbonImmutable::parse($date),
                        'duration_minutes' => $this->durationMinutes($schedule),
                        'time_slot' => substr((string) $schedule->start_time, 0, 2) . ':00',
                    ]);
            })
            // Laravel 13: sortBy([closure, closure, ...]) เรียงผิด — ใช้ closure เดียวสร้าง composite key
            ->sortBy(fn ($item) => $item['date']->toDateString()
                . ' ' . substr((string) $item['schedule']->start_time, 0, 8)
                . ' ' . str_pad((string) $item['schedule']->id, 10, '0', STR_PAD_LEFT))
            ->values();
    }

    private function durationMinutes(Schedule $schedule): int
    {
        $start = CarbonImmutable::createFromFormat('H:i:s', strlen((string) $schedule->start_time) === 5 ? $schedule->start_time . ':00' : (string) $schedule->start_time);
        $end = CarbonImmutable::createFromFormat('H:i:s', strlen((string) $schedule->end_time) === 5 ? $schedule->end_time . ':00' : (string) $schedule->end_time);

        return (int) max(0, $start->diffInMinutes($end));
    }

    private function validateSchedule(Request $request, CourseOffering $courseOffering): array
    {
        return $request->validate([
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
            'lead_instructor_id' => ['nullable', 'integer'],
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
            'start_date' => 'วันที่เริ่ม',
            'end_date' => 'วันที่สิ้นสุด',
            'start_time' => 'เวลาเริ่ม',
            'end_time' => 'เวลาสิ้นสุด',
            'activity_type_id' => 'ประเภทกิจกรรม',
            'room_id' => 'ห้อง/สถานที่',
            'topic' => 'หัวข้อ',
            'remark' => 'หมายเหตุ',
            'capacity_required' => 'จำนวนรองรับ',
            'sub_group_label' => 'ป้ายกลุ่มย่อย',
            'lead_instructor_id' => 'ผู้สอนหลัก',
            'instructor_ids' => 'ผู้สอน',
            'instructor_ids.*' => 'ผู้สอน',
            'student_group_ids' => 'กลุ่มนักศึกษา',
            'student_group_ids.*' => 'กลุ่มนักศึกษา',
        ]);
    }

    private function assertLeadInstructorSelected(array $validated): void
    {
        $leadId = $validated['lead_instructor_id'] ?? null;
        if (! $leadId) {
            return;
        }

        if (! in_array((int) $leadId, array_map('intval', $validated['instructor_ids']), true)) {
            throw ValidationException::withMessages([
                'lead_instructor_id' => 'ผู้สอนหลักต้องอยู่ในรายชื่อผู้สอนที่เลือก',
            ]);
        }
    }

    private function assertNoConflicts(
        ScheduleConflictChecker $conflictChecker,
        array $validated,
        ?int $ignoreScheduleId = null
    ): void {
        $conflicts = $conflictChecker->check(
            Arr::only($validated, ['start_date', 'end_date', 'start_time', 'end_time', 'room_id']),
            array_map('intval', $validated['instructor_ids']),
            array_map('intval', $validated['student_group_ids']),
            $ignoreScheduleId
        );

        if (! empty($conflicts)) {
            throw ValidationException::withMessages([
                'schedule' => collect($conflicts)->pluck('message')->unique()->implode(' / '),
            ]);
        }
    }

    private function syncInstructors(Schedule $schedule, array $validated): void
    {
        $leadId = isset($validated['lead_instructor_id']) ? (int) $validated['lead_instructor_id'] : null;
        $payload = collect($validated['instructor_ids'])
            ->mapWithKeys(fn ($id) => [
                (int) $id => ['is_lead' => $leadId ? (int) $id === $leadId : false],
            ])
            ->all();

        $schedule->instructors()->sync($payload);
    }
}
