<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, $role)
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        if ($request->user()->role !== $role) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        return $next($request);
    }
}