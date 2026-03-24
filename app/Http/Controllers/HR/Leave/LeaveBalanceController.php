<?php

namespace App\Http\Controllers\HR\Leave;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveBalance;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class LeaveBalanceController extends Controller
{
    /**
     * Display a listing of leave balances with pagination.
     * Shows real leave balance information from database for all employees or filtered by year/employee.
     */
    public function index(Request $request): Response
    {
        // Get filters and pagination parameters
        $selectedYear = $request->input('year', now()->year);
        $employeeId = $request->input('employee_id');
        $perPage = (int) $request->input('per_page', 25); // Default: 25 employees per page
        $page = (int) $request->input('page', 1);
        $sortBy = $request->input('sort_by', 'name');        // ← new
        $sortDirection = $request->input('sort_direction', 'asc');  // ← new

        // Validate per_page value
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        // Get available years from database
        $years = range(now()->year - 5, now()->year + 1);

        // Fetch employees for filter dropdown
        $employees = Employee::with('profile:id,first_name,last_name')
            ->where('status', 'active')
            ->orderBy('employee_number')
            ->get()
            ->map(function ($emp) {
                return [
                    'id' => $emp->id,
                    'employee_number' => $emp->employee_number,
                    'name' => $emp->profile?->first_name . ' ' . $emp->profile?->last_name,
                ];
            });

        // Build balances query with real data
        $balancesQuery = LeaveBalance::with([
            'employee.profile:id,first_name,last_name',
            'employee.department:id,name',
            'leavePolicy:id,name,code',
        ])
            ->whereHas('employee', fn($q) => $q->where('status', 'active'))
            ->where('year', $selectedYear);

        if ($employeeId) {
            $balancesQuery->where('employee_id', $employeeId);
        }

        // Get all balances for summary calculations (before pagination)
        $allBalances = $balancesQuery->get();

        // Determine if sorting by nested leave balance fields or group-level fields
        $sortByNestedField = in_array($sortBy, ['earned', 'used', 'remaining', 'carried_forward', 'leave_type']);

        if ($sortByNestedField) {
            // Sort flat collection by nested field BEFORE grouping
            if ($sortBy === 'leave_type') {
                $sortedBalances = $allBalances->sortBy(function($bal) { 
                    return $bal->leavePolicy->name; 
                }, SORT_REGULAR, $sortDirection === 'desc');
            } else {
                // Numeric fields: earned, used, remaining, carried_forward
                $sortedBalances = $allBalances->sortBy($sortBy, SORT_REGULAR, $sortDirection === 'desc');
            }
            
            // NOW group after sorting
            $groupedBalances = $sortedBalances->groupBy('employee_id')
                ->map(function ($employeeBalances) {
                    $employee = $employeeBalances->first()->employee;
                    
                    return [
                        'id' => $employee->id,
                        'employee_number' => $employee->employee_number,
                        'name' => $employee->profile?->first_name . ' ' . $employee->profile?->last_name,
                        'department' => $employee->department?->name,
                        'balances' => $employeeBalances->map(fn($bal) => [
                            'type' => $bal->leavePolicy->code,
                            'name' => $bal->leavePolicy->name,
                            'earned' => (float) $bal->earned,
                            'used' => (float) $bal->used,
                            'remaining' => (float) $bal->remaining,
                            'carried_forward' => (float) $bal->carried_forward,
                        ])->values(),
                    ];
                })->values();
        } else {
            // Group by employee first
            $groupedBalances = $allBalances->groupBy('employee_id')
                ->map(function ($employeeBalances) {
                    $employee = $employeeBalances->first()->employee;
                    
                    return [
                        'id' => $employee->id,
                        'employee_number' => $employee->employee_number,
                        'name' => $employee->profile?->first_name . ' ' . $employee->profile?->last_name,
                        'department' => $employee->department?->name,
                        'balances' => $employeeBalances->map(fn($bal) => [
                            'type' => $bal->leavePolicy->code,
                            'name' => $bal->leavePolicy->name,
                            'earned' => (float) $bal->earned,
                            'used' => (float) $bal->used,
                            'remaining' => (float) $bal->remaining,
                            'carried_forward' => (float) $bal->carried_forward,
                        ])->values(),
                    ];
                })->values();

            // Sort $groupedBalances by group-level field (name, department, number)
            $groupedBalances = $groupedBalances->sortBy(function ($item) use ($sortBy) {
                return match ($sortBy) {
                    'name'       => $item['name'],
                    'department' => $item['department'],
                    'number'     => $item['employee_number'],
                    default      => $item['name'],
                };
            }, SORT_REGULAR, $sortDirection === 'desc');
        }

        // Apply pagination AFTER grouping
        $paginated = new LengthAwarePaginator(
            $groupedBalances->forPage($page, $perPage)->values(),
            $groupedBalances->count(),
            $perPage,
            $page,
            [
                'path' => route('hr.leave.balances'),
                'query' => $request->query(),
            ]
        );

        // Calculate summary statistics
        $summary = [
            'total_employees' => $groupedBalances->count(),
            'total_earned' => (float) $allBalances->sum('earned'),
            'total_used' => (float) $allBalances->sum('used'),
            'total_remaining' => (float) $allBalances->sum(fn($b) => $b->remaining),
        ];

        return Inertia::render('HR/Leave/Balances', [
            'balances' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
                'has_more_pages' => $paginated->hasMorePages(),
            ],
            'employees' => $employees,
            'selectedYear' => $selectedYear,
            'selectedEmployeeId' => $employeeId,
            'years' => $years,
            'summary' => $summary,
            'sortBy'        => $sortBy,           // ← pass back to frontend
            'sortDirection' => $sortDirection,    // ← pass back to frontend
        ]);
    }

    /**
     * Get leave balances for a specific employee for the current year.
     * Used to display actual leave balance when creating/editing leave requests.
     */
    public function getEmployeeBalances(int $employeeId)
    {
        $employee = Employee::findOrFail($employeeId);
        $currentYear = now()->year;
        
        // Get all leave balances for this employee for the current year
        $balances = LeaveBalance::with('leavePolicy')
            ->where('employee_id', $employeeId)
            ->where('year', $currentYear)
            ->get()
            ->map(function ($balance) {
                return [
                    'leave_policy_id' => $balance->leave_policy_id,
                    'leave_type_name' => $balance->leavePolicy?->name,
                    'earned' => (float) $balance->earned,
                    'used' => (float) $balance->used,
                    'carried_forward' => (float) $balance->carried_forward,
                    'remaining' => (float) $balance->remaining,
                ];
            });
        
        return response()->json([
            'employee_id' => $employeeId,
            'year' => $currentYear,
            'balances' => $balances,
        ]);
    }
}
