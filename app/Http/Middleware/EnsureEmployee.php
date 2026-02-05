<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployee
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user has Employee or Superadmin role.
     * This middleware restricts access to the employee self-service portal.
     * 
     * Superadmin is allowed for testing and administrative oversight purposes.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!$request->user()) {
            \Log::warning('EnsureEmployee: No authenticated user', [
                'path' => $request->path(),
                'method' => $request->method(),
                'ip' => $request->ip(),
            ]);
            return redirect()->route('login')->with('error', 'You must be logged in to access the employee portal.');
        }

        // Log the user and their roles for audit trail
        $user = $request->user();
        $roles = $user->getRoleNames()->toArray();
        \Log::debug('EnsureEmployee checking user', [
            'user_id' => $user->id,
            'email' => $user->email,
            'roles' => $roles,
            'path' => $request->path(),
            'method' => $request->method(),
        ]);

        // Check if user has Employee or Superadmin role
        $hasRole = $user->hasRole(['Employee', 'Superadmin']);
        if (!$hasRole) {
            \Log::error('EnsureEmployee: User lacks required role', [
                'user_id' => $user->id,
                'email' => $user->email,
                'roles' => $roles,
                'path' => $request->path(),
                'required_roles' => ['Employee', 'Superadmin'],
            ]);
            abort(403, 'Access denied. You do not have permission to access the employee portal.');
        }

        // Log successful authorization
        \Log::info('EnsureEmployee: Access granted', [
            'user_id' => $user->id,
            'email' => $user->email,
            'path' => $request->path(),
        ]);

        return $next($request);
    }
}
