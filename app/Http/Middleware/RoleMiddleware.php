<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // For now, we'll check a simple role field on the user
        // In a real application, you might use a more sophisticated role system
        if (! $user->hasRole($role)) {
            return response()->json([
                'message' => 'Insufficient permissions. Required role: '.$role,
            ], 403);
        }

        return $next($request);
    }
}
