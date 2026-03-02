<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use App\Models\Employee;
use App\Models\Department;
use App\Http\Requests\HR\Timekeeping\StoreOvertimeRequest;
use App\Http\Requests\HR\Timekeeping\UpdateOvertimeRequest;
use App\Http\Requests\HR\Timekeeping\ProcessOvertimeRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;

class OvertimeController extends Controller
{
    /**
     * Display a listing of overtime records with real database data.
     */
    public function index(Request $request): Response
    {
        // Build query with eager loading
        $query = OvertimeRequest::with([
            'employee:id,employee_number,profile_id,department_id',
            'employee.profile:id,first_name,last_name',
            'employee.department:id,name',
            'approver:id,name',
            'creator:id,name',
        ]);
        
        // Apply filters
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        
        if ($request->filled('department_id')) {
            $query->whereHas('employee', function($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('request_date', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('request_date', '<=', $request->date_to);
        }
        
        // Paginate results
        $overtime = $query->orderBy('request_date', 'desc')
            ->paginate(20)
            ->through(function ($record) {
                return [
                    'id' => $record->id,
                    'employee_id' => $record->employee->id,
                    'employee_name' => $record->employee->profile->first_name . ' ' . $record->employee->profile->last_name,
                    'employee_number' => $record->employee->employee_number,
                    'overtime_date' => $record->request_date->format('Y-m-d'),
                    'start_time' => $record->planned_start_time->format('H:i:s'),
                    'end_time' => $record->planned_end_time->format('H:i:s'),
                    'planned_hours' => $record->planned_hours,
                    'actual_hours' => $record->actual_hours,
                    'reason' => $record->reason,
                    'status' => $record->status,
                    'department_id' => $record->employee->department->id ?? null,
                    'department_name' => $record->employee->department->name ?? 'N/A',
                    'approved_by' => $record->approver?->name,
                    'approved_at' => $record->approved_at?->format('Y-m-d H:i:s'),
                    'created_by' => $record->creator->name,
                    'created_at' => $record->created_at->format('Y-m-d H:i:s'),
                ];
            });
        
        // Calculate summary from database
        $summary = [
            'total_records' => OvertimeRequest::count(),
            'pending' => OvertimeRequest::where('status', 'pending')->count(),
            'approved' => OvertimeRequest::where('status', 'approved')->count(),
            'in_progress' => OvertimeRequest::where('status', 'approved')->count(),
            'completed' => OvertimeRequest::where('status', 'completed')->count(),
            'rejected' => OvertimeRequest::where('status', 'rejected')->count(),
            'total_ot_hours' => OvertimeRequest::whereIn('status', ['completed'])->sum('actual_hours') ?: OvertimeRequest::sum('planned_hours'),
        ];

        return Inertia::render('HR/Timekeeping/Overtime/Index', [
            'overtime' => $overtime,
            'summary' => $summary,
            'filters' => $request->only(['employee_id', 'department_id', 'status', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Show the form for creating a new overtime record.
     */
    public function create(): Response
    {
        // Get active employees with departments
        $employees = Employee::with('profile:id,first_name,last_name', 'department:id,name')
            ->where('employment_status', 'active')
            ->get()
            ->map(fn($emp) => [
                'id' => $emp->id,
                'name' => $emp->profile->first_name . ' ' . $emp->profile->last_name,
                'employee_number' => $emp->employee_number,
                'department_id' => $emp->department?->id,
                'department_name' => $emp->department?->name ?? 'N/A',
            ]);
        
        // Get all departments
        $departments = Department::select('id', 'name')->get();
        
        return Inertia::render('HR/Timekeeping/Overtime/Create', [
            'employees' => $employees,
            'departments' => $departments,
        ]);
    }

    /**
     * Store a newly created overtime record.
     */
    public function store(StoreOvertimeRequest $request): JsonResponse
    {
        // Create overtime request with validated data
        $overtimeRequest = OvertimeRequest::create([
            'employee_id' => $request->employee_id,
            'request_date' => $request->request_date,
            'planned_start_time' => $request->planned_start_time,
            'planned_end_time' => $request->planned_end_time,
            'planned_hours' => $request->planned_hours,
            'reason' => $request->reason,
            'status' => $request->status ?? 'pending',
            'created_by' => Auth::id(),
        ]);
        
        // Load relationships for response
        $overtimeRequest->load('employee.profile', 'employee.department', 'creator');
        
        return response()->json([
            'success' => true,
            'message' => 'Overtime request created successfully',
            'data' => [
                'id' => $overtimeRequest->id,
                'employee_name' => $overtimeRequest->employee->profile->first_name . ' ' . 
                                  $overtimeRequest->employee->profile->last_name,
                'employee_id' => $overtimeRequest->employee_id,
                'request_date' => $overtimeRequest->request_date->format('Y-m-d'),
                'planned_hours' => $overtimeRequest->planned_hours,
                'status' => $overtimeRequest->status,
                'created_at' => $overtimeRequest->created_at->format('Y-m-d H:i:s'),
            ],
        ], 201);
    }

    /**
     * Display the specified overtime record.
     */
    public function show(int $id): Response
    {
        $record = OvertimeRequest::with([
            'employee:id,employee_number,profile_id,department_id',
            'employee.profile:id,first_name,last_name',
            'employee.department:id,name',
            'approver:id,name',
            'creator:id,name',
        ])->findOrFail($id);
        
        $statusHistory = [
            [
                'id' => 1,
                'status' => 'pending',
                'changed_by' => $record->creator->name,
                'changed_at' => $record->created_at->format('Y-m-d H:i:s'),
                'notes' => 'Request created',
            ],
        ];
        
        if ($record->approved_at) {
            $statusHistory[] = [
                'id' => 2,
                'status' => $record->status,
                'changed_by' => $record->approver->name,
                'changed_at' => $record->approved_at->format('Y-m-d H:i:s'),
                'notes' => $record->status === 'rejected' ? $record->rejection_reason : 'Request approved',
            ];
        }
        
        return Inertia::render('HR/Timekeeping/Overtime/Show', [
            'overtime' => [
                'id' => $record->id,
                'employee_name' => $record->employee->profile->first_name . ' ' . $record->employee->profile->last_name,
                'employee_number' => $record->employee->employee_number,
                'department_name' => $record->employee->department->name ?? 'N/A',
                'overtime_date' => $record->request_date->format('Y-m-d'),
                'start_time' => $record->planned_start_time->format('H:i:s'),
                'end_time' => $record->planned_end_time->format('H:i:s'),
                'planned_hours' => $record->planned_hours,
                'actual_hours' => $record->actual_hours,
                'reason' => $record->reason,
                'status' => $record->status,
                'approved_by' => $record->approver?->name,
                'approved_at' => $record->approved_at?->format('Y-m-d H:i:s'),
                'rejection_reason' => $record->rejection_reason,
                'created_by' => $record->creator->name,
                'created_at' => $record->created_at->format('Y-m-d H:i:s'),
            ],
            'status_history' => $statusHistory,
        ]);
    }

    /**
     * Show the form for editing the specified overtime record.
     */
    public function edit(int $id): Response
    {
        $record = OvertimeRequest::with([
            'employee:id,employee_number,profile_id,department_id',
            'employee.profile:id,first_name,last_name',
            'employee.department:id,name',
        ])->findOrFail($id);
        
        // Get active employees
        $employees = Employee::with('profile:id,first_name,last_name', 'department:id,name')
            ->where('employment_status', 'active')
            ->get()
            ->map(fn($emp) => [
                'id' => $emp->id,
                'name' => $emp->profile->first_name . ' ' . $emp->profile->last_name,
                'employee_number' => $emp->employee_number,
                'department_id' => $emp->department?->id,
                'department_name' => $emp->department?->name ?? 'N/A',
            ]);
        
        $departments = Department::select('id', 'name')->get();
        
        return Inertia::render('HR/Timekeeping/Overtime/Edit', [
            'overtime' => [
                'id' => $record->id,
                'employee_id' => $record->employee->id,
                'request_date' => $record->request_date->format('Y-m-d'),
                'planned_start_time' => $record->planned_start_time->format('H:i:s'),
                'planned_end_time' => $record->planned_end_time->format('H:i:s'),
                'planned_hours' => $record->planned_hours,
                'actual_hours' => $record->actual_hours,
                'reason' => $record->reason,
                'status' => $record->status,
            ],
            'employees' => $employees,
            'departments' => $departments,
        ]);
    }

    /**
     * Update the specified overtime record.
     */
    public function update(UpdateOvertimeRequest $request, int $id): JsonResponse
    {
        $overtimeRequest = OvertimeRequest::findOrFail($id);
        
        // Update the record with validated data
        $overtimeRequest->update($request->validated());
        
        // Load relationships for response
        $overtimeRequest->load('employee.profile', 'employee.department', 'creator');
        
        return response()->json([
            'success' => true,
            'message' => 'Overtime record updated successfully',
            'data' => [
                'id' => $overtimeRequest->id,
                'employee_name' => $overtimeRequest->employee->profile->first_name . ' ' . 
                                  $overtimeRequest->employee->profile->last_name,
                'status' => $overtimeRequest->status,
                'planned_hours' => $overtimeRequest->planned_hours,
                'actual_hours' => $overtimeRequest->actual_hours,
                'request_date' => $overtimeRequest->request_date->format('Y-m-d'),
                'planned_start_time' => $overtimeRequest->planned_start_time->format('H:i:s'),
                'planned_end_time' => $overtimeRequest->planned_end_time->format('H:i:s'),
                'reason' => $overtimeRequest->reason,
                'updated_at' => $overtimeRequest->updated_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Remove the specified overtime record.
     */
    public function destroy(int $id): JsonResponse
    {
        $overtimeRequest = OvertimeRequest::findOrFail($id);
        
        // Only allow deletion of pending requests
        if (!$overtimeRequest->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending overtime requests can be deleted.',
            ], 403);
        }
        
        $overtimeRequest->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Overtime request deleted successfully',
        ]);
    }

    /**
     * Process/mark overtime as completed or update status.
     */
    public function processOvertime(ProcessOvertimeRequest $request, int $id): JsonResponse
    {
        $overtimeRequest = OvertimeRequest::findOrFail($id);
        
        $status = $request->status;
        $userId = Auth::id();
        
        // Execute appropriate action based on status
        match ($status) {
            'approved' => $overtimeRequest->approve($userId),
            'rejected' => $overtimeRequest->reject($userId, $request->rejection_reason),
            'completed' => $overtimeRequest->complete(
                $request->actual_hours,
                $request->actual_start_time ? Carbon::parse($request->actual_start_time) : null,
                $request->actual_end_time ? Carbon::parse($request->actual_end_time) : null
            ),
            default => null,
        };
        
        // Refresh model to get updated data
        $overtimeRequest->refresh();
        
        return response()->json([
            'success' => true,
            'message' => "Overtime request {$status} successfully",
            'data' => [
                'id' => $overtimeRequest->id,
                'status' => $overtimeRequest->status,
                'approved_by' => $overtimeRequest->approver?->name,
                'approved_at' => $overtimeRequest->approved_at?->format('Y-m-d H:i:s'),
                'rejection_reason' => $overtimeRequest->rejection_reason,
                'actual_hours' => $overtimeRequest->actual_hours,
            ],
        ]);
    }

    /**
     * Get overtime budget information for a department.
     */
    public function getBudget(int $departmentId): JsonResponse
    {
        $department = Department::findOrFail($departmentId);
        
        // Calculate overtime hours for this month
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        
        $usedHours = OvertimeRequest::whereHas('employee', function($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            })
            ->whereIn('status', ['completed'])
            ->whereBetween('request_date', [$startOfMonth, $endOfMonth])
            ->sum('actual_hours');
        
        // Hardcoded monthly allocation (can be made configurable later)
        $allocatedHours = 200; // 200 hours per month per department
        $availableHours = max(0, $allocatedHours - $usedHours);
        $percentage = $allocatedHours > 0 ? round(($usedHours / $allocatedHours) * 100, 1) : 0;
        
        return response()->json([
            'success' => true,
            'data' => [
                'department_id' => $departmentId,
                'department_name' => $department->name,
                'allocated_hours' => $allocatedHours,
                'used_hours' => round($usedHours, 2),
                'available_hours' => round($availableHours, 2),
                'utilization_percentage' => $percentage,
                'is_over_budget' => $percentage > 100,
                'near_limit' => $percentage > 90,
                'period' => now()->format('F Y'),
            ],
        ]);
    }
}
