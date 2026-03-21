<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdvancesController extends Controller
{
    /**
     * Display a listing of cash advances
     */
    public function index(Request $request)
    {
        // Query cash advances from database (using EmployeeLoan model with loan_type = 'cash_advance')
        $query = EmployeeLoan::with([
            'employee.user',
            'employee.department',
            'createdBy'
        ])
        ->where('loan_type', 'cash_advance');

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('employee.user', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%");
            })
            ->orWhereHas('employee', function ($q) use ($search) {
                $q->where('employee_number', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if ($request->filled('status')) {
            $status = $request->status;
            // Map frontend status to database status
            // Handle both approval_status (pending, approved, rejected) and deduction_status (active, completed, cancelled)
            if (in_array($status, ['pending', 'approved', 'rejected'])) {
                $query->where('status', $status);
            } elseif (in_array($status, ['active', 'completed', 'cancelled'])) {
                // For now, status column handles deduction status as well
                $query->where('status', $status);
            }
        }

        // Apply department filter
        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }

        // Paginate and get advances
        $advances = $query->latest('loan_date')->paginate(15);

        // Transform advances to frontend shape
        $advances->getCollection()->transform(function ($advance) {
            $employee = $advance->employee;
            $employeeName = $employee?->user?->full_name ?? 'N/A';
            $employeeNumber = $employee?->employee_number ?? 'N/A';
            $departmentName = $employee?->department?->name ?? 'N/A';

            return [
                'id' => $advance->id,
                'employee_id' => $advance->employee_id,
                'employee_name' => $employeeName,
                'employee_number' => $employeeNumber,
                'department_id' => $employee?->department_id,
                'department_name' => $departmentName,
                'advance_type' => $advance->loan_type_label ?? 'Cash Advance',
                'amount_requested' => (float) $advance->principal_amount,
                'amount_approved' => (float) $advance->total_loan_amount,
                'approval_status' => $this->mapApprovalStatus($advance->status),
                'approval_status_label' => ucfirst(str_replace('_', ' ', $this->mapApprovalStatus($advance->status))),
                'deduction_status' => $advance->status,
                'deduction_status_label' => ucfirst(str_replace('_', ' ', $advance->status)),
                'remaining_balance' => (float) $advance->remaining_balance,
                'number_of_installments' => $advance->number_of_installments,
                'installments_completed' => $advance->installments_paid ?? 0,
                'requested_date' => $advance->loan_date?->format('Y-m-d'),
                'purpose' => $advance->notes,
                'created_at' => $advance->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $advance->updated_at?->format('Y-m-d H:i:s'),
            ];
        });

        // Get employees list for dropdown (all active employees)
        $employees = Employee::with('user.profile', 'department')
            ->where('status', 'active')
            ->get()
            ->map(function ($emp) {
                $user = $emp->user;
                $profile = $user?->profile;
                $name = null;
                if ($profile) {
                    $first = trim((string) ($profile->first_name ?? ''));
                    $last = trim((string) ($profile->last_name ?? ''));
                    $full = trim($first . ' ' . $last);
                    $name = $full !== '' ? $full : null;
                }
                if (!$name && $user) {
                    $name = $user->name ?: $user->username ?: 'N/A';
                }
                if (!$name) {
                    $name = 'N/A';
                }
                return [
                    'id' => $emp->id,
                    'name' => $name,
                    'employee_number' => $emp->employee_number,
                    'department' => $emp->department?->name ?? 'N/A',
                ];
            })
            ->values();

        // Get departments for dropdown
        $departments = Department::select('id', 'name')
            ->where('is_active', true)
            ->get();

        return Inertia::render('Payroll/Advances/Index', [
            'advances' => $advances,
            'employees' => $employees,
            'departments' => $departments,
            'filters' => $request->only(['search', 'status', 'department_id']),
            'statuses' => ['pending', 'approved', 'rejected', 'active', 'completed', 'cancelled'],
        ]);
    }

    /**
     * Map loan status to approval_status for frontend compatibility
     */
    private function mapApprovalStatus($status)
    {
        return match ($status) {
            'pending' => 'pending',
            'approved' => 'approved',
            'rejected' => 'rejected',
            'active' => 'approved',
            'completed' => 'approved',
            'cancelled' => 'rejected',
            'defaulted' => 'rejected',
            default => 'pending',
        };
    }

    /**
     * Display a listing of cash advances (OLD MOCK CODE - REPLACED)
     * DEPRECATED - Use new index() method above
     */
    private function indexOldMock(Request $request)
    {
        // OLD MOCK CODE - DEPRECATED - Kept for reference only
        $advances = [
            [
                'id' => 'ADV001',
                'employee_id' => 'EMP001',
                'employee_name' => 'Juan dela Cruz',
                'employee_number' => 'EMP-2023-001',
                'department_id' => 'DEPT-ENG',
                'department_name' => 'Engineering',
                'advance_type' => 'Cash Advance',
                'amount_requested' => 50000.00,
                'amount_approved' => 50000.00,
                'approval_status' => 'approved',
                'approval_status_label' => 'Approved',
                'approval_status_color' => 'blue',
                'approved_by' => 'HR Manager',
                'approved_at' => now()->subDays(15)->toDateTimeString(),
                'approval_notes' => 'Approved for emergency home repair',
                'deduction_status' => 'active',
                'deduction_status_label' => 'Active',
                'remaining_balance' => 30000.00,
                'deduction_schedule' => 'installments',
                'number_of_installments' => 5,
                'installments_completed' => 2,
                'requested_date' => now()->subDays(20)->toDateString(),
                'purpose' => 'Home repair and maintenance',
                'priority_level' => 'normal',
                'supporting_documents' => [],
                'created_by' => 'Juan dela Cruz',
                'created_at' => now()->subDays(20)->toDateTimeString(),
                'updated_by' => 'HR Manager',
                'updated_at' => now()->subDays(15)->toDateTimeString(),
            ],
            [
                'id' => 'ADV002',
                'employee_id' => 'EMP002',
                'employee_name' => 'Maria Santos',
                'employee_number' => 'EMP-2023-002',
                'department_id' => 'DEPT-FIN',
                'department_name' => 'Finance',
                'advance_type' => 'Travel Advance',
                'amount_requested' => 35000.00,
                'amount_approved' => 35000.00,
                'approval_status' => 'approved',
                'approval_status_label' => 'Approved',
                'approval_status_color' => 'blue',
                'approved_by' => 'CFO',
                'approved_at' => now()->subDays(10)->toDateTimeString(),
                'approval_notes' => 'Approved for business trip to Manila',
                'deduction_status' => 'active',
                'deduction_status_label' => 'Active',
                'remaining_balance' => 17500.00,
                'deduction_schedule' => 'installments',
                'number_of_installments' => 2,
                'installments_completed' => 1,
                'requested_date' => now()->subDays(15)->toDateString(),
                'purpose' => 'Business trip travel and accommodation',
                'priority_level' => 'normal',
                'supporting_documents' => [],
                'created_by' => 'Maria Santos',
                'created_at' => now()->subDays(15)->toDateTimeString(),
                'updated_by' => 'CFO',
                'updated_at' => now()->subDays(10)->toDateTimeString(),
            ],
            [
                'id' => 'ADV003',
                'employee_id' => 'EMP003',
                'employee_name' => 'Carlos Reyes',
                'employee_number' => 'EMP-2023-003',
                'department_id' => 'DEPT-OPS',
                'department_name' => 'Operations',
                'advance_type' => 'Medical Advance',
                'amount_requested' => 25000.00,
                'amount_approved' => 20000.00,
                'approval_status' => 'approved',
                'approval_status_label' => 'Approved',
                'approval_status_color' => 'blue',
                'approved_by' => 'HR Manager',
                'approved_at' => now()->subDays(8)->toDateTimeString(),
                'approval_notes' => 'Partial approval limited to 20000',
                'deduction_status' => 'active',
                'deduction_status_label' => 'Active',
                'remaining_balance' => 20000.00,
                'deduction_schedule' => 'installments',
                'number_of_installments' => 4,
                'installments_completed' => 0,
                'requested_date' => now()->subDays(12)->toDateString(),
                'purpose' => 'Medical treatment for surgery',
                'priority_level' => 'urgent',
                'supporting_documents' => [],
                'created_by' => 'Carlos Reyes',
                'created_at' => now()->subDays(12)->toDateTimeString(),
                'updated_by' => 'HR Manager',
                'updated_at' => now()->subDays(8)->toDateTimeString(),
            ],
            [
                'id' => 'ADV004',
                'employee_id' => 'EMP004',
                'employee_name' => 'Ana Garcia',
                'employee_number' => 'EMP-2023-004',
                'department_id' => 'DEPT-SAL',
                'department_name' => 'Sales',
                'advance_type' => 'Cash Advance',
                'amount_requested' => 15000.00,
                'amount_approved' => 15000.00,
                'approval_status' => 'pending',
                'approval_status_label' => 'Pending',
                'approval_status_color' => 'yellow',
                'approved_by' => null,
                'approved_at' => null,
                'approval_notes' => null,
                'deduction_status' => 'pending',
                'deduction_status_label' => 'Pending',
                'remaining_balance' => 15000.00,
                'deduction_schedule' => null,
                'number_of_installments' => null,
                'installments_completed' => 0,
                'requested_date' => now()->toDateString(),
                'purpose' => 'Emergency household expenses',
                'priority_level' => 'urgent',
                'supporting_documents' => [],
                'created_by' => 'Ana Garcia',
                'created_at' => now()->toDateTimeString(),
                'updated_by' => 'Ana Garcia',
                'updated_at' => now()->toDateTimeString(),
            ],
            [
                'id' => 'ADV005',
                'employee_id' => 'EMP005',
                'employee_name' => 'Miguel Torres',
                'employee_number' => 'EMP-2023-005',
                'department_id' => 'DEPT-ENG',
                'department_name' => 'Engineering',
                'advance_type' => 'Equipment Advance',
                'amount_requested' => 45000.00,
                'amount_approved' => 0.00,
                'approval_status' => 'rejected',
                'approval_status_label' => 'Rejected',
                'approval_status_color' => 'red',
                'approved_by' => 'Engineering Manager',
                'approved_at' => now()->subDays(5)->toDateTimeString(),
                'approval_notes' => 'Equipment purchase should go through procurement',
                'deduction_status' => 'cancelled',
                'deduction_status_label' => 'Cancelled',
                'remaining_balance' => 0.00,
                'deduction_schedule' => null,
                'number_of_installments' => null,
                'installments_completed' => 0,
                'requested_date' => now()->subDays(7)->toDateString(),
                'purpose' => 'Laptop and technical equipment',
                'priority_level' => 'normal',
                'supporting_documents' => [],
                'created_by' => 'Miguel Torres',
                'created_at' => now()->subDays(7)->toDateTimeString(),
                'updated_by' => 'Engineering Manager',
                'updated_at' => now()->subDays(5)->toDateTimeString(),
            ],
            [
                'id' => 'ADV006',
                'employee_id' => 'EMP006',
                'employee_name' => 'Rosa Mendoza',
                'employee_number' => 'EMP-2023-006',
                'department_id' => 'DEPT-MAR',
                'department_name' => 'Marketing',
                'advance_type' => 'Travel Advance',
                'amount_requested' => 28000.00,
                'amount_approved' => 28000.00,
                'approval_status' => 'approved',
                'approval_status_label' => 'Approved',
                'approval_status_color' => 'blue',
                'approved_by' => 'Marketing Manager',
                'approved_at' => now()->subDays(3)->toDateTimeString(),
                'approval_notes' => 'Approved for conference attendance',
                'deduction_status' => 'active',
                'deduction_status_label' => 'Active',
                'remaining_balance' => 28000.00,
                'deduction_schedule' => 'single_period',
                'number_of_installments' => 1,
                'installments_completed' => 0,
                'requested_date' => now()->subDays(5)->toDateString(),
                'purpose' => 'Annual marketing conference Bangkok',
                'priority_level' => 'normal',
                'supporting_documents' => [],
                'created_by' => 'Rosa Mendoza',
                'created_at' => now()->subDays(5)->toDateTimeString(),
                'updated_by' => 'Marketing Manager',
                'updated_at' => now()->subDays(3)->toDateTimeString(),
            ],
            [
                'id' => 'ADV007',
                'employee_id' => 'EMP007',
                'employee_name' => 'Luis Fernandez',
                'employee_number' => 'EMP-2023-007',
                'department_id' => 'DEPT-FIN',
                'department_name' => 'Finance',
                'advance_type' => 'Cash Advance',
                'amount_requested' => 60000.00,
                'amount_approved' => 60000.00,
                'approval_status' => 'approved',
                'approval_status_label' => 'Approved',
                'approval_status_color' => 'blue',
                'approved_by' => 'CFO',
                'approved_at' => now()->subDays(30)->toDateTimeString(),
                'approval_notes' => 'Approved for family emergency',
                'deduction_status' => 'completed',
                'deduction_status_label' => 'Completed',
                'remaining_balance' => 0.00,
                'deduction_schedule' => 'installments',
                'number_of_installments' => 6,
                'installments_completed' => 6,
                'requested_date' => now()->subDays(35)->toDateString(),
                'purpose' => 'Family emergency unexpected medical bills',
                'priority_level' => 'urgent',
                'supporting_documents' => [],
                'created_by' => 'Luis Fernandez',
                'created_at' => now()->subDays(35)->toDateTimeString(),
                'updated_by' => 'CFO',
                'updated_at' => now()->subDays(1)->toDateTimeString(),
            ],
            [
                'id' => 'ADV008',
                'employee_id' => 'EMP008',
                'employee_name' => 'Patricia Diaz',
                'employee_number' => 'EMP-2023-008',
                'department_id' => 'DEPT-HR',
                'department_name' => 'Human Resources',
                'advance_type' => 'Cash Advance',
                'amount_requested' => 20000.00,
                'amount_approved' => null,
                'approval_status' => 'pending',
                'approval_status_label' => 'Pending',
                'approval_status_color' => 'yellow',
                'approved_by' => null,
                'approved_at' => null,
                'approval_notes' => null,
                'deduction_status' => 'pending',
                'deduction_status_label' => 'Pending',
                'remaining_balance' => 20000.00,
                'deduction_schedule' => null,
                'number_of_installments' => null,
                'installments_completed' => 0,
                'requested_date' => now()->subDays(2)->toDateString(),
                'purpose' => 'Child school fees and supplies',
                'priority_level' => 'normal',
                'supporting_documents' => [],
                'created_by' => 'Patricia Diaz',
                'created_at' => now()->subDays(2)->toDateTimeString(),
                'updated_by' => 'Patricia Diaz',
                'updated_at' => now()->subDays(2)->toDateTimeString(),
            ],
        ];
    }

    /**
     * Show the form for creating a new cash advance request
     */
    public function create()
    {
        return response()->json([
            'employees' => $this->getEmployeesList(),
            'advance_types' => [
                'Cash Advance',
                'Equipment Advance',
                'Travel Advance',
                'Medical Advance',
            ],
        ]);
    }

    /**
     * Store a newly created cash advance in storage
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id'      => 'required|integer|exists:employees,id',
            'advance_type'     => 'required|string|in:cash_advance,equipment_advance,travel_advance,medical_advance',
            'amount_requested' => 'required|numeric|min:1',
            'purpose'          => 'required|string|max:1000',
            'requested_date'   => 'required|date',
            'priority_level'   => 'nullable|in:normal,urgent',
        ]);

        // Map advance_type to a human-readable label
        $typeLabels = [
            'cash_advance'      => 'Cash Advance',
            'equipment_advance' => 'Equipment Advance',
            'travel_advance'    => 'Travel Advance',
            'medical_advance'   => 'Medical Advance',
        ];

        try {
            $advance = EmployeeLoan::create([
                'employee_id'         => $validated['employee_id'],
                'loan_type'           => $validated['advance_type'],
                'loan_type_label'     => $typeLabels[$validated['advance_type']] ?? 'Cash Advance',
                'loan_number'         => 'ADV-' . now()->format('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT),
                'principal_amount'    => $validated['amount_requested'],
                'total_loan_amount'   => $validated['amount_requested'],
                'remaining_balance'   => $validated['amount_requested'],
                'loan_date'           => $validated['requested_date'],
                'status'              => 'pending',
                'notes'               => $validated['purpose'],
                'created_by'          => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Advance request submitted successfully',
                'data'    => ['id' => $advance->id],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create advance request: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve a pending cash advance
     */
    public function approve(Request $request, $id)
    {
        $validated = $request->validate([
            'amount_approved'        => 'required|numeric|min:0',
            'deduction_schedule'     => 'required|in:single_period,installments',
            'number_of_installments' => 'required|integer|min:1|max:24',
            'approval_notes'         => 'nullable|string|max:1000',
        ]);

        $advance = EmployeeLoan::where('loan_type', 'cash_advance')
            ->where('status', 'pending')
            ->findOrFail($id);

        try {
            $amountApproved  = (float) $validated['amount_approved'];
            $installments    = (int) $validated['number_of_installments'];
            $installmentAmt  = $installments > 0 ? round($amountApproved / $installments, 2) : $amountApproved;

            $advance->update([
                'total_loan_amount'       => $amountApproved,
                'remaining_balance'       => $amountApproved,
                'number_of_installments'  => $installments,
                'installment_amount'      => $installmentAmt,
                'status'                  => 'active',
                'notes'                   => $advance->notes . ($validated['approval_notes'] ? "\n[Approval note] " . $validated['approval_notes'] : ''),
                'updated_by'              => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Advance approved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve advance: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a pending cash advance
     */
    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'approval_notes' => 'required|string|min:10|max:1000',
        ]);

        $advance = EmployeeLoan::where('loan_type', 'cash_advance')
            ->where('status', 'pending')
            ->findOrFail($id);

        try {
            $advance->update([
                'status'     => 'cancelled',
                'notes'      => $advance->notes . "\n[Rejection reason] " . $validated['approval_notes'],
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Advance rejected successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject advance: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of employees for advance requests (from database)
     */
    private function getEmployeesList()
    {
        return Employee::with('user', 'department')
            ->where('status', 'active')
            ->get()
            ->map(function ($emp) {
                return [
                    'id' => $emp->id,
                    'name' => $emp->user?->full_name ?? 'N/A',
                    'employee_number' => $emp->employee_number,
                    'department' => $emp->department?->name ?? 'N/A',
                ];
            })
            ->values()
            ->toArray();
    }
}
