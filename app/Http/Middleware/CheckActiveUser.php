<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckActiveUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && !Auth::user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'บัญชีผู้ใช้งานของคุณถูกระงับชั่วคราว กรุณาติดต่อผู้ดูแลระบบ';

            // คำขอแบบ AJAX/JSON (เช่น หน้าจัดตารางที่ยิง realtime) ต้องได้ 401 ชัด ๆ
            // ไม่ใช่ 302 ไปหน้า login HTML ที่ฝั่ง JS ตีความไม่ออก
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 401);
            }

            return redirect()->route('login')->withErrors([
                'username' => $message,
            ]);
        }

        return $next($request);
    }
}
