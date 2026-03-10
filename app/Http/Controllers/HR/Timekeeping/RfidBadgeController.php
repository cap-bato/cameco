<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\Timekeeping\DeactivateBadgeRequest;
use App\Http\Requests\HR\Timekeeping\ReplaceBadgeRequest;
use App\Models\BadgeIssueLog;
use App\Models\Employee;
use App\Models\RfidCardMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class RfidBadgeController extends Controller
{
    /**
     * Display a listing of badges.
     * Task 2.3.1: Implement index() Method with Filters
     * 
     * Fetches badges from database with:
     * - Employee and department relationships
     * - Search filtering (card_uid, employee name, employee_id)
     * - Status filtering (active, inactive, expired, expiring_soon)
     * - Department and card_type filtering
     * - Pagination and statistics
     */
    public function index(Request $request)
    {
        // Authorization: HR Staff + HR Manager only
        abort_unless(
            auth()->user()->can('hr.timekeeping.badges.view'),
            403,
            'You do not have permission to view badges.'
        );

        try {
            // Build query with relationships
            $query = RfidCardMapping::query()
                ->with(['employee.department', 'issuedBy'])
                ->latest('created_at');

            // Search filter: card_uid, employee name, or employee_id
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('card_uid', 'like', "%{$search}%")
                        ->orWhereHas('employee', function ($q) use ($search) {
                            $q->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', "%{$search}%")
                              ->orWhere('employee_number', 'like', "%{$search}%");
                        });
                });
            }

            // Status filter: active, inactive, expired, expiring_soon
            if ($request->filled('status')) {
                $status = $request->input('status');
                switch ($status) {
                    case 'active':
                        $query->active();
                        break;
                    case 'inactive':
                        $query->inactive();
                        break;
                    case 'expired':
                        $query->expired();
                        break;
                    case 'expiring_soon':
                        $query->expiringSoon(30);
                        break;
                }
            }

            // Department filter
            if ($request->filled('department')) {
                $departmentId = $request->input('department');
                $query->whereHas('employee', function ($q) use ($departmentId) {
                    $q->where('department_id', $departmentId);
                });
            }

            // Card type filter
            if ($request->filled('card_type')) {
                $query->where('card_type', $request->input('card_type'));
            }

            // Paginate results
            $badges = $query->paginate($request->input('per_page', 25));

            // Calculate statistics
            $employeesWithoutBadges = $this->getEmployeesWithoutBadgesCount();
            $expiringSoon = RfidCardMapping::expiringSoon(30)->count();
            
            $stats = [
                'total' => RfidCardMapping::count(),
                'active' => RfidCardMapping::active()->count(),
                'inactive' => RfidCardMapping::inactive()->count(),
                'expiring_soon' => $expiringSoon,
                'expiringSoon' => $expiringSoon, // camelCase for frontend
                'employees_without_badges' => $employeesWithoutBadges,
                'employeesWithoutBadges' => $employeesWithoutBadges, // camelCase for frontend
            ];

            // Get all active employees
            $allActiveEmployees = Employee::where('status', 'active')
                ->with(['department', 'rfidCardMappings' => fn($q) => $q->where('is_active', true)])
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
                ->map(fn($emp) => [
                    'id' => $emp->id,
                    'name' => $emp->full_name,
                    'employee_id' => $emp->employee_number,
                    'department' => $emp->department?->name ?? 'N/A',
                    'position' => $emp->position,
                    'hire_date' => $emp->hire_date?->format('Y-m-d'),
                    'photo' => $emp->photo_url,
                ]);

            // Get employees without active badges
            $employeesWithoutActiveBadges = Employee::where('status', 'active')
                ->whereDoesntHave('rfidCardMappings', fn($q) => $q->where('is_active', true))
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
                ->map(fn($emp) => [
                    'id' => $emp->id,
                    'name' => $emp->full_name,
                    'employee_id' => $emp->employee_number,
                    'department' => $emp->department?->name ?? 'N/A',
                    'position' => $emp->position,
                    'hire_date' => $emp->hire_date?->format('Y-m-d'),
                    'photo' => $emp->photo_url,
                ]);

            return Inertia::render('HR/Timekeeping/Badges/Index', [
                'badges' => $badges,
                'stats' => $stats,
                'filters' => $request->only(['search', 'status', 'department', 'card_type']),
                'employees' => $allActiveEmployees,
                'employeesWithoutBadges' => $employeesWithoutActiveBadges,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch badges', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return proper paginated structure even on error
            $emptyPagination = new \Illuminate\Pagination\LengthAwarePaginator(
                [],
                0,
                25,
                1,
                ['path' => $request->url()]
            );

            return Inertia::render('HR/Timekeeping/Badges/Index', [
                'badges' => $emptyPagination,
                'stats' => [
                    'total' => 0,
                    'active' => 0,
                    'inactive' => 0,
                    'expiring_soon' => 0,
                    'expiringSoon' => 0,
                    'employees_without_badges' => 0,
                    'employeesWithoutBadges' => 0,
                ],
                'filters' => $request->only(['search', 'status', 'department', 'card_type']),
                'employees' => [],
                'employeesWithoutBadges' => [],
                'error' => 'Failed to load badges. Please try again later.',
            ]);
        }
    }

    /**
     * Get count of employees without active badges
     * Used for dashboard statistics
     */
    private function getEmployeesWithoutBadgesCount(): int
    {
        return Employee::where('status', 'active')
            ->whereDoesntHave('rfidCardMappings', function ($query) {
                $query->where('is_active', true);
            })
            ->count();
    }

    /**
     * Show the form for creating a new badge.
     * Task 3.1.1: Provide list of active employees and existing badge UIDs for validation
     */
    public function create()
    {
        abort_unless(
            auth()->user()->can('hr.timekeeping.badges.manage'),
            403,
            'You do not have permission to issue badges.'
        );
        
        try {
            // Get all active employees with their current badge status
            $employees = Employee::where('status', 'active')
                ->with(['department', 'rfidCardMappings' => function ($query) {
                    $query->where('is_active', true);
                }])
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get()
                ->map(function ($employee) {
                    $activeBadge = $employee->rfidCardMappings->first();
                    
                    return [
                        'id' => $employee->id,
                        'name' => $employee->full_name,
                        'employee_id' => $employee->employee_number,
                        'department' => $employee->department?->name ?? 'N/A',
                        'position' => $employee->position ?? 'N/A',
                        'photo' => $employee->photo_url ?? null,
                        'badge' => $activeBadge ? [
                            'card_uid' => $activeBadge->card_uid,
                            'issued_at' => $activeBadge->issued_at->toDateTimeString(),
                            'expires_at' => $activeBadge->expires_at?->toDateString(),
                            'last_used_at' => $activeBadge->last_used_at?->toDateTimeString(),
                            'is_active' => $activeBadge->is_active,
                        ] : null,
                    ];
                });

            // Get all existing badge UIDs for uniqueness validation
            $existingBadgeUids = RfidCardMapping::pluck('card_uid')->toArray();

            return Inertia::render('HR/Timekeeping/Badges/Create', [
                'employees' => $employees,
                'existingBadgeUids' => $existingBadgeUids,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load badge creation form', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to load employee data. Please try again.']);
        }
    }

    /**
     * Store a newly created badge in storage.
     * Task 2.3.2: Implement store() Method (Issue Badge)
     *
     * Validates and creates a new badge assignment with:
     * - Authorization check
     * - Duplicate card UID verification
     * - Active badge check per employee
     * - Database transaction for atomicity
     * - Audit trail logging
     * - Activity logging for compliance
     */
    public function store(Request $request)
    {
        // Authorization: HR Staff + HR Manager only
        abort_unless(
            auth()->user()->can('hr.timekeeping.badges.manage'),
            403,
            'You do not have permission to issue badges.'
        );

        // Validate badge data
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'card_uid' => 'required|string|regex:/^[0-9A-Fa-f:]+$/|unique:rfid_card_mappings,card_uid',
            'card_type' => 'required|in:mifare,desfire,em4100',
            'expires_at' => 'nullable|date|after:today',
            'notes' => 'nullable|string|max:1000',
            'acknowledgement_signature' => 'nullable|string|max:1000',
            'replace_existing' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            // Check for existing active badge for this employee
            $existingBadge = RfidCardMapping::where('employee_id', $validated['employee_id'])
                ->where('is_active', true)
                ->first();

            if ($existingBadge && !$request->boolean('replace_existing')) {
                DB::rollBack();
                return back()->withErrors([
                    'employee_id' => 'Employee already has an active badge. Use Replace Badge workflow or set replace_existing to true.',
                ]);
            }

            // If replacing, deactivate existing badge
            if ($existingBadge && $request->boolean('replace_existing')) {
                $existingBadge->update([
                    'is_active' => false,
                    'deactivated_at' => now(),
                    'deactivated_by' => Auth::id(),
                    'deactivation_reason' => 'Replaced with new badge',
                ]);

                // Log deactivation
                BadgeIssueLog::create([
                    'card_uid' => $existingBadge->card_uid,
                    'employee_id' => $validated['employee_id'],
                    'issued_by' => Auth::id(),
                    'issued_at' => now(),
                    'action_type' => 'deactivated',
                    'reason' => 'Replaced with new badge',
                    'previous_card_uid' => null,
                ]);
            }

            // Create new badge
            $badge = RfidCardMapping::create([
                'card_uid' => strtoupper($validated['card_uid']),
                'employee_id' => $validated['employee_id'],
                'card_type' => $validated['card_type'],
                'issued_at' => now(),
                'issued_by' => Auth::id(),
                'expires_at' => $validated['expires_at'] ?? null,
                'is_active' => true,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Log badge issuance
            BadgeIssueLog::create([
                'card_uid' => $badge->card_uid,
                'employee_id' => $validated['employee_id'],
                'issued_by' => Auth::id(),
                'issued_at' => now(),
                'action_type' => $existingBadge ? 'replaced' : 'issued',
                'reason' => $validated['notes'] ?? 'Badge issuance',
                'previous_card_uid' => $existingBadge?->card_uid ?? null,
                'acknowledgement_signature' => $validated['acknowledgement_signature'] ?? null,
            ]);

            // Activity logging for audit trail
            activity()
                ->causedBy(Auth::user())
                ->performedOn($badge)
                ->withProperties([
                    'card_uid' => $badge->card_uid,
                    'employee_id' => $validated['employee_id'],
                    'card_type' => $validated['card_type'],
                ])
                ->log('Badge issued');

            DB::commit();

            return redirect()->route('hr.timekeeping.badges.index')
                ->with('success', 'Badge issued successfully to ' . $badge->employee->full_name);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Badge issuance failed', [
                'employee_id' => $validated['employee_id'] ?? null,
                'card_uid' => $validated['card_uid'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput($validated)
                ->withErrors(['error' => 'Failed to issue badge. Please try again.' . ($e->getMessage() ? ' Error: ' . $e->getMessage() : '')]);
        }
    }

    /**
     * Display badge details with usage statistics.
     * Task 2.3.3: Implement show() Method
     * 
     * Displays badge details with:
     * - Badge and employee information
     * - Issue history and activity logs
     * - Usage statistics from rfid_ledger
     * - Recent RFID scans
     */
    public function show(RfidCardMapping $badge)
    {
        abort_unless(
            auth()->user()->can('hr.timekeeping.badges.view'),
            403,
            'You do not have permission to view badge details.'
        );

        try {
            // Load relationships
            $badge->load([
                'employee.department',
                'issuedBy',
                'deactivatedBy',
                'issueLogs' => fn($q) => $q->latest()->limit(20),
            ]);

            // Get usage statistics from rfid_ledger
            $usageStats = DB::table('rfid_ledger')
                ->where('employee_rfid', $badge->card_uid)
                ->selectRaw('
                    COUNT(*) as total_scans,
                    MIN(scan_timestamp) as first_scan,
                    MAX(scan_timestamp) as last_scan,
                    COUNT(DISTINCT DATE(scan_timestamp)) as days_used,
                    COUNT(DISTINCT device_id) as devices_used
                ')
                ->first();

            // Get recent scans (last 50)
            $recentScans = DB::table('rfid_ledger')
                ->where('employee_rfid', $badge->card_uid)
                ->leftJoin('rfid_devices', 'rfid_ledger.device_id', '=', 'rfid_devices.device_id')
                ->select([
                    'rfid_ledger.scan_timestamp',
                    'rfid_ledger.event_type',
                    DB::raw("COALESCE(rfid_devices.device_name, 'Unknown Device') as device_name"),
                    DB::raw("COALESCE(rfid_devices.location, 'Unknown Location') as location"),
                ])
                ->orderBy('rfid_ledger.scan_timestamp', 'desc')
                ->limit(50)
                ->get();

            return Inertia::render('HR/Timekeeping/Badges/Show', [
                'badge' => $badge,
                'usageStats' => $usageStats ?? (object)[
                    'total_scans' => 0,
                    'first_scan' => null,
                    'last_scan' => null,
                    'days_used' => 0,
                    'devices_used' => 0,
                ],
                'recentScans' => $recentScans,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to show badge details', [
                'badge_id' => $badge->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to load badge details. Please try again.']);
        }
    }

    /**
     * Deactivate a badge.
     * Task 2.3.4: Implement deactivate() Method
     * 
     * Deactivates a badge with:
     * - Reason for deactivation
     * - Activity logging for audit trail
     * - Badge issue log entry
     * - Timestamp tracking
     */
    public function deactivate(DeactivateBadgeRequest $request, RfidCardMapping $badge)
    {
        abort_unless(
            auth()->user()->can('hr.timekeeping.badges.manage'),
            403,
            'You do not have permission to deactivate badges.'
        );

        try {
            DB::beginTransaction();

            // Update badge to inactive
            $badge->update([
                'is_active' => false,
                'deactivated_at' => now(),
                'deactivated_by' => Auth::id(),
                'deactivation_reason' => $request->reason,
            ]);

            // Log deactivation to badge issue log
            BadgeIssueLog::create([
                'card_uid' => $badge->card_uid,
                'employee_id' => $badge->employee_id,
                'issued_by' => Auth::id(),
                'issued_at' => now(),
                'action_type' => 'deactivated',
                'reason' => $request->reason,
            ]);

            // Activity logging for audit trail
            activity()
                ->causedBy(Auth::user())
                ->performedOn($badge)
                ->log('Badge deactivated: ' . $request->reason);

            DB::commit();

            return redirect()->back()
                ->with('success', 'Badge deactivated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Badge deactivation failed', [
                'badge_id' => $badge->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to deactivate badge. Please try again.']);
        }
    }

    /**
     * Replace an existing badge with a new one
     * Task 2.3.5: Implement replace() Method
     * 
     * Replaces a badge with:
     * - Deactivation of old badge
     * - Creation of new badge
     * - Full audit trail logging
     * - Database transaction for atomicity
     * - Optional replacement fee tracking
     */
    public function replace(ReplaceBadgeRequest $request, RfidCardMapping $oldBadge)
    {
        abort_unless(
            auth()->user()->can('hr.timekeeping.badges.manage'),
            403,
            'You do not have permission to replace badges.'
        );

        DB::beginTransaction();
        try {
            // Deactivate old badge
            $oldBadge->update([
                'is_active' => false,
                'deactivated_at' => now(),
                'deactivated_by' => Auth::id(),
                'deactivation_reason' => $request->reason . ' - Replaced',
            ]);

            // Create new badge with same employee
            $newBadge = RfidCardMapping::create([
                'card_uid' => strtoupper($request->new_card_uid),
                'employee_id' => $oldBadge->employee_id,
                'card_type' => $request->card_type ?? $oldBadge->card_type,
                'issued_at' => now(),
                'issued_by' => Auth::id(),
                'expires_at' => $request->expires_at ?? $oldBadge->expires_at,
                'is_active' => true,
                'notes' => $request->notes,
            ]);

            // Log replacement to badge issue logs
            BadgeIssueLog::create([
                'card_uid' => $newBadge->card_uid,
                'employee_id' => $oldBadge->employee_id,
                'issued_by' => Auth::id(),
                'issued_at' => now(),
                'action_type' => 'replaced',
                'reason' => $request->reason,
                'previous_card_uid' => $oldBadge->card_uid,
                'replacement_fee' => $request->replacement_fee,
            ]);

            // Activity logging for audit trail
            activity()
                ->causedBy(Auth::user())
                ->performedOn($newBadge)
                ->withProperties([
                    'old_badge_id' => $oldBadge->id,
                    'new_card_uid' => $newBadge->card_uid,
                    'replacement_reason' => $request->reason,
                    'replacement_fee' => $request->replacement_fee,
                ])
                ->log('Badge replaced - Reason: ' . $request->reason);

            DB::commit();

            return redirect()->route('hr.timekeeping.badges.show', $newBadge)
                ->with('success', 'Badge replaced successfully for ' . $newBadge->employee->full_name);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Badge replacement failed', [
                'old_badge_id' => $oldBadge->id,
                'new_card_uid' => $request->new_card_uid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput($request->all())
                ->withErrors(['error' => 'Failed to replace badge. Please try again.' . ($e->getMessage() ? ' Error: ' . $e->getMessage() : '')]);
        }
    }

    /**
     * Validate bulk badge import file
     * Task 1.7.1 & 1.7.2: Build Import Interface & Implement Import Validation
     */
    public function validateImport(Request $request)
    {
        // Authorization: HR Staff + HR Manager only
        abort_unless(
            auth()->user()->can('hr.timekeeping.badges.bulk-import'),
            403,
            'You do not have permission to import badges.'
        );

        // Validate file upload
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:5120', // 5 MB
            'rows' => 'required|array',
        ]);

        try {
            $validationResults = [];
            $rowNumber = 1;

            // Get existing card UIDs from database (all active and inactive badges)
            $existingCardUids = RfidCardMapping::pluck('card_uid')->toArray();
            
            // Get employees who already have active badges
            $activeEmployeeWithBadges = RfidCardMapping::where('is_active', true)
                ->pluck('employee_id')
                ->toArray();

            // Get all active employees for validation
            // Keep as model instances to use accessors and relationships
            $activeEmployees = Employee::where('status', 'active')
                ->with('profile')
                ->get()
                ->keyBy('employee_number');

            // Validate each row
            foreach ($validated['rows'] as $row) {
                $result = [
                    'row' => $rowNumber,
                    'employee_id' => $row['employee_id'] ?? '',
                    'employee_name' => '',
                    'card_uid' => $row['card_uid'] ?? '',
                    'card_type' => $row['card_type'] ?? '',
                    'status' => 'valid',
                    'errors' => [],
                    'warnings' => [],
                ];

                $errors = [];
                $warnings = [];

                // 1. Check if employee exists and is active
                $employeeNumber = $row['employee_id'] ?? null;
                $employee = $activeEmployees->get($employeeNumber);
                
                if (!$employee) {
                    $errors[] = [
                        'field' => 'employee_id',
                        'message' => 'Employee not found or inactive in system',
                    ];
                } else {
                    $result['employee_name'] = $employee->full_name;
                    
                    // Check if employee already has active badge (warning only)
                    if (in_array($employee->id, $activeEmployeeWithBadges)) {
                        $warnings[] = 'Employee already has an active badge. This will be replaced.';
                    }
                }

                // 2. Employee is active (already filtered in database query)
                // No additional check needed

                // 3. Validate card UID format (XX:XX:XX:XX:XX)
                $cardUid = $row['card_uid'] ?? '';
                if (empty($cardUid)) {
                    $errors[] = [
                        'field' => 'card_uid',
                        'message' => 'Card UID is required',
                    ];
                } elseif (!preg_match('/^([0-9A-Fa-f]{2}:){4}[0-9A-Fa-f]{2}$/', $cardUid)) {
                    $errors[] = [
                        'field' => 'card_uid',
                        'message' => 'Invalid format. Expected XX:XX:XX:XX:XX (hex)',
                    ];
                }

                // 4. Check for duplicate card UID in existing badges
                if ($cardUid && in_array($cardUid, $existingCardUids)) {
                    $errors[] = [
                        'field' => 'card_uid',
                        'message' => 'Card UID already exists in system',
                    ];
                }

                // 5. Check for duplicate card UID within import file
                $importedCardUids = array_column($validationResults, 'card_uid');
                if ($cardUid && in_array($cardUid, $importedCardUids)) {
                    $errors[] = [
                        'field' => 'card_uid',
                        'message' => 'Duplicate card UID within import file',
                    ];
                }

                // 6. Validate card type
                $validCardTypes = ['mifare', 'desfire', 'em4100'];
                $cardType = strtolower($row['card_type'] ?? '');
                if (empty($cardType)) {
                    $errors[] = [
                        'field' => 'card_type',
                        'message' => 'Card type is required',
                    ];
                } elseif (!in_array($cardType, $validCardTypes)) {
                    $errors[] = [
                        'field' => 'card_type',
                        'message' => 'Invalid card type. Must be one of: ' . implode(', ', $validCardTypes),
                    ];
                }

                // 7. Validate expiration date format (YYYY-MM-DD or empty)
                $expirationDate = $row['expiration_date'] ?? '';
                if (!empty($expirationDate)) {
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expirationDate)) {
                        $errors[] = [
                            'field' => 'expiration_date',
                            'message' => 'Invalid date format. Expected YYYY-MM-DD',
                        ];
                    } else {
                        // Validate it's a valid date
                        $date = \DateTime::createFromFormat('Y-m-d', $expirationDate);
                        if (!$date || $date->format('Y-m-d') !== $expirationDate) {
                            $errors[] = [
                                'field' => 'expiration_date',
                                'message' => 'Invalid date value',
                            ];
                        }
                    }
                }

                // Set status based on errors/warnings
                if (!empty($errors)) {
                    $result['status'] = 'error';
                } elseif (!empty($warnings)) {
                    $result['status'] = 'warning';
                } else {
                    $result['status'] = 'valid';
                }

                $result['errors'] = $errors;
                $result['warnings'] = $warnings;
                $validationResults[] = $result;
                $rowNumber++;
            }

            // Calculate summary
            $summary = [
                'total' => count($validationResults),
                'valid' => count(array_filter($validationResults, fn($r) => $r['status'] === 'valid')),
                'warnings' => count(array_filter($validationResults, fn($r) => $r['status'] === 'warning')),
                'errors' => count(array_filter($validationResults, fn($r) => $r['status'] === 'error')),
            ];

            return response()->json([
                'success' => true,
                'results' => $validationResults,
                'summary' => $summary,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export badge report in various formats
     * Task 1.6.1, 1.6.2, 1.6.3: Badge Reports & Export
     */
    public function export(Request $request)
    {
        // Authorization: HR Staff + HR Manager only
        abort_unless(
            auth()->user()->can('hr.timekeeping.badges.reports'),
            403,
            'You do not have permission to export badge reports.'
        );

        // Validate export request
        $validated = $request->validate([
            'reportType' => 'required|in:active,no-badge,expiring,history,lost-stolen',
            'format' => 'required|in:pdf,excel,csv,email',
            'data' => 'required|array',
            'groupBy' => 'nullable|in:department,status,none',
            'includeStats' => 'nullable|boolean',
            'emailTo' => 'nullable|email',
            'includeDetailedData' => 'nullable|boolean',
        ]);

        try {
            $format = $validated['format'];
            $reportType = $validated['reportType'];
            $data = $validated['data'];

            // Generate report content based on format
            match ($format) {
                'csv' => $this->exportToCSV($reportType, $data),
                'pdf' => $this->exportToPDF($reportType, $data, $validated),
                'excel' => $this->exportToExcel($reportType, $data, $validated),
                'email' => $this->exportViaEmail($reportType, $data, $validated),
            };

            // Phase 1: Return mock success response
            // Phase 2: Return actual file downloads or email responses
            return response()->json([
                'success' => true,
                'message' => "Report exported successfully as {$format}",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export report: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export report as CSV
     */
    private function exportToCSV($reportType, $data)
    {
        // TODO: Phase 2 - Implement actual CSV generation
        // TODO: Database query to fetch real data based on reportType
        // TODO: Stream CSV file as download
        // 
        // Example CSV structure:
        // employee_name,employee_id,card_uid,card_type,status,issued_at,expires_at
        // Juan Dela Cruz,EMP-2024-001,04:3A:B2:C5:D8,mifare,active,2024-01-15,2026-01-15

        return true;
    }

    /**
     * Export report as PDF
     */
    private function exportToPDF($reportType, $data, $options)
    {
        // TODO: Phase 2 - Implement PDF generation
        // TODO: Use barryvdh/laravel-dompdf or similar library
        // TODO: Create professional report layout with:
        //   - Company header and footer
        //   - Summary statistics
        //   - Grouped data tables
        //   - Print-friendly formatting
        // TODO: Stream PDF as download

        return true;
    }

    /**
     * Export report as Excel
     */
    private function exportToExcel($reportType, $data, $options)
    {
        // TODO: Phase 2 - Implement Excel generation
        // TODO: Use maatwebsite/excel (Laravel Excel) library
        // TODO: Create multiple sheets for:
        //   - Summary (statistics and charts)
        //   - Detailed data (grouped by department/status)
        //   - Data can be filtered based on groupBy option
        // TODO: Format cells with:
        //   - Column headers (bold, background color)
        //   - Proper data types (dates, numbers)
        //   - Conditional formatting for status badges
        //   - Auto-adjusted column widths
        // TODO: Stream Excel file as download

        return true;
    }

    /**
     * Export report via email
     */
    private function exportViaEmail($reportType, $data, $options)
    {
        // TODO: Phase 2 - Implement email delivery
        // TODO: Generate report (PDF or Excel)
        // TODO: Create email template with:
        //   - Report title and description
        //   - Summary statistics inline
        //   - Attachment with full report
        //   - Call to action (download link if hosted)
        // TODO: Queue email job for async sending:
        //   - Mail::queue(new BadgeReportMail(...))
        //   - Include detailed data toggle (includeDetailedData)
        //   - Add tracking metadata (sent_by, sent_at, sent_to)

        return true;
    }

    /**
     * Get badge usage history/timeline.
     * Task 2.3.3: Badge Usage History
     * 
     * Retrieves the complete usage history for a badge:
     * - Issue logs (all actions on this badge)
     * - Usage statistics from rfid_ledger
     * - Recent scans with timestamps
     */
    public function history(RfidCardMapping $badge)
    {
        abort_unless(
            auth()->user()->can('hr.timekeeping.badges.view'),
            403,
            'You do not have permission to view badge history.'
        );

        try {
            // Load badge relationships
            $badge->load(['employee.department', 'issuedBy', 'deactivatedBy']);

            // Get complete issue history (all actions on this badge)
            $issueHistory = BadgeIssueLog::where('card_uid', $badge->card_uid)
                ->with('issuedBy')
                ->orderBy('issued_at', 'desc')
                ->paginate(50);

            return Inertia::render('HR/Timekeeping/Badges/History', [
                'badge' => $badge,
                'issueHistory' => $issueHistory,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load badge history', [
                'badge_id' => $badge->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to load badge history. Please try again.']);
        }
    }

    /**
     * Process bulk badge import.
     * Task 1.7: Badge Bulk Import
     * 
     * Processes validated badge import data:
     * - Creates badge records in batch
     * - Logs import activity
     * - Returns import summary
     * - Handles partial failures gracefully
     */
    public function bulkImport(Request $request)
    {
        abort_unless(
            auth()->user()->can('hr.timekeeping.badges.bulk-import'),
            403,
            'You do not have permission to import badges.'
        );

        // Validate import request
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:5120',
            'import_data' => 'required|array',
        ]);

        DB::beginTransaction();
        try {
            $importData = $validated['import_data'];
            $successCount = 0;
            $failureCount = 0;
            $errors = [];

            foreach ($importData as $index => $row) {
                try {
                    // Check if employee exists
                    $employee = Employee::where('employee_number', $row['employee_number'])
                        ->orWhere('id', $row['employee_id'])
                        ->first();

                    if (!$employee) {
                        throw new \Exception("Employee not found: {$row['employee_number']}");
                    }

                    // Check for duplicate card UID
                    $existingBadge = RfidCardMapping::where('card_uid', strtoupper($row['card_uid']))->first();
                    if ($existingBadge) {
                        throw new \Exception("Card UID already registered: {$row['card_uid']}");
                    }

                    // Create badge record
                    $badge = RfidCardMapping::create([
                        'card_uid' => strtoupper($row['card_uid']),
                        'employee_id' => $employee->id,
                        'card_type' => $row['card_type'] ?? 'mifare',
                        'issued_at' => now(),
                        'issued_by' => Auth::id(),
                        'expires_at' => isset($row['expires_at']) ? \Carbon\Carbon::parse($row['expires_at']) : null,
                        'is_active' => true,
                        'notes' => $row['notes'] ?? 'Imported via bulk import',
                    ]);

                    // Log badge issuance
                    BadgeIssueLog::create([
                        'card_uid' => $badge->card_uid,
                        'employee_id' => $employee->id,
                        'issued_by' => Auth::id(),
                        'issued_at' => now(),
                        'action_type' => 'issued',
                        'reason' => 'Bulk import',
                    ]);

                    $successCount++;
                } catch (\Exception $e) {
                    $failureCount++;
                    $errors[$index] = $e->getMessage();
                }
            }

            // Activity logging
            activity()
                ->causedBy(Auth::user())
                ->log("Bulk badge import: {$successCount} succeeded, {$failureCount} failed");

            DB::commit();

            return redirect()->route('hr.timekeeping.badges.index')
                ->with('success', "Bulk import completed: {$successCount} badges imported successfully")
                ->with('import_summary', [
                    'success' => $successCount,
                    'failure' => $failureCount,
                    'errors' => $errors,
                ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk badge import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()
                ->withInput($validated)
                ->withErrors(['error' => 'Bulk import failed. Please try again.' . ($e->getMessage() ? ' Error: ' . $e->getMessage() : '')]);
        }
    }

    /**
     * Get report of employees without badges.
     * Task 1.8: Employees Without Badges Report
     * 
     * Returns list of active employees who don't have active badges:
     * - Lists affected employees
     * - Shows department and position
     * - Allows quick badge issuance from report
     * - Supports filtering and export
     */
    public function employeesWithoutBadges(Request $request)
    {
        abort_unless(
            auth()->user()->can('hr.timekeeping.badges.view'),
            403,
            'You do not have permission to view employee badge reports.'
        );

        try {
            // Get active employees without active badges
            $employeesWithoutBadges = Employee::where('status', 'active')
                ->whereDoesntHave('rfidCardMappings', function ($query) {
                    $query->where('is_active', true);
                })
                ->with(['department', 'user'])
                ->paginate($request->input('per_page', 25));

            // Get statistics
            $stats = [
                'total_active_employees' => Employee::where('status', 'active')->count(),
                'employees_with_badges' => Employee::where('status', 'active')
                    ->whereHas('rfidCardMappings', function ($query) {
                        $query->where('is_active', true);
                    })
                    ->count(),
                'employees_without_badges' => $employeesWithoutBadges->total(),
            ];

            return Inertia::render('HR/Timekeeping/Badges/EmployeesWithoutBadges', [
                'employees' => $employeesWithoutBadges,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load employees without badges report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to load report. Please try again.']);
        }
    }

    /**
     * Get usage analytics for a specific badge.
     * Task 2.9.1: Add Analytics Method
     *
     * Returns JSON with:
     * - Usage timeline (90-day scan history)
     * - Peak hours heatmap (hour vs day of week)
     * - Device usage breakdown (top 10)
     * - Consistency score (days with scans vs workdays)
     *
     * @param RfidCardMapping $badge The badge to analyze
     * @return \Illuminate\Http\JsonResponse
     */
    public function analytics(RfidCardMapping $badge)
    {
        abort_unless(
            auth()->user()->can('hr.timekeeping.badges.view'),
            403,
            'You do not have permission to view badge analytics.'
        );

        try {
            // Usage patterns over time (last 90 days)
            $usageTimeline = DB::table('rfid_ledger')
                ->where('card_uid', $badge->card_uid)
                ->where('scan_timestamp', '>=', now()->subDays(90))
                ->selectRaw('DATE(scan_timestamp) as date, COUNT(*) as scans')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Peak hours heatmap (hour of day vs day of week)
            $peakHours = DB::table('rfid_ledger')
                ->where('card_uid', $badge->card_uid)
                ->where('scan_timestamp', '>=', now()->subDays(30))
                ->selectRaw('
                    HOUR(scan_timestamp) as hour,
                    DAYOFWEEK(scan_timestamp) as day_of_week,
                    DAYNAME(scan_timestamp) as day_name,
                    COUNT(*) as scans
                ')
                ->groupBy('hour', 'day_of_week', 'day_name')
                ->orderBy('hour')
                ->orderBy('day_of_week')
                ->get();

            // Device usage breakdown (top 10 devices)
            $deviceUsage = DB::table('rfid_ledger')
                ->where('card_uid', $badge->card_uid)
                ->leftJoin('rfid_devices', 'rfid_ledger.device_id', '=', 'rfid_devices.device_id')
                ->select([
                    DB::raw('COALESCE(rfid_devices.device_name, rfid_ledger.device_id) as device_name'),
                    DB::raw('COALESCE(rfid_devices.location, "Unknown") as location'),
                    DB::raw('COUNT(*) as scans'),
                    DB::raw('MAX(rfid_ledger.scan_timestamp) as last_scan'),
                ])
                ->groupBy('rfid_ledger.device_id', 'rfid_devices.device_name', 'rfid_devices.location')
                ->orderByDesc('scans')
                ->limit(10)
                ->get();

            // Consistency score (days with at least 1 scan / total workdays in last 30 days)
            $workdays = now()->subDays(30)->diffInWeekdays(now());
            $daysWithScans = DB::table('rfid_ledger')
                ->where('card_uid', $badge->card_uid)
                ->where('scan_timestamp', '>=', now()->subDays(30))
                ->selectRaw('COUNT(DISTINCT DATE(scan_timestamp)) as days')
                ->value('days') ?? 0;

            $consistencyScore = $workdays > 0 ? round(($daysWithScans / $workdays) * 100, 2) : 0;

            // Total scans calculation
            $totalScans = DB::table('rfid_ledger')
                ->where('card_uid', $badge->card_uid)
                ->count();

            // First and last scan timestamps
            $scanStats = DB::table('rfid_ledger')
                ->where('card_uid', $badge->card_uid)
                ->selectRaw('MIN(scan_timestamp) as first_scan, MAX(scan_timestamp) as last_scan')
                ->first();

            return response()->json([
                'success' => true,
                'badge' => [
                    'id' => $badge->id,
                    'card_uid' => $badge->card_uid,
                    'employee_name' => $badge->employee->full_name,
                    'employee_id' => $badge->employee->employee_id,
                ],
                'usage_timeline' => $usageTimeline,
                'peak_hours' => $peakHours,
                'device_usage' => $deviceUsage,
                'consistency_score' => $consistencyScore,
                'total_workdays' => $workdays,
                'days_with_scans' => $daysWithScans,
                'total_scans' => $totalScans,
                'first_scan' => $scanStats?->first_scan,
                'last_scan' => $scanStats?->last_scan,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve badge analytics', [
                'badge_id' => $badge->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve badge analytics',
            ], 500);
        }
    }

    /**
     * Get inactive badges report.
     * Task 2.9.2: Inactive Badges Report
     *
     * Returns Inertia page with badges not scanned in 30+ days
     * Categorizes by alert level: warning (30-59 days), critical (60+ days)
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function inactiveBadges(Request $request)
    {
        abort_unless(
            auth()->user()->can('hr.timekeeping.badges.view'),
            403,
            'You do not have permission to view badge reports.'
        );

        try {
            $perPage = $request->input('per_page', 50);
            $sortBy = $request->input('sort_by', 'days_inactive');
            $sortOrder = $request->input('sort_order', 'desc');

            // Find badges not scanned in 30+ days or never used
            $inactiveBadgesQuery = RfidCardMapping::active()
                ->with(['employee.department', 'issuedBy'])
                ->where(function ($q) {
                    $q->whereNull('last_used_at')
                      ->orWhere('last_used_at', '<', now()->subDays(30));
                });

            // Get total count before pagination
            $totalCount = $inactiveBadgesQuery->count();

            // Fetch and map data
            $inactiveBadges = $inactiveBadgesQuery
                ->get()
                ->map(function ($badge) {
                    $daysSinceLastScan = $badge->last_used_at
                        ? now()->diffInDays($badge->last_used_at)
                        : 9999;

                    return [
                        'id' => $badge->id,
                        'card_uid' => $badge->card_uid,
                        'card_type' => $badge->card_type,
                        'employee_id' => $badge->employee_id,
                        'employee_name' => $badge->employee->full_name,
                        'employee_number' => $badge->employee->employee_id,
                        'department' => $badge->employee->department?->name ?? 'N/A',
                        'issued_at' => $badge->issued_at->format('Y-m-d H:i'),
                        'last_used_at' => $badge->last_used_at?->format('Y-m-d H:i') ?? 'Never',
                        'days_inactive' => $daysSinceLastScan,
                        'alert_level' => $daysSinceLastScan >= 60 ? 'critical' : 'warning',
                        'badge_status' => $badge->is_active ? 'active' : 'inactive',
                        'issued_by_name' => $badge->issuedBy?->name ?? 'System',
                    ];
                })
                ->sortBy($sortBy);

            if ($sortOrder === 'desc') {
                $inactiveBadges = $inactiveBadges->reverse();
            }

            // Paginate manually
            $inactiveBadges = $inactiveBadges->values()->paginate($perPage);

            // Calculate summary stats
            $criticalCount = $inactiveBadges->where('alert_level', 'critical')->count();
            $warningCount = $inactiveBadges->where('alert_level', 'warning')->count();
            $averageDaysInactive = $inactiveBadges->avg('days_inactive');

            // Load relationships for view
            $stats = [
                'total_inactive' => $totalCount,
                'critical_count' => $criticalCount,
                'warning_count' => $warningCount,
                'average_days_inactive' => round($averageDaysInactive, 2),
                'percentage_inactive' => round(($totalCount / RfidCardMapping::active()->count()) * 100, 2),
            ];

            return Inertia::render('HR/Timekeeping/Badges/InactiveBadges', [
                'badges' => $inactiveBadges,
                'stats' => $stats,
                'filters' => [
                    'sort_by' => $sortBy,
                    'sort_order' => $sortOrder,
                    'per_page' => $perPage,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load inactive badges report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('HR/Timekeeping/Badges/InactiveBadges', [
                'badges' => collect([]),
                'stats' => [
                    'total_inactive' => 0,
                    'critical_count' => 0,
                    'warning_count' => 0,
                    'average_days_inactive' => 0,
                    'percentage_inactive' => 0,
                ],
                'filters' => [
                    'sort_by' => 'days_inactive',
                    'sort_order' => 'desc',
                    'per_page' => 50,
                ],
                'error' => 'Failed to load inactive badges report. Please try again.',
            ]);
        }
    }
}



