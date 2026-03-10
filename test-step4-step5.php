<?php

/**
 * Test Step 4 & Step 5 Implementation
 * Tests period name formatting in PayrollAdjustmentController
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Payroll\PayrollProcessing\PayrollAdjustmentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

echo "=================================================================\n";
echo "Step 4 & Step 5 Implementation Test\n";
echo "=================================================================\n\n";

// Test counter
$passed = 0;
$failed = 0;

// Authenticate as a user
$user = User::where('email', 'payroll@cathay.com')->first();
if (!$user) {
    echo "❌ FAILED: Test user not found (payroll@cathay.com)\n";
    exit(1);
}
Auth::login($user);

// Create controller instance
$controller = new PayrollAdjustmentController();

echo "Test 1: Check required imports in controller\n";
echo "------------------------------------------------------------\n";
try {
    $reflection = new ReflectionClass($controller);
    $fileContent = file_get_contents($reflection->getFileName());
    
    $requiredImports = [
        'use App\Models\Employee;',
        'use App\Models\PayrollAdjustment;',
        'use App\Models\PayrollPeriod;',
    ];
    
    $allImportsPresent = true;
    foreach ($requiredImports as $import) {
        if (strpos($fileContent, $import) === false) {
            echo "❌ MISSING: $import\n";
            $allImportsPresent = false;
            $failed++;
        } else {
            echo "✅ FOUND: $import\n";
        }
    }
    
    if ($allImportsPresent) {
        echo "✅ PASSED: All required imports present (Step 5)\n";
        $passed++;
    } else {
        echo "❌ FAILED: Some imports missing\n";
    }
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n\nTest 2: Test index() method - period name formatting\n";
echo "------------------------------------------------------------\n";
try {
    $request = Request::create('/payroll/adjustments', 'GET');
    $response = $controller->index($request);
    
    // Extract props from Inertia response
    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);
    
    $availablePeriods = $props['available_periods'];
    
    if (empty($availablePeriods)) {
        echo "⚠️  WARNING: No periods available to test\n";
    } else {
        echo "Found " . count($availablePeriods) . " periods\n\n";
        
        $allValid = true;
        foreach (array_slice($availablePeriods, 0, 3) as $period) {
            echo "Period ID {$period['id']}: {$period['name']}\n";
            
            // Check format: should contain parentheses and en-dash
            if (strpos($period['name'], '(') !== false && 
                strpos($period['name'], ')') !== false &&
                strpos($period['name'], '–') !== false) {
                echo "  ✅ Format looks correct (contains parentheses and en-dash)\n";
            } else {
                echo "  ❌ Format incorrect - expected 'period_number (MMM DD–MMM DD, YYYY)'\n";
                $allValid = false;
            }
            
            // Check it's not just using period_name from DB
            if (strpos($period['name'], '-') !== false && strpos($period['name'], '–') === false) {
                echo "  ⚠️  WARNING: May be using old period_name format\n";
                $allValid = false;
            }
        }
        
        if ($allValid) {
            echo "\n✅ PASSED: Period names formatted correctly in index()\n";
            $passed++;
        } else {
            echo "\n❌ FAILED: Some period names have incorrect format\n";
            $failed++;
        }
    }
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n\nTest 3: Test history() method - period name formatting\n";
echo "------------------------------------------------------------\n";
try {
    // Get first employee
    $employee = \App\Models\Employee::first();
    
    if (!$employee) {
        echo "⚠️  WARNING: No employees to test\n";
    } else {
        $request = Request::create("/payroll/adjustments/history/{$employee->id}", 'GET');
        $response = $controller->history($request, $employee->id);
        
        // Extract props from Inertia response
        $reflection = new ReflectionClass($response);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($response);
        
        $availablePeriods = $props['available_periods'];
        
        if (empty($availablePeriods)) {
            echo "⚠️  WARNING: No periods available to test\n";
        } else {
            echo "Found " . count($availablePeriods) . " periods in history view\n\n";
            
            $allValid = true;
            foreach (array_slice($availablePeriods, 0, 3) as $period) {
                echo "Period ID {$period['id']}: {$period['name']}\n";
                
                // Check format
                if (strpos($period['name'], '(') !== false && 
                    strpos($period['name'], ')') !== false &&
                    strpos($period['name'], '–') !== false) {
                    echo "  ✅ Format looks correct\n";
                } else {
                    echo "  ❌ Format incorrect\n";
                    $allValid = false;
                }
            }
            
            if ($allValid) {
                echo "\n✅ PASSED: Period names formatted correctly in history()\n";
                $passed++;
            } else {
                echo "\n❌ FAILED: Some period names have incorrect format\n";
                $failed++;
            }
        }
    }
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n\nTest 4: Test transformAdjustment() - period name in adjustments\n";
echo "------------------------------------------------------------\n";
try {
    $request = Request::create('/payroll/adjustments', 'GET');
    $response = $controller->index($request);
    
    // Extract props from Inertia response
    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);
    
    $adjustments = $props['adjustments'];
    
    if (empty($adjustments)) {
        echo "⚠️  WARNING: No adjustments available to test\n";
    } else {
        echo "Found " . count($adjustments) . " adjustments\n\n";
        
        $allValid = true;
        foreach (array_slice($adjustments, 0, 3) as $adjustment) {
            $periodName = $adjustment['payroll_period']['name'];
            echo "Adjustment ID {$adjustment['id']} - Period: {$periodName}\n";
            
            // Check format
            if (strpos($periodName, '(') !== false && 
                strpos($periodName, ')') !== false &&
                strpos($periodName, '–') !== false) {
                echo "  ✅ Format looks correct\n";
            } else {
                echo "  ❌ Format incorrect\n";
                $allValid = false;
            }
        }
        
        if ($allValid) {
            echo "\n✅ PASSED: Period names formatted correctly in transformAdjustment()\n";
            $passed++;
        } else {
            echo "\n❌ FAILED: Some period names have incorrect format\n";
            $failed++;
        }
    }
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    $failed++;
}

echo "\n\nTest 5: Verify period name format matches specification\n";
echo "------------------------------------------------------------\n";
try {
    // Get a period directly from database
    $period = \App\Models\PayrollPeriod::first();
    
    if (!$period) {
        echo "⚠️  WARNING: No periods in database to test\n";
    } else {
        // Format according to Step 4 spec
        $expectedFormat = $period->period_number . ' (' . 
            $period->period_start?->format('M d') . '–' . 
            $period->period_end?->format('M d, Y') . ')';
        
        echo "Period from DB:\n";
        echo "  ID: {$period->id}\n";
        echo "  period_number: {$period->period_number}\n";
        echo "  period_start: " . $period->period_start?->format('Y-m-d') . "\n";
        echo "  period_end: " . $period->period_end?->format('Y-m-d') . "\n";
        echo "\nExpected formatted name: {$expectedFormat}\n";
        
        // Now get it from controller
        $request = Request::create('/payroll/adjustments', 'GET');
        $response = $controller->index($request);
        
        // Extract props from Inertia response
        $reflection = new ReflectionClass($response);
        $propsProperty = $reflection->getProperty('props');
        $propsProperty->setAccessible(true);
        $props = $propsProperty->getValue($response);
        
        $availablePeriods = $props['available_periods'];
        
        $matchingPeriod = null;
        foreach ($availablePeriods as $p) {
            if ($p['id'] === $period->id) {
                $matchingPeriod = $p;
                break;
            }
        }
        
        if ($matchingPeriod) {
            echo "Actual formatted name:   {$matchingPeriod['name']}\n\n";
            
            if ($matchingPeriod['name'] === $expectedFormat) {
                echo "✅ PASSED: Format exactly matches specification\n";
                $passed++;
            } else {
                echo "❌ FAILED: Format does not match specification\n";
                $failed++;
            }
        } else {
            echo "❌ FAILED: Could not find period in controller response\n";
            $failed++;
        }
    }
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    $failed++;
}

// Summary
echo "\n\n=================================================================\n";
echo "Test Summary\n";
echo "=================================================================\n";
echo "Total Tests: " . ($passed + $failed) . "\n";
echo "✅ Passed: $passed\n";
echo "❌ Failed: $failed\n";

if ($failed === 0) {
    echo "\n🎉 All tests passed! Step 4 & Step 5 implementation is correct.\n";
    exit(0);
} else {
    echo "\n⚠️  Some tests failed. Please review the implementation.\n";
    exit(1);
}
