<?php

namespace App\Http\Controllers;

use App\Models\UserRole;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login    = $request->input('username');
        $password = $request->input('password');

        // Detect if input is email or username
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (Auth::attempt([$field => $login, 'password' => $password])) {
            $user = Auth::user();

            if (!$user->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->withErrors([
                    'username' => 'บัญชีผู้ใช้นี้ถูกระงับการใช้งาน',
                ])->onlyInput('username');
            }

            $request->session()->regenerate();

            // Find primary role and set in session
            $primaryRole = UserRole::where('user_id', $user->id)
                ->where('is_primary', true)
                ->first();

            if ($primaryRole) {
                $request->session()->put('active_role', $primaryRole->role);
            } else {
                $firstRole = UserRole::where('user_id', $user->id)->first();
                if ($firstRole) {
                    $request->session()->put('active_role', $firstRole->role);
                }
            }

            AuditLogger::log(
                action: 'ระบบ.เข้าสู่ระบบ',
                table: 'users',
                recordId: $user->id,
                oldValues: null,
                newValues: [
                    'username'    => $user->username,
                    'email'       => $user->email,
                    'active_role' => session('active_role'),
                    'login_via'   => $field,
                ],
                category: 'ระบบ',
                description: "เข้าสู่ระบบ: {$user->name}",
            );

            return redirect('/dashboard');
        }

        return back()->withErrors([
            'username' => 'ชื่อผู้ใช้/อีเมลหรือรหัสผ่านไม่ถูกต้อง กรุณาลองอีกครั้ง',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        $user = auth()->user();

        if ($user) {
            AuditLogger::log(
                action: 'ระบบ.ออกจากระบบ',
                table: 'users',
                recordId: $user->id,
                oldValues: null,
                newValues: null,
                category: 'ระบบ',
                description: "ออกจากระบบ: {$user->name}",
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'new_password.min' => 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 8 ตัวอักษร',
            'new_password.confirmed' => 'การยืนยันรหัสผ่านใหม่ไม่ตรงกัน',
        ]);

        $user = Auth::user();
        $user->password = $request->new_password;
        $user->save();

        AuditLogger::log(
            action: 'ผู้ใช้และสิทธิ์.เปลี่ยนรหัสผ่าน',
            table: 'users',
            recordId: $user->id,
            oldValues: null,
            newValues: ['password_changed' => true],
            category: 'ผู้ใช้และสิทธิ์',
            description: "เปลี่ยนรหัสผ่านผู้ใช้ {$user->name}",
        );

        return back()->with('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
    }
}
