<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AcademicYear;
use App\Models\AcademicCalendar;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CourseOffering;
use App\Models\CourseRole;
use App\Models\Holiday;
use App\Models\SystemSetting;
use App\Services\HolidayService;
use App\Http\Controllers\Admin\AlertController;
use App\Services\AuditLogger;
use App\Services\NavigationBadgeService;
use App\Support\ThaiDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AdminSettingController extends Controller
{
    private function auditSnapshot(AcademicYear $year): array
    {
        return collect($year->only(['name', 'start_date', 'end_date', 'is_active']))
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
            description: "แก้ไขปีการศึกษา {$year->name}",
        );
    }

    private function schedulingLockMessage(AcademicYear $year): string
    {
        return "ไม่สามารถตั้งปีการศึกษา {$year->name} เป็นปีปัจจุบันได้ เนื่องจากยังมีช่วงจัดตารางที่เปิดใช้งานอยู่ กรุณาปิดช่วงจัดตารางเดิมก่อน";
    }

    /**
     * สร้างเทอมเริ่มต้น (เทอม 1/2) ให้ปีใหม่ โดยแบ่งช่วงปีครึ่ง ๆ
     * วันสอบเว้นว่างไว้ให้ Admin กรอกภายหลัง · ภาคฤดูร้อนเพิ่มเองได้
     */
    private function createDefaultTerms(AcademicYear $year): void
    {
        $calendar = $year->fallbackCalendar();
        if ($calendar->terms()->exists()) {
            return;
        }

        $start = Carbon::parse($year->start_date);
        $end = Carbon::parse($year->end_date);
        $mid = $start->copy()->addDays((int) floor($start->diffInDays($end) / 2));

        $calendar->terms()->createMany([
            ['sequence' => 1, 'name' => 'ภาคเรียนที่ 1', 'start_date' => $start->toDateString(), 'end_date' => $mid->toDateString()],
            ['sequence' => 2, 'name' => 'ภาคเรียนที่ 2', 'start_date' => $mid->copy()->addDay()->toDateString(), 'end_date' => $end->toDateString()],
        ]);
    }

    /**
     * V2: วิชาเปิดทั้งปี → status ตาม curriculum active เท่านั้น (เลิกผูก default_semester)
     */
    private function syncCourseStatusByCurriculum(): void
    {
        Course::whereHas('curriculum', fn ($q) => $q->where('is_active', false))->update(['status' => 'inactive']);
        Course::whereHas('curriculum', fn ($q) => $q->where('is_active', true))->update(['status' => 'active']);
    }

    /**
     * แปลงวันที่เทอม (terms[][]) จากรูปแบบ พ.ศ. → ISO ก่อน validate/บันทึก
     */
    private function normalizeTermDates(Request $request): void
    {
        $terms = $request->input('terms');
        if (! is_array($terms)) {
            return;
        }

        $fields = ['start_date', 'end_date', 'midterm_start', 'midterm_end', 'final_start', 'final_end'];
        foreach ($terms as $i => $t) {
            foreach ($fields as $f) {
                $value = $t[$f] ?? null;
                if ($value === null || trim((string) $value) === '') {
                    $terms[$i][$f] = null;
                    continue;
                }
                $terms[$i][$f] = ThaiDate::parseToIso((string) $value) ?: null;
            }
        }

        $request->merge(['terms' => $terms]);
    }

    /**
     * คำนวณช่วงปีการศึกษาจากเทอม: วันเริ่ม = min(start เทอม), วันสิ้นสุด = max(end เทอม)
     * (วันที่เป็น ISO แล้ว — sort แบบ string = ตามลำดับเวลา)
     */
    private function yearSpanFromTerms(Request $request): array
    {
        $starts = [];
        $ends = [];
        foreach ($request->input('terms', []) as $t) {
            if (! empty($t['start_date'])) $starts[] = $t['start_date'];
            if (! empty($t['end_date'])) $ends[] = $t['end_date'];
        }
        if (empty($starts) || empty($ends)) {
            return [null, null];
        }
        sort($starts);
        sort($ends);
        return [$starts[0], end($ends)];
    }

    /**
     * เติมวันหยุดราชการอัตโนมัติจาก API ตามปีปฏิทินที่ปีการศึกษาคร่อม (fail-safe)
     * คืน ['ok'=>bool, 'message'=>string] เพื่อแนบใน flash
     */
    private function autoFetchHolidays(AcademicYear $year): array
    {
        $count = app(\App\Services\HolidayService::class)
            ->syncForAcademicYearSpan((string) $year->start_date, (string) $year->end_date);

        if ($count === null) {
            return ['ok' => false, 'message' => "ดึงวันหยุดของปีการศึกษา {$year->name} อัตโนมัติไม่สำเร็จ (เพิ่มเอง/กดดึงซ้ำได้ในตารางวันหยุด)"];
        }

        return ['ok' => true, 'message' => "ดึงวันหยุดราชการของปีการศึกษา {$year->name} แล้ว {$count} วัน"];
    }

    /**
     * V4: วันเริ่ม-สิ้นสุดของปี = min/max ของวันเทอมในทุกปฏิทินของปีนั้น
     * คืน true ถ้าช่วงเปลี่ยน + มีช่วงครบ (ใช้ตัดสินใจดึงวันหยุดใหม่)
     */
    private function recomputeYearSpan(AcademicYear $year): bool
    {
        $calendarIds = $year->calendars()->pluck('id');
        $row = $calendarIds->isEmpty() ? null : \App\Models\Term::whereIn('academic_calendar_id', $calendarIds)
            ->selectRaw('MIN(start_date) as s, MAX(end_date) as e')->first();

        $start = $row?->s ?: null;
        $end = $row?->e ?: null;

        $changed = ((string) $year->start_date !== (string) $start) || ((string) $year->end_date !== (string) $end);
        $year->update(['start_date' => $start, 'end_date' => $end]);

        return $changed && $start && $end;
    }

    /**
     * ตรวจความถูกต้องของวันเทอม/วันสอบ (วันเป็น ISO แล้ว — เทียบ string ได้)
     * คืน array ข้อความ error (ว่าง = ผ่าน)
     */
    private function termValidationErrors(Request $request): array
    {
        $errors = [];
        $terms = collect($request->input('terms', []))
            ->filter(fn ($t) => ! empty($t['name']) && ! empty($t['start_date']) && ! empty($t['end_date']))
            ->values();

        foreach ($terms as $t) {
            $label = $t['name'];
            $ts = $t['start_date'];
            $te = $t['end_date'];

            if ($te < $ts) {
                $errors[] = "{$label}: วันสิ้นสุดเทอมต้องไม่ก่อนวันเริ่มเทอม";
            }

            foreach ([['midterm', 'สอบกลางภาค'], ['final', 'สอบปลายภาค']] as [$key, $examLabel]) {
                $es = $t["{$key}_start"] ?? null;
                $ee = $t["{$key}_end"] ?? null;
                if ($es && $ee && $ee < $es) {
                    $errors[] = "{$label}: ช่วง{$examLabel} — วันสิ้นสุดก่อนวันเริ่ม";
                }
                foreach (array_filter([$es, $ee]) as $d) {
                    if ($d < $ts || $d > $te) {
                        $errors[] = "{$label}: วัน{$examLabel}อยู่นอกช่วงเทอม";
                        break;
                    }
                }
            }

            // สอบกลางภาคต้องมาก่อนสอบปลายภาค
            $midtermEnd = $t['midterm_end'] ?? null;
            $finalStart = $t['final_start'] ?? null;
            if ($midtermEnd && $finalStart && $finalStart <= $midtermEnd) {
                $errors[] = "{$label}: สอบปลายภาคต้องอยู่หลังสอบกลางภาค";
            }
        }

        // ห้ามช่วงเทอมซ้อนทับกัน (เรียงตามวันเริ่ม)
        $sorted = $terms->sortBy('start_date')->values();
        for ($i = 1; $i < $sorted->count(); $i++) {
            if ($sorted[$i]['start_date'] <= $sorted[$i - 1]['end_date']) {
                $errors[] = "ช่วงเทอมซ้อนทับกัน: {$sorted[$i]['name']} เริ่มก่อน {$sorted[$i - 1]['name']} จะสิ้นสุด";
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * บันทึกเทอมจากฟอร์ม — ถ้าไม่ส่ง terms มาเลย → สร้างเทอมเริ่มต้นให้ (fallback)
     */
    private function syncTerms(AcademicYear $year, Request $request): void
    {
        $terms = collect($request->input('terms', []))
            ->filter(fn ($t) => ! empty($t['name']) && ! empty($t['start_date']) && ! empty($t['end_date']))
            ->values();

        if ($terms->isEmpty()) {
            $this->createDefaultTerms($year);
            return;
        }

        $calendar = $year->fallbackCalendar();
        $keptSeqs = [];
        foreach ($terms as $i => $t) {
            $seq = (int) ($t['sequence'] ?? ($i + 1));
            $keptSeqs[] = $seq;
            $calendar->terms()->updateOrCreate(['sequence' => $seq], [
                'name'          => $t['name'],
                'start_date'    => $t['start_date'],
                'end_date'      => $t['end_date'],
                'midterm_start' => $t['midterm_start'] ?? null,
                'midterm_end'   => $t['midterm_end'] ?? null,
                'final_start'   => $t['final_start'] ?? null,
                'final_end'     => $t['final_end'] ?? null,
            ]);
        }

        $calendar->terms()->whereNotIn('sequence', $keptSeqs)->delete();
    }

    // ── ปฏิทินการศึกษาตามกลุ่ม (V4 ข้อ 8) ────────────────────────────────

    /**
     * เขียนเทอมของ "ปฏิทินที่ระบุ" (reuse โดย default calendar + ปฏิทินตามกลุ่ม)
     */
    private function syncCalendarTerms(AcademicCalendar $calendar, Request $request): void
    {
        $terms = collect($request->input('terms', []))
            ->filter(fn ($t) => ! empty($t['name']) && ! empty($t['start_date']) && ! empty($t['end_date']))
            ->values();

        $keptSeqs = [];
        foreach ($terms as $i => $t) {
            $seq = (int) ($t['sequence'] ?? ($i + 1));
            $keptSeqs[] = $seq;
            $calendar->terms()->updateOrCreate(['sequence' => $seq], [
                'name'          => $t['name'],
                'start_date'    => $t['start_date'],
                'end_date'      => $t['end_date'],
                'midterm_start' => $t['midterm_start'] ?? null,
                'midterm_end'   => $t['midterm_end'] ?? null,
                'final_start'   => $t['final_start'] ?? null,
                'final_end'     => $t['final_end'] ?? null,
            ]);
        }

        $calendar->terms()->whereNotIn('sequence', $keptSeqs)->delete();
    }

    private function validateCalendar(Request $request): array
    {
        return $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'curriculum_id'  => ['nullable', \Illuminate\Validation\Rule::exists('curriculums', 'id')],
            'year_levels'    => ['nullable', 'array'],
            'year_levels.*'  => ['integer', 'min:1', 'max:12'],
            'terms'          => ['required', 'array', 'min:1'],
        ], [
            'name.required'        => 'กรุณาตั้งชื่อปฏิทิน',
            'curriculum_id.exists' => 'ไม่พบหลักสูตรที่เลือก',
            'terms.required'       => 'ต้องระบุอย่างน้อย 1 ภาคการศึกษา',
        ]);
    }

    /** normalize ชั้นปีเป็น array int เรียง (null/ว่าง → []) เพื่อเทียบ scope */
    private function normalizeYearLevels(?array $levels): array
    {
        if (empty($levels)) {
            return [];
        }
        $ints = array_map('intval', (array) $levels);
        sort($ints);
        return array_values(array_unique($ints));
    }

    /** มีปฏิทินอื่นในปีเดียวกันที่ scope (หลักสูตร + ชั้นปี) ซ้ำกันไหม */
    private function calendarScopeConflict(AcademicYear $year, ?int $curriculumId, ?array $yearLevels, ?int $exceptId = null): bool
    {
        $normalized = $this->normalizeYearLevels($yearLevels);

        return $year->calendars()
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->get()
            ->contains(fn ($cal) => (int) $cal->curriculum_id === (int) $curriculumId
                && $this->normalizeYearLevels($cal->year_levels) === $normalized);
    }

    private const CALENDAR_SCOPE_MESSAGE = 'มีปฏิทินสำหรับหลักสูตร/ชั้นปีนี้อยู่แล้วในปีการศึกษานี้ — กรุณาแก้ปฏิทินเดิม หรือเลือกขอบเขตอื่น';

    /** ข้อความ error เมื่อ scope ซ้ำ (ผูกกับช่อง curriculum_id) */
    private function calendarScopeError(): array
    {
        return ['curriculum_id' => self::CALENDAR_SCOPE_MESSAGE];
    }

    public function storeCalendar(Request $request, AcademicYear $year)
    {
        $this->normalizeTermDates($request);
        $validated = $this->validateCalendar($request);

        if ($termErrors = $this->termValidationErrors($request)) {
            return back()->withInput()->withErrors(['calendar_terms' => $termErrors]);
        }

        if ($this->calendarScopeConflict($year, $validated['curriculum_id'] ?? null, $validated['year_levels'] ?? null)) {
            return back()->withInput()->withErrors($this->calendarScopeError())->with('error', self::CALENDAR_SCOPE_MESSAGE);
        }

        DB::transaction(function () use ($year, $validated, $request) {
            $calendar = $year->calendars()->create([
                'name'           => $validated['name'],
                'curriculum_id'  => $validated['curriculum_id'] ?? null,
                'year_levels'    => ! empty($validated['year_levels']) ? array_values(array_map('intval', $validated['year_levels'])) : null,
            ]);
            $this->syncCalendarTerms($calendar, $request);
        });

        return $this->calendarSavedRedirect($year, 'เพิ่มปฏิทินการศึกษาเรียบร้อยแล้ว');
    }

    /**
     * หลังบันทึกปฏิทิน → คำนวณช่วงวันของปีใหม่ + ดึงวันหยุดถ้าช่วงเพิ่งครบ/เปลี่ยน
     */
    private function calendarSavedRedirect(AcademicYear $year, string $msg)
    {
        AlertController::flushCache();
        if ($this->recomputeYearSpan($year)) {
            $note = $this->autoFetchHolidays($year->fresh());
            $r = back()->with('success', $msg . ' — ' . $note['message']);
            return $note['ok'] ? $r : $r->with('holiday_warning', $note['message']);
        }
        return back()->with('success', $msg);
    }

    public function updateCalendar(Request $request, AcademicCalendar $calendar)
    {
        $this->normalizeTermDates($request);
        $validated = $this->validateCalendar($request);

        if ($termErrors = $this->termValidationErrors($request)) {
            return back()->withInput()->withErrors(['calendar_terms' => $termErrors]);
        }

        if ($this->calendarScopeConflict($calendar->academicYear, $validated['curriculum_id'] ?? null, $validated['year_levels'] ?? null, $calendar->id)) {
            return back()->withInput()->withErrors($this->calendarScopeError())->with('error', self::CALENDAR_SCOPE_MESSAGE);
        }

        DB::transaction(function () use ($calendar, $validated, $request) {
            $calendar->update([
                'name'           => $validated['name'],
                'curriculum_id'  => $validated['curriculum_id'] ?? null,
                'year_levels'    => ! empty($validated['year_levels']) ? array_values(array_map('intval', $validated['year_levels'])) : null,
            ]);
            $this->syncCalendarTerms($calendar, $request);
        });

        return $this->calendarSavedRedirect($calendar->academicYear, 'อัปเดตปฏิทินการศึกษาเรียบร้อยแล้ว');
    }

    public function destroyCalendar(AcademicCalendar $calendar)
    {
        $year = $calendar->academicYear;
        $calendar->delete(); // terms cascade
        if ($year) {
            $this->recomputeYearSpan($year);
        }

        return back()->with('success', 'ลบปฏิทินการศึกษาเรียบร้อยแล้ว');
    }

    private function shiftDate(mixed $date, int $years): ?string
    {
        return $date ? Carbon::parse((string) $date)->addYears($years)->toDateString() : null;
    }

    /**
     * คัดลอกปฏิทินทั้งชุดจาก $source → $target (ทับของเดิม) เลื่อนวันที่ตามผลต่างปี
     * คืนค่า diff (จำนวนปีที่เลื่อน) เพื่อใช้ใน flash message
     */
    private function copyCalendarsInto(AcademicYear $target, AcademicYear $source): int
    {
        $source->loadMissing('calendars.terms');
        $diff = (int) $target->name - (int) $source->name;

        DB::transaction(function () use ($target, $source, $diff) {
            $target->calendars()->delete(); // ทับของเดิม (cascades terms)
            foreach ($source->calendars as $cal) {
                $new = $target->calendars()->create([
                    'name'          => $cal->name,
                    'curriculum_id' => $cal->curriculum_id,
                    'year_levels'   => $cal->year_levels,
                ]);
                foreach ($cal->terms as $t) {
                    $new->terms()->create([
                        'sequence'      => $t->sequence,
                        'name'          => $t->name,
                        'start_date'    => $this->shiftDate($t->start_date, $diff),
                        'end_date'      => $this->shiftDate($t->end_date, $diff),
                        'midterm_start' => $this->shiftDate($t->midterm_start, $diff),
                        'midterm_end'   => $this->shiftDate($t->midterm_end, $diff),
                        'final_start'   => $this->shiftDate($t->final_start, $diff),
                        'final_end'     => $this->shiftDate($t->final_end, $diff),
                    ]);
                }
            }
        });

        return $diff;
    }

    private function hasOtherOpenSchedulingWindow(AcademicYear $year): bool
    {
        return AcademicYear::where('phase', 'scheduling')
            ->where('id', '!=', $year->id)
            ->exists();
    }

    public function index()
    {
        $academicYears = AcademicYear::with(['terms', 'calendars' => fn ($q) => $q->orderBy('curriculum_id')->orderBy('name'), 'calendars.terms', 'calendars.curriculum'])
            ->orderBy('name', 'desc')->get();
        $calendarCurriculums = Curriculum::orderBy('education_level')->orderBy('name')
            ->get(['id', 'name', 'uses_year_level', 'duration_years']);
        $holidays = Holiday::orderBy('date')->get();
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

        $schedulingSummary = AcademicYear::orderBy('name', 'desc')->get();
        $schedulingCriticals = AlertController::getCriticals();

        $isAdmin     = true;
        $routePrefix = 'admin';
        return view('admin.settings', compact('academicYears', 'calendarCurriculums', 'holidays', 'paCriteria', 'workloadQuota', 'teachingQuota', 'workloadWeeks', 'teachingWeeks', 'workloadHoursPerWeek', 'isAdmin', 'routePrefix', 'schedulingSummary', 'schedulingCriticals'));
    }

    public function storeYear(Request $request)
    {
        // V4: ปีการศึกษา = ชื่อ + active เท่านั้น · เทอม+สอบไปอยู่ใน "ปฏิทิน" (วันเริ่ม-สิ้นสุดปี derive ภายหลัง)
        $validated = $request->validate([
            'name'               => ['required', 'string', \Illuminate\Validation\Rule::unique('academic_years')],
            'copy_from_year_id'  => ['nullable', \Illuminate\Validation\Rule::exists('academic_years', 'id')],
        ], [
            'name.required' => 'กรุณากรอกปีการศึกษา',
            'name.unique'   => 'ปีการศึกษา ' . $request->input('name') . ' มีอยู่แล้วในระบบ',
        ]);

        $copyFromYearId = $validated['copy_from_year_id'] ?? null;
        unset($validated['copy_from_year_id']);
        $validated['is_active'] = $request->has('is_active');

        if ($validated['is_active']) {
            $newYearGuard = new AcademicYear(['name' => $validated['name']]);

            if ($this->hasOtherOpenSchedulingWindow($newYearGuard)) {
                return back()
                    ->withInput()
                    ->withErrors(['is_active' => $this->schedulingLockMessage($newYearGuard)])
                    ->with('error', $this->schedulingLockMessage($newYearGuard));
            }

            AcademicYear::where('is_active', true)->update(['is_active' => false]);
            $this->closeAllSchedulingWindows();
            $this->syncCourseStatusByCurriculum();
        }

        $year = AcademicYear::create($validated); // start/end = null จนกว่าจะตั้งเทอมในปฏิทิน
        $year->fallbackCalendar(); // สร้างปฏิทินหลักว่างไว้ให้กรอกเทอม

        // คัดลอกปฏิทินจากปีก่อน (ถ้าเลือก) — ลอกทั้งชุด เลื่อนวันที่ตามผลต่างปี
        $copySource = $copyFromYearId ? AcademicYear::find($copyFromYearId) : null;
        if ($copySource && $copySource->id !== $year->id) {
            $diff = $this->copyCalendarsInto($year, $copySource);
            $this->recomputeYearSpan($year);
            $note = $this->autoFetchHolidays($year->fresh());
            $successMsg = "เพิ่มปีการศึกษาเรียบร้อยแล้ว — คัดลอกปฏิทินจากปี {$copySource->name} (เลื่อนวันที่ {$diff} ปี · โปรดตรวจ/ปรับวันที่) · {$note['message']}";
        } else {
            $successMsg = 'เพิ่มปีการศึกษาเรียบร้อยแล้ว — กดไอคอนปฏิทินที่แถวปีเพื่อกำหนดเทอมและช่วงสอบ';
        }

        AuditLogger::log(
            action: 'ข้อมูลหลัก.สร้าง',
            table: 'academic_years',
            recordId: $year->id,
            oldValues: null,
            newValues: $this->auditSnapshot($year),
            description: "สร้างปีการศึกษา {$year->name}",
        );

        AlertController::flushCache();
        return redirect()->route($this->settingsRoute(), ['tab' => 'academic'])
            ->with('success', $successMsg);
    }

    public function updateYear(Request $request, AcademicYear $year)
    {
        // V4: แก้แค่ชื่อ + active · เทอม+สอบจัดการในปฏิทิน (วันปี derive จากเทอม)
        $validated = $request->validate([
            'name' => ['required', 'string', \Illuminate\Validation\Rule::unique('academic_years')->ignore($year->id)],
        ], [
            'name.required' => 'กรุณากรอกปีการศึกษา',
            'name.unique'   => 'ปีการศึกษา ' . $request->input('name') . ' มีอยู่แล้วในระบบ',
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
            $this->syncCourseStatusByCurriculum();
        }

        $year->update($validated);

        $after = $this->auditSnapshot($year->fresh());
        [$oldValues, $newValues] = $this->auditDiff($before, $after);
        $this->logAcademicYearUpdate($year->fresh(), $oldValues, $newValues);

        AlertController::flushCache();
        return redirect()->route($this->settingsRoute(), ['tab' => 'academic'])
            ->with('success', 'อัปเดตปีการศึกษาเรียบร้อยแล้ว');
    }

    public function destroyYear(AcademicYear $year)
    {
        // ห้ามลบปีปัจจุบัน — ต้องมีปีที่ใช้งานอยู่เสมอ
        if ($year->is_active) {
            return redirect()->route($this->settingsRoute(), ['tab' => 'academic'])
                ->with('error', 'ไม่สามารถลบปีการศึกษาปัจจุบันได้ — ตั้งปีอื่นเป็นปีปัจจุบันก่อน แล้วจึงลบ');
        }

        // ห้ามลบถ้ามีรายวิชาที่เปิดสอน (course offering) ผูกอยู่ — กันข้อมูลตารางหาย
        if ($year->courseOfferings()->exists()) {
            return redirect()->route($this->settingsRoute(), ['tab' => 'academic'])
                ->with('error', "ไม่สามารถลบปีการศึกษา {$year->name} ได้ — มีรายวิชาที่เปิดสอน/ตารางผูกอยู่กับปีนี้");
        }

        $snapshot = $this->auditSnapshot($year);
        $yearId = $year->id;
        $name = $year->name;

        $year->delete(); // ปฏิทิน + เทอม cascade ตาม FK

        AuditLogger::log(
            action: 'ข้อมูลหลัก.ลบ',
            table: 'academic_years',
            recordId: $yearId,
            oldValues: $snapshot,
            newValues: null,
            description: "ลบปีการศึกษา {$name}",
        );

        AlertController::flushCache();
        return redirect()->route($this->settingsRoute(), ['tab' => 'academic'])
            ->with('success', "ลบปีการศึกษา {$name} เรียบร้อยแล้ว");
    }

    public function openSchedulingWindow(AcademicYear $year)
    {
        if (!$year->is_active) {
            return redirect()
                ->route('admin.settings', ['tab' => 'academic'])
                ->with('error', "เปิดช่วงจัดตารางได้เฉพาะปีการศึกษาปัจจุบัน ({$year->name} ไม่ใช่ปีปัจจุบัน)");
        }

        if ($year->phase === 'published') {
            return redirect()
                ->route('admin.settings', ['tab' => 'academic'])
                ->with('error', "ปีการศึกษา {$year->name} เผยแพร่แล้ว ไม่สามารถย้อนกลับได้");
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
            ->offerableForActiveCurriculum()
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
                ->whereHas('course', fn ($query) => $query->offerableForActiveCurriculum())
                ->get();

            foreach ($offeringsToSync as $offering) {
                $course = $offering->course;
                if ($course) {
                    $offering->fill([
                        'coordinator_id' => $course->head_instructor_id,
                        'planned_lecture_hours' => $course->lecture_hours,
                        'planned_lab_hours' => $course->lab_hours,
                        'teaching_weeks' => $teachingWeeks,
                    ])->save();
                }
                $offering->syncInstructorPoolFromCourseTemplate($coordinatorRoleId);
                $synced++;
            }

            $year->update(['phase' => 'scheduling']);
        });

        $total = CourseOffering::withActiveCourse()
            ->where('academic_year_id', $year->id)
            ->whereHas('course', fn ($query) => $query->offerableForActiveCurriculum())
            ->count();
        $newMsg = $created > 0 ? "สร้างใหม่ {$created} รายวิชา" : "ไม่มีรายวิชาใหม่";
        $syncMsg = $synced > 0 ? "ซิงก์แม่แบบ {$synced} รายวิชา" : "ไม่พบรายวิชาเดิมที่ต้องซิงก์";

        AuditLogger::log(
            action:      'ตั้งค่าระบบ.เปิดช่วงจัดตาราง',
            table:       'academic_years',
            recordId:    $year->id,
            oldValues:   ['phase' => 'preparation'],
            newValues:   ['phase' => 'scheduling', 'offerings_created' => $created, 'offerings_synced' => $synced, 'other_scheduling_windows_closed' => $closedSchedulingWindows],
            description: "เปิดช่วงจัดตารางปีการศึกษา {$year->name}",
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
                    'offerings_created' => $created,
                    'offerings_synced' => $synced,
                    'affected_count' => $synced ?: $created,
                    'sample_course_codes' => $courses->pluck('course_code')->take(5)->values()->all(),
                ],
                category:    'รายวิชาและผู้รับผิดชอบ',
                description: "ซิงก์ข้อมูลรายวิชาจากต้นแบบไปยังรอบเปิดสอน ปีการศึกษา {$year->name}",
            );
        }

        return redirect()
            ->route('admin.settings', ['tab' => 'academic'])
            ->with('success', "เปิดช่วงจัดตารางสำหรับปีการศึกษา {$year->name} แล้ว — {$newMsg}, {$syncMsg} รวม {$total} รายวิชาพร้อมจัดตาราง");
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
                ->with('error', "ปีการศึกษา {$year->name} ไม่ได้อยู่ในช่วงจัดตาราง");
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
            description: "ปิดช่วงจัดตารางปีการศึกษา {$year->name}",
        );

        return redirect()
            ->route('admin.settings', ['tab' => 'academic'])
            ->with('success', "ปิดช่วงจัดตารางสำหรับปีการศึกษา {$year->name} แล้ว");
    }

    // ── วันหยุดราชการ (V3 ข้อ 2.4) ──────────────────────────────────────

    public function storeHoliday(Request $request)
    {
        $this->normalizeThaiDateInputs($request, ['date']);
        $validated = $request->validate([
            'date'   => ['required', 'date', \Illuminate\Validation\Rule::unique('holidays')],
            'name'   => ['required', 'string', 'max:255'],
            'remark' => ['nullable', 'string', 'max:255'],
        ], [
            'date.unique'   => 'มีวันหยุดวันที่นี้อยู่แล้วในระบบ',
            'date.required' => 'กรุณาระบุวันที่',
            'name.required' => 'กรุณาระบุชื่อวันหยุด',
        ]);
        $validated['source'] = 'manual';

        $holiday = Holiday::create($validated);
        AlertController::flushCache();

        // 8.4: บันทึกประวัติการเพิ่มวันหยุด
        AuditLogger::log(
            action: 'ตั้งค่าระบบ.สร้าง',
            table: 'holidays',
            recordId: $holiday->id,
            oldValues: null,
            newValues: $validated,
            description: "เพิ่มวันหยุด: {$validated['name']} ({$validated['date']})",
        );

        return redirect()->route($this->settingsRoute(), ['tab' => 'academic'])->with('success', 'เพิ่มวันหยุดเรียบร้อยแล้ว');
    }

    public function updateHoliday(Request $request, Holiday $holiday)
    {
        $this->normalizeThaiDateInputs($request, ['date']);
        $validated = $request->validate([
            'date'   => ['required', 'date', \Illuminate\Validation\Rule::unique('holidays')->ignore($holiday->id)],
            'name'   => ['required', 'string', 'max:255'],
            'remark' => ['nullable', 'string', 'max:255'],
        ], [
            'date.unique'   => 'มีวันหยุดวันที่นี้อยู่แล้วในระบบ',
            'name.required' => 'กรุณาระบุชื่อวันหยุด',
        ]);

        $before = [
            'date'   => $holiday->date->format('Y-m-d'),
            'name'   => $holiday->name,
            'remark' => $holiday->remark,
        ];

        $holiday->update($validated);
        AlertController::flushCache();

        // 8.4: บันทึกประวัติการแก้ไขวันหยุด (เฉพาะ field ที่เปลี่ยน)
        $changed = AuditLogger::diff($before, [
            'date'   => $validated['date'],
            'name'   => $validated['name'],
            'remark' => $validated['remark'] ?? null,
        ]);
        AuditLogger::log(
            action: 'ตั้งค่าระบบ.แก้ไข',
            table: 'holidays',
            recordId: $holiday->id,
            oldValues: $changed['old'],
            newValues: $changed['new'],
            description: "แก้ไขวันหยุด: {$validated['name']} ({$validated['date']})",
        );

        return redirect()->route($this->settingsRoute(), ['tab' => 'academic'])->with('success', 'อัปเดตวันหยุดเรียบร้อยแล้ว');
    }

    public function destroyHoliday(Holiday $holiday)
    {
        $snapshot = [
            'date'   => $holiday->date->format('Y-m-d'),
            'name'   => $holiday->name,
            'remark' => $holiday->remark,
            'source' => $holiday->source,
        ];
        $holidayId = $holiday->id;

        $holiday->delete();
        AlertController::flushCache();

        // 8.4: บันทึกประวัติการลบวันหยุด
        AuditLogger::log(
            action: 'ตั้งค่าระบบ.ลบ',
            table: 'holidays',
            recordId: $holidayId,
            oldValues: $snapshot,
            newValues: null,
            description: "ลบวันหยุด: {$snapshot['name']} ({$snapshot['date']})",
        );

        return redirect()->route($this->settingsRoute(), ['tab' => 'academic'])->with('success', 'ลบวันหยุดเรียบร้อยแล้ว');
    }

    /**
     * ดึงวันหยุดซ้ำ (manual) — ตามช่วงปีการศึกษาปัจจุบัน หรือปีปฏิทินปัจจุบันถ้ายังไม่มีปี active
     */
    public function syncHolidays(Request $request)
    {
        // ดึงตามปีที่ระบุ (year) ถ้าส่งมา · ไม่งั้นใช้ปีปัจจุบัน — ต้องมีปีปัจจุบันก่อน
        $year = $request->filled('year')
            ? AcademicYear::find($request->integer('year'))
            : AcademicYear::where('is_active', true)->first();

        $route = redirect()->route($this->settingsRoute(), ['tab' => 'academic']);

        if (! $year) {
            return $route->with('error', 'กรุณาตั้งปีการศึกษาปัจจุบันก่อน จึงจะดึงวันหยุดได้ (วันหยุดจะดึงตามช่วงของปีนั้น)');
        }

        $count = app(HolidayService::class)->syncForAcademicYearSpan((string) $year->start_date, (string) $year->end_date);
        AlertController::flushCache();

        if ($count === null) {
            return $route->with('error', "ดึงวันหยุดของปีการศึกษา {$year->name} ไม่สำเร็จ — ตรวจอินเทอร์เน็ตแล้วลองใหม่ หรือเพิ่มเอง");
        }

        // 8.4: บันทึกประวัติการดึงวันหยุดราชการอัตโนมัติ (bulk)
        AuditLogger::log(
            action: 'ตั้งค่าระบบ.ซิงก์ข้อมูล',
            table: 'holidays',
            recordId: $year->id,
            oldValues: null,
            newValues: ['academic_year' => $year->name, 'synced_count' => $count],
            description: "ดึงวันหยุดราชการของปีการศึกษา {$year->name} จำนวน {$count} วัน",
        );

        return $route->with('success', "ดึงวันหยุดราชการของปีการศึกษา {$year->name} แล้ว {$count} วัน");
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

    private function weekdayDateRule(string $label): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($label): void {
            if ($value === null || trim((string) $value) === '') {
                return;
            }

            try {
                $date = Carbon::parse($value);
            } catch (\Throwable $e) {
                return;
            }

            if ($date->isWeekend()) {
                $fail("{$label}ต้องเป็นวันจันทร์-ศุกร์เท่านั้น");
            }
        };
    }
}
