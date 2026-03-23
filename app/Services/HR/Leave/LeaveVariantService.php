<?php

namespace App\Services\HR\Leave;

use App\Models\LeaveRequest;

/**
 * Leave Variant Service
 *
 * Handles leave type variant operations:
 * - Variant validation
 * - Variant label mapping
 * - Days calculation based on variant
 * - Variant application to leave requests
 */
class LeaveVariantService
{
    /**
     * Valid variant codes
     */
    private const VALID_VARIANTS = ['half_am', 'half_pm'];

    /**
     * Validate if variant is a valid value
     *
     * @param string|null $variant
     * @return bool
     */
    public function isValidVariant(?string $variant): bool
    {
        if ($variant === null) {
            return true; // null is valid (full day)
        }

        return in_array($variant, self::VALID_VARIANTS, true);
    }

    /**
     * Get readable label for variant
     *
     * @param string|null $variant
     * @return string|null
     */
    public function getVariantLabel(?string $variant): ?string
    {
        return match ($variant) {
            'half_am' => 'Half Day AM',
            'half_pm' => 'Half Day PM',
            null => null,
            default => null,
        };
    }

    /**
     * Get number of days for variant
     *
     * @param string|null $variant
     * @return float
     */
    public function getDaysForVariant(?string $variant): float
    {
        return match ($variant) {
            'half_am', 'half_pm' => 0.5,
            null => 1.0,
            default => 1.0,
        };
    }

    /**
     * Apply variant to leave request days calculation
     *
     * Updates the days_requested field based on the variant.
     * For half-day variants, sets to 0.5 days.
     * For null variant (full day), derives from start/end dates.
     *
     * @param LeaveRequest $request
     * @return void
     */
    public function applyVariantToDaysRequested(LeaveRequest $request): void
    {
        if ($this->isHalfDay($request->leave_type_variant)) {
            $request->days_requested = 0.5;
        }
        // For full days (null variant), days_requested should be calculated
        // from start_date and end_date by the controller, not here
    }

    /**
     * Check if variant is half-day
     *
     * @param string|null $variant
     * @return bool
     */
    public function isHalfDay(?string $variant): bool
    {
        return in_array($variant, self::VALID_VARIANTS, true);
    }

    /**
     * Get all available variants
     *
     * @return array
     */
    public function getAvailableVariants(): array
    {
        return [
            ['code' => null, 'label' => 'Full Day'],
            ['code' => 'half_am', 'label' => 'Half Day AM'],
            ['code' => 'half_pm', 'label' => 'Half Day PM'],
        ];
    }
}
