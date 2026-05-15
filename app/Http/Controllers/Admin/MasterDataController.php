<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\ActivityType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MasterDataController extends Controller
{
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

        // Courses with curriculum and head instructor
        $courses = Course::with(['curriculum', 'department', 'headInstructor', 'assignedStaff'])->get();

        // Curriculums with course count and courses list
        $curriculums = Curriculum::withCount('courses')->with(['courses' => fn($q) => $q->orderBy('course_code')])->get();

        // Activity Types
        $activityTypes = ActivityType::orderBy('name')->get();

        // Pre-flight counts for import warnings
        $usersWithEmployeeIdCount = User::whereNotNull('employee_id')->where('employee_id', '!=', '')->count();

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
            'isAdmin',
            'routePrefix',
            'usersWithEmployeeIdCount'
        ));
    }

    private function masterDataRouteName(): string
    {
        return session('active_role') === 'staff' ? 'staff.master_data' : 'admin.master_data';
    }

    private function redirectToMasterData(string $tab): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route($this->masterDataRouteName(), ['tab' => $tab]);
    }

    public function storeLocationType(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:location_types,name',
        ]);

        LocationType::create($validated);

        return $this->redirectToMasterData('location_types')->with('success', 'เพิ่มประเภทสถานที่เรียบร้อยแล้ว');
    }

    public function updateLocationType(Request $request, LocationType $locationType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:location_types,name,' . $locationType->id,
        ]);

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

        // Block: same person as both head and secretary
        if ($newHeadId && $newSecId && (int)$newHeadId === (int)$newSecId) {
            return back()->withErrors(['head_user_id' => 'ผู้เดียวกันไม่สามารถเป็นทั้งหัวหน้าและเลขานุการได้']);
        }

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

        return redirect()->back()->with('success', 'อัปเดตภาควิชาเรียบร้อยแล้ว');
    }

    public function updateInstructor(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $profile = $user->instructorProfile;

        $title  = $request->input('title', '');
        $degree = $request->input('academic_degree', '');

        $paRules = match(true) {
            $title === 'ผู้ช่วยอาจารย์ (คลินิก)'                          => ['teaching'=>[0,10],  'research'=>[0,5],   'service'=>[70,80], 'culture'=>[0,15], 'other'=>[0,20]],
            $title === 'ผู้ช่วยอาจารย์ (สอนภาคปฏิบัติ)'                   => ['teaching'=>[0,70],  'research'=>[0,0],   'service'=>[5,20],  'culture'=>[0,15], 'other'=>[0,20]],
            $title === 'ผู้ช่วยอาจารย์' && $degree === 'ปริญญาตรี'         => ['teaching'=>[30,60], 'research'=>[0,0],   'service'=>[10,30], 'culture'=>[0,15], 'other'=>[0,20]],
            str_contains($title, 'ผู้ช่วยอาจารย์')                         => ['teaching'=>[0,70],  'research'=>[15,20], 'service'=>[5,20],  'culture'=>[0,15], 'other'=>[0,20]],
            default                                                         => ['teaching'=>[20,70], 'research'=>[20,70], 'service'=>[5,20],  'culture'=>[5,15], 'other'=>[0,20]],
        };

        $request->validate([
            'name'            => 'required|string|max:255',
            'prefix'          => 'required|string|max:50',
            'employee_id'     => 'nullable|string|max:50|unique:users,employee_id,' . $user->id,
            'department_id'   => 'required|integer|exists:departments,id',
            'title'           => 'required|string|max:100',
            'academic_degree' => 'required|string|max:100',
            'employment_type' => 'required|string|max:100',
            'hired_at'        => 'nullable|date',
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
                'teaching_pct'    => $request->teaching_pct,
                'research_pct'    => $request->research_pct,
                'service_pct'     => $request->service_pct,
                'culture_pct'     => $request->culture_pct,
                'other_pct'       => $request->other_pct,
            ]);
        }

        return redirect()->back()->with('success', 'อัปเดตข้อมูลอาจารย์เรียบร้อยแล้ว');
    }

    public function storeCourse(Request $request)
    {
        $validated = $request->validate([
            'course_code'                 => [
                'required', 'string', 'max:20',
                Rule::unique('courses', 'course_code')
                    ->where(fn($q) => $q->where('curriculum_id', $request->input('curriculum_id'))),
            ],
            'name_th'                     => 'required|string|max:255',
            'name_en'                     => 'nullable|string|max:255',
            'curriculum_id'               => 'required|exists:curriculums,id',
            'department_id'               => 'nullable|exists:departments,id',
            'head_instructor_id'          => 'required|exists:users,id',
            'staff_ids'                   => 'nullable|array',
            'staff_ids.*'                 => 'exists:users,id',
            'course_type'                 => 'required|in:theory,practicum,theory_practicum',
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
        ]);

        $validated['requires_practicum_rotation'] = $request->boolean('requires_practicum_rotation');
        $staffIds = $validated['staff_ids'] ?? [];
        unset($validated['staff_ids']);

        $course = Course::create($validated);
        $course->assignedStaff()->sync($staffIds);

        return $this->redirectToMasterData('courses')->with('success', 'เพิ่มรายวิชาเรียบร้อยแล้ว');
    }

    public function updateCourse(Request $request, Course $course)
    {
        $validated = $request->validate([
            'course_code'                 => [
                'required', 'string', 'max:20',
                Rule::unique('courses', 'course_code')
                    ->where(fn($q) => $q->where('curriculum_id', $request->input('curriculum_id')))
                    ->ignore($course->id),
            ],
            'name_th'                     => 'required|string|max:255',
            'name_en'                     => 'nullable|string|max:255',
            'curriculum_id'               => 'required|exists:curriculums,id',
            'department_id'               => 'nullable|exists:departments,id',
            'head_instructor_id'          => 'required|exists:users,id',
            'staff_ids'                   => 'nullable|array',
            'staff_ids.*'                 => 'exists:users,id',
            'course_type'                 => 'required|in:theory,practicum,theory_practicum',
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
        ]);

        $validated['requires_practicum_rotation'] = $request->boolean('requires_practicum_rotation');
        $staffIds = $validated['staff_ids'] ?? [];
        unset($validated['staff_ids']);

        $course->update($validated);
        $course->assignedStaff()->sync($staffIds);

        return $this->redirectToMasterData('courses')->with('success', 'อัปเดตข้อมูลรายวิชาเรียบร้อยแล้ว');
    }

    public function storeCurriculum(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:curriculums,name',
            'effective_year' => 'required|integer',
            'is_active' => 'required|boolean'
        ]);

        Curriculum::create($validated);

        return $this->redirectToMasterData('curriculums')->with('success', 'เพิ่มหลักสูตรเรียบร้อยแล้ว');
    }

    public function updateCurriculum(Request $request, Curriculum $curriculum)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:curriculums,name,' . $curriculum->id,
            'effective_year' => 'required|integer',
            'is_active' => 'required|boolean'
        ]);

        $curriculum->update($validated);

        return $this->redirectToMasterData('curriculums')->with('success', 'อัปเดตข้อมูลหลักสูตรเรียบร้อยแล้ว');
    }

    public function cloneCurriculum(Request $request, Curriculum $curriculum)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:curriculums,name',
            'effective_year' => 'required|integer'
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

    public function destroyCurriculum(Curriculum $curriculum)
    {
        try {
            $curriculum->delete();
            return $this->redirectToMasterData('curriculums')->with('success', 'ลบหลักสูตรเรียบร้อยแล้ว');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->back()->with('error', 'ไม่สามารถลบได้เนื่องจากมีรายวิชาผูกอยู่ กรุณาลบวิชาในหลักสูตรนี้ออกก่อน');
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
            $courseType = trim($csv['course_type'] ?? '');

            if (!$code || !$nameTh || !$currName || $credits === '' || !$headEmpId || !$courseType) {
                $errors[] = "แถว {$row}: ข้อมูลบังคับไม่ครบ (course_code, name_th, curriculum_name, credits, head_instructor_employee_id, course_type)";
                continue;
            }

            if (!in_array($courseType, ['theory', 'practicum', 'theory_practicum'])) {
                $errors[] = "แถว {$row}: course_type ต้องเป็น theory, practicum หรือ theory_practicum";
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

            // requires_practicum_rotation บังคับสำหรับ practicum / theory_practicum
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
