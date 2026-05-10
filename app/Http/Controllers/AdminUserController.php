<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::with('roles')->orderBy('created_at', 'desc')->get();
        
        // Count users by role for stats if needed
        $stats = [
            'total' => User::count(),
            'active' => User::where('is_active', true)->count(),
            'inactive' => User::where('is_active', false)->count(),
        ];

        return view('admin.users.index', compact('users', 'stats'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:100|unique:users',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:4',
            'roles' => 'required|array|min:1',
            'is_active' => 'boolean',
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'username' => $validated['username'],
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'is_active' => $validated['is_active'] ?? true,
            ]);

            foreach ($validated['roles'] as $role) {
                UserRole::create([
                    'user_id' => $user->id,
                    'role' => $role,
                    'is_primary' => $role === $validated['roles'][0], // First role as primary for now
                ]);
            }
        });

        return redirect()->route('admin.users')->with('success', 'เพิ่มผู้ใช้เรียบร้อยแล้ว');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:4',
            'roles' => 'required|array|min:1',
            'is_active' => 'required|boolean',
        ]);

        DB::transaction(function () use ($validated, $user, $request) {
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'is_active' => $validated['is_active'],
            ]);

            if ($request->filled('password')) {
                $user->update(['password' => Hash::make($validated['password'])]);
            }

            // Sync roles
            UserRole::where('user_id', $user->id)->delete();
            foreach ($validated['roles'] as $role) {
                UserRole::create([
                    'user_id' => $user->id,
                    'role' => $role,
                    'is_primary' => $role === $validated['roles'][0],
                ]);
            }
        });

        return redirect()->route('admin.users')->with('success', 'อัปเดตข้อมูลผู้ใช้เรียบร้อยแล้ว');
    }


    public function toggleStatus(User $user)
    {
        $user->update(['is_active' => !$user->is_active]);
        return response()->json(['success' => true, 'is_active' => $user->is_active]);
    }

    public function destroy(User $user)
    {
        // For safety, maybe soft delete or just deactivate
        $user->delete();
        return redirect()->route('admin.users')->with('success', 'ลบผู้ใช้เรียบร้อยแล้ว');
    }
}
