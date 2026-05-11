<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $activeRole = session('active_role');

        if (!in_array($activeRole, $roles)) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}
