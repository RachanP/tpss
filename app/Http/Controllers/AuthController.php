<?php

namespace App\Http\Controllers;

use App\Models\UserRole;
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

            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'username' => 'ชื่อผู้ใช้/อีเมลหรือรหัสผ่านไม่ถูกต้อง กรุณาลองอีกครั้ง',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
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

        return back()->with('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
    }
}
