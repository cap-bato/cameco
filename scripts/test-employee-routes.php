<?php

/**
 * Employee Routes End-to-End Validation Test
 * 
 * Tests route resolution and middleware application
 * (Controllers don't exist yet, so we expect ReflectionException)
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n";
echo "==========================================================\n";
echo "Employee Routes End-to-End Validation Test\n";
echo "==========================================================\n";
echo "\n";

$router = app('router');
$routes = collect($router->getRoutes())->filter(function($route) {
    return str_starts_with($route->getName() ?? '', 'employee.');
});

echo "Testing route resolution for all employee.* routes:\n";
echo "----------------------------------------------------------\n";

$testResults = [
    'total' => 0,
    'resolved' => 0,
    'has_middleware' => 0,
    'has_permission' => 0,
];

foreach ($routes as $route) {
    $testResults['total']++;
    $name = $route->getName();
    $uri = $route->uri();
    $methods = implode('|', $route->methods());
    $action = $route->getActionName();
    
    // Check if route has auth middleware
    $middleware = $route->gatherMiddleware();
    $hasAuth = in_array('auth', $middleware) || in_array('App\Http\Middleware\Authenticate', $middleware);
    $hasVerified = in_array('verified', $middleware);
    $hasEmployee = in_array('App\Http\Middleware\EnsureEmployee', $middleware);
    $hasPermission = collect($middleware)->contains(fn($m) => str_starts_with($m, 'permission:'));
    
    if ($hasAuth && $hasVerified && $hasEmployee) {
        $testResults['has_middleware']++;
    }
    
    if ($hasPermission) {
        $testResults['has_permission']++;
    }
    
    // Check if route can be resolved (controller exists)
    try {
        $controller = $route->getController();
        $testResults['resolved']++;
        $status = "✅ RESOLVED";
    } catch (\ReflectionException $e) {
        $status = "⚠️  PENDING (Controller not created yet)";
    } catch (\Exception $e) {
        $status = "❌ ERROR: " . $e->getMessage();
    }
    
    echo "Route: {$name}\n";
    echo "  URI: {$methods} /{$uri}\n";
    echo "  Action: {$action}\n";
    echo "  Middleware: " . ($hasAuth && $hasVerified && $hasEmployee ? "✅ Correct (auth, verified, EnsureEmployee)" : "❌ Missing required middleware") . "\n";
    echo "  Permission: " . ($hasPermission ? "✅ Protected" : "❌ No permission check") . "\n";
    echo "  Status: {$status}\n";
    echo "\n";
}

echo "==========================================================\n";
echo "Test Summary\n";
echo "==========================================================\n";
echo "\n";

echo "Total employee routes: {$testResults['total']}\n";
echo "Routes with correct middleware: {$testResults['has_middleware']}/{$testResults['total']}\n";
echo "Routes with permission checks: {$testResults['has_permission']}/{$testResults['total']}\n";
echo "Routes resolved (controllers exist): {$testResults['resolved']}/{$testResults['total']}\n";

echo "\n";

$middlewarePassed = $testResults['has_middleware'] === $testResults['total'];
$permissionPassed = $testResults['has_permission'] === $testResults['total'];
$routesRegistered = $testResults['total'] === 18;

echo "Middleware Protection: " . ($middlewarePassed ? "✅ PASSED" : "❌ FAILED") . "\n";
echo "Permission Protection: " . ($permissionPassed ? "✅ PASSED" : "❌ FAILED") . "\n";
echo "Route Registration: " . ($routesRegistered ? "✅ PASSED (18/18)" : "❌ FAILED") . "\n";
echo "Controller Resolution: ⚠️  EXPECTED PENDING (Phase 3 - Controllers not created yet)\n";

echo "\n";

$allChecksPassed = $middlewarePassed && $permissionPassed && $routesRegistered;
echo "Overall Status: " . ($allChecksPassed ? "✅ ALL ROUTING CHECKS PASSED" : "❌ SOME CHECKS FAILED") . "\n";
echo "Note: Controller resolution failures are expected until Phase 3 completion.\n";

echo "\n";
