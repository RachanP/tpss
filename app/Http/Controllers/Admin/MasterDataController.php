<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Models\LocationType;
use App\Models\Room;
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

        return view('admin.master_data.index', compact(
            'instructors', 
            'departments', 
            'users', 
            'locationTypes', 
            'rooms'
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
            'room_code'        => 'required|string|max:255|unique:rooms,room_code',
            'room_name'        => 'required|string|max:255',
            'building'         => 'nullable|string|max:100',
            'capacity'         => 'required|integer|min:0',
            'location_type_id' => 'required|exists:location_types,id',
            'status'           => 'required|in:active,inactive,maintenance',
            'address'          => 'nullable|string',
        ]);

        Room::create($validated);

        return redirect()->route('admin.master_data', ['tab' => 'rooms'])->with('success', 'เพิ่มห้อง/สถานที่เรียบร้อยแล้ว');
    }

    public function updateRoom(Request $request, Room $room)
    {
        $validated = $request->validate([
            'room_code'        => 'required|string|max:255|unique:rooms,room_code,' . $room->id,
            'room_name'        => 'required|string|max:255',
            'building'         => 'nullable|string|max:100',
            'capacity'         => 'required|integer|min:0',
            'location_type_id' => 'required|exists:location_types,id',
            'status'           => 'required|in:active,inactive,maintenance',
            'address'          => 'nullable|string',
        ]);

        $room->update($validated);

        return redirect()->route('admin.master_data', ['tab' => 'rooms'])->with('success', 'อัปเดตห้อง/สถานที่เรียบร้อยแล้ว');
    }

    public function storeDepartment(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'head_user_id' => 'nullable|exists:users,id',
            'secretary_user_id' => 'nullable|exists:users,id',
        ]);

        Department::create($validated);

        return redirect()->back()->with('success', 'เพิ่มภาควิชาเรียบร้อยแล้ว');
    }

    public function updateDepartment(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'head_user_id'     => 'nullable|exists:users,id',
            'secretary_user_id'=> 'nullable|exists:users,id',
        ]);

        // If head is being changed, check for duplicate conflict
        $newHeadId = $validated['head_user_id'] ?? null;
        $newSecId  = $validated['secretary_user_id'] ?? null;

        // Block duplicate: same person as both head and secretary
        if ($newHeadId && $newSecId && (int)$newHeadId === (int)$newSecId) {
            return back()->withInput()->withErrors([
                'head_user_id' => 'ผู้เดียวกันไม่สามารถเป็นทั้งหัวหน้าและเลขานุการในคราวเดียวกันได้'
            ]);
        }

        // If head is being replaced, block if new head is already head of another dept
        if ($newHeadId && (int)$newHeadId !== (int)($department->head_user_id ?? 0)) {
            $conflict = Department::where('id', '!=', $department->id)
                ->where('head_user_id', $newHeadId)->first();
            if ($conflict) {
                $person = User::find($newHeadId);
                return back()->withInput()->withErrors([
                    'head_user_id' => "{$person?->name} เป็นหัวหน้าภาควิชา {$conflict->name} อยู่แล้ว"
                ]);
            }
        }

        // If secretary is being replaced, block if new sec is already sec of another dept
        if ($newSecId && (int)$newSecId !== (int)($department->secretary_user_id ?? 0)) {
            $conflict = Department::where('id', '!=', $department->id)
                ->where('secretary_user_id', $newSecId)->first();
            if ($conflict) {
                $person = User::find($newSecId);
                return back()->withInput()->withErrors([
                    'secretary_user_id' => "{$person?->name} เป็นเลขานุการภาควิชา {$conflict->name} อยู่แล้ว"
                ]);
            }
        }

        $department->update($validated);

        return redirect()->back()->with('success', 'อัปเดตภาควิชาเรียบร้อยแล้ว');
    }

    public function updateInstructor(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $profile = $user->instructorProfile;

        // Update user prefix
        $user->update(['prefix' => $request->prefix]);

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
}
