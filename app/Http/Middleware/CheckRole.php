<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UserRole;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $activeRole = session('active_role');

        if (!in_array($activeRole, $roles)) {
            abort(403, 'ไม่มีสิทธิ์เข้าถึงส่วนนี้');
        }

        // ยืนยันว่า user ถือ role นั้นจริงใน DB (ป้องกัน session tampering)
        $hasRole = UserRole::where('user_id', Auth::id())
            ->where('role', $activeRole)
            ->exists();

        if (!$hasRole) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['username' => 'Session ไม่ถูกต้อง กรุณาเข้าสู่ระบบใหม่']);
        }

        return $next($request);
    }
}
