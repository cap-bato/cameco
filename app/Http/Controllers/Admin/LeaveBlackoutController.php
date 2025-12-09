<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\LeaveBlackoutPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Leave Blackout Period Controller
 * 
 * Manages leave blackout periods for Office Admin.
 * Blackout periods block leave requests during critical business periods.
 */
class LeaveBlackoutController extends Controller
{
    /**
     * Display a listing of blackout periods.
     */
    public function index(Request $request)
    {
        $query = LeaveBlackoutPeriod::with(['department', 'createdBy'])
            ->orderBy('start_date', 'desc');

        // Filter by department if provided
        if ($request->has('department_id') && $request->department_id) {
            $query->where('department_id', $request->department_id);
        }

        // Filter by status
        if ($request->has('status')) {
            switch ($request->status) {
                case 'current':
                    $query->current();
                    break;
                case 'upcoming':
                    $query->upcoming();
                    break;
                case 'company_wide':
                    $query->companyWide();
                    break;
            }
        }

        $blackoutPeriods = $query->paginate(20)->withQueryString();

        // Get departments for filter dropdown
        $departments = Department::select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/LeavePolicies/BlackoutPeriods', [
            'blackoutPeriods' => $blackoutPeriods,
            'departments' => $departments,
            'filters' => [
                'department_id' => $request->department_id,
                'status' => $request->status,
            ],
        ]);
    }

    /**
     * Store a newly created blackout period.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:500',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        // Check for overlapping blackout periods
        $overlapping = LeaveBlackoutPeriod::overlapping(
            $validated['start_date'],
            $validated['end_date']
        );

        // If department-specific, only check within same department
        if ($validated['department_id'] ?? null) {
            $overlapping->where(function ($query) use ($validated) {
                $query->where('department_id', $validated['department_id'])
                      ->orWhereNull('department_id'); // Company-wide also affects department
            });
        } else {
            // Company-wide, check all
            $overlapping->whereNull('department_id');
        }

        if ($overlapping->exists()) {
            return back()->withErrors([
                'start_date' => 'This date range overlaps with an existing blackout period.',
            ]);
        }

        // Create blackout period
        $validated['created_by'] = Auth::id();
        $blackoutPeriod = LeaveBlackoutPeriod::create($validated);

        // Log activity
        activity('leave_blackout_created')
            ->causedBy(Auth::user())
            ->performedOn($blackoutPeriod)
            ->withProperties([
                'name' => $blackoutPeriod->name,
                'start_date' => $blackoutPeriod->start_date->toDateString(),
                'end_date' => $blackoutPeriod->end_date->toDateString(),
                'department_id' => $blackoutPeriod->department_id,
                'is_company_wide' => $blackoutPeriod->isCompanyWide(),
            ])
            ->log('Created leave blackout period: ' . $blackoutPeriod->name);

        Log::info('Leave blackout period created', [
            'id' => $blackoutPeriod->id,
            'name' => $blackoutPeriod->name,
            'start_date' => $blackoutPeriod->start_date,
            'end_date' => $blackoutPeriod->end_date,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('admin.leave-blackouts.index')
            ->with('success', 'Blackout period created successfully.');
    }

    /**
     * Update the specified blackout period.
     */
    public function update(Request $request, LeaveBlackoutPeriod $blackout)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:500',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        // Check for overlapping blackout periods (excluding current one)
        $overlapping = LeaveBlackoutPeriod::overlapping(
            $validated['start_date'],
            $validated['end_date']
        )->where('id', '!=', $blackout->id);

        // If department-specific, only check within same department
        if ($validated['department_id'] ?? null) {
            $overlapping->where(function ($query) use ($validated) {
                $query->where('department_id', $validated['department_id'])
                      ->orWhereNull('department_id');
            });
        } else {
            $overlapping->whereNull('department_id');
        }

        if ($overlapping->exists()) {
            return back()->withErrors([
                'start_date' => 'This date range overlaps with an existing blackout period.',
            ]);
        }

        // Store old values for audit
        $oldValues = $blackout->only(['name', 'start_date', 'end_date', 'reason', 'department_id']);

        // Update blackout period
        $blackout->update($validated);

        // Log activity
        activity('leave_blackout_updated')
            ->causedBy(Auth::user())
            ->performedOn($blackout)
            ->withProperties([
                'old' => $oldValues,
                'new' => $validated,
            ])
            ->log('Updated leave blackout period: ' . $blackout->name);

        Log::info('Leave blackout period updated', [
            'id' => $blackout->id,
            'name' => $blackout->name,
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('admin.leave-blackouts.index')
            ->with('success', 'Blackout period updated successfully.');
    }

    /**
     * Remove the specified blackout period.
     */
    public function destroy(LeaveBlackoutPeriod $blackout)
    {
        // Soft delete the blackout period
        $blackout->delete();

        // Log activity
        activity('leave_blackout_deleted')
            ->causedBy(Auth::user())
            ->performedOn($blackout)
            ->withProperties([
                'name' => $blackout->name,
                'start_date' => $blackout->start_date->toDateString(),
                'end_date' => $blackout->end_date->toDateString(),
            ])
            ->log('Deleted leave blackout period: ' . $blackout->name);

        Log::info('Leave blackout period deleted', [
            'id' => $blackout->id,
            'name' => $blackout->name,
            'deleted_by' => Auth::id(),
        ]);

        return redirect()->route('admin.leave-blackouts.index')
            ->with('success', 'Blackout period deleted successfully.');
    }

    /**
     * Check if a date range conflicts with any blackout periods.
     * API endpoint for leave request validation.
     */
    public function checkBlackout(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        // Find overlapping blackout periods
        $query = LeaveBlackoutPeriod::active()
            ->overlapping($validated['start_date'], $validated['end_date']);

        // Check both company-wide and department-specific blackouts
        if ($validated['department_id'] ?? null) {
            $query->where(function ($q) use ($validated) {
                $q->whereNull('department_id') // Company-wide
                  ->orWhere('department_id', $validated['department_id']); // Department-specific
            });
        } else {
            $query->companyWide(); // Only company-wide
        }

        $blackouts = $query->get();

        return response()->json([
            'has_blackout' => $blackouts->isNotEmpty(),
            'blackouts' => $blackouts->map(function ($blackout) {
                return [
                    'name' => $blackout->name,
                    'start_date' => $blackout->start_date->toDateString(),
                    'end_date' => $blackout->end_date->toDateString(),
                    'reason' => $blackout->reason,
                    'is_company_wide' => $blackout->isCompanyWide(),
                    'department' => $blackout->department ? $blackout->department->name : null,
                ];
            }),
        ]);
    }
}
