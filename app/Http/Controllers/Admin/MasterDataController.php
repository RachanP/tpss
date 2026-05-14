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

class MasterDataController extends Controller
{
    public function index()
    {
        // View-only for instructors: get users who have 'instructor' role
        $instructors = User::whereHas('roles', function($q) {
            $q->where('role', 'instructor');
        })->with(['instructorProfile.department', 'roles'])->get();

        // Departments with count of instructors
        $departments = Department::with(['head', 'secretary'])
            ->withCount('instructorProfiles as instructors_count')
            ->get();

        // Active users for head/secretary dropdown
        $users = User::with('instructorProfile')->where('is_active', true)->orderBy('name')->get();

        // Location Types
        $locationTypes = LocationType::withCount('rooms')->get();

        // Rooms with their types
        $rooms = Room::with('locationType')->get();

        // Staff users for assigned_staff dropdown
        $staffUsers = User::whereHas('roles', function ($q) {
            $q->where('role', 'staff');
        })->with('instructorProfile')->where('is_active', true)->orderBy('name')->get();

        // Courses with curriculum and head instructor
        $courses = Course::with(['curriculum', 'department', 'headInstructor', 'assignedStaff'])->get();

        // Curriculums with course count
        $curriculums = Curriculum::withCount('courses')->get();

        // Activity Types
        $activityTypes = ActivityType::orderBy('name')->get();

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
            'routePrefix'
        ));
    }

    public function storeLocationType(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:location_types,name',
        ]);

        LocationType::create($validated);

        return redirect()->route('admin.master_data', ['tab' => 'location_types'])->with('success', 'เพิ่มประเภทสถานที่เรียบร้อยแล้ว');
    }

    public function updateLocationType(Request $request, LocationType $locationType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:location_types,name,' . $locationType->id,
        ]);

        $locationType->update($validated);

        return redirect()->route('admin.master_data', ['tab' => 'location_types'])->with('success', 'อัปเดตประเภทสถานที่เรียบร้อยแล้ว');
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

        return redirect()->route('admin.master_data', ['tab' => 'rooms'])->with('success', 'เพิ่มห้อง/สถานที่เรียบร้อยแล้ว');
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

        return redirect()->route('admin.master_data', ['tab' => 'rooms'])->with('success', 'อัปเดตห้อง/สถานที่เรียบร้อยแล้ว');
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

        $request->validate([
            'name'            => 'required|string|max:255',
            'prefix'          => 'required|string|max:50',
            'employee_id'     => 'nullable|string|max:50|unique:users,employee_id,' . $user->id,
            'department_id'   => 'required|integer|exists:departments,id',
            'title'           => 'required|string|max:100',
            'academic_degree' => 'required|string|max:100',
            'employment_type' => 'required|string|max:100',
            'teaching_pct'    => 'required|integer|min:0|max:100',
        ]);

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
                'teaching_pct'    => $request->teaching_pct,
            ]);
        }

        return redirect()->back()->with('success', 'อัปเดตข้อมูลอาจารย์เรียบร้อยแล้ว');
    }

    public function storeCourse(Request $request)
    {
        $validated = $request->validate([
            'course_code' => 'required|string|max:20|unique:courses,course_code',
            'name_th' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'curriculum_id' => 'required|exists:curriculums,id',
            'department_id' => 'required|exists:departments,id',
            'head_instructor_id' => 'nullable|exists:users,id',
            'staff_ids' => 'nullable|array',
            'staff_ids.*' => 'exists:users,id',
            'academic_level' => 'nullable|in:undergraduate,graduate',
            'default_year_level' => 'nullable|integer|min:1|max:4',
            'default_semester' => 'nullable|integer|min:1|max:3',
            'credits' => 'required|integer|min:0',
            'lecture_hours' => 'nullable|integer|min:0',
            'lab_hours' => 'nullable|integer|min:0',
            'self_study_hours' => 'nullable|integer|min:0',
            'capacity' => 'nullable|integer|min:1',
            'color_code' => 'nullable|string|max:7',
            'status' => 'required|in:active,inactive',
            'requires_practicum_rotation' => 'nullable|boolean'
        ]);

        $lecture = $validated['lecture_hours'] ?? 0;
        $lab = $validated['lab_hours'] ?? 0;
        if ($lecture > 0 && $lab > 0) {
            $validated['course_type'] = 'theory_practicum';
        } elseif ($lecture == 0 && $lab > 0) {
            $validated['course_type'] = 'practicum';
        } else {
            $validated['course_type'] = 'theory';
        }

        $validated['requires_practicum_rotation'] = $request->has('requires_practicum_rotation');
        $staffIds = $validated['staff_ids'] ?? [];
        unset($validated['staff_ids']);

        $course = Course::create($validated);
        $course->assignedStaff()->sync($staffIds);

        return redirect()->route('admin.master_data', ['tab' => 'courses'])->with('success', 'เพิ่มรายวิชาเรียบร้อยแล้ว');
    }

    public function updateCourse(Request $request, Course $course)
    {
        $validated = $request->validate([
            'course_code' => 'required|string|max:20|unique:courses,course_code,' . $course->id,
            'name_th' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'curriculum_id' => 'required|exists:curriculums,id',
            'department_id' => 'required|exists:departments,id',
            'head_instructor_id' => 'nullable|exists:users,id',
            'staff_ids' => 'nullable|array',
            'staff_ids.*' => 'exists:users,id',
            'course_type' => 'required|in:theory,practicum,theory_practicum',
            'academic_level' => 'nullable|in:undergraduate,graduate',
            'default_year_level' => 'nullable|integer|min:1|max:4',
            'default_semester' => 'nullable|integer|min:1|max:3',
            'credits' => 'required|integer|min:0',
            'lecture_hours' => 'nullable|integer|min:0',
            'lab_hours' => 'nullable|integer|min:0',
            'self_study_hours' => 'nullable|integer|min:0',
            'capacity' => 'nullable|integer|min:1',
            'color_code' => 'nullable|string|max:7',
            'status' => 'required|in:active,inactive',
            'requires_practicum_rotation' => 'nullable|boolean'
        ]);

        $validated['requires_practicum_rotation'] = $request->has('requires_practicum_rotation');
        $staffIds = $validated['staff_ids'] ?? [];
        unset($validated['staff_ids']);

        $course->update($validated);
        $course->assignedStaff()->sync($staffIds);

        return redirect()->route('admin.master_data', ['tab' => 'courses'])->with('success', 'อัปเดตข้อมูลรายวิชาเรียบร้อยแล้ว');
    }

    public function storeCurriculum(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'effective_year' => 'required|integer',
            'is_active' => 'required|boolean'
        ]);

        Curriculum::create($validated);

        return redirect()->route('admin.master_data', ['tab' => 'curriculums'])->with('success', 'เพิ่มหลักสูตรเรียบร้อยแล้ว');
    }

    public function updateCurriculum(Request $request, Curriculum $curriculum)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'effective_year' => 'required|integer',
            'is_active' => 'required|boolean'
        ]);

        $curriculum->update($validated);

        return redirect()->route('admin.master_data', ['tab' => 'curriculums'])->with('success', 'อัปเดตข้อมูลหลักสูตรเรียบร้อยแล้ว');
    }

    public function cloneCurriculum(Request $request, Curriculum $curriculum)
    {
        // Business Rule: Can only clone inactive curriculums
        if ($curriculum->is_active) {
            return redirect()->back()->with('error', 'ไม่สามารถคัดลอกหลักสูตรที่กำลังเปิดใช้งานอยู่ได้ กรุณาปิดการใช้งานหลักสูตรต้นฉบับก่อน');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'effective_year' => 'required|integer'
        ]);

        // Create new curriculum
        $newCurriculum = Curriculum::create([
            'name' => $validated['name'],
            'effective_year' => $validated['effective_year'],
            'is_active' => true // Default to active
        ]);

        // Clone all courses
        $courses = $curriculum->courses;
        foreach ($courses as $course) {
            $newCourse = $course->replicate();
            $newCourse->curriculum_id = $newCurriculum->id;
            
            // Database uses composite unique key ['course_code', 'curriculum_id']
            // So we can safely keep the exact same course_code.
            // head_instructor_id is copied automatically via replicate()
            
            $newCourse->save();
        }

        return redirect()->route('admin.master_data', ['tab' => 'curriculums'])->with('success', 'คัดลอกหลักสูตรและรายวิชาทั้งหมดเรียบร้อยแล้ว (' . $courses->count() . ' วิชา)');
    }

    public function destroyDepartment(Department $department)
    {
        try {
            $department->delete();
            return redirect()->route('admin.master_data', ['tab' => 'departments'])->with('success', 'ลบภาควิชาเรียบร้อยแล้ว');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->back()->with('error', 'ไม่สามารถลบได้เนื่องจากมีข้อมูลผูกพันอยู่ (เช่น มีอาจารย์หรือวิชาสังกัดอยู่)');
        }
    }

    public function destroyLocationType(LocationType $locationType)
    {
        // Release FK on rooms before deleting
        $affected = $locationType->rooms()->count();
        $locationType->rooms()->update(['location_type_id' => null]);
        $locationType->delete();

        $msg = 'ลบประเภทสถานที่เรียบร้อยแล้ว';
        if ($affected > 0) {
            $msg .= " (ยกเลิกการกำหนดประเภทจาก {$affected} ห้อง)";
        }
        return redirect()->route('admin.master_data', ['tab' => 'location_types'])->with('success', $msg);
    }

    public function destroyRoom(Room $room)
    {
        try {
            $room->delete();
            return redirect()->route('admin.master_data', ['tab' => 'rooms'])->with('success', 'ลบห้องเรียบร้อยแล้ว');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->back()->with('error', 'ไม่สามารถลบได้เนื่องจากมีข้อมูลผูกพันอยู่');
        }
    }

    public function destroyCourse(Course $course)
    {
        try {
            $course->delete();
            return redirect()->route('admin.master_data', ['tab' => 'courses'])->with('success', 'ลบรายวิชาเรียบร้อยแล้ว');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->back()->with('error', 'ไม่สามารถลบได้เนื่องจากมีข้อมูลการสอนผูกอยู่');
        }
    }

    public function destroyCurriculum(Curriculum $curriculum)
    {
        try {
            $curriculum->delete();
            return redirect()->route('admin.master_data', ['tab' => 'curriculums'])->with('success', 'ลบหลักสูตรเรียบร้อยแล้ว');
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
        return redirect()->route('admin.master_data', ['tab' => 'activity_types'])->with('success', 'เพิ่มประเภทกิจกรรมเรียบร้อยแล้ว');
    }

    public function updateActivityType(Request $request, ActivityType $activityType)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100|unique:activity_types,name,' . $activityType->id,
            'color_code' => 'required|string|max:10',
            'category'   => 'required|in:lecture,practicum,thesis,other',
        ]);
        $activityType->update($validated);
        return redirect()->route('admin.master_data', ['tab' => 'activity_types'])->with('success', 'อัปเดตประเภทกิจกรรมเรียบร้อยแล้ว');
    }

    public function destroyActivityType(ActivityType $activityType)
    {
        try {
            $activityType->delete();
            return redirect()->route('admin.master_data', ['tab' => 'activity_types'])->with('success', 'ลบประเภทกิจกรรมเรียบร้อยแล้ว');
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->back()->with('error', 'ไม่สามารถลบได้เนื่องจากมีกิจกรรมผูกอยู่กับประเภทนี้');
        }
    }

    // ── CSV Import ────────────────────────────────────────────────────

    public function importRooms(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt|max:5120']);

        $file   = $request->file('csv_file');
        $handle = fopen($file->getPathname(), 'r');

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return back()->with('error', 'ไฟล์ CSV ว่างเปล่า');
        }
        $header = array_map(fn($h) => trim(str_replace("\xEF\xBB\xBF", '', $h)), $header);

        $locationTypes = LocationType::pluck('id', 'name')->toArray();
        $updateOnDup   = $request->boolean('update_on_duplicate');
        $successCount  = 0;
        $errors        = [];
        $row           = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if (count(array_filter($data)) === 0) continue;

            $csv    = array_combine($header, array_pad($data, count($header), ''));
            $name   = trim($csv['name'] ?? '');
            $code   = trim($csv['code'] ?? '');
            $ltName = trim($csv['location_type_name'] ?? '');

            if (!$name || !$code || !$ltName) {
                $errors[] = "แถว {$row}: ข้อมูลบังคับไม่ครบ (name, code, location_type_name)";
                continue;
            }

            $ltId = $locationTypes[$ltName] ?? null;
            if (!$ltId) {
                $errors[] = "แถว {$row}: ประเภทสถานที่ '{$ltName}' ไม่พบในระบบ";
                continue;
            }

            $exists = Room::where('room_code', $code)->exists();
            if ($exists && !$updateOnDup) {
                $errors[] = "แถว {$row}: code '{$code}' มีในระบบแล้ว — ข้ามแถวนี้";
                continue;
            }

            $status   = in_array(trim($csv['status'] ?? ''), ['active', 'inactive', 'maintenance'])
                ? trim($csv['status'])
                : 'active';
            $capacity = (int)(trim($csv['capacity'] ?? '') ?: '0') ?: null;
            $building = trim($csv['building'] ?? '') ?: null;

            try {
                Room::updateOrCreate(
                    ['room_code' => $code],
                    [
                        'room_name'        => $name,
                        'location_type_id' => $ltId,
                        'capacity'         => $capacity,
                        'building'         => $building,
                        'status'           => $status,
                    ]
                );
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = "แถว {$row}: เกิดข้อผิดพลาด — " . $e->getMessage();
            }
        }

        fclose($handle);

        $routePrefix = session('active_role') === 'admin' ? 'admin' : 'staff';
        $msg = "นำเข้าสำเร็จ {$successCount} ห้อง";
        if ($errors) {
            return redirect()->route("{$routePrefix}.master_data", ['tab' => 'rooms'])
                ->with('success', $msg)->with('import_errors', $errors);
        }
        return redirect()->route("{$routePrefix}.master_data", ['tab' => 'rooms'])->with('success', $msg);
    }

    public function importCourses(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt|max:5120']);

        $file   = $request->file('csv_file');
        $handle = fopen($file->getPathname(), 'r');

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return back()->with('error', 'ไฟล์ CSV ว่างเปล่า');
        }
        $header = array_map(fn($h) => trim(str_replace("\xEF\xBB\xBF", '', $h)), $header);

        $curriculums  = Curriculum::pluck('id', 'name')->toArray();
        $departments  = Department::pluck('id', 'name')->toArray();
        $updateOnDup  = $request->boolean('update_on_duplicate');
        $successCount = 0;
        $errors       = [];
        $row          = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $row++;
            if (count(array_filter($data)) === 0) continue;

            $csv      = array_combine($header, array_pad($data, count($header), ''));
            $code     = trim($csv['course_code'] ?? '');
            $nameTh   = trim($csv['name_th'] ?? '');
            $currName = trim($csv['curriculum_name'] ?? '');
            $deptName = trim($csv['department_name'] ?? '');
            $credits  = trim($csv['credits'] ?? '');

            if (!$code || !$nameTh || !$currName || !$deptName || $credits === '') {
                $errors[] = "แถว {$row}: ข้อมูลบังคับไม่ครบ (course_code, name_th, curriculum_name, department_name, credits)";
                continue;
            }

            $currId = $curriculums[$currName] ?? null;
            if (!$currId) {
                $errors[] = "แถว {$row}: หลักสูตร '{$currName}' ไม่พบในระบบ";
                continue;
            }

            $deptId = $departments[$deptName] ?? null;
            if (!$deptId) {
                $errors[] = "แถว {$row}: ภาควิชา '{$deptName}' ไม่พบในระบบ";
                continue;
            }

            $exists = Course::where('course_code', $code)->where('curriculum_id', $currId)->exists();
            if ($exists && !$updateOnDup) {
                $errors[] = "แถว {$row}: course_code '{$code}' ในหลักสูตรนี้มีอยู่แล้ว — ข้ามแถวนี้";
                continue;
            }

            $lecture = (int)(trim($csv['lecture_hours'] ?? '0') ?: '0');
            $lab     = (int)(trim($csv['lab_hours'] ?? '0') ?: '0');
            if ($lecture > 0 && $lab > 0) {
                $courseType = 'theory_practicum';
            } elseif ($lecture == 0 && $lab > 0) {
                $courseType = 'practicum';
            } else {
                $courseType = 'theory';
            }

            $status      = in_array(trim($csv['status'] ?? ''), ['active', 'inactive']) ? trim($csv['status']) : 'active';
            $yearLevel   = (int)(trim($csv['default_year_level'] ?? '') ?: '0') ?: null;
            $semester    = (int)(trim($csv['default_semester'] ?? '') ?: '0') ?: null;
            $selfStudy   = (int)(trim($csv['self_study_hours'] ?? '0') ?: '0');

            try {
                Course::updateOrCreate(
                    ['course_code' => $code, 'curriculum_id' => $currId],
                    [
                        'name_th'            => $nameTh,
                        'name_en'            => trim($csv['name_en'] ?? '') ?: null,
                        'department_id'      => $deptId,
                        'credits'            => (int)$credits,
                        'lecture_hours'      => $lecture,
                        'lab_hours'          => $lab,
                        'self_study_hours'   => $selfStudy,
                        'default_year_level' => $yearLevel,
                        'default_semester'   => $semester,
                        'course_type'        => $courseType,
                        'status'             => $status,
                        'academic_level'     => 'undergraduate',
                        'capacity'           => (int)(trim($csv['capacity'] ?? '') ?: '0') ?: null,
                    ]
                );
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = "แถว {$row}: เกิดข้อผิดพลาด — " . $e->getMessage();
            }
        }

        fclose($handle);

        $routePrefix = session('active_role') === 'admin' ? 'admin' : 'staff';
        $msg = "นำเข้าสำเร็จ {$successCount} วิชา";
        if ($errors) {
            return redirect()->route("{$routePrefix}.master_data", ['tab' => 'courses'])
                ->with('success', $msg)->with('import_errors', $errors);
        }
        return redirect()->route("{$routePrefix}.master_data", ['tab' => 'courses'])->with('success', $msg);
    }
}
