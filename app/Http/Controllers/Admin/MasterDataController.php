<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\Course;
use App\Models\CourseRole;
use App\Models\Curriculum;
use App\Models\ActivityType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MasterDataController extends Controller
{
    /**
     * วันที่เริ่มบังคับเกณฑ์ภาษาอังกฤษ สำหรับผู้ช่วยอาจารย์ ป.เอก (ตามเอกสาร PA criteria)
     * ก่อนวันนี้: ใช้เกณฑ์ "อาจารย์" / ตั้งแต่วันนี้: ต้องผ่านอังกฤษถึงจะใช้เกณฑ์ "อาจารย์"
     * Sync ค่านี้กับ JS ใน views/admin/users/index.blade.php + views/shared/master_data/index.blade.php
     */
    private const PA_ENGLISH_CRITERION_DATE = '2016-10-01';

    private const COURSE_CODE_ALLOWED_REGEX = '/^[A-Za-z0-9 _-]+$/';
    private const COURSE_CODE_ALLOWED_MESSAGE = 'รหัสวิชาต้องใช้เฉพาะตัวอักษรภาษาอังกฤษ ตัวเลข ช่องว่าง ขีดกลาง หรือขีดล่าง';

    public function index()
    {
        // View-only for instructors: get users who have 'instructor' role
        $instructors = User::whereHas('roles', function($q) {
            $q->where('role', 'instructor');
        })->with(['instructorProfile.department', 'roles'])->get();

        // Departments with count of instructors
        $departments = Department::with(['head', 'secretary', 'instructorProfiles.user'])
            ->withCount('instructorProfiles as instructors_count')
            ->get();

        // Active users for head/secretary dropdown
        $users = User::with(['instructorProfile', 'roles'])->where('is_active', true)->orderBy('name')->get();

        // Location Types
        $locationTypes = LocationType::withCount('rooms')->with('rooms')->get();

        // Rooms with their types
        $rooms = Room::with('locationType')->get();

        // Staff users for assigned_staff dropdown
        $staffUsers = User::whereHas('roles', function ($q) {
            $q->where('role', 'staff');
        })->with('instructorProfile')->where('is_active', true)->orderBy('name')->get();

        // Courses with curriculum, head instructor, staff, and academic prerequisites
        $courses = Course::with([
            'curriculum',
            'department',
            'headInstructor.instructorProfile.department',
            'assignedStaff',
            'instructors.instructorProfile.department',
            'prerequisites:id,course_code,name_th,name_en',
        ])
            ->withExists([
                'courseOfferings as has_locked_offering' => fn ($query) => $query->whereHas(
                    'academicYear',
                    fn ($yearQuery) => $yearQuery->whereIn('phase', ['scheduling', 'published'])
                ),
            ])
            ->orderBy('course_code')
            ->get();

        $courseRoles = CourseRole::orderBy('sort_order')
            ->where('name_th', '!=', 'หัวหน้าวิชา')
            ->get();

        $courseInstructorUsers = User::query()
            ->with('instructorProfile.department')
            ->where('is_active', true)
            ->whereHas('instructorProfile')
            ->whereHas('roles', fn ($q) => $q->whereIn('role', ['instructor', 'course_head']))
            ->orderBy('name')
            ->get();

        // Curriculums with course count and courses list
        $curriculums = Curriculum::withCount('courses')->with(['courses' => fn($q) => $q->orderBy('course_code')])->get();

        // Activity Types
        $activityTypes = ActivityType::orderBy('name')->get();

        // Pre-flight counts for import warnings
        $usersWithEmployeeIdCount = User::whereNotNull('employee_id')->where('employee_id', '!=', '')->count();

        $paCriteria = json_decode(\App\Models\SystemSetting::get('pa_criteria_config', '{}'), true);
        $firstGroup = !empty($paCriteria) ? reset($paCriteria) : null;
        $firstField = $firstGroup ? reset($firstGroup) : null;
        if (empty($paCriteria) || !is_array($firstField)) {
            $paCriteria = \App\Http\Controllers\AdminSettingController::defaultPaCriteria();
        }

        $activeRole = session('active_role');
        $isAdmin    = $activeRole === 'admin';
        $routePrefix = $isAdmin ? 'admin' : 'staff';

        return view('shared.master_data.index', compact(
            'instructors',
            'departments',
            'users',
            'locationTypes',
            'rooms',
            'courses',
            'curriculums',
            'activityTypes',
            'staffUsers',
            'courseRoles',
            'courseInstructorUsers',
            'isAdmin',
            'routePrefix',
            'usersWithEmployeeIdCount',
            'paCriteria'
        ));
    }

    private function masterDataRouteName(): string
    {
        return session('active_role') === 'staff' ? 'staff.master_data' : 'admin.master_data';
    }

    private function redirectToMasterData(string $tab): \Illuminate\Http\RedirectResponse
    {
        AlertController::flushCache();
        return redirect()->route($this->masterDataRouteName(), ['tab' => $tab]);
    }

    private function paCriteriaGroup(string $title, string $degree, ?string $hiredAt = null, bool $isEnglishPassed = false): string
    {
        $isNote1 = $title === 'ผู้ช่วยอาจารย์'
            && $degree === 'ปริญญาเอก'
            && $hiredAt
            && $this->isBeforeEnglishCriterionDate($hiredAt);

        $usesInstructorRules = in_array($title, ['อาจารย์', 'ผู้ช่วยศาสตราจารย์', 'รองศาสตราจารย์', 'ศาสตราจารย์'], true)
            || $isNote1
            || ($title === 'ผู้ช่วยอาจารย์' && $degree === 'ปริญญาเอก' && $isEnglishPassed);

        if ($title === 'ผู้ช่วยอาจารย์ (คลินิก)') {
            return 'ผู้ช่วยอาจารย์_คลินิก';
        }

        if ($title === 'ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ)') {
            return 'ผู้ช่วยอาจารย์_ปฏิบัติ';
        }

        if ($title === 'ผู้ช่วยอาจารย์' && $degree === 'ปริญญาตรี') {
            return 'ผู้ช่วยอาจารย์_ปตรี';
        }

        if ($usesInstructorRules) {
            return 'อาจารย์';
        }

        if ($title === 'ผู้ช่วยอาจารย์') {
            return 'ผู้ช่วยอาจารย์';
        }

        return AlertController::paGroup($title, $degree);
    }

    private function requiresEnglishCriterion(?string $title, ?string $degree, ?string $hiredAt): bool
    {
        if ($title !== 'ผู้ช่วยอาจารย์' || $degree !== 'ปริญญาเอก' || !$hiredAt) {
            return false;
        }

        return !$this->isBeforeEnglishCriterionDate($hiredAt);
    }

    private function isBeforeEnglishCriterionDate(string $hiredAt): bool
    {
        try {
            return \Carbon\Carbon::parse($hiredAt)->lt(\Carbon\Carbon::parse(self::PA_ENGLISH_CRITERION_DATE));
        } catch (\Exception $e) {
            return false;
        }
    }

    public function storeLocationType(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:location_types,name',
        ]);
        $validated['requires_capacity'] = $request->boolean('requires_capacity');

        LocationType::create($validated);

        return $this->redirectToMasterData('location_types')->with('success', 'เพิ่มประเภทสถานที่เรียบร้อยแล้ว');
    }

    public function updateLocationType(Request $request, LocationType $locationType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:location_types,name,' . $locationType->id,
        ]);
        $validated['requires_capacity'] = $request->boolean('requires_capacity');

        $locationType->update($validated);

        return $this->redirectToMasterData('location_types')->with('success', 'อัปเดตประเภทสถานที่เรียบร้อยแล้ว');
    }

    public function storeRoom(Request $request)
    {
        $validated = $request->validate([
            'room_code'        => 'required|string|max:255|unique:rooms,room_code' . (isset($room) ? ',' . $room->id : ''),
            'room_name'        => 'required|string|max:255',
            'building'         => 'nullable|string|max:100',
            'capacity'         => 'nullable|integer|min:0',
            'location_type_id' => 'required|exists:location_types,id',
            'status'           => 'required|in:active,inactive,maintenance',
            'address'          => 'nullable|string',
            'equipment_type'   => 'nullable|string',
        ]);

        if (!empty($validated['equipment_type'])) {
            $validated['equipment_type'] = array_values(array_filter(array_map('trim', explode(',', $validated['equipment_type']))));
        } else {
            $validated['equipment_type'] = [];
        }

        Room::create($validated);

        return $this->redirectToMasterData('location_types')->with('success', 'เพิ่มห้อง/สถานที่เรียบร้อยแล้ว');
    }

    public function updateRoom(Request $request, Room $room)
    {
        $validated = $request->validate([
            'room_code'        => 'required|string|max:255|unique:rooms,room_code,' . $room->id,
            'room_name'        => 'required|string|max:255',
            'building'         => 'nullable|string|max:100',
            'capacity'         => 'nullable|integer|min:0',
            'location_type_id' => 'required|exists:location_types,id',
            'status'           => 'required|in:active,inactive,maintenance',
            'address'          => 'nullable|string',
            'equipment_type'   => 'nullable|string',
        ]);

        if (!empty($validated['equipment_type'])) {
            $validated['equipment_type'] = array_values(array_filter(array_map('trim', explode(',', $validated['equipment_type']))));
        } else {
            $validated['equipment_type'] = [];
        }

        $room->update($validated);

        return $this->redirectToMasterData('location_types')->with('success', 'อัปเดตห้อง/สถานที่เรียบร้อยแล้ว');
    }

    public function storeDepartment(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
            'head_user_id' => 'nullable|exists:users,id',
            'secretary_user_id' => 'nullable|exists:users,id',
        ]);

        Department::create($validated);
        AlertController::flushCache();
        return redirect()->back()->with('success', 'เพิ่มภาควิชาเรียบร้อยแล้ว');
    }

    public function updateDepartment(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255|unique:departments,name,' . $department->id,
            'head_user_id'     => 'nullable|exists:users,id',
            'secretary_user_id'=> 'nullable|exists:users,id',
        ]);

        $newHeadId = $validated['head_user_id'] ?? null;
        $newSecId  = $validated['secretary_user_id'] ?? null;
        $forceOverride = $request->boolean('force_position_override');

        // If override confirmed by user, release positions from other depts first
        if ($forceOverride) {
            if ($newHeadId) {
                Department::where('id', '!=', $department->id)
                    ->where('head_user_id', $newHeadId)
                    ->update(['head_user_id' => null]);
            }
            if ($newSecId) {
                Department::where('id', '!=', $department->id)
                    ->where('secretary_user_id', $newSecId)
                    ->update(['secretary_user_id' => null]);
            }
        }

        $department->update($validated);
        AlertController::flushCache();
        return redirect()->back()->with('success', 'อัปเดตภาควิชาเรียบร้อยแล้ว');
    }

    public function updateInstructor(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $profile = $user->instructorProfile;

        $title  = $request->input('title', '');
        $degree = $request->input('academic_degree', '');
        $hiredAt = $request->input('hired_at');
        $englishPassed = $request->boolean('is_english_passed');

        $paCriteria = json_decode(\App\Models\SystemSetting::get('pa_criteria_config', '{}'), true);
        $firstGroup = !empty($paCriteria) ? reset($paCriteria) : null;
        $firstField = $firstGroup ? reset($firstGroup) : null;
        if (empty($paCriteria) || !is_array($firstField)) {
            $paCriteria = \App\Http\Controllers\AdminSettingController::defaultPaCriteria();
        }
        $group = $this->paCriteriaGroup($title, $degree, $hiredAt, $englishPassed);
        $gc = $paCriteria[$group] ?? $paCriteria['อาจารย์'];
        $paRules = [
            'teaching' => [$gc['t']['min'] ?? 0, $gc['t']['max'] ?? 100],
            'research'  => [$gc['r']['min'] ?? 0, $gc['r']['max'] ?? 100],
            'service'   => [$gc['s']['min'] ?? 0, $gc['s']['max'] ?? 100],
            'culture'   => [$gc['c']['min'] ?? 0, $gc['c']['max'] ?? 100],
            'other'     => [$gc['o']['min'] ?? 0, $gc['o']['max'] ?? 100],
        ];

        $request->validate([
            'name'            => 'required|string|max:255',
            'prefix'          => 'required|string|max:50',
            'employee_id'     => 'nullable|string|max:50|unique:users,employee_id,' . $user->id,
            'department_id'   => 'required|integer|exists:departments,id',
            'title'           => 'required|string|max:100',
            'academic_degree' => 'required|string|max:100',
            'employment_type' => 'required|string|max:100',
            'hired_at'        => 'nullable|date',
            'is_english_passed' => 'nullable|boolean',
            'teaching_pct'    => "required|integer|min:{$paRules['teaching'][0]}|max:{$paRules['teaching'][1]}",
            'research_pct'    => "required|integer|min:{$paRules['research'][0]}|max:{$paRules['research'][1]}",
            'service_pct'     => "required|integer|min:{$paRules['service'][0]}|max:{$paRules['service'][1]}",
            'culture_pct'     => "required|integer|min:{$paRules['culture'][0]}|max:{$paRules['culture'][1]}",
            'other_pct'       => "required|integer|min:{$paRules['other'][0]}|max:{$paRules['other'][1]}",
        ]);

        $total = $request->integer('teaching_pct') + $request->integer('research_pct')
               + $request->integer('service_pct')  + $request->integer('culture_pct')
               + $request->integer('other_pct');
        if ($total !== 100) {
            return redirect()->back()->withErrors(['teaching_pct' => "สัดส่วนรวมทั้งหมดต้องเท่ากับ 100% (ปัจจุบัน: {$total}%)"])->withInput();
        }

        // Update user name, prefix, and employee_id
        $user->update(['name' => $request->name, 'prefix' => $request->prefix, 'employee_id' => $request->employee_id ?: null]);

        // If department is changing and user was head/secretary of old dept, clear that role
        if ($profile && $request->filled('department_id') && (int)$request->department_id !== (int)$profile->department_id) {
            Department::where('head_user_id', $user->id)->update(['head_user_id' => null]);
            Department::where('secretary_user_id', $user->id)->update(['secretary_user_id' => null]);
        }

        // Update profile
        if ($profile) {
            $profile->update([
                'title'           => $request->title,
                'academic_degree' => $request->academic_degree,
                'department_id'   => $request->department_id,
                'employment_type' => $request->employment_type,
                'hired_at'        => $request->hired_at ?: null,
                'is_english_passed' => $this->requiresEnglishCriterion($request->title, $request->academic_degree, $request->hired_at)
                    ? $request->boolean('is_english_passed')
                    : false,
                'teaching_pct'    => $request->teaching_pct,
                'research_pct'    => $request->research_pct,
                'service_pct'     => $request->service_pct,
                'culture_pct'     => $request->culture_pct,
                'other_pct'       => $request->other_pct,
            ]);
        }

        AlertController::flushCache();
        return redirect()->back()->with('success', 'อัปเดตข้อมูลอาจารย์เรียบร้อยแล้ว');
    }

    /**
     * Whitespace-insensitive duplicate check: course_code stored as user typed,
     * but matched via REPLACE/UPPER so "NSBS 111" and "NSBS111" are treated as duplicates.
     * Note: cannot use a regular DB index — full scan. Acceptable for small course tables.
     */
    private function courseCodeExistsInCurriculum(string $courseCode, int $curriculumId, ?int $ignoreCourseId = null): bool
    {
        $normalized = preg_replace('/\s+/', '', mb_strtoupper(trim($courseCode)));

        return Course::query()
            ->where('curriculum_id', $curriculumId)
            ->when($ignoreCourseId, fn($query) => $query->whereKeyNot($ignoreCourseId))
            ->whereRaw("REPLACE(UPPER(course_code), ' ', '') = ?", [$normalized])
            ->exists();
    }

    private function normalizeCurriculumInput(Request $request): void
    {
        if (!$request->has('name')) {
            return;
        }

        $request->merge([
            'name' => trim((string) $request->input('name')),
        ]);
    }

    public function storeCourse(Request $request)
    {
        $validated = $request->validate([
            'course_code'                 => [
                'required', 'string', 'max:20', 'regex:' . self::COURSE_CODE_ALLOWED_REGEX,
                Rule::unique('courses', 'course_code')
                    ->where(fn($q) => $q->where('curriculum_id', $request->input('curriculum_id'))),
            ],
            'name_th'                     => 'required|string|max:255',
            'name_en'                     => 'nullable|string|max:255',
            'curriculum_id'               => 'required|exists:curriculums,id',
            'department_id'               => 'nullable|exists:departments,id',
            'head_instructor_id'          => [
                Rule::requiredIf(fn () => $request->input('status') === 'active'),
                'nullable',
                'exists:users,id',
            ],
            'staff_ids'                   => 'nullable|array',
            'staff_ids.*'                 => 'exists:users,id',
            'instructor_ids'              => 'nullable|array',
            'instructor_ids.*'            => 'integer|distinct|exists:users,id',
            'instructor_role_ids'         => 'nullable|array',
            'instructor_role_ids.*'       => 'nullable|integer|exists:course_roles,id',
            'academic_level'              => 'nullable|in:undergraduate,graduate',
            'default_year_level'          => 'required|integer|min:1|max:4',
            'default_semester'            => 'required|integer|min:1|max:3',
            'credits'                     => 'required|integer|min:0',
            'lecture_hours'               => 'required|integer|min:0',
            'lab_hours'                   => 'required|integer|min:0',
            'self_study_hours'            => 'required|integer|min:0',
            'capacity'                    => 'required|integer|min:1',
            'color_code'                  => 'nullable|string|max:7',
            'status'                      => 'required|in:active,inactive',
            'requires_practicum_rotation' => 'required|boolean',
            'prerequisite_ids'            => 'nullable|array',
            'prerequisite_ids.*'          => ['integer', 'distinct', 'exists:courses,id'],
        ], $this->courseValidationMessages());

        if ($this->courseCodeExistsInCurriculum($validated['course_code'], (int) $validated['curriculum_id'])) {
            return back()
                ->withErrors(['course_code' => 'รหัสวิชานี้มีอยู่แล้วในหลักสูตรนี้'])
                ->withInput();
        }

        $validated['requires_practicum_rotation'] = $request->boolean('requires_practicum_rotation');
        $staffIds = $validated['staff_ids'] ?? [];
        $instructorIds = $validated['instructor_ids'] ?? [];
        $instructorRoleIds = $validated['instructor_role_ids'] ?? [];
        $prerequisiteIds = $validated['prerequisite_ids'] ?? [];
        unset($validated['staff_ids'], $validated['instructor_ids'], $validated['instructor_role_ids'], $validated['prerequisite_ids']);

        $course = Course::create($validated);
        $course->assignedStaff()->sync($staffIds);
        $this->syncCourseInstructors($course, $instructorIds, $instructorRoleIds);
        $course->prerequisites()->sync($prerequisiteIds);

        return $this->redirectToMasterData('courses')->with('success', 'เพิ่มรายวิชาเรียบร้อยแล้ว');
    }

    public function updateCourse(Request $request, Course $course)
    {
        $assignmentsLocked = $this->isCourseAssignmentLocked($course);

        $validated = $request->validate([
            'course_code'                 => [
                'required', 'string', 'max:20', 'regex:' . self::COURSE_CODE_ALLOWED_REGEX,
                Rule::unique('courses', 'course_code')
                    ->where(fn($q) => $q->where('curriculum_id', $request->input('curriculum_id')))
                    ->ignore($course->id),
            ],
            'name_th'                     => 'required|string|max:255',
            'name_en'                     => 'nullable|string|max:255',
            'curriculum_id'               => 'required|exists:curriculums,id',
            'department_id'               => 'nullable|exists:departments,id',
            'head_instructor_id'          => [
                Rule::requiredIf(fn () => ! $assignmentsLocked && $request->input('status') === 'active'),
                'nullable',
                'exists:users,id',
            ],
            'staff_ids'                   => 'nullable|array',
            'staff_ids.*'                 => 'exists:users,id',
            'instructor_ids'              => 'nullable|array',
            'instructor_ids.*'            => 'integer|distinct|exists:users,id',
            'instructor_role_ids'         => 'nullable|array',
            'instructor_role_ids.*'       => 'nullable|integer|exists:course_roles,id',
            'academic_level'              => 'nullable|in:undergraduate,graduate',
            'default_year_level'          => 'required|integer|min:1|max:4',
            'default_semester'            => 'required|integer|min:1|max:3',
            'credits'                     => 'required|integer|min:0',
            'lecture_hours'               => 'required|integer|min:0',
            'lab_hours'                   => 'required|integer|min:0',
            'self_study_hours'            => 'required|integer|min:0',
            'capacity'                    => 'required|integer|min:1',
            'color_code'                  => 'nullable|string|max:7',
            'status'                      => 'required|in:active,inactive',
            'requires_practicum_rotation' => 'required|boolean',
            'prerequisite_ids'            => 'nullable|array',
            'prerequisite_ids.*'          => ['integer', 'distinct', 'exists:courses,id', Rule::notIn([$course->id])],
        ], $this->courseValidationMessages());

        if ($this->courseCodeExistsInCurriculum($validated['course_code'], (int) $validated['curriculum_id'], $course->id)) {
            return back()
                ->withErrors(['course_code' => 'รหัสวิชานี้มีอยู่แล้วในหลักสูตรนี้'])
                ->withInput();
        }

        $validated['requires_practicum_rotation'] = $request->boolean('requires_practicum_rotation');
        $staffIds = $validated['staff_ids'] ?? [];
        $instructorIds = $validated['instructor_ids'] ?? [];
        $instructorRoleIds = $validated['instructor_role_ids'] ?? [];
        $prerequisiteIds = $validated['prerequisite_ids'] ?? [];
        if ($assignmentsLocked) {
            unset($validated['head_instructor_id']);
        }
        unset($validated['staff_ids'], $validated['instructor_ids'], $validated['instructor_role_ids'], $validated['prerequisite_ids']);

        $course->update($validated);
        if (! $assignmentsLocked) {
            $course->assignedStaff()->sync($staffIds);
            $this->syncCourseInstructors($course, $instructorIds, $instructorRoleIds);
        }
        $course->prerequisites()->sync($prerequisiteIds);

        return $this->redirectToMasterData('courses')->with('success', 'อัปเดตข้อมูลรายวิชาเรียบร้อยแล้ว');
    }

    private function isCourseAssignmentLocked(Course $course): bool
    {
        return $course->courseOfferings()
            ->whereHas('academicYear', fn ($query) => $query->whereIn('phase', ['scheduling', 'published']))
            ->exists();
    }

    private function syncCourseInstructors(Course $course, array $instructorIds, array $roleIds): void
    {
        $defaultRoleId = CourseRole::where('name_th', 'อาจารย์ผู้สอน')->value('id');

        $syncPayload = collect($instructorIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->mapWithKeys(function (int $id) use ($roleIds, $defaultRoleId) {
                $roleId = $roleIds[$id] ?? $roleIds[(string) $id] ?? $defaultRoleId;

                return [
                    $id => ['course_role_id' => $roleId ? (int) $roleId : null],
                ];
            })
            ->all();

        $course->instructors()->sync($syncPayload);
    }

    public function storeCurriculum(Request $request)
    {
        $this->normalizeCurriculumInput($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('curriculums', 'name')
                    ->where(fn($query) => $query->where('effective_year', $request->input('effective_year'))),
            ],
            'effective_year' => 'required|integer',
            'is_active' => 'required|boolean'
        ], [
            'name.unique' => 'มีหลักสูตรนี้ในปีที่เริ่มใช้นี้อยู่ในระบบแล้ว',
            'name.required' => 'กรุณากรอกชื่อหลักสูตร',
            'effective_year.required' => 'กรุณากรอกปีที่เริ่มใช้หลักสูตร',
        ]);

        Curriculum::create($validated);

        return $this->redirectToMasterData('curriculums')->with('success', 'เพิ่มหลักสูตรเรียบร้อยแล้ว');
    }

    public function updateCurriculum(Request $request, Curriculum $curriculum)
    {
        $this->normalizeCurriculumInput($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('curriculums', 'name')
                    ->where(fn($query) => $query->where('effective_year', $request->input('effective_year')))
                    ->ignore($curriculum->id),
            ],
            'effective_year' => 'required|integer',
            'is_active' => 'required|boolean'
        ], [
            'name.unique' => 'มีหลักสูตรนี้ในปีที่เริ่มใช้นี้อยู่ในระบบแล้ว',
            'name.required' => 'กรุณากรอกชื่อหลักสูตร',
            'effective_year.required' => 'กรุณากรอกปีที่เริ่มใช้หลักสูตร',
        ]);

        $curriculum->update($validated);

        return $this->redirectToMasterData('curriculums')->with('success', 'อัปเดตข้อมูลหลักสูตรเรียบร้อยแล้ว');
    }

    public function cloneCurriculum(Request $request, Curriculum $curriculum)
    {
        $this->normalizeCurriculumInput($request);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('curriculums', 'name')
                    ->where(fn($query) => $query->where('effective_year', $request->input('effective_year'))),
            ],
            'effective_year' => 'required|integer'
        ], [
            'name.unique' => 'มีหลักสูตรนี้ในปีที่เริ่มใช้นี้อยู่ในระบบแล้ว',
            'name.required' => 'กรุณากรอกชื่อหลักสูตร',
            'effective_year.required' => 'กรุณากรอกปีที่เริ่มใช้หลักสูตร',
        ]);

        $newCurriculum = Curriculum::create([
            'name' => $validated['name'],
            'effective_year' => $validated['effective_year'],
            'is_active' => false,
        ]);

        $courses = $curriculum->courses;
        foreach ($courses as $course) {
            $newCourse = $course->replicate();
            $newCourse->curriculum_id = $newCurriculum->id;
            $newCourse->status = 'inactive';
            $newCourse->save();
        }

        return $this->redirectToMasterData('curriculums')->with('success', 'คัดลอกหลักสูตรเรียบร้อยแล้ว (' . $courses->count() . ' วิชา) — สถานะทั้งหมดตั้งเป็นปิดใช้งาน กรุณาเปิดใช้งานด้วยตนเอง');
    }

    public function destroyDepartment(Department $department)
    {
        try {
            $department->delete();
            return $this->redirectToMasterData('departments')->with('success', 'ลบภาควิชาเรียบร้อยแล้ว');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->back()->with('error', 'ไม่สามารถลบได้เนื่องจากมีข้อมูลผูกพันอยู่ (เช่น มีอาจารย์หรือวิชาสังกัดอยู่)');
        }
    }

    public function destroyLocationType(LocationType $locationType)
    {
        $affected = $locationType->rooms()->count();
        $locationType->rooms()->delete();
        $locationType->delete();

        $msg = 'ลบประเภทสถานที่เรียบร้อยแล้ว';
        if ($affected > 0) {
            $msg .= " (ลบห้อง/สถานที่ที่เกี่ยวข้องออก {$affected} แห่ง)";
        }
        return $this->redirectToMasterData('location_types')->with('success', $msg);
    }

    public function destroyRoom(Room $room)
    {
        try {
            $room->delete();
            return $this->redirectToMasterData('location_types')->with('success', 'ลบห้องเรียบร้อยแล้ว');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->back()->with('error', 'ไม่สามารถลบได้เนื่องจากมีข้อมูลผูกพันอยู่');
        }
    }

    public function destroyCourse(Course $course)
    {
        try {
            $course->delete();
            return $this->redirectToMasterData('courses')->with('success', 'ลบรายวิชาเรียบร้อยแล้ว');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->back()->with('error', 'ไม่สามารถลบได้เนื่องจากมีข้อมูลการสอนผูกอยู่');
        }
    }

    public function destroyCurriculum(Request $request, Curriculum $curriculum)
    {
        try {
            $courses = $curriculum->courses()->withCount('courseOfferings')->get();
            $blockedCourses = $courses->filter(fn($course) => $course->course_offerings_count > 0);

            if ($blockedCourses->isNotEmpty()) {
                $courseList = $blockedCourses
                    ->take(5)
                    ->map(fn($course) => $course->course_code)
                    ->implode(', ');

                return redirect()->back()->with('error', 'ไม่สามารถลบหลักสูตรได้ เนื่องจากมีรายวิชาที่ถูกนำไปใช้ในข้อมูลการสอนแล้ว: ' . $courseList);
            }

            $deletedCourseCount = $courses->count();

            // Require explicit cascade confirmation when curriculum still has courses
            if ($deletedCourseCount > 0 && !$request->boolean('confirm_cascade')) {
                $sampleCodes = $courses->take(5)->pluck('course_code')->implode(', ');
                $more = $deletedCourseCount > 5 ? " และอีก " . ($deletedCourseCount - 5) . " วิชา" : '';

                return redirect()->back()
                    ->with('error', "หลักสูตรนี้มี {$deletedCourseCount} รายวิชา ({$sampleCodes}{$more}) — กรุณายืนยันการลบแบบ cascade")
                    ->with('cascade_pending', [
                        'curriculum_id' => $curriculum->id,
                        'course_count' => $deletedCourseCount,
                        'sample_codes' => $sampleCodes,
                    ]);
            }

            DB::transaction(function () use ($curriculum) {
                $curriculum->courses()->delete();
                $curriculum->delete();
            });

            $message = 'ลบหลักสูตรเรียบร้อยแล้ว';
            if ($deletedCourseCount > 0) {
                $message .= " (ลบรายวิชาในหลักสูตรออก {$deletedCourseCount} วิชา)";
            }

            return $this->redirectToMasterData('curriculums')->with('success', $message);
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->back()->with('error', 'ไม่สามารถลบได้เนื่องจากมีข้อมูลผูกพันอยู่');
        }
    }

    // ── Activity Types ────────────────────────────────────────────────

    public function storeActivityType(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100|unique:activity_types,name',
            'color_code' => 'required|string|max:10',
            'category'   => 'required|in:lecture,practicum,thesis,other',
        ]);
        ActivityType::create($validated);
        return $this->redirectToMasterData('activity_types')->with('success', 'เพิ่มประเภทกิจกรรมเรียบร้อยแล้ว');
    }

    public function updateActivityType(Request $request, ActivityType $activityType)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100|unique:activity_types,name,' . $activityType->id,
            'color_code' => 'required|string|max:10',
            'category'   => 'required|in:lecture,practicum,thesis,other',
        ]);
        $activityType->update($validated);
        return $this->redirectToMasterData('activity_types')->with('success', 'อัปเดตประเภทกิจกรรมเรียบร้อยแล้ว');
    }

    public function destroyActivityType(ActivityType $activityType)
    {
        try {
            $activityType->delete();
            return $this->redirectToMasterData('activity_types')->with('success', 'ลบประเภทกิจกรรมเรียบร้อยแล้ว');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->back()->with('error', 'ไม่สามารถลบได้เนื่องจากมีกิจกรรมผูกอยู่กับประเภทนี้');
        }
    }

    // ── CSV Import ────────────────────────────────────────────────────

    private function courseCodeValidationMessages(): array
    {
        return [
            'course_code.regex' => self::COURSE_CODE_ALLOWED_MESSAGE,
            'course_code.unique' => 'รหัสวิชานี้มีอยู่แล้วในหลักสูตรนี้',
        ];
    }

    private function courseValidationMessages(): array
    {
        return $this->courseCodeValidationMessages() + [
            'head_instructor_id.required' => 'กรุณากำหนดหัวหน้าวิชาสำหรับรายวิชาที่เปิดใช้งาน',
        ];
    }

    public function importRooms(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|extensions:csv,txt|max:5120']);

        $file   = $request->file('csv_file');
        $handle = $this->openCsvHandle($file);

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return back()->with('error', 'ไฟล์ CSV ว่างเปล่า');
        }
        $header = $this->normalizeCsvHeader($header);
        $missing = $this->missingCsvHeaders($header, ['room_code', 'room_name', 'location_type_name']);
        if ($missing) {
            fclose($handle);
            return back()->with('error', 'หัวไฟล์ CSV ไม่ครบ: ' . implode(', ', $missing));
        }

        $locationTypes = LocationType::pluck('id', 'name')->toArray();
        $updateOnDup   = $request->boolean('update_on_duplicate');
        $successCount  = 0;
        $errors        = [];
        $row           = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if (!$this->csvRowHasData($data)) continue;

            $csv = $this->combineCsvRow($header, $data, $row, $errors);
            if ($csv === null) continue;
            $roomName = trim($csv['room_name'] ?? '');
            $roomCode = trim($csv['room_code'] ?? '');
            $ltName   = trim($csv['location_type_name'] ?? '');

            if (!$roomName || !$roomCode || !$ltName) {
                $errors[] = "แถว {$row}: ข้อมูลบังคับไม่ครบ (room_code, room_name, location_type_name)";
                continue;
            }

            $ltId = $locationTypes[$ltName] ?? null;
            if (!$ltId) {
                $errors[] = "แถว {$row}: ประเภทสถานที่ '{$ltName}' ไม่พบในระบบ";
                continue;
            }

            $exists = Room::where('room_code', $roomCode)->exists();
            if ($exists && !$updateOnDup) {
                $errors[] = "แถว {$row}: room_code '{$roomCode}' มีในระบบแล้ว — ข้ามแถวนี้";
                continue;
            }

            $status        = in_array(trim($csv['status'] ?? ''), ['active', 'inactive', 'maintenance'])
                ? trim($csv['status'])
                : 'active';
            $capacity      = (int)(trim($csv['capacity'] ?? '') ?: '0') ?: null;
            $building      = trim($csv['building'] ?? '') ?: null;
            $address       = trim($csv['address'] ?? '') ?: null;
            $equipmentRaw  = trim($csv['equipment_type'] ?? '');
            $equipmentType = $equipmentRaw
                ? array_values(array_filter(array_map('trim', explode(',', $equipmentRaw))))
                : null;

            try {
                Room::updateOrCreate(
                    ['room_code' => $roomCode],
                    [
                        'room_name'        => $roomName,
                        'location_type_id' => $ltId,
                        'capacity'         => $capacity,
                        'building'         => $building,
                        'address'          => $address,
                        'equipment_type'   => $equipmentType,
                        'status'           => $status,
                    ]
                );
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = "แถว {$row}: เกิดข้อผิดพลาด — " . $e->getMessage();
            }
        }

        fclose($handle);

        $failCount = count($errors);
        $msg = $failCount > 0
            ? "นำเข้าสำเร็จ {$successCount} ห้อง, ข้าม {$failCount} แถว — ดูรายละเอียดด้านล่างขวา"
            : "นำเข้าสำเร็จทั้งหมด {$successCount} ห้อง";
        $redirect = $this->redirectToMasterData('location_types')->with('success', $msg);
        if ($errors) $redirect->with('import_errors', $errors);
        return $redirect;
    }

    public function importCourses(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|extensions:csv,txt|max:5120']);

        $file   = $request->file('csv_file');
        $handle = $this->openCsvHandle($file);

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return back()->with('error', 'ไฟล์ CSV ว่างเปล่า');
        }
        $header = $this->normalizeCsvHeader($header);
        $missing = $this->missingCsvHeaders($header, ['course_code', 'name_th', 'curriculum_name', 'credits']);
        if ($missing) {
            fclose($handle);
            return back()->with('error', 'หัวไฟล์ CSV ไม่ครบ: ' . implode(', ', $missing));
        }

        $curriculums    = Curriculum::pluck('id', 'name')->toArray();
        $departments    = Department::pluck('id', 'name')->toArray();
        $employeeIds    = User::whereNotNull('employee_id')->pluck('id', 'employee_id')->toArray();
        $updateOnDup    = $request->boolean('update_on_duplicate');
        $successCount = 0;
        $errors       = [];
        $row          = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if (!$this->csvRowHasData($data)) continue;

            $csv = $this->combineCsvRow($header, $data, $row, $errors);
            if ($csv === null) continue;
            $code       = trim($csv['course_code'] ?? '');
            $nameTh     = trim($csv['name_th'] ?? '');
            $currName   = trim($csv['curriculum_name'] ?? '');
            $credits    = trim($csv['credits'] ?? '');
            $headEmpId  = trim($csv['head_instructor_employee_id'] ?? '');
            if (!$code || !$nameTh || !$currName || $credits === '' || !$headEmpId) {
                $errors[] = "แถว {$row}: ข้อมูลบังคับไม่ครบ (course_code, name_th, curriculum_name, credits, head_instructor_employee_id)";
                continue;
            }

            if (! preg_match(self::COURSE_CODE_ALLOWED_REGEX, $code)) {
                $errors[] = "แถว {$row}: course_code '{$code}' " . self::COURSE_CODE_ALLOWED_MESSAGE;
                continue;
            }

            $currId = $curriculums[$currName] ?? null;
            if (!$currId) {
                $errors[] = "แถว {$row}: หลักสูตร '{$currName}' ไม่พบในระบบ";
                continue;
            }

            $headId = $employeeIds[$headEmpId] ?? null;
            if (!$headId) {
                $errors[] = "แถว {$row}: ไม่พบผู้ใช้ที่มีรหัสพนักงาน '{$headEmpId}'";
                continue;
            }

            // department_name เป็น optional — วิชาเรียนรวมไม่ต้องสังกัดภาควิชา
            $deptName = trim($csv['department_name'] ?? '');
            $deptId   = null;
            if ($deptName !== '') {
                $deptId = $departments[$deptName] ?? null;
                if (!$deptId) {
                    $errors[] = "แถว {$row}: ไม่พบภาควิชา '{$deptName}' — ถ้าเป็นวิชาเรียนรวมให้เว้นช่อง department_name ว่างไว้";
                    continue;
                }
            }

            $yearLevel = trim($csv['default_year_level'] ?? '');
            $semester  = trim($csv['default_semester'] ?? '');
            if ($yearLevel === '' || $semester === '') {
                $errors[] = "แถว {$row}: ต้องระบุ default_year_level และ default_semester";
                continue;
            }

            $capacity = trim($csv['capacity'] ?? '');
            if ($capacity === '' || (int)$capacity < 1) {
                $errors[] = "แถว {$row}: ต้องระบุ capacity (จำนวนนักศึกษาสูงสุด ≥ 1)";
                continue;
            }

            $courseType = trim($csv['course_type'] ?? '') ?: 'theory';
            if (!in_array($courseType, ['theory', 'practicum', 'theory_practicum'], true)) {
                $errors[] = "แถว {$row}: course_type ต้องเป็น theory, practicum หรือ theory_practicum";
                continue;
            }

            // requires_practicum_rotation บังคับสำหรับ practicum / theory_practicum
            $courseType = trim($csv['course_type'] ?? '') ?: 'theory';
            $rotationRaw = trim($csv['requires_practicum_rotation'] ?? '');
            if (in_array($courseType, ['practicum', 'theory_practicum']) && $rotationRaw === '') {
                $errors[] = "แถว {$row}: วิชาประเภท {$courseType} ต้องระบุ requires_practicum_rotation (0 หรือ 1)";
                continue;
            }
            $rotation = in_array($rotationRaw, ['1', 'true', 'yes']) ? true : false;

            $exists = Course::where('course_code', $code)->where('curriculum_id', $currId)->exists();
            if ($exists && !$updateOnDup) {
                $errors[] = "แถว {$row}: course_code '{$code}' ในหลักสูตรนี้มีอยู่แล้ว — ข้ามแถวนี้";
                continue;
            }

            $lecture   = (int)(trim($csv['lecture_hours'] ?? '0') ?: '0');
            $lab       = (int)(trim($csv['lab_hours'] ?? '0') ?: '0');
            $selfStudy = (int)(trim($csv['self_study_hours'] ?? '0') ?: '0');
            $status    = in_array(trim($csv['status'] ?? ''), ['active', 'inactive']) ? trim($csv['status']) : 'active';
            $colorCode = trim($csv['color_code'] ?? '') ?: null;

            try {
                Course::updateOrCreate(
                    ['course_code' => $code, 'curriculum_id' => $currId],
                    [
                        'name_th'                     => $nameTh,
                        'name_en'                     => trim($csv['name_en'] ?? '') ?: null,
                        'department_id'               => $deptId,
                        'head_instructor_id'          => $headId,
                        'course_type'                 => $courseType,
                        'credits'                     => (int)$credits,
                        'lecture_hours'               => $lecture,
                        'lab_hours'                   => $lab,
                        'self_study_hours'            => $selfStudy,
                        'default_year_level'          => (int)$yearLevel,
                        'default_semester'            => (int)$semester,
                        'capacity'                    => (int)$capacity,
                        'requires_practicum_rotation' => $rotation,
                        'color_code'                  => $colorCode,
                        'status'                      => $status,
                        'academic_level'              => 'undergraduate',
                    ]
                );
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = "แถว {$row}: เกิดข้อผิดพลาด — " . $e->getMessage();
            }
        }

        fclose($handle);

        $failCount = count($errors);
        $msg = $failCount > 0
            ? "นำเข้าสำเร็จ {$successCount} วิชา, ข้าม {$failCount} แถว — ดูรายละเอียดด้านล่างขวา"
            : "นำเข้าสำเร็จทั้งหมด {$successCount} วิชา";
        $redirect = $this->redirectToMasterData('courses')->with('success', $msg);
        if ($errors) $redirect->with('import_errors', $errors);
        return $redirect;
    }
}
