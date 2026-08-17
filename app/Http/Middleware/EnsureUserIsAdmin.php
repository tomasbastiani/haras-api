<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || (int) $request->user()->admin !== 1) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return $next($request);
    }
}
