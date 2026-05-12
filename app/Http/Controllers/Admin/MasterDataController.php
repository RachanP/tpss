<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Models\LocationType;
use App\Models\Room;
use App\Models\Course;
use App\Models\Curriculum;
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

        // Courses with curriculum and head instructor
        $courses = Course::with(['curriculum', 'department', 'headInstructor'])->get();

        // Curriculums with course count
        $curriculums = Curriculum::withCount('courses')->get();

        return view('admin.master_data.index', compact(
            'instructors', 
            'departments', 
            'users', 
            'locationTypes', 
            'rooms',
            'courses',
            'curriculums'
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
            'employee_id'     => 'required|string|max:50|unique:instructor_profiles,employee_id,' . ($profile ? $profile->id : 'NULL'),
            'department_id'   => 'required|integer|exists:departments,id',
            'title'           => 'required|string|max:100',
            'academic_degree' => 'required|string|max:100',
            'employment_type' => 'required|string|max:100',
            'teaching_pct'    => 'required|integer|min:0|max:100',
        ]);

        // Update user name and prefix
        $user->update(['name' => $request->name, 'prefix' => $request->prefix]);

        // If department is changing and user was head/secretary of old dept, clear that role
        if ($profile && $request->filled('department_id') && (int)$request->department_id !== (int)$profile->department_id) {
            Department::where('head_user_id', $user->id)->update(['head_user_id' => null]);
            Department::where('secretary_user_id', $user->id)->update(['secretary_user_id' => null]);
        }

        // Update profile
        if ($profile) {
            $profile->update([
                'employee_id'     => $request->employee_id,
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
            'academic_level' => 'nullable|in:undergraduate,graduate',
            'default_year_level' => 'nullable|integer|min:1|max:4',
            'default_semester' => 'nullable|integer|min:1|max:3',
            'credits' => 'required|integer|min:0',
            'lecture_hours' => 'nullable|integer|min:0',
            'lab_hours' => 'nullable|integer|min:0',
            'self_study_hours' => 'nullable|integer|min:0',
            'color_code' => 'nullable|string|max:7',
            'status' => 'required|in:active,inactive',
            'requires_practicum_rotation' => 'nullable|boolean'
        ]);

        // Auto-calculate course type
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

        Course::create($validated);

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
            'course_type' => 'required|in:theory,practicum,theory_practicum',
            'academic_level' => 'nullable|in:undergraduate,graduate',
            'default_year_level' => 'nullable|integer|min:1|max:4',
            'default_semester' => 'nullable|integer|min:1|max:3',
            'credits' => 'required|integer|min:0',
            'lecture_hours' => 'nullable|integer|min:0',
            'lab_hours' => 'nullable|integer|min:0',
            'self_study_hours' => 'nullable|integer|min:0',
            'color_code' => 'nullable|string|max:7',
            'status' => 'required|in:active,inactive',
            'requires_practicum_rotation' => 'nullable|boolean'
        ]);

        $validated['requires_practicum_rotation'] = $request->has('requires_practicum_rotation');

        $course->update($validated);

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
}
