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
        $userRoles = UserRole::where('user_id', $user->id)->pluck('role');

        if (
            is_string($activeRole)
            && $activeRole !== ''
            && $userRoles->contains($activeRole)
            && in_array($activeRole, $roles, true)
        ) {
            return $next($request);
        }

        $allowedRole = collect($roles)->first(fn ($role) => $userRoles->contains($role));

        if ($allowedRole) {
            $request->session()->put('active_role', $allowedRole);
            return $next($request);
        }

        abort(403, 'ไม่มีสิทธิ์เข้าถึงส่วนนี้');
    }
}
