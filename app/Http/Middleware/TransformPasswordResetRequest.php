<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransformPasswordResetRequest
{
    public function handle(Request $request, Closure $next)
    {
        if (($request->is('forgot-password') || $request->is('reset-password')) && $request->isMethod('post')) {
            if ($request->has('email')) {
                $request->merge(['username' => $request->email]);
            }
        }

        return $next($request);
    }
}