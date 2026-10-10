<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireInitialPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->is('initial-password', 'logout', 'forgot-password', 'reset-password*', 'email/*')) {
            return redirect('/initial-password');
        }

        return $next($request);
    }
}
