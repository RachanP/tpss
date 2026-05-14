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
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        $activeRole = $request->session()->get('active_role');

        if (!is_string($activeRole) || $activeRole === '') {
            abort(403, 'ไม่มีสิทธิ์เข้าถึงส่วนนี้');
        }

        $hasRole = UserRole::where('user_id', $user->id)
            ->where('role', $activeRole)
            ->exists();

        if (!$hasRole) {
            abort(403, 'ไม่มีสิทธิ์เข้าถึงส่วนนี้');
        }

        if (!in_array($activeRole, $roles, true)) {
            abort(403, 'ไม่มีสิทธิ์เข้าถึงส่วนนี้');
        }

        return $next($request);
    }
}
