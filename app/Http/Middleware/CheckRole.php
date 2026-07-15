<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        // L'admin a toujours accès, quel que soit le(s) rôle(s) exigé(s) par la route
        if ($request->user()->role === 'admin') {
            return $next($request);
        }

        if (!in_array($request->user()->role, $roles)) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        return $next($request);
    }
}