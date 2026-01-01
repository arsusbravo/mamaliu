<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Constants\UserType;

class CheckUserType
{
    public function handle(Request $request, Closure $next, string $userType)
    {
        // Admins can access everything
        if ($request->user()->usertype === UserType::ADMIN) {
            return $next($request);
        }

        if ($request->user()->usertype !== $userType) {
            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}