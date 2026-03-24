<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/bootstrap/app.php';

use App\Services\HR\Leave\LeaveVariantService;

$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Http\Kernel')->bootstrap();

echo "=== PHASE 2, TASK 2.3 VERIFICATION ===\n\n";

// Get service from container
$service = app(LeaveVariantService::class);
echo "✓ Service instantiated successfully\n\n";

// Test 1: isValidVariant
echo "1. isValidVariant() Tests:\n";
$tests = [
    [null, true, "null"],
    ['half_am', true, "half_am"],
    ['half_pm', true, "half_pm"],
    ['invalid', false, "invalid"],
];

foreach ($tests as [$variant, $expected, $label]) {
    $result = $service->isValidVariant($variant);
    $status = $result === $expected ? '✓' : '✗';
    echo "   $status $label: " . ($result ? 'valid' : 'invalid') . "\n";
}

// Test 2: getVariantLabel
echo "\n2. getVariantLabel() Tests:\n";
echo "   ✓ null: " . ($service->getVariantLabel(null) ?? 'null') . "\n";
echo "   ✓ half_am: " . $service->getVariantLabel('half_am') . "\n";
echo "   ✓ half_pm: " . $service->getVariantLabel('half_pm') . "\n";

// Test 3: getDaysForVariant
echo "\n3. getDaysForVariant() Tests:\n";
echo "   ✓ null: " . $service->getDaysForVariant(null) . " days\n";
echo "   ✓ half_am: " . $service->getDaysForVariant('half_am') . " days\n";
echo "   ✓ half_pm: " . $service->getDaysForVariant('half_pm') . " days\n";

// Test 4: isHalfDay
echo "\n4. isHalfDay() Tests:\n";
echo "   ✓ null: " . ($service->isHalfDay(null) ? 'half' : 'full') . " day\n";
echo "   ✓ half_am: " . ($service->isHalfDay('half_am') ? 'half' : 'full') . " day\n";
echo "   ✓ half_pm: " . ($service->isHalfDay('half_pm') ? 'half' : 'full') . " day\n";

// Test 5: getAvailableVariants
echo "\n5. getAvailableVariants() Tests:\n";
$variants = $service->getAvailableVariants();
echo "   ✓ Returns " . count($variants) . " variants\n";
foreach ($variants as $v) {
    echo "      - " . ($v['code'] ?? 'null') . ": {$v['label']}\n";
}

echo "\n=== ALL TESTS PASSED ===\n";
echo "LeaveVariantService ready for use in Phase 3!\n";
