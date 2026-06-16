<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRole;
use App\Models\Curriculum;
use App\Models\StudentCohort;
use App\Models\ActivityType;
use App\Services\AuditLogger;
use App\Services\ReferenceDataCache;
use App\Support\ThaiDate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
    private const COURSE_MANAGEMENT_CATEGORY = 'รายวิชาและผู้รับผิดชอบ';

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
        $locationTypes = LocationType::query()
            ->withCount('rooms')
            ->with(['rooms:id,location_type_id,room_code,room_name,building,capacity,equipment_type,address,status'])
            ->get();
        $locationTypes->each(function (LocationType $type): void {
            $rooms = $type->rooms->sortBy('room_name')->values();
            $statusCounts = $rooms->countBy('status');

            $type->setRelation('rooms', $rooms);
            $type->room_status_counts = [
                'active' => (int) ($statusCounts['active'] ?? 0),
                'inactive' => (int) ($statusCounts['inactive'] ?? 0),
                'maintenance' => (int) ($statusCounts['maintenance'] ?? 0),
            ];
            $type->room_statuses = $rooms->pluck('status')->unique()->join(' ');
            $type->room_search_haystack = Str::lower(collect([
                $type->name,
                $type->rooms_count,
                'แห่ง',
                $type->room_status_counts['active'],
                'ใช้งาน',
                $type->room_status_counts['maintenance'],
                'ซ่อมบำรุง',
                $type->room_status_counts['inactive'],
                'ปิดใช้งาน',
                $rooms->pluck('room_code')->join(' '),
                $rooms->pluck('room_name')->join(' '),
                $rooms->pluck('building')->join(' '),
                $rooms->pluck('capacity')->join(' '),
            ])->filter()->join(' '));

            $rooms->each(function (Room $room): void {
                $statusMap = [
                    'active' => 'ใช้งาน',
                    'inactive' => 'ปิดใช้งาน',
                    'maintenance' => 'ซ่อมบำรุง',
                ];

                $room->search_haystack = Str::lower(collect([
                    $room->room_code,
                    $room->room_name,
                    $room->building,
                    $room->capacity,
                    'คน',
                    $room->address,
                    is_array($room->equipment_type) ? implode(' ', $room->equipment_type) : $room->equipment_type,
                    $room->status,
                    $statusMap[$room->status] ?? '',
                ])->filter()->join(' '));
            });
        });

        // Rooms with their types
        $rooms = Room::query()
            ->select(['id', 'location_type_id', 'room_code', 'room_name', 'building', 'capacity', 'equipment_type', 'address', 'status'])
            ->with('locationType:id,name,is_shared')
            ->get();

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
            'topics:id,course_id,name,sort_order',
        ])
            ->withExists([
                'courseOfferings as has_locked_offering' => fn ($query) => $query->whereHas(
                    'academicYear',
                    fn ($yearQuery) => $yearQuery->whereIn('phase', ['scheduling', 'published'])
                ),
            ])
            ->orderBy('course_code')
            ->get();

        // หมายเหตุ: รายงาน deviation (ผู้สอนต่างจากแม่แบบ) ย้ายไปอยู่หน้าแจ้งเตือนแล้ว
        // (App\Support\CourseDeviationFinder + section ใน admin.alerts) — เลิกคำนวณ/แสดงปุ่มที่นี่

        $courseRoles = app(ReferenceDataCache::class)
            ->courseRoles()
            ->filter(fn ($role) => $role->name_th !== 'หัวหน้าวิชา')
            ->values();

        $courseInstructorUsers = User::query()
            ->with('instructorProfile.department')
            ->where('is_active', true)
            ->whereHas('instructorProfile')
            ->whereHas('roles', fn ($q) => $q->whereIn('role', ['instructor', 'course_head']))
            ->orderBy('name')
            ->get();

        // Curriculums with course count and courses list
        $curriculums = Curriculum::withCount('courses')->with(['courses' => fn($q) => $q->with('department')->orderBy('course_code')])->get();

        // กลุ่มนักศึกษา (cohort — V2) — ทุกระดับหลักสูตร
        // หลักสูตรที่ใช้ระบบชั้นปี (ป.ตรี) มี year_level / หลักสูตรที่ไม่ใช้ (ป.โท-เอก) year_level = null
        $cohortCurriculums = Curriculum::query()
            ->with(['studentCohorts' => fn ($q) => $q
                ->orderBy('year_level')
                ->orderByRaw('COALESCE(parent_id, id)') // จัดกลุ่มย่อยให้อยู่ใต้กลุ่มใหญ่เดียวกัน
                ->orderByRaw('parent_id IS NOT NULL')   // กลุ่มใหญ่ (null) มาก่อนกลุ่มย่อย
                ->orderBy('code')])
            ->orderBy('education_level')
            ->orderBy('effective_year', 'desc')
            ->orderBy('name')
            ->get();

        // Activity Types
        $activityTypes = app(ReferenceDataCache::class)->activityTypes();

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
        $canManageMasterData = in_array($activeRole, ['admin', 'staff'], true);
        $routePrefix = $isAdmin ? 'admin' : 'staff';

        return view('shared.master_data.index', compact(
            'instructors',
            'departments',
            'users',
            'locationTypes',
            'rooms',
            'courses',
            'curriculums',
            'cohortCurriculums',
            'activityTypes',
            'staffUsers',
            'courseRoles',
            'courseInstructorUsers',
            'isAdmin',
            'canManageMasterData',
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

    private function auditSnapshot(Model $model, array $fields): array
    {
        return collect($model->only($fields))
            ->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value)
            ->all();
    }

    private function auditDiff(array $before, array $after): array
    {
        $old = [];
        $new = [];

        foreach ($after as $key => $value) {
            $beforeValue = $before[$key] ?? null;

            if ($this->normalizeAuditValue($beforeValue) !== $this->normalizeAuditValue($value)) {
                $old[$key] = $beforeValue;
                $new[$key] = $value;
            }
        }

        return [$old, $new];
    }

    private function normalizeAuditValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_array($value)) {
            return array_values($value);
        }

        return $value;
    }

    private function logMasterDataCreate(string $table, int $recordId, array $newValues, string $description): void
    {
        AuditLogger::log(
            action: 'ข้อมูลหลัก.สร้าง',
            table: $table,
            recordId: $recordId,
            oldValues: null,
            newValues: $newValues,
            description: $description,
        );
    }

    private function logMasterDataUpdate(string $table, int $recordId, array $oldValues, array $newValues, string $description): void
    {
        if (empty($oldValues) && empty($newValues)) {
            return;
        }

        AuditLogger::log(
            action: 'ข้อมูลหลัก.แก้ไข',
            table: $table,
            recordId: $recordId,
            oldValues: $oldValues,
            newValues: $newValues,
            description: $description,
        );
    }

    private function logMasterDataDelete(string $table, int $recordId, array $oldValues, ?array $newValues, string $description): void
    {
        AuditLogger::log(
            action: 'ข้อมูลหลัก.ลบ',
            table: $table,
            recordId: $recordId,
            oldValues: $oldValues,
            newValues: $newValues,
            description: $description,
        );
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

    private function normalizeThaiDateInput(Request $request, string $field): void
    {
        if (! $request->has($field)) {
            return;
        }

        $value = $request->input($field);
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        $iso = ThaiDate::parseToIso((string) $value);
        if ($iso) {
            $request->merge([$field => $iso]);
        }
    }

    public function storeLocationType(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:location_types,name',
        ]);
        $validated['is_shared'] = $request->boolean('is_shared');

        $locationType = LocationType::create($validated);

        $this->logMasterDataCreate(
            'location_types',
            $locationType->id,
            $this->auditSnapshot($locationType, ['name', 'is_shared']),
            "สร้างประเภทสถานที่ {$locationType->name}",
        );

        return $this->redirectToMasterData('location_types')->with('success', 'เพิ่มประเภทสถานที่เรียบร้อยแล้ว');
    }

    public function updateLocationType(Request $request, LocationType $locationType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:location_types,name,' . $locationType->id,
        ]);
        $validated['is_shared'] = $request->boolean('is_shared');

        $fields = ['name', 'is_shared'];
        $before = $this->auditSnapshot($locationType, $fields);

        $locationType->update($validated);

        $after = $this->auditSnapshot($locationType->fresh(), $fields);
        [$oldValues, $newValues] = $this->auditDiff($before, $after);
        $this->logMasterDataUpdate(
            'location_types',
            $locationType->id,
            $oldValues,
            $newValues,
            "แก้ไขประเภทสถานที่ {$locationType->name}",
        );

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

        $room = Room::create($validated);

        $this->logMasterDataCreate(
            'rooms',
            $room->id,
            $this->auditSnapshot($room, ['room_code', 'room_name', 'building', 'capacity', 'location_type_id', 'status', 'address', 'equipment_type']),
            "สร้างห้อง {$room->room_code}",
        );

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

        $fields = ['room_code', 'room_name', 'building', 'capacity', 'location_type_id', 'status', 'address', 'equipment_type'];
        $before = $this->auditSnapshot($room, $fields);

        $room->update($validated);

        $after = $this->auditSnapshot($room->fresh(), $fields);
        [$oldValues, $newValues] = $this->auditDiff($before, $after);
        $this->logMasterDataUpdate(
            'rooms',
            $room->id,
            $oldValues,
            $newValues,
            "แก้ไขห้อง {$room->room_code}",
        );

        return $this->redirectToMasterData('location_types')->with('success', 'อัปเดตห้อง/สถานที่เรียบร้อยแล้ว');
    }

    public function storeDepartment(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
        ]);

        $department = Department::create($validated);
        $this->logMasterDataCreate(
            'departments',
            $department->id,
            $this->auditSnapshot($department, ['name', 'head_user_id', 'secretary_user_id']),
            "สร้างภาควิชา {$department->name}",
        );
        AlertController::flushCache();
        return redirect()->back()->with('success', 'เพิ่มภาควิชาเรียบร้อยแล้ว');
    }

    public function updateDepartment(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255|unique:departments,name,' . $department->id,
            'secretary_user_id'=> 'nullable|exists:users,id',
        ]);
        $this->validateDepartmentSecretary($validated['secretary_user_id'] ?? null, $department);

        $newSecId  = $validated['secretary_user_id'] ?? null;
        $forceOverride = $request->boolean('force_position_override');

        // If override confirmed by user, release secretary role from other depts first
        if ($forceOverride) {
            if ($newSecId) {
                Department::where('id', '!=', $department->id)
                    ->where('secretary_user_id', $newSecId)
                    ->update(['secretary_user_id' => null]);
            }
        }

        $fields = ['name', 'head_user_id', 'secretary_user_id'];
        $before = $this->auditSnapshot($department, $fields);

        $department->update($validated);

        $after = $this->auditSnapshot($department->fresh(), $fields);
        [$oldValues, $newValues] = $this->auditDiff($before, $after);
        $this->logMasterDataUpdate(
            'departments',
            $department->id,
            $oldValues,
            $newValues,
            "แก้ไขภาควิชา {$department->name}",
        );
        AlertController::flushCache();
        return redirect()->back()->with('success', 'อัปเดตภาควิชาเรียบร้อยแล้ว');
    }

    private function validateDepartmentSecretary(mixed $secretaryUserId, Department $department): void
    {
        if (! $secretaryUserId) {
            return;
        }

        $secretary = User::with(['instructorProfile', 'roles'])->find($secretaryUserId);
        $hasInstructorRole = $secretary?->roles
            ? $secretary->roles->pluck('role')->intersect(['instructor', 'course_head'])->isNotEmpty()
            : false;

        if (! $secretary?->instructorProfile || ! $hasInstructorRole || (int) $secretary->instructorProfile->department_id !== (int) $department->id) {
            throw ValidationException::withMessages([
                'secretary_user_id' => 'เลขานุการภาควิชาต้องเป็นอาจารย์ที่อยู่ในภาควิชานี้เท่านั้น',
            ]);
        }
    }

    public function updateInstructor(Request $request, $id)
    {
        $this->normalizeThaiDateInput($request, 'hired_at');

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

        $userFields = ['name', 'prefix', 'employee_id'];
        $profileFields = [
            'title',
            'academic_degree',
            'department_id',
            'employment_type',
            'hired_at',
            'is_english_passed',
            'teaching_pct',
            'research_pct',
            'service_pct',
            'culture_pct',
            'other_pct',
        ];
        $before = $this->auditSnapshot($user, $userFields) + ($profile ? $this->auditSnapshot($profile, $profileFields) : []);

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

        $user->refresh();
        $profile = $user->instructorProfile;
        $after = $this->auditSnapshot($user, $userFields) + ($profile ? $this->auditSnapshot($profile, $profileFields) : []);
        [$oldValues, $newValues] = $this->auditDiff($before, $after);
        $this->logMasterDataUpdate(
            'instructor_profiles',
            $profile?->id ?? $user->id,
            $oldValues,
            $newValues,
            "แก้ไขข้อมูลอาจารย์ {$user->name}",
        );

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
            // checkbox/select boolean — กัน field หายตอนไม่ติ๊ก ให้เป็น false เสมอ
            'counts_service_only' => $request->boolean('counts_service_only'),
        ]);
    }

    public function storeCourse(Request $request)
    {
        $curriculum = Curriculum::find($request->input('curriculum_id'));
        $rules = $this->courseValidationRules($request, $curriculum);

        $validated = $request->validate($rules, $this->courseValidationMessages());

        if ($this->courseCodeExistsInCurriculum($validated['course_code'], (int) $validated['curriculum_id'])) {
            return back()
                ->withErrors(['course_code' => 'รหัสวิชานี้มีอยู่แล้วในหลักสูตรนี้'])
                ->withInput();
        }

        $validated['is_required'] = $request->boolean('is_required');
        if ($curriculum && ! $curriculum->uses_year_level) {
            $validated['default_year_level'] = null;
        }
        $staffIds = $validated['staff_ids'] ?? [];
        $instructorIds = $validated['instructor_ids'] ?? [];
        $instructorRoleIds = $validated['instructor_role_ids'] ?? [];
        $prerequisiteIds = $validated['prerequisite_ids'] ?? [];
        unset($validated['staff_ids'], $validated['instructor_ids'], $validated['instructor_role_ids'], $validated['prerequisite_ids']);

        $course = Course::create($validated);
        $course->assignedStaff()->sync($staffIds);
        $this->syncCourseInstructors($course, $instructorIds, $instructorRoleIds);
        $course->prerequisites()->sync($prerequisiteIds);

        AuditLogger::log(
            action:      'ข้อมูลหลัก.สร้าง',
            table:       'courses',
            recordId:    $course->id,
            oldValues:   null,
            newValues:   [
                'course_code'        => $course->course_code,
                'name_th'            => $course->name_th,
                'status'             => $course->status,
                'credits'            => $course->credits,
                'head_instructor_id' => $course->head_instructor_id,
            ],
            description: "สร้างรายวิชา {$course->course_code} {$course->name_th}",
        );

        return $this->redirectToMasterData('courses')->with('success', 'เพิ่มรายวิชาเรียบร้อยแล้ว');
    }

    public function updateCourse(Request $request, Course $course)
    {
        $assignmentsLocked = $this->isCourseAssignmentLocked($course);
        $curriculum = Curriculum::find($request->input('curriculum_id'));

        $rules = $this->courseValidationRules($request, $curriculum, $course, $assignmentsLocked);
        $rules['prerequisite_ids.*'][] = Rule::notIn([$course->id]);

        $validated = $request->validate($rules, $this->courseValidationMessages());

        if ($this->courseCodeExistsInCurriculum($validated['course_code'], (int) $validated['curriculum_id'], $course->id)) {
            return back()
                ->withErrors(['course_code' => 'รหัสวิชานี้มีอยู่แล้วในหลักสูตรนี้'])
                ->withInput();
        }

        $validated['is_required'] = $request->boolean('is_required');
        if ($curriculum && ! $curriculum->uses_year_level) {
            $validated['default_year_level'] = null;
        }
        $staffIds = $validated['staff_ids'] ?? [];
        $instructorIds = $validated['instructor_ids'] ?? [];
        $instructorRoleIds = $validated['instructor_role_ids'] ?? [];
        $prerequisiteIds = $validated['prerequisite_ids'] ?? [];
        if ($assignmentsLocked) {
            unset($validated['head_instructor_id']);
        }
        unset($validated['staff_ids'], $validated['instructor_ids'], $validated['instructor_role_ids'], $validated['prerequisite_ids']);

        // Snapshot auditable fields before update
        $auditFields = ['course_code', 'name_th', 'status', 'credits', 'head_instructor_id'];
        $auditBefore = $course->only($auditFields);
        $responsibilityBefore = $assignmentsLocked ? null : $this->courseResponsibilitySnapshot($course);

        $course->update($validated);
        if (! $assignmentsLocked) {
            $course->assignedStaff()->sync($staffIds);
            $this->syncCourseInstructors($course, $instructorIds, $instructorRoleIds);
        }
        $course->prerequisites()->sync($prerequisiteIds);

        // Diff and log only when something actually changed
        $auditAfter = $course->fresh()->only($auditFields);
        $diff = AuditLogger::diff($auditBefore, $auditAfter);
        if (!empty($diff['old']) || !empty($diff['new'])) {
            AuditLogger::log(
                action:      'ข้อมูลหลัก.แก้ไข',
                table:       'courses',
                recordId:    $course->id,
                oldValues:   $diff['old'] ?: null,
                newValues:   $diff['new'] ?: null,
                description: "แก้ไขรายวิชา {$course->course_code} {$course->name_th}",
            );
        }

        if (! $assignmentsLocked && $responsibilityBefore !== null) {
            $freshCourse = $course->fresh();
            $responsibilityAfter = $this->courseResponsibilitySnapshot($freshCourse);
            $responsibilityDiff = AuditLogger::diff($responsibilityBefore, $responsibilityAfter);

            if (!empty($responsibilityDiff['old']) || !empty($responsibilityDiff['new'])) {
                AuditLogger::log(
                    action: self::COURSE_MANAGEMENT_CATEGORY . '.แก้ไข',
                    table: 'course_instructors',
                    recordId: $course->id,
                    oldValues: $responsibilityDiff['old'] ?: null,
                    newValues: ($responsibilityDiff['new'] ?: []) + $this->courseAuditContext($freshCourse),
                    category: self::COURSE_MANAGEMENT_CATEGORY,
                    description: "แก้ไขผู้รับผิดชอบรายวิชา {$freshCourse->course_code} {$freshCourse->name_th}",
                );
            }
        }

        return $this->redirectToMasterData('courses')->with('success', 'อัปเดตข้อมูลรายวิชาเรียบร้อยแล้ว');
    }

    private function courseValidationRules(
        Request $request,
        ?Curriculum $curriculum,
        ?Course $existing = null,
        bool $assignmentsLocked = false
    ): array {
        $uniqueRule = Rule::unique('courses', 'course_code')
            ->where(fn($q) => $q->where('curriculum_id', $request->input('curriculum_id')));
        if ($existing) {
            $uniqueRule->ignore($existing->id);
        }

        $usesYearLevel = $curriculum ? (bool) $curriculum->uses_year_level : true;
        $maxYear = $curriculum ? max(1, (int) $curriculum->duration_years) : 4;

        $yearRules = $usesYearLevel
            ? ['required', 'integer', 'min:1', 'max:' . $maxYear]
            : ['nullable', 'integer', 'min:1', 'max:' . $maxYear];

        return [
            'course_code'                 => [
                'required', 'string', 'max:20', 'regex:' . self::COURSE_CODE_ALLOWED_REGEX,
                $uniqueRule,
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
            'default_year_level'          => $yearRules,
            'credits'                     => 'required|integer|min:0',
            'lecture_hours'               => 'required|integer|min:0',
            'lab_hours'                   => 'required|integer|min:0',
            'self_study_hours'            => 'required|integer|min:0',
            'color_code'                  => 'nullable|string|max:7',
            'status'                      => 'required|in:active,inactive',
            'is_required'                 => 'required|boolean',
            'prerequisite_ids'            => 'nullable|array',
            'prerequisite_ids.*'          => ['integer', 'distinct', 'exists:courses,id'],
        ];
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

    private function courseResponsibilitySnapshot(Course $course): array
    {
        $roles = CourseRole::pluck('name_th', 'id');
        $head = $course->head_instructor_id ? User::find($course->head_instructor_id) : null;

        return [
            'head_instructor_id' => $course->head_instructor_id,
            'head_instructor_name' => $head?->formatted_name ?? $head?->name,
            'responsible_instructors' => $course->instructors()
                ->orderBy('users.id')
                ->get()
                ->map(fn (User $user) => [
                    'user_id' => $user->id,
                    'instructor_name' => $user->formatted_name ?? $user->name,
                    'course_role_id' => $user->pivot->course_role_id ? (int) $user->pivot->course_role_id : null,
                    'course_role_name' => $user->pivot->course_role_id ? ($roles[$user->pivot->course_role_id] ?? null) : null,
                ])
                ->values()
                ->all(),
        ];
    }

    private function courseAuditContext(Course $course): array
    {
        return [
            'course_id' => $course->id,
            'course_code' => $course->course_code,
            'course_name' => $course->name_th,
        ];
    }

    public function storeCurriculum(Request $request)
    {
        $this->normalizeCurriculumInput($request);

        $validated = $request->validate(
            $this->curriculumValidationRules($request),
            $this->curriculumValidationMessages()
        );

        $validated['duration_years'] = $validated['duration_years'] ?? 1;

        $curriculum = Curriculum::create($validated);

        $this->logMasterDataCreate(
            'curriculums',
            $curriculum->id,
            $this->auditSnapshot($curriculum, ['name', 'effective_year', 'education_level', 'duration_years', 'uses_year_level', 'total_credits_required', 'counts_service_only', 'is_active']),
            "สร้างหลักสูตร {$curriculum->name}",
        );

        return $this->redirectToMasterData('curriculums')->with('success', 'เพิ่มหลักสูตรเรียบร้อยแล้ว');
    }

    public function updateCurriculum(Request $request, Curriculum $curriculum)
    {
        $this->normalizeCurriculumInput($request);

        $validated = $request->validate(
            $this->curriculumValidationRules($request, $curriculum),
            $this->curriculumValidationMessages()
        );

        $validated['duration_years'] = $validated['duration_years'] ?? $curriculum->duration_years ?? 1;

        $fields = ['name', 'effective_year', 'education_level', 'duration_years', 'uses_year_level', 'total_credits_required', 'counts_service_only', 'is_active'];
        $before = $this->auditSnapshot($curriculum, $fields);

        DB::transaction(function () use ($curriculum, $validated) {
            // ถ้าเปลี่ยน uses_year_level เป็น false → เคลียร์ default_year_level ของทุกวิชาในหลักสูตร
            if (! $validated['uses_year_level']) {
                $curriculum->courses()->update(['default_year_level' => null]);
            }

            $curriculum->update($validated);
        });

        $after = $this->auditSnapshot($curriculum->fresh(), $fields);
        [$oldValues, $newValues] = $this->auditDiff($before, $after);
        $this->logMasterDataUpdate(
            'curriculums',
            $curriculum->id,
            $oldValues,
            $newValues,
            "แก้ไขหลักสูตร {$curriculum->name}",
        );

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
            'name'                   => $validated['name'],
            'effective_year'         => $validated['effective_year'],
            'education_level'        => $curriculum->education_level,
            'duration_years'         => $curriculum->duration_years,
            'uses_year_level'        => $curriculum->uses_year_level,
            'total_credits_required' => $curriculum->total_credits_required,
            'counts_service_only'    => $curriculum->counts_service_only,
            'is_active'              => false,
        ]);

        $courses = $curriculum->courses;
        foreach ($courses as $course) {
            $newCourse = $course->replicate();
            $newCourse->curriculum_id = $newCurriculum->id;
            $newCourse->status = 'inactive';
            $newCourse->save();
        }

        AuditLogger::log(
            action: 'ข้อมูลหลัก.คัดลอก',
            table: 'curriculums',
            recordId: $newCurriculum->id,
            oldValues: [
                'source_curriculum_id' => $curriculum->id,
                'source_name' => $curriculum->name,
                'source_effective_year' => $curriculum->effective_year,
            ],
            newValues: [
                'curriculum_id' => $newCurriculum->id,
                'name' => $newCurriculum->name,
                'effective_year' => $newCurriculum->effective_year,
                'cloned_course_count' => $courses->count(),
                'sample_course_codes' => $courses->pluck('course_code')->take(5)->values()->all(),
            ],
            category: 'ข้อมูลหลัก',
            description: "คัดลอกหลักสูตร {$curriculum->name} เป็น {$newCurriculum->name}",
        );

        return $this->redirectToMasterData('curriculums')->with('success', 'คัดลอกหลักสูตรเรียบร้อยแล้ว (' . $courses->count() . ' วิชา) — สถานะทั้งหมดตั้งเป็นปิดใช้งาน กรุณาเปิดใช้งานด้วยตนเอง');
    }

    public function destroyDepartment(Department $department)
    {
        $snapshot = $this->auditSnapshot($department, ['name', 'head_user_id', 'secretary_user_id']);
        $departmentId = $department->id;

        try {
            $department->delete();

            $this->logMasterDataDelete(
                'departments',
                $departmentId,
                $snapshot,
                null,
                "ลบภาควิชา {$snapshot['name']}",
            );

            return $this->redirectToMasterData('departments')->with('success', 'ลบภาควิชาเรียบร้อยแล้ว');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->back()->with('error', 'ไม่สามารถลบได้เนื่องจากมีข้อมูลผูกพันอยู่ (เช่น มีอาจารย์หรือวิชาสังกัดอยู่)');
        }
    }

    public function destroyLocationType(LocationType $locationType)
    {
        $snapshot = $this->auditSnapshot($locationType, ['name', 'is_shared']);
        $locationTypeId = $locationType->id;
        $affected = $locationType->rooms()->count();

        DB::transaction(function () use ($locationType) {
            $locationType->rooms()->delete();
            $locationType->delete();
        });

        $this->logMasterDataDelete(
            'location_types',
            $locationTypeId,
            $snapshot,
            ['deleted_room_count' => $affected],
            "ลบประเภทสถานที่ {$snapshot['name']}",
        );

        $msg = 'ลบประเภทสถานที่เรียบร้อยแล้ว';
        if ($affected > 0) {
            $msg .= " (ลบห้อง/สถานที่ที่เกี่ยวข้องออก {$affected} แห่ง)";
        }
        return $this->redirectToMasterData('location_types')->with('success', $msg);
    }

    public function destroyRoom(Room $room)
    {
        $snapshot = $this->auditSnapshot($room, ['room_code', 'room_name', 'building', 'capacity', 'location_type_id', 'status', 'address', 'equipment_type']);
        $roomId = $room->id;

        try {
            $room->delete();

            $this->logMasterDataDelete(
                'rooms',
                $roomId,
                $snapshot,
                null,
                "ลบห้อง {$snapshot['room_code']}",
            );

            return $this->redirectToMasterData('location_types')->with('success', 'ลบห้องเรียบร้อยแล้ว');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->back()->with('error', 'ไม่สามารถลบได้เนื่องจากมีข้อมูลผูกพันอยู่');
        }
    }

    /**
     * V4 ข้อ 1 — บันทึกชุดหัวข้อกิจกรรมของวิชา (replace ทั้งชุด)
     */
    public function syncCourseTopics(Request $request, Course $course)
    {
        $validated = $request->validate([
            'topics'   => ['nullable', 'array'],
            'topics.*' => ['nullable', 'string', 'max:255'],
        ], [
            'topics.*.max' => 'หัวข้อกิจกรรมต้องไม่เกิน 255 ตัวอักษร',
        ]);

        $names = collect($validated['topics'] ?? [])
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->values();

        \Illuminate\Support\Facades\DB::transaction(function () use ($course, $names) {
            $course->topics()->delete();
            foreach ($names as $i => $name) {
                $course->topics()->create(['name' => $name, 'sort_order' => $i]);
            }
        });

        AuditLogger::log(
            action: 'ข้อมูลหลัก.แก้ไข',
            table: 'activity_topics',
            recordId: $course->id,
            oldValues: null,
            newValues: ['course' => $course->course_code, 'topics' => $names->all()],
            description: "ตั้งหัวข้อกิจกรรมของวิชา {$course->course_code} ({$names->count()} หัวข้อ)",
        );

        return back()->with('success', "บันทึกหัวข้อกิจกรรมของวิชา {$course->course_code} เรียบร้อยแล้ว ({$names->count()} หัวข้อ)");
    }

    /**
     * V4 ข้อ 1 — รายการหัวข้อกิจกรรมของวิชา (JSON) สำหรับหน้าจัดตาราง (Branch A เรียกใช้)
     */
    public function courseTopics(Course $course)
    {
        return response()->json([
            'topics' => $course->topics()->get(['id', 'name']),
        ]);
    }

    public function courseInstructorDeviation(Course $course)
    {
        // Include offerings ทุก phase — admin ใช้ดู pattern ข้ามปีเพื่อตัดสินใจ template รอบหน้า
        $offerings = $course->courseOfferings()
            ->with(['academicYear', 'coordinator', 'instructorPool.instructorProfile.department'])
            ->get()
            ->sortByDesc(fn ($o) => $o->academicYear?->name);

        $course->load([
            'instructors.instructorProfile.department',
            'headInstructor.instructorProfile',
            'department',
            'curriculum',
        ]);

        $userIds = collect();
        $deviations = $offerings->mapWithKeys(function ($offering) use ($course, &$userIds) {
            $diff = $course->instructorPoolDeviationFor($offering);
            foreach (['added', 'removed', 'role_changed'] as $bucket) {
                foreach ($diff[$bucket] as $entry) {
                    $userIds->push($entry['user_id']);
                }
            }
            return [$offering->id => $diff];
        });

        $users = User::whereIn('id', $userIds->unique()->values())
            ->with('instructorProfile.department')
            ->get()
            ->keyBy('id');

        $courseRoles = CourseRole::orderBy('sort_order')->get()->keyBy('id');

        $templateUpdatedAt = \Illuminate\Support\Facades\DB::table('course_instructors')
            ->where('course_id', $course->id)
            ->max('updated_at');

        return view('admin.courses.instructor_deviation', [
            'course'             => $course,
            'offerings'          => $offerings,
            'deviations'         => $deviations,
            'users'              => $users,
            'courseRoles'        => $courseRoles,
            'templateUpdatedAt'  => $templateUpdatedAt,
        ]);
    }

    public function destroyCourse(Course $course)
    {
        $snapshot  = ['course_code' => $course->course_code, 'name_th' => $course->name_th, 'status' => $course->status];
        $courseId  = $course->id;

        try {
            $course->delete();

            AuditLogger::log(
                action:      'ข้อมูลหลัก.ลบ',
                table:       'courses',
                recordId:    $courseId,
                oldValues:   $snapshot,
                newValues:   null,
                description: "ลบรายวิชา {$snapshot['course_code']} {$snapshot['name_th']}",
            );

            return $this->redirectToMasterData('courses')->with('success', 'ลบรายวิชาเรียบร้อยแล้ว');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->back()->with('error', 'ไม่สามารถลบได้เนื่องจากมีข้อมูลการสอนผูกอยู่');
        }
    }

    public function destroyCurriculum(Request $request, Curriculum $curriculum)
    {
        try {
            $snapshot = $this->auditSnapshot($curriculum, ['name', 'effective_year', 'education_level', 'duration_years', 'uses_year_level', 'total_credits_required', 'is_active']);
            $curriculumId = $curriculum->id;
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

            $this->logMasterDataDelete(
                'curriculums',
                $curriculumId,
                $snapshot,
                ['deleted_course_count' => $deletedCourseCount],
                "ลบหลักสูตร {$snapshot['name']}",
            );

            $message = 'ลบหลักสูตรเรียบร้อยแล้ว';
            if ($deletedCourseCount > 0) {
                $message .= " (ลบรายวิชาในหลักสูตรออก {$deletedCourseCount} วิชา)";
            }

            return $this->redirectToMasterData('curriculums')->with('success', $message);
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->back()->with('error', 'ไม่สามารถลบได้เนื่องจากมีข้อมูลผูกพันอยู่');
        }
    }

    // ── Student Cohorts (กลุ่มชั้นปี — V2) ─────────────────────────────

    /**
     * Validation rules สำหรับกลุ่มชั้นปี
     * cohort ใช้เฉพาะหลักสูตร ป.ตรี ที่ใช้ระบบชั้นปี (uses_year_level=true)
     */
    private function studentCohortValidationRules(Request $request, ?StudentCohort $cohort = null): array
    {
        $curriculum = Curriculum::find($request->input('curriculum_id'));
        $usesYear = (bool) ($curriculum?->uses_year_level ?? false);
        $maxYear = $curriculum?->duration_years ?? 8;

        // หลักสูตรที่ไม่ใช้ระบบชั้นปี (ป.โท/ป.เอก) → year_level = null → uniqueness บน (curriculum, code)
        $yearLevel = $usesYear ? $request->input('year_level') : null;
        $uniqueRule = Rule::unique('student_cohorts')
            ->where(fn ($q) => $q
                ->where('curriculum_id', $request->input('curriculum_id'))
                ->where('year_level', $yearLevel));
        if ($cohort) {
            $uniqueRule->ignore($cohort->id);
        }

        // กลุ่มใหญ่ของหลักสูตรชั้นปี (ป.ตรี) = ตัวอักษรล้วน (A, B) · กลุ่มย่อย (มี parent) = อนุญาตตัวเลข (A1)
        // หลักสูตรไม่ใช้ชั้นปี (ป.โท/เอก) = ยืดหยุ่น (เช่น "รุ่น 1")
        $isSubgroup = $cohort && $cohort->parent_id !== null;
        $codeRule = ['required', 'string', 'max:50'];
        if ($usesYear && ! $isSubgroup) {
            $codeRule[] = 'regex:/^[\p{L}]+$/u'; // ตัวอักษรล้วน (ไทย/อังกฤษ) ไม่มีตัวเลข
        }
        $codeRule[] = $uniqueRule;

        return [
            'curriculum_id' => ['required', Rule::exists('curriculums', 'id')],
            'year_level' => $usesYear
                ? ['required', 'integer', 'min:1', 'max:' . $maxYear]
                : ['nullable'],
            'code' => $codeRule,
            'student_count' => ['required', 'integer', 'min:0', 'max:9999'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function studentCohortValidationMessages(): array
    {
        return [
            'curriculum_id.required' => 'กรุณาเลือกหลักสูตร',
            'curriculum_id.exists' => 'ไม่พบหลักสูตรที่เลือก',
            'year_level.required' => 'กรุณาเลือกชั้นปี',
            'year_level.max' => 'ชั้นปีต้องไม่เกินจำนวนปีของหลักสูตร',
            'code.required' => 'กรุณาระบุรหัสกลุ่ม',
            'code.regex' => 'รหัสกลุ่มใหญ่ต้องเป็นตัวอักษรเท่านั้น (ไม่มีตัวเลข) เช่น A, B',
            'code.unique' => 'มีรหัสกลุ่มนี้ในชั้นปีเดียวกันของหลักสูตรนี้แล้ว',
            'student_count.required' => 'กรุณาระบุจำนวนนักศึกษา',
            'subgroups.*.student_count.required' => 'กรุณาระบุจำนวนนักศึกษาของกลุ่มย่อย',
            'subgroups.*.code.required' => 'กลุ่มย่อยต้องมีรหัส',
        ];
    }

    /**
     * หลักสูตรไม่ใช้ระบบชั้นปี → บังคับ year_level = null (ไม่สน input ที่ส่งมา)
     */
    private function normalizeCohortYearLevel(array $validated): array
    {
        $curriculum = Curriculum::find($validated['curriculum_id']);
        if (! ($curriculum?->uses_year_level)) {
            $validated['year_level'] = null;
        }
        return $validated;
    }

    public function storeStudentCohort(Request $request)
    {
        // กลุ่มใหญ่ + กลุ่มย่อย (optional) ในฟอร์มเดียว
        $rules = $this->studentCohortValidationRules($request);
        $rules['subgroups'] = ['nullable', 'array'];
        $rules['subgroups.*.code'] = ['required', 'string', 'max:50', 'distinct'];
        $rules['subgroups.*.student_count'] = ['required', 'integer', 'min:0', 'max:9999'];

        $validated = $this->normalizeCohortYearLevel(
            $request->validate($rules, $this->studentCohortValidationMessages())
        );

        $subgroups = $validated['subgroups'] ?? [];
        unset($validated['subgroups']);
        $validated['parent_id'] = null;

        // กันรหัสกลุ่มย่อยซ้ำกับกลุ่มใหญ่ หรือซ้ำกับที่มีอยู่ในชั้นปีเดียวกัน
        $subCodes = array_map(static fn ($s) => $s['code'], $subgroups);
        if (in_array($validated['code'], $subCodes, true)) {
            return back()->withInput()->withErrors(['code' => 'รหัสกลุ่มย่อยซ้ำกับกลุ่มใหญ่']);
        }
        if (! empty($subCodes)) {
            $existing = StudentCohort::where('curriculum_id', $validated['curriculum_id'])
                ->where('year_level', $validated['year_level'])
                ->whereIn('code', $subCodes)
                ->pluck('code')->all();
            if (! empty($existing)) {
                return back()->withInput()->withErrors(['code' => 'รหัสกลุ่มย่อยซ้ำกับที่มีอยู่แล้ว: ' . implode(', ', $existing)]);
            }
        }

        $cohort = DB::transaction(function () use ($validated, $subgroups) {
            $major = StudentCohort::create($validated);
            foreach ($subgroups as $sg) {
                StudentCohort::create([
                    'curriculum_id' => $major->curriculum_id,
                    'parent_id'     => $major->id,
                    'year_level'    => $major->year_level,
                    'code'          => $sg['code'],
                    'student_count' => $sg['student_count'],
                ]);
            }
            return $major;
        });

        $this->logMasterDataCreate(
            'student_cohorts',
            $cohort->id,
            $this->auditSnapshot($cohort, ['curriculum_id', 'year_level', 'code', 'student_count']),
            'สร้างกลุ่ม ' . $this->cohortLabel($cohort) . (count($subgroups) ? ' (+' . count($subgroups) . ' กลุ่มย่อย)' : ''),
        );

        return $this->redirectToMasterData('student_cohorts')->with('success', 'เพิ่มกลุ่มชั้นปีเรียบร้อยแล้ว');
    }

    private function cohortLabel(StudentCohort $cohort): string
    {
        return $cohort->code . ($cohort->year_level ? " (ปี {$cohort->year_level})" : '');
    }

    public function updateStudentCohort(Request $request, StudentCohort $studentCohort)
    {
        $validated = $this->normalizeCohortYearLevel($request->validate(
            $this->studentCohortValidationRules($request, $studentCohort),
            $this->studentCohortValidationMessages()
        ));

        $fields = ['curriculum_id', 'year_level', 'code', 'student_count', 'note'];
        $before = $this->auditSnapshot($studentCohort, $fields);

        $studentCohort->update($validated);

        $after = $this->auditSnapshot($studentCohort->fresh(), $fields);
        [$oldValues, $newValues] = $this->auditDiff($before, $after);
        $this->logMasterDataUpdate(
            'student_cohorts',
            $studentCohort->id,
            $oldValues,
            $newValues,
            'แก้ไขกลุ่ม ' . $this->cohortLabel($studentCohort),
        );

        return $this->redirectToMasterData('student_cohorts')->with('success', 'อัปเดตกลุ่มชั้นปีเรียบร้อยแล้ว');
    }

    public function destroyStudentCohort(Request $request, StudentCohort $studentCohort)
    {
        $snapshot = $this->auditSnapshot($studentCohort, ['curriculum_id', 'year_level', 'code', 'student_count']);
        $id = $studentCohort->id;
        $label = $this->cohortLabel($studentCohort);
        $subCount = $studentCohort->subgroups()->count();

        // ลบกลุ่มใหญ่ → ลบกลุ่มย่อยที่สังกัดด้วย
        DB::transaction(function () use ($studentCohort) {
            $studentCohort->subgroups()->delete();
            $studentCohort->delete();
        });

        $this->logMasterDataDelete(
            'student_cohorts',
            $id,
            $snapshot,
            null,
            "ลบกลุ่มชั้นปี {$label}" . ($subCount ? " (+{$subCount} กลุ่มย่อย)" : ''),
        );

        return $this->redirectToMasterData('student_cohorts')->with('success', 'ลบกลุ่มชั้นปีเรียบร้อยแล้ว');
    }

    // ── Activity Types ────────────────────────────────────────────────

    public function storeActivityType(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100|unique:activity_types,name',
            'color_code' => 'required|string|max:10',
            'category'   => 'required|in:lecture,practicum,thesis,other',
        ]);
        $validated['counts_toward_workload'] = $request->boolean('counts_toward_workload');
        $activityType = ActivityType::create($validated);

        $this->logMasterDataCreate(
            'activity_types',
            $activityType->id,
            $this->auditSnapshot($activityType, ['name', 'color_code', 'category', 'counts_toward_workload']),
            "สร้างประเภทกิจกรรม {$activityType->name}",
        );

        return $this->redirectToMasterData('activity_types')->with('success', 'เพิ่มประเภทกิจกรรมเรียบร้อยแล้ว');
    }

    public function updateActivityType(Request $request, ActivityType $activityType)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100|unique:activity_types,name,' . $activityType->id,
            'color_code' => 'required|string|max:10',
            'category'   => 'required|in:lecture,practicum,thesis,other',
        ]);
        $validated['counts_toward_workload'] = $request->boolean('counts_toward_workload');
        $fields = ['name', 'color_code', 'category', 'counts_toward_workload'];
        $before = $this->auditSnapshot($activityType, $fields);

        $activityType->update($validated);

        $after = $this->auditSnapshot($activityType->fresh(), $fields);
        [$oldValues, $newValues] = $this->auditDiff($before, $after);
        $this->logMasterDataUpdate(
            'activity_types',
            $activityType->id,
            $oldValues,
            $newValues,
            "แก้ไขประเภทกิจกรรม {$activityType->name}",
        );

        return $this->redirectToMasterData('activity_types')->with('success', 'อัปเดตประเภทกิจกรรมเรียบร้อยแล้ว');
    }

    public function destroyActivityType(ActivityType $activityType)
    {
        $snapshot = $this->auditSnapshot($activityType, ['name', 'color_code', 'category']);
        $activityTypeId = $activityType->id;

        try {
            $activityType->delete();

            $this->logMasterDataDelete(
                'activity_types',
                $activityTypeId,
                $snapshot,
                null,
                "ลบประเภทกิจกรรม {$snapshot['name']}",
            );

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
            'default_year_level.required' => 'หลักสูตรนี้ใช้ระบบชั้นปี กรุณาระบุชั้นปีตามแผน',
            'default_year_level.max'      => 'ชั้นปีตามแผนต้องไม่เกินจำนวนปีของหลักสูตร',
            'is_required.required'        => 'กรุณาระบุว่าเป็นวิชาบังคับหรือวิชาเลือก',
        ];
    }

    private function curriculumValidationRules(Request $request, ?Curriculum $existing = null): array
    {
        $uniqueRule = Rule::unique('curriculums', 'name')
            ->where(fn($query) => $query->where('effective_year', $request->input('effective_year')));
        if ($existing) {
            $uniqueRule->ignore($existing->id);
        }

        $usesYearLevel = filter_var($request->input('uses_year_level'), FILTER_VALIDATE_BOOLEAN);

        return [
            'name'                   => ['required', 'string', 'max:255', $uniqueRule],
            'effective_year'         => 'required|integer',
            'education_level'        => 'required|in:bachelor,master,doctorate',
            'uses_year_level'        => 'required|boolean',
            'duration_years'         => $usesYearLevel
                ? ['required', 'integer', 'min:1', 'max:10']
                : ['nullable', 'integer', 'min:1', 'max:10'],
            'total_credits_required' => $usesYearLevel
                ? ['nullable', 'integer', 'min:0', 'max:9999']
                : ['required', 'integer', 'min:1', 'max:9999'],
            'counts_service_only'    => 'required|boolean',
            'is_active'              => 'required|boolean',
        ];
    }

    private function curriculumValidationMessages(): array
    {
        return [
            'name.unique' => 'มีหลักสูตรนี้ในปีที่เริ่มใช้นี้อยู่ในระบบแล้ว',
            'name.required' => 'กรุณากรอกชื่อหลักสูตร',
            'effective_year.required' => 'กรุณากรอกปีที่เริ่มใช้หลักสูตร',
            'education_level.required' => 'กรุณาเลือกระดับการศึกษา',
            'education_level.in' => 'ระดับการศึกษาต้องเป็น ป.ตรี / ป.โท / ป.เอก',
            'duration_years.required' => 'กรุณาระบุจำนวนปีของหลักสูตร',
            'duration_years.min' => 'จำนวนปีของหลักสูตรต้องอย่างน้อย 1 ปี',
            'uses_year_level.required' => 'กรุณาเลือกรูปแบบการจัดชั้นปี',
            'total_credits_required.required' => 'หลักสูตรที่ไม่ใช้ระบบชั้นปีต้องระบุหน่วยกิตขั้นต่ำเพื่อใช้เป็นเงื่อนไขจบการศึกษา',
            'total_credits_required.min' => 'หน่วยกิตขั้นต่ำต้องอย่างน้อย 1 หน่วยกิต',
        ];
    }

    public function importRooms(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|extensions:csv,txt|max:5120']);

        $file   = $request->file('csv_file');
        $handle = $this->openCsvHandle($file);

        $requiredHeaders = ['room_code', 'room_name', 'location_type_name'];
        $header = $this->readCsvHeader($handle, $requiredHeaders);
        if (!$header) {
            fclose($handle);
            return back()->with('error', 'ไฟล์ CSV ว่างเปล่า');
        }
        $header = $this->normalizeCsvHeader($header);
        $missing = $this->missingCsvHeaders($header, $requiredHeaders);
        if ($missing) {
            fclose($handle);
            return back()->with('error', 'หัวไฟล์ CSV ไม่ครบ: ' . implode(', ', $missing));
        }

        $locationTypes = LocationType::pluck('id', 'name')->toArray();
        $updateOnDup   = $request->boolean('update_on_duplicate');
        $successCount  = 0;
        $createdCount  = 0;
        $updatedCount  = 0;
        $sampleRoomCodes = [];
        $errors        = [];
        $row           = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if (!$this->csvRowHasData($data)) continue;
            if ($this->isCsvCommentRow($data)) continue;

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
                $exists ? $updatedCount++ : $createdCount++;
                if (count($sampleRoomCodes) < 5) {
                    $sampleRoomCodes[] = $roomCode;
                }
            } catch (\Exception $e) {
                $errors[] = "แถว {$row}: เกิดข้อผิดพลาด — " . $e->getMessage();
            }
        }

        fclose($handle);

        $failCount = count($errors);
        $msg = $failCount > 0
            ? "นำเข้าสำเร็จ {$successCount} ห้อง, ข้าม {$failCount} แถว — ดูรายละเอียดด้านล่างขวา"
            : "นำเข้าสำเร็จทั้งหมด {$successCount} ห้อง";

        if ($successCount > 0) {
            AuditLogger::log(
                action: 'ข้อมูลหลัก.นำเข้า CSV',
                table: 'rooms',
                recordId: 0,
                oldValues: null,
                newValues: [
                    'success_count' => $successCount,
                    'created_count' => $createdCount,
                    'updated_count' => $updatedCount,
                    'error_count' => $failCount,
                    'update_on_duplicate' => $updateOnDup,
                    'sample_room_codes' => $sampleRoomCodes,
                ],
                category: 'ข้อมูลหลัก',
                description: "นำเข้าห้อง/สถานที่จาก CSV สำเร็จ {$successCount} ห้อง",
            );
        }

        $redirect = $this->redirectToMasterData('location_types')->with('success', $msg);
        if ($errors) $redirect->with('import_errors', $errors);
        return $redirect;
    }

    public function importCourses(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|extensions:csv,txt|max:5120']);

        $file   = $request->file('csv_file');
        $handle = $this->openCsvHandle($file);

        $requiredHeaders = ['course_code', 'name_th', 'curriculum_name', 'credits'];
        $header = $this->readCsvHeader($handle, $requiredHeaders);
        if (!$header) {
            fclose($handle);
            return back()->with('error', 'ไฟล์ CSV ว่างเปล่า');
        }
        $header = $this->normalizeCsvHeader($header);
        $missing = $this->missingCsvHeaders($header, $requiredHeaders);
        if ($missing) {
            fclose($handle);
            return back()->with('error', 'หัวไฟล์ CSV ไม่ครบ: ' . implode(', ', $missing));
        }

        $curriculumModels = Curriculum::all()->keyBy('id');
        $curriculums    = $curriculumModels->mapWithKeys(fn($c) => [$c->name => $c->id])->toArray();
        $departments    = Department::pluck('id', 'name')->toArray();
        $employeeIds    = User::whereNotNull('employee_id')->pluck('id', 'employee_id')->toArray();
        $updateOnDup    = $request->boolean('update_on_duplicate');
        $successCount = 0;
        $createdCount = 0;
        $updatedCount = 0;
        $sampleCourseCodes = [];
        $errors       = [];
        $row          = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if (!$this->csvRowHasData($data)) continue;
            if ($this->isCsvCommentRow($data)) continue;

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
            $curriculumModel = $curriculumModels->get($currId);
            $usesYearLevel = $curriculumModel ? (bool) $curriculumModel->uses_year_level : true;
            $maxYearForCurriculum = $curriculumModel ? max(1, (int) $curriculumModel->duration_years) : 4;

            // V2: ตัด default_semester — วิชาเปิดทั้งปี
            if ($usesYearLevel && $yearLevel === '') {
                $errors[] = "แถว {$row}: หลักสูตร '{$currName}' ใช้ระบบชั้นปี ต้องระบุ default_year_level";
                continue;
            }
            if ($yearLevel !== '' && ((int) $yearLevel < 1 || (int) $yearLevel > $maxYearForCurriculum)) {
                $errors[] = "แถว {$row}: default_year_level ต้องอยู่ระหว่าง 1 ถึง {$maxYearForCurriculum} ตามจำนวนปีของหลักสูตร";
                continue;
            }
            $yearLevelValue = ($usesYearLevel && $yearLevel !== '') ? (int) $yearLevel : null;

            $courseType = trim($csv['course_type'] ?? '') ?: 'theory';
            if (!in_array($courseType, ['theory', 'practicum', 'theory_practicum'], true)) {
                $errors[] = "แถว {$row}: course_type ต้องเป็น theory, practicum หรือ theory_practicum";
                continue;
            }

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
            $isRequiredRaw = trim($csv['is_required'] ?? '');
            $isRequired = $isRequiredRaw === '' ? true : in_array(strtolower($isRequiredRaw), ['1', 'true', 'yes', 'y']);

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
                        'default_year_level'          => $yearLevelValue,
                        'is_required'                 => $isRequired,
                        'color_code'                  => $colorCode,
                        'status'                      => $status,
                    ]
                );
                $successCount++;
                $exists ? $updatedCount++ : $createdCount++;
                if (count($sampleCourseCodes) < 5) {
                    $sampleCourseCodes[] = $code;
                }
            } catch (\Exception $e) {
                $errors[] = "แถว {$row}: เกิดข้อผิดพลาด — " . $e->getMessage();
            }
        }

        fclose($handle);

        $failCount = count($errors);
        $msg = $failCount > 0
            ? "นำเข้าสำเร็จ {$successCount} วิชา, ข้าม {$failCount} แถว — ดูรายละเอียดด้านล่างขวา"
            : "นำเข้าสำเร็จทั้งหมด {$successCount} วิชา";

        if ($successCount > 0) {
            AuditLogger::log(
                action: 'ข้อมูลหลัก.นำเข้า CSV',
                table: 'courses',
                recordId: 0,
                oldValues: null,
                newValues: [
                    'success_count' => $successCount,
                    'created_count' => $createdCount,
                    'updated_count' => $updatedCount,
                    'error_count' => $failCount,
                    'update_on_duplicate' => $updateOnDup,
                    'sample_course_codes' => $sampleCourseCodes,
                ],
                category: 'ข้อมูลหลัก',
                description: "นำเข้ารายวิชาจาก CSV สำเร็จ {$successCount} วิชา",
            );
        }

        $redirect = $this->redirectToMasterData('courses')->with('success', $msg);
        if ($errors) $redirect->with('import_errors', $errors);
        return $redirect;
    }
}
