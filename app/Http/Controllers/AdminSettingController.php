<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRole;
use App\Models\SystemSetting;
use App\Http\Controllers\Admin\AlertController;
use App\Services\AuditLogger;
use App\Services\NavigationBadgeService;
use App\Support\ThaiDate;
use Illuminate\Support\Facades\DB;

class AdminSettingController extends Controller
{
    private function auditSnapshot(AcademicYear $year): array
    {
        return collect($year->only(['name', 'semester', 'start_date', 'end_date', 'is_active']))
            ->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value)
            ->all();
    }

    private function auditDiff(array $before, array $after): array
    {
        $old = [];
        $new = [];

        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $old[$key] = $before[$key] ?? null;
                $new[$key] = $value;
            }
        }

        return [$old, $new];
    }

    private function logAcademicYearUpdate(AcademicYear $year, array $oldValues, array $newValues): void
    {
        if (empty($oldValues) && empty($newValues)) {
            return;
        }

        AuditLogger::log(
            action: 'ข้อมูลหลัก.แก้ไข',
            table: 'academic_years',
            recordId: $year->id,
            oldValues: $oldValues,
            newValues: $newValues,
            description: "แก้ไขปีการศึกษา {$year->name} ภาค {$year->semester}",
        );
    }

    private function schedulingLockMessage(AcademicYear $year): string
    {
        return "ไม่สามารถตั้งปีการศึกษา {$year->name} ภาค {$year->semester} เป็นปีปัจจุบันได้ เนื่องจากยังมีช่วงจัดตารางที่เปิดใช้งานอยู่ กรุณาปิดช่วงจัดตารางเดิมก่อน";
    }

    private function hasOtherOpenSchedulingWindow(AcademicYear $year): bool
    {
        return AcademicYear::where('phase', 'scheduling')
            ->where('id', '!=', $year->id)
            ->exists();
    }

    public function index()
    {
        $academicYears = AcademicYear::orderBy('name', 'desc')->orderBy('semester', 'desc')->get();
        $paCriteria = json_decode(SystemSetting::get('pa_criteria_config', '{}'), true);

        $workloadWeeks = SystemSetting::get('teaching_quota_weeks', 46);
        $teachingWeeks = SystemSetting::get('teaching_load_weeks', 39);
        $workloadHoursPerWeek = SystemSetting::get('teaching_quota_hours_per_week', 35);
        $workloadQuota = $workloadWeeks * $workloadHoursPerWeek;
        $teachingQuota = $teachingWeeks * $workloadHoursPerWeek;

        $firstGroup = !empty($paCriteria) ? reset($paCriteria) : null;
        $firstField = $firstGroup ? reset($firstGroup) : null;
        if (empty($paCriteria) || !is_array($firstField)) {
            $paCriteria = self::defaultPaCriteria();
        }

        $schedulingSummary = AcademicYear::orderBy('name', 'desc')->orderBy('semester', 'desc')->get();
        $schedulingCriticals = AlertController::getCriticals();

        $isAdmin     = true;
        $routePrefix = 'admin';
        return view('admin.settings', compact('academicYears', 'paCriteria', 'workloadQuota', 'teachingQuota', 'workloadWeeks', 'teachingWeeks', 'workloadHoursPerWeek', 'isAdmin', 'routePrefix', 'schedulingSummary', 'schedulingCriticals'));
    }

    public function storeYear(Request $request)
    {
        $this->normalizeThaiDateInputs($request, ['start_date', 'end_date']);

        $validated = $request->validate([
            'name'       => ['required', 'string', \Illuminate\Validation\Rule::unique('academic_years')->where('semester', $request->integer('semester'))],
            'semester'   => 'required|integer',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ], [
            'name.unique'            => 'ปีการศึกษา ' . $request->input('name') . ' ภาคเรียนที่ ' . $request->input('semester') . ' มีอยู่แล้วในระบบ',
            'end_date.after_or_equal' => 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น',
        ]);

        $validated['is_active'] = $request->has('is_active');

        if ($validated['is_active']) {
            $newYearGuard = new AcademicYear([
                'name' => $validated['name'],
                'semester' => $validated['semester'],
            ]);

            if ($this->hasOtherOpenSchedulingWindow($newYearGuard)) {
                return back()
                    ->withInput()
                    ->withErrors(['is_active' => $this->schedulingLockMessage($newYearGuard)])
                    ->with('error', $this->schedulingLockMessage($newYearGuard));
            }

            AcademicYear::where('is_active', true)->update(['is_active' => false]);
            $this->closeAllSchedulingWindows();

            // Auto-sync courses status based on default_semester AND curriculum status
            // 1. Force inactive for any course whose curriculum is inactive
            \App\Models\Course::whereHas('curriculum', function($q) {
                $q->where('is_active', false);
            })->update(['status' => 'inactive']);

            // 2. For active curriculums, open courses matching the semester
            \App\Models\Course::whereHas('curriculum', function($q) {
                $q->where('is_active', true);
            })->where('default_semester', $validated['semester'])->update(['status' => 'active']);

            // 3. For active curriculums, close courses NOT matching the semester
            \App\Models\Course::whereHas('curriculum', function($q) {
                $q->where('is_active', true);
            })->where(function($query) use ($validated) {
                $query->where('default_semester', '!=', $validated['semester'])
                      ->orWhereNull('default_semester');
            })->update(['status' => 'inactive']);
        }

        $year = AcademicYear::create($validated);

        AuditLogger::log(
            action: 'ข้อมูลหลัก.สร้าง',
            table: 'academic_years',
            recordId: $year->id,
            oldValues: null,
            newValues: $this->auditSnapshot($year),
            description: "สร้างปีการศึกษา {$year->name} ภาค {$year->semester}",
        );

        AlertController::flushCache();
        return redirect()->route($this->settingsRoute(), ['tab' => 'academic'])->with('success', 'เพิ่มปีการศึกษาเรียบร้อยแล้ว');
    }

    public function updateYear(Request $request, AcademicYear $year)
    {
        $this->normalizeThaiDateInputs($request, ['start_date', 'end_date']);

        $validated = $request->validate([
            'name'       => ['required', 'string', \Illuminate\Validation\Rule::unique('academic_years')->where('semester', $request->integer('semester'))->ignore($year->id)],
            'semester'   => 'required|integer',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ], [
            'name.unique'            => 'ปีการศึกษา ' . $request->input('name') . ' ภาคเรียนที่ ' . $request->input('semester') . ' มีอยู่แล้วในระบบ',
            'end_date.after_or_equal' => 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น',
        ]);

        $validated['is_active'] = $request->has('is_active');

        if (!$validated['is_active'] && $year->is_active) {
            return redirect()->route($this->settingsRoute(), ['tab' => 'academic'])
                ->with('error', 'ไม่สามารถยกเลิกปีการศึกษาปัจจุบันได้ — ต้องมีปีการศึกษาที่ใช้งานอยู่เสมอ กรุณาตั้งค่าปีการศึกษาอื่นเป็นปัจจุบันก่อน');
        }

        $before = $this->auditSnapshot($year);

        if ($validated['is_active'] && (! $year->is_active || $this->hasOtherOpenSchedulingWindow($year))) {
            if ($this->hasOtherOpenSchedulingWindow($year)) {
                return back()
                    ->withInput()
                    ->withErrors(['is_active' => $this->schedulingLockMessage($year)])
                    ->with('error', $this->schedulingLockMessage($year));
            }
        }

        if ($validated['is_active']) {
            AcademicYear::where('id', '!=', $year->id)->where('is_active', true)->update(['is_active' => false]);
            $this->closeSchedulingWindowsExcept($year);

            // Auto-sync courses status based on default_semester AND curriculum status
            // 1. Force inactive for any course whose curriculum is inactive
            \App\Models\Course::whereHas('curriculum', function($q) {
                $q->where('is_active', false);
            })->update(['status' => 'inactive']);

            // 2. For active curriculums, open courses matching the semester
            \App\Models\Course::whereHas('curriculum', function($q) {
                $q->where('is_active', true);
            })->where('default_semester', $validated['semester'])->update(['status' => 'active']);

            // 3. For active curriculums, close courses NOT matching the semester
            \App\Models\Course::whereHas('curriculum', function($q) {
                $q->where('is_active', true);
            })->where(function($query) use ($validated) {
                $query->where('default_semester', '!=', $validated['semester'])
                      ->orWhereNull('default_semester');
            })->update(['status' => 'inactive']);
        }

        $year->update($validated);

        $after = $this->auditSnapshot($year->fresh());
        [$oldValues, $newValues] = $this->auditDiff($before, $after);
        $this->logAcademicYearUpdate($year->fresh(), $oldValues, $newValues);

        AlertController::flushCache();
        return redirect()->route($this->settingsRoute(), ['tab' => 'academic'])->with('success', 'อัปเดตปีการศึกษาเรียบร้อยแล้ว');
    }

    public function openSchedulingWindow(AcademicYear $year)
    {
        if (!$year->is_active) {
            return redirect()
                ->route('admin.settings', ['tab' => 'academic'])
                ->with('error', "เปิดช่วงจัดตารางได้เฉพาะปีการศึกษาปัจจุบัน ({$year->name} ภาค {$year->semester} ไม่ใช่ปีปัจจุบัน)");
        }

        if ($year->phase === 'published') {
            return redirect()
                ->route('admin.settings', ['tab' => 'academic'])
                ->with('error', "ปีการศึกษา {$year->name} ภาค {$year->semester} เผยแพร่แล้ว ไม่สามารถย้อนกลับได้");
        }

        $criticals = AlertController::getCriticals();
        if (!empty($criticals)) {
            $labels = collect($criticals)->pluck('label')->take(3)->implode(', ');
            $suffix = count($criticals) > 3 ? ' และรายการอื่น ๆ' : '';

            return redirect()
                ->route('admin.settings', ['tab' => 'academic'])
                ->with('error', "ยังไม่สามารถเปิดช่วงจัดตารางได้ เนื่องจากยังมี Critical: {$labels}{$suffix}");
        }

        // Auto-create offerings for active courses in this academic year/semester.
        // The selected academic year supplies the year/term; courses must match
        // the academic year's semester to be opened in this round.
        // Existing offerings for this academic year are synced too, because they may
        // have been seeded or created before the template was finalized.
        $courses = Course::query()
            ->offeredInAcademicTerm($year)
            ->get();

        $created = 0;
        $synced = 0;
        $closedSchedulingWindows = 0;
        $teachingWeeks = (int) SystemSetting::get('teaching_load_weeks', 39);
        $coordinatorRoleId = CourseRole::where('name_th', 'หัวหน้าวิชา')->value('id');

        DB::transaction(function () use ($courses, $year, $teachingWeeks, $coordinatorRoleId, &$created, &$synced, &$closedSchedulingWindows) {
            $closedSchedulingWindows = $this->closeSchedulingWindowsExcept($year);

            foreach ($courses as $course) {
                $offering = CourseOffering::firstOrCreate(
                    [
                        'course_id' => $course->id,
                        'academic_year_id' => $year->id,
                    ],
                    [
                        'course_id'       => $course->id,
                        'academic_year_id'=> $year->id,
                        'coordinator_id'  => $course->head_instructor_id,
                        'approval_status' => 'draft',
                    ]
                );

                if ($offering->wasRecentlyCreated) {
                    $created++;
                }
            }

            $offeringsToSync = CourseOffering::with('course')
                ->where('academic_year_id', $year->id)
                ->whereHas('course', fn ($query) => $query->offeredInAcademicTerm($year))
                ->get();

            foreach ($offeringsToSync as $offering) {
                $course = $offering->course;
                if ($course) {
                    $offering->fill([
                        'coordinator_id' => $course->head_instructor_id,
                        'total_student_count' => $course->capacity,
                        'planned_lecture_hours' => $course->lecture_hours,
                        'planned_lab_hours' => $course->requires_practicum_rotation ? 0 : $course->lab_hours,
                        'planned_practicum_hours' => $course->requires_practicum_rotation ? $course->lab_hours : 0,
                        'teaching_weeks' => $teachingWeeks,
                        'requires_practicum_rotation' => $course->requires_practicum_rotation,
                        'practicum_note' => null,
                    ])->save();
                }
                $offering->syncInstructorPoolFromCourseTemplate($coordinatorRoleId);
                $synced++;
            }

            $year->update(['phase' => 'scheduling']);
        });

        $total = CourseOffering::withActiveCourse()
            ->where('academic_year_id', $year->id)
            ->whereHas('course', fn ($query) => $query->offeredInAcademicTerm($year))
            ->count();
        $newMsg = $created > 0 ? "สร้างใหม่ {$created} รายวิชา" : "ไม่มีรายวิชาใหม่";
        $syncMsg = $synced > 0 ? "ซิงก์แม่แบบ {$synced} รายวิชา" : "ไม่พบรายวิชาเดิมที่ต้องซิงก์";

        AuditLogger::log(
            action:      'ตั้งค่าระบบ.เปิดช่วงจัดตาราง',
            table:       'academic_years',
            recordId:    $year->id,
            oldValues:   ['phase' => 'preparation'],
            newValues:   ['phase' => 'scheduling', 'offerings_created' => $created, 'offerings_synced' => $synced, 'other_scheduling_windows_closed' => $closedSchedulingWindows],
            description: "เปิดช่วงจัดตารางปีการศึกษา {$year->name} ภาค {$year->semester}",
        );

        if ($created > 0 || $synced > 0) {
            AuditLogger::log(
                action:      'รายวิชาและผู้รับผิดชอบ.ซิงก์ข้อมูล',
                table:       'course_offerings',
                recordId:    $year->id,
                oldValues:   null,
                newValues:   [
                    'academic_year_id' => $year->id,
                    'academic_year_name' => $year->name,
                    'semester' => $year->semester,
                    'offerings_created' => $created,
                    'offerings_synced' => $synced,
                    'affected_count' => $synced ?: $created,
                    'sample_course_codes' => $courses->pluck('course_code')->take(5)->values()->all(),
                ],
                category:    'รายวิชาและผู้รับผิดชอบ',
                description: "ซิงก์ข้อมูลรายวิชาจากต้นแบบไปยังรอบเปิดสอน ปีการศึกษา {$year->name} ภาค {$year->semester}",
            );
        }

        return redirect()
            ->route('admin.settings', ['tab' => 'academic'])
            ->with('success', "เปิดช่วงจัดตารางสำหรับปีการศึกษา {$year->name} ภาค {$year->semester} แล้ว — {$newMsg}, {$syncMsg} รวม {$total} รายวิชาพร้อมจัดตาราง");
    }

    public function closeSchedulingWindow(AcademicYear $year)
    {
        if (!$year->is_active) {
            return redirect()
                ->route('admin.settings', ['tab' => 'academic'])
                ->with('error', "ปิดช่วงจัดตารางได้เฉพาะปีการศึกษาปัจจุบัน");
        }

        if ($year->phase !== 'scheduling') {
            return redirect()
                ->route('admin.settings', ['tab' => 'academic'])
                ->with('error', "ปีการศึกษา {$year->name} ภาค {$year->semester} ไม่ได้อยู่ในช่วงจัดตาราง");
        }

        $year->update(['phase' => 'preparation']);

        CourseOffering::query()
            ->withActiveCourse()
            ->where('academic_year_id', $year->id)
            ->pluck('coordinator_id')
            ->filter()
            ->unique()
            ->each(fn ($userId) => NavigationBadgeService::flushCourseHead((int) $userId));

        AuditLogger::log(
            action:      'ตั้งค่าระบบ.ปิดช่วงจัดตาราง',
            table:       'academic_years',
            recordId:    $year->id,
            oldValues:   ['phase' => 'scheduling'],
            newValues:   ['phase' => 'preparation'],
            description: "ปิดช่วงจัดตารางปีการศึกษา {$year->name} ภาค {$year->semester}",
        );

        return redirect()
            ->route('admin.settings', ['tab' => 'academic'])
            ->with('success', "ปิดช่วงจัดตารางสำหรับปีการศึกษา {$year->name} ภาค {$year->semester} แล้ว");
    }

    private function settingsRoute(): string
    {
        return request()->routeIs('staff.*') ? 'staff.settings' : 'admin.settings';
    }

    private function closeAllSchedulingWindows(): int
    {
        return AcademicYear::where('phase', 'scheduling')
            ->update(['phase' => 'preparation']);
    }

    private function closeSchedulingWindowsExcept(AcademicYear $year): int
    {
        return AcademicYear::where('id', '!=', $year->id)
            ->where('phase', 'scheduling')
            ->update(['phase' => 'preparation']);
    }

    public function updateConstants(Request $request)
    {
        $request->validate([
            'teaching_quota_weeks'          => 'required|numeric|min:1',
            'teaching_load_weeks'           => 'required|numeric|min:1',
            'teaching_quota_hours_per_week' => 'required|numeric|min:1',
            'pa_criteria'                   => 'required|array',
            'pa_criteria.*.*.min'           => 'required|integer|min:0|max:100',
            'pa_criteria.*.*.max'           => 'required|integer|min:0|max:100',
        ]);

        foreach ($request->pa_criteria as $rank => $fields) {
            foreach ($fields as $field => $range) {
                if ((int)$range['min'] > (int)$range['max']) {
                    return back()->withErrors(['pa_criteria' => "เกณฑ์ {$rank} ด้าน {$field}: ค่าต่ำสุด ({$range['min']}) ต้องไม่มากกว่าค่าสูงสุด ({$range['max']})"]);
                }
            }
        }

        $oldValues = [
            'teaching_quota_weeks' => (int) SystemSetting::get('teaching_quota_weeks', 46),
            'teaching_load_weeks' => (int) SystemSetting::get('teaching_load_weeks', 39),
            'teaching_quota_hours_per_week' => (int) SystemSetting::get('teaching_quota_hours_per_week', 35),
            'teaching_quota_hours' => (int) SystemSetting::get('teaching_quota_hours', 1610),
            'pa_criteria_config' => json_decode(SystemSetting::get('pa_criteria_config', '{}'), true) ?: [],
        ];

        $totalHours = $request->teaching_quota_weeks * $request->teaching_quota_hours_per_week;

        SystemSetting::set('teaching_quota_weeks', $request->teaching_quota_weeks);
        SystemSetting::set('teaching_load_weeks', $request->teaching_load_weeks);
        SystemSetting::set('teaching_quota_hours_per_week', $request->teaching_quota_hours_per_week);
        SystemSetting::set('teaching_quota_hours', $totalHours);

        $criteria = [];
        foreach ($request->pa_criteria as $rank => $fields) {
            foreach ($fields as $field => $range) {
                $criteria[$rank][$field] = [
                    'min' => (int) $range['min'],
                    'max' => (int) $range['max'],
                ];
            }
        }
        SystemSetting::set('pa_criteria_config', json_encode($criteria));

        $newValues = [
            'teaching_quota_weeks' => (int) $request->teaching_quota_weeks,
            'teaching_load_weeks' => (int) $request->teaching_load_weeks,
            'teaching_quota_hours_per_week' => (int) $request->teaching_quota_hours_per_week,
            'teaching_quota_hours' => (int) $totalHours,
            'pa_criteria_config' => $criteria,
        ];

        [$changedOld, $changedNew] = $this->auditDiff($oldValues, $newValues);
        if (!empty($changedOld) || !empty($changedNew)) {
            AuditLogger::log(
                action: 'ตั้งค่าระบบ.แก้ไข',
                table: 'system_settings',
                recordId: 0,
                oldValues: $changedOld,
                newValues: $changedNew,
                category: 'ตั้งค่าระบบ',
                description: 'แก้ไขค่าคงที่และเกณฑ์ PA',
            );
        }

        AlertController::flushCache();
        return redirect()->route('admin.settings', ['tab' => 'pa'])->with('success', 'บันทึกค่าคงที่และเกณฑ์ PA เรียบร้อยแล้ว');
    }

    public static function defaultPaCriteria(): array
    {
        return [
            'อาจารย์'               => ['t' => ['min' => 20, 'max' => 70], 'r' => ['min' => 20, 'max' => 70], 's' => ['min' => 5,  'max' => 20], 'c' => ['min' => 5, 'max' => 15], 'o' => ['min' => 0, 'max' => 20]],
            'ผู้ช่วยอาจารย์'        => ['t' => ['min' => 0,  'max' => 70], 'r' => ['min' => 15, 'max' => 20], 's' => ['min' => 5,  'max' => 20], 'c' => ['min' => 5, 'max' => 20], 'o' => ['min' => 0, 'max' => 20]],
            'ผู้ช่วยอาจารย์_ปตรี'   => ['t' => ['min' => 30, 'max' => 60], 'r' => ['min' => 0,  'max' => 0],  's' => ['min' => 10, 'max' => 30], 'c' => ['min' => 10,'max' => 20], 'o' => ['min' => 0, 'max' => 30]],
            'ผู้ช่วยอาจารย์_คลินิก' => ['t' => ['min' => 0,  'max' => 10], 'r' => ['min' => 0,  'max' => 5],  's' => ['min' => 70, 'max' => 80], 'c' => ['min' => 0, 'max' => 5],  'o' => ['min' => 0, 'max' => 10]],
            'ผู้ช่วยอาจารย์_ปฏิบัติ'=> ['t' => ['min' => 0,  'max' => 70], 'r' => ['min' => 0,  'max' => 0],  's' => ['min' => 5,  'max' => 20], 'c' => ['min' => 5, 'max' => 20], 'o' => ['min' => 0, 'max' => 20]],
        ];
    }

    private function normalizeThaiDateInputs(Request $request, array $fields): void
    {
        foreach ($fields as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $value = $request->input($field);
            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            $iso = ThaiDate::parseToIso((string) $value);
            if ($iso) {
                $request->merge([$field => $iso]);
            }
        }
    }
}
