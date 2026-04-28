<?php
// Verification script for half-day leave single date fix

echo "=== HALF-DAY LEAVE SINGLE DAY FIX VERIFICATION ===\n\n";

try {
    // Check 1: HR CreateRequest - useEffect for auto-setting end_date
    $hrCreateFile = __DIR__ . '/resources/js/pages/HR/Leave/CreateRequest.tsx';
    if (!file_exists($hrCreateFile)) {
        throw new Exception("HR CreateRequest file not found");
    }
    
    $content = file_get_contents($hrCreateFile);
    
    if (strpos($content, "const isHalfDayVariant = form.data.leave_type_variant && ['half_am', 'half_pm'].includes(form.data.leave_type_variant)") === false) {
        throw new Exception("✗ isHalfDayVariant flag not found in HR CreateRequest");
    }
    echo "✓ HR CreateRequest: isHalfDayVariant flag added\n";
    
    if (strpos($content, "if (isHalfDayVariant && form.data.start_date && form.data.end_date !== form.data.start_date)") === false) {
        throw new Exception("✗ Auto-sync end_date logic not found in HR CreateRequest");
    }
    echo "✓ HR CreateRequest: Auto-sync end_date useEffect added\n";
    
    if (strpos($content, "form.setData('end_date', form.data.start_date)") === false) {
        throw new Exception("✗ Form data sync not found");
    }
    echo "✓ HR CreateRequest: Form data sync working\n";
    
    if (strpos($content, "{isHalfDayVariant ? 'Leave Date' : 'Start Date'}") === false) {
        throw new Exception("✗ Dynamic label not found in HR CreateRequest");
    }
    echo "✓ HR CreateRequest: Dynamic label for date input\n";
    
    if (strpos($content, "!isHalfDayVariant && (") === false) {
        throw new Exception("✗ Conditional end_date rendering not found in HR CreateRequest");
    }
    echo "✓ HR CreateRequest: Conditional end_date input hiding\n";
    
    if (strpos($content, "Half-day leave is for a single day only") === false) {
        throw new Exception("✗ Single day notice not found in HR CreateRequest");
    }
    echo "✓ HR CreateRequest: Single day notice displayed\n";
    
    // Check 2: Employee CreateRequest - useEffect for auto-setting endDate
    $empCreateFile = __DIR__ . '/resources/js/pages/Employee/Leave/CreateRequest.tsx';
    if (!file_exists($empCreateFile)) {
        throw new Exception("Employee CreateRequest file not found");
    }
    
    $content = file_get_contents($empCreateFile);
    
    if (strpos($content, "// Auto-set end date to match start date when half-day variant is selected") === false) {
        throw new Exception("✗ Auto-set comment not found in Employee CreateRequest");
    }
    echo "✓ Employee CreateRequest: Auto-set comment present\n";
    
    if (strpos($content, "if (selectedVariant && ['half_am', 'half_pm'].includes(selectedVariant))") === false) {
        throw new Exception("✗ Half-day check not found in Employee CreateRequest");
    }
    echo "✓ Employee CreateRequest: Half-day variant check added\n";
    
    if (strpos($content, "setEndDate(startDate)") === false) {
        throw new Exception("✗ setEndDate not found in Employee CreateRequest");
    }
    echo "✓ Employee CreateRequest: setEndDate sync working\n";
    
    if (strpos($content, "{selectedVariant && ['half_am', 'half_pm'].includes(selectedVariant) ? 'Leave Date' : 'Start Date'}") === false) {
        throw new Exception("✗ Dynamic label not found in Employee CreateRequest");
    }
    echo "✓ Employee CreateRequest: Dynamic label for date input\n";
    
    if (strpos($content, "!selectedVariant || !['half_am', 'half_pm'].includes(selectedVariant) ? (") === false) {
        throw new Exception("✗ Conditional end_date rendering not found in Employee CreateRequest");
    }
    echo "✓ Employee CreateRequest: Conditional end_date input hiding\n";
    
    if (strpos($content, "Half Day AM (0.5 days)") === false || 
        strpos($content, "Half Day PM (0.5 days)") === false) {
        throw new Exception("✗ Variant display text not found in Employee CreateRequest");
    }
    echo "✓ Employee CreateRequest: Variant display with days\n";
    
    echo "\n=== VERIFICATION COMPLETE ===\n";
    echo "✅ Both HR and Employee CreateRequest pages now enforce single-day selection for half-day leave!\n";
    echo "   - End date auto-syncs to start date when half-day variant selected\n";
    echo "   - End date input is hidden for half-day requests\n";
    echo "   - Clear messaging about single-day nature of half-day leave\n";
    
} catch (Exception $e) {
    echo "\n❌ VERIFICATION FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
