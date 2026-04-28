<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckModuleEnabled
{
    public function handle(Request $request, Closure $next, $module)
    {
        if (!config("modules.{$module}")) {
            if ($request->expectsJson()) {
                return response()->json(['message' => "The {$module} module is currently disabled pending deployment."], 403);
            }
            return \Inertia\Inertia::render('Errors/FeatureDisabled', [
                'module' => ucfirst($module),
                'message' => "The requested module (" . ucfirst($module) . ") is currently disabled pending deployment."
            ])->toResponse($request)->setStatusCode(403);
        }
        
        return $next($request);
    }
}
