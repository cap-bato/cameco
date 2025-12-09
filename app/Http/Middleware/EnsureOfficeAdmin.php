<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfficeAdmin
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user has either Office Admin or Superadmin role.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!$request->user()) {
            \Log::warning('EnsureOfficeAdmin: No authenticated user', [
                'path' => $request->path(),
                'method' => $request->method(),
            ]);
            return redirect()->route('login')->with('error', 'You must be logged in to access this page.');
        }

        // Log the user and their roles
        $user = $request->user();
        $roles = $user->getRoleNames()->toArray();
        \Log::debug('EnsureOfficeAdmin checking user', [
            'user_id' => $user->id,
            'email' => $user->email,
            'roles' => $roles,
            'path' => $request->path(),
            'method' => $request->method(),
        ]);

        // Check if user has Office Admin or Superadmin role
        $hasRole = $user->hasRole(['Office Admin', 'Superadmin']);
        if (!$hasRole) {
            \Log::error('EnsureOfficeAdmin: User lacks required role', [
                'user_id' => $user->id,
                'email' => $user->email,
                'roles' => $roles,
                'path' => $request->path(),
            ]);
            abort(403, 'Access denied. You do not have permission to access office admin features.');
        }

        return $next($request);
    }
}
