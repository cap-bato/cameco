<?php

namespace App\Http\Controllers\Api\Timekeeping;

use App\Http\Controllers\Controller;
use App\Models\RfidCardMapping;
use Illuminate\Http\JsonResponse;

class CardValidationController extends Controller
{
    public function show(string $cardUid): JsonResponse
    {
        $mapping = RfidCardMapping::with('employee')
            ->where('card_uid', $cardUid)
            ->first();

        if (!$mapping) {
            return response()->json(['valid' => false, 'reason' => 'unknown_card'], 404);
        }

        if (!$mapping->is_active) {
            return response()->json([
                'valid'       => false,
                'reason'      => 'card_inactive',
                'employee_id' => $mapping->employee_id,
            ], 200);
        }

        return response()->json([
            'valid'       => true,
            'employee_id' => $mapping->employee_id,
            'employee'    => $mapping->employee ? [
                'id'             => $mapping->employee->id,
                'employee_number' => $mapping->employee->employee_number,
                'name'           => $mapping->employee->full_name,
            ] : null,
        ]);
    }
}
