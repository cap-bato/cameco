<?php

/*
 * Heavy workflow: wraps repositories, storage, dependents,
 * number generation, metrics.
 * Clearly crosses HR subdomains.
 * 
 * This should be brokendown more, keep as an orchestration service. later
 * 
*/ 

namespace App\Services\HR;

use App\Models\Employee;
use App\Models\EmployeeDependent;
use App\Repositories\Contracts\HR\EmployeeRepositoryInterface;
use App\Repositories\Contracts\HR\ProfileRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EmployeeService
{
    protected EmployeeRepositoryInterface $employeeRepository;
    protected ProfileRepositoryInterface $profileRepository;

    public function __construct(
        EmployeeRepositoryInterface $employeeRepository,
        ProfileRepositoryInterface $profileRepository
    ) {
        $this->employeeRepository = $employeeRepository;
        $this->profileRepository = $profileRepository;
    }

    /**
     * Handle profile picture upload.
     */
    private function handleProfilePictureUpload($file, $employeeNumber): ?string
    {
        if (!$file) {
            Log::debug('handleProfilePictureUpload: No file provided');
            return null;
        }

        try {
            Log::debug('handleProfilePictureUpload: Starting upload', [
                'employee_number' => $employeeNumber,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_mime' => $file->getClientMimeType(),
            ]);
            
            $fileName = "employees/{$employeeNumber}/" . time() . '.' . $file->getClientOriginalExtension();
            $path = Storage::disk('public')->putFileAs('', $file, $fileName);
            
            Log::info('Profile picture uploaded successfully', [
                'employee_number' => $employeeNumber,
                'path' => $path,
            ]);
            
            return $path;
        } catch (\Exception $e) {
            Log::error('Profile picture upload failed', [
                'employee_number' => $employeeNumber,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Delete old profile picture.
     */
    private function deleteOldProfilePicture($profilePicturePath): void
    {
        if ($profilePicturePath && Storage::disk('public')->exists($profilePicturePath)) {
            Storage::disk('public')->delete($profilePicturePath);
        }
    }

    /**
     * Create a new employee with auto-created profile.
     */
    public function createEmployee(array $data): array
    {
        DB::beginTransaction();

        try {
            // Auto-create profile from employee data
            $profileData = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'suffix' => $data['suffix'] ?? null,
                'date_of_birth' => $data['date_of_birth'],
                'place_of_birth' => $data['place_of_birth'] ?? null,
                'is_pwd' => $data['is_pwd'] ?? false,
                'gender' => $data['gender'] ?? null,
                'civil_status' => $data['civil_status'] ?? null,
                'spouse_name' => $data['spouse_name'] ?? null,
                'spouse_date_of_birth' => $data['spouse_date_of_birth'] ?? null,
                'spouse_contact_number' => $data['spouse_contact_number'] ?? null,
                'father_name' => $data['father_name'] ?? null,
                'father_date_of_birth' => $data['father_date_of_birth'] ?? null,
                'mother_name' => $data['mother_name'] ?? null,
                'mother_date_of_birth' => $data['mother_date_of_birth'] ?? null,
                'phone' => $data['phone'] ?? null,
                'mobile' => $data['mobile'] ?? null,
                'current_address' => $data['current_address'] ?? null,
                'permanent_address' => $data['permanent_address'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_relationship' => $data['emergency_contact_relationship'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'emergency_contact_address' => $data['emergency_contact_address'] ?? null,
                'sss_number' => $data['sss_number'] ?? null,
                'tin_number' => $data['tin_number'] ?? null,
                'philhealth_number' => $data['philhealth_number'] ?? null,
                'pagibig_number' => $data['pagibig_number'] ?? null,
            ];

            $profile = $this->profileRepository->create($profileData);

            // Generate employee number
            $employeeNumber = $this->generateEmployeeNumber();

            // Prepare employee data
            $employeeData = [
                'profile_id' => $profile->id,
                'user_id' => $data['user_id'] ?? null,
                'employee_number' => $employeeNumber,
                'email' => $data['email'],
                'department_id' => $data['department_id'],
                'immediate_supervisor_id' => $data['supervisor_id'] ?? null,
                'position_id' => $data['position_id'],
                'employment_type' => $data['employment_type'],
                'date_hired' => $data['date_hired'],
                'regularization_date' => $data['regularization_date'] ?? null,
                'status' => $data['status'],
            ];

            $employee = $this->employeeRepository->create($employeeData);

            // Handle profile picture upload
            if (!empty($data['profile_picture'])) {
                $profilePicturePath = $this->handleProfilePictureUpload($data['profile_picture'], $employeeNumber);
                if ($profilePicturePath) {
                    $this->profileRepository->update($profile->id, [
                        'profile_picture_path' => $profilePicturePath,
                    ]);
                }
            }

            // Save dependents if provided
            if (!empty($data['dependents']) && is_array($data['dependents'])) {
                foreach ($data['dependents'] as $dependent) {
                    // Skip if dependent is empty (all fields null/empty)
                    if (empty($dependent['first_name']) && empty($dependent['last_name'])) {
                        continue;
                    }

                    EmployeeDependent::create([
                        'employee_id' => $employee->id,
                        'first_name' => $dependent['first_name'] ?? null,
                        'middle_name' => $dependent['middle_name'] ?? null,
                        'last_name' => $dependent['last_name'] ?? null,
                        'date_of_birth' => $dependent['date_of_birth'] ?? null,
                        'relationship' => $dependent['relationship'] ?? null,
                        'remarks' => $dependent['remarks'] ?? null,
                    ]);
                }
            }

            DB::commit();

            Log::info('Employee created successfully', [
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
            ]);

            return [
                'success' => true,
                'employee' => $employee->load(['profile', 'department', 'supervisor', 'position', 'dependents']),
                'message' => 'Employee created successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Employee creation failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Employee creation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update an existing employee.
     */
    public function updateEmployee(int $id, array $data): array
    {
        DB::beginTransaction();

        try {
            $employee = $this->employeeRepository->find($id);
            
            // Debug: Log file upload attempt
            Log::info('Employee update - profile picture data:', [
                'employee_id' => $id,
                'has_profile_picture' => isset($data['profile_picture']),
                'profile_picture_type' => isset($data['profile_picture']) ? gettype($data['profile_picture']) : 'not set',
                'profile_picture_class' => isset($data['profile_picture']) ? get_class($data['profile_picture']) : 'N/A',
            ]);

            if (!$employee) {
                return [
                    'success' => false,
                    'message' => 'Employee not found'
                ];
            }

            // Update profile if profile data is provided
            $profileUpdates = array_filter([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'middle_name' => $data['middle_name'] ?? null,
                'suffix' => $data['suffix'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'place_of_birth' => $data['place_of_birth'] ?? null,
                'is_pwd' => $data['is_pwd'] ?? null,
                'gender' => $data['gender'] ?? null,
                'civil_status' => $data['civil_status'] ?? null,
                'spouse_name' => $data['spouse_name'] ?? null,
                'spouse_date_of_birth' => $data['spouse_date_of_birth'] ?? null,
                'spouse_contact_number' => $data['spouse_contact_number'] ?? null,
                'father_name' => $data['father_name'] ?? null,
                'father_date_of_birth' => $data['father_date_of_birth'] ?? null,
                'mother_name' => $data['mother_name'] ?? null,
                'mother_date_of_birth' => $data['mother_date_of_birth'] ?? null,
                'phone' => $data['phone'] ?? null,
                'mobile' => $data['mobile'] ?? null,
                'current_address' => $data['current_address'] ?? null,
                'permanent_address' => $data['permanent_address'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_relationship' => $data['emergency_contact_relationship'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'emergency_contact_address' => $data['emergency_contact_address'] ?? null,
                'sss_number' => $data['sss_number'] ?? null,
                'tin_number' => $data['tin_number'] ?? null,
                'philhealth_number' => $data['philhealth_number'] ?? null,
                'pagibig_number' => $data['pagibig_number'] ?? null,
            ], fn($value) => $value !== null);

            if (!empty($profileUpdates)) {
                $this->profileRepository->update($employee->profile_id, $profileUpdates);
            }

            // Handle profile picture upload
            if (!empty($data['profile_picture'])) {
                // Delete old profile picture if exists
                if ($employee->profile->profile_picture_path) {
                    $this->deleteOldProfilePicture($employee->profile->profile_picture_path);
                }
                
                $profilePicturePath = $this->handleProfilePictureUpload($data['profile_picture'], $employee->employee_number);
                if ($profilePicturePath) {
                    $this->profileRepository->update($employee->profile_id, [
                        'profile_picture_path' => $profilePicturePath,
                    ]);
                }
            }

            // Update employee data
            $employeeUpdates = array_filter([
                'email' => $data['email'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'immediate_supervisor_id' => $data['supervisor_id'] ?? null,
                'position_id' => $data['position_id'] ?? null,
                'employment_type' => $data['employment_type'] ?? null,
                'date_hired' => $data['date_hired'] ?? null,
                'regularization_date' => $data['regularization_date'] ?? null,
                'status' => $data['status'] ?? null,
                'termination_date' => $data['termination_date'] ?? null,
                'termination_reason' => $data['termination_reason'] ?? null,
            ], fn($value) => $value !== null);

            $updatedEmployee = $this->employeeRepository->update($id, $employeeUpdates);

            // Sync dependents if provided
            if (isset($data['dependents']) && is_array($data['dependents'])) {
                $this->syncDependents($id, $data['dependents']);
            }

            DB::commit();

            Log::info('Employee updated successfully', [
                'employee_id' => $id,
            ]);

            return [
                'success' => true,
                'employee' => $updatedEmployee->load(['profile', 'department', 'supervisor', 'position', 'dependents']),
                'message' => 'Employee updated successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Employee update failed', [
                'employee_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Employee update failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Sync dependents for an employee (create, update, delete).
     */
    private function syncDependents(int $employeeId, array $dependents): void
    {
        // Get existing dependent IDs from database
        $existingDependents = EmployeeDependent::where('employee_id', $employeeId)->get();
        $existingIds = $existingDependents->pluck('id')->toArray();
        
        // Collect IDs of dependents coming from the request (excluding temporary IDs)
        $incomingIds = [];
        
        foreach ($dependents as $dependent) {
            // Skip if dependent is empty (all fields null/empty)
            if (empty($dependent['first_name']) && empty($dependent['last_name'])) {
                continue;
            }
            
            $id = $dependent['id'] ?? null;
            
            // If ID exists and is a valid integer (not a temporary frontend ID like Date.now())
            if ($id && is_numeric($id) && $id < 2147483647) {
                $incomingIds[] = $id;
                
                // Update existing dependent
                EmployeeDependent::where('id', $id)
                    ->where('employee_id', $employeeId)
                    ->update([
                        'first_name' => $dependent['first_name'] ?? null,
                        'middle_name' => $dependent['middle_name'] ?? null,
                        'last_name' => $dependent['last_name'] ?? null,
                        'date_of_birth' => $dependent['date_of_birth'] ?? null,
                        'relationship' => $dependent['relationship'] ?? null,
                        'remarks' => $dependent['remarks'] ?? null,
                    ]);
            } else {
                // Create new dependent (no ID or temporary ID)
                EmployeeDependent::create([
                    'employee_id' => $employeeId,
                    'first_name' => $dependent['first_name'] ?? null,
                    'middle_name' => $dependent['middle_name'] ?? null,
                    'last_name' => $dependent['last_name'] ?? null,
                    'date_of_birth' => $dependent['date_of_birth'] ?? null,
                    'relationship' => $dependent['relationship'] ?? null,
                    'remarks' => $dependent['remarks'] ?? null,
                ]);
            }
        }
        
        // Delete dependents that were removed (in DB but not in incoming list)
        $dependentsToDelete = array_diff($existingIds, $incomingIds);
        if (!empty($dependentsToDelete)) {
            EmployeeDependent::whereIn('id', $dependentsToDelete)->delete();
        }
    }

    /**
     * Archive an employee (soft delete with reason).
     */
    public function archiveEmployee(int $employeeId, string $reason, ?string $terminationDate = null): array
    {
        DB::beginTransaction();

        try {
            $employee = $this->employeeRepository->find($employeeId);

            if (!$employee) {
                return [
                    'success' => false,
                    'message' => 'Employee not found'
                ];
            }

            // Update status to terminated with reason
            $this->employeeRepository->update($employeeId, [
                'status' => 'terminated',
                'termination_date' => $terminationDate ?? now()->toDateString(),
                'termination_reason' => $reason,
            ]);

            // Soft delete
            $this->employeeRepository->delete($employeeId);

            DB::commit();

            Log::info('Employee archived', [
                'employee_id' => $employeeId,
                'reason' => $reason,
            ]);

            return [
                'success' => true,
                'message' => 'Employee archived successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Employee archive failed', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Employee archive failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Restore an archived employee.
     */
    public function restoreEmployee(int $id): array
    {
        DB::beginTransaction();

        try {
            $result = $this->employeeRepository->restore($id);

            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Employee not found or already active'
                ];
            }

            // Update status back to active
            $employee = $this->employeeRepository->update($id, [
                'status' => 'active',
                'termination_date' => null,
                'termination_reason' => null,
            ]);

            DB::commit();

            Log::info('Employee restored', [
                'employee_id' => $id,
            ]);

            return [
                'success' => true,
                'employee' => $employee->load(['profile', 'department', 'supervisor', 'position']),
                'message' => 'Employee restored successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Employee restore failed', [
                'employee_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Employee restore failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get employee by ID.
     */
    public function getEmployeeById(int $id)
    {
        return $this->employeeRepository->find($id);
    }

    /**
     * Generate a unique employee number in format: EMP-YYYY-NNNN.
     */
    public function generateEmployeeNumber(): string
    {
        $year = now()->year;
        $prefix = "EMP-{$year}-";

        // Get the last employee number for this year
        $lastEmployee = Employee::where('employee_number', 'like', "{$prefix}%")
            ->withTrashed()
            ->orderBy('employee_number', 'desc')
            ->first();

        if ($lastEmployee) {
            $lastNumber = (int) substr($lastEmployee->employee_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        $employeeNumber = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

        // Ensure uniqueness
        while (Employee::withTrashed()->where('employee_number', $employeeNumber)->exists()) {
            $newNumber++;
            $employeeNumber = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        }

        return $employeeNumber;
    }

    /**
     * Search employees with filters.
     */
    public function searchEmployees(array $filters = [], int $perPage = 15)
    {
        // Convert "all" to null/empty for filtering
        $cleanFilters = [
            'search' => $filters['search'] ?? '',
            'department_id' => (!empty($filters['department_id']) && $filters['department_id'] !== 'all') ? $filters['department_id'] : null,
            'status' => (!empty($filters['status']) && $filters['status'] !== 'all') ? $filters['status'] : null,
            'employment_type' => (!empty($filters['employment_type']) && $filters['employment_type'] !== 'all') ? $filters['employment_type'] : null,
            'sort_by' => $filters['sort_by'] ?? 'created_at',
            'sort_order' => $filters['sort_order'] ?? 'desc',
        ];

        return $this->employeeRepository->all($cleanFilters, $perPage);
    }

    /**
     * Get dashboard metrics for HR Manager.
     */
    public function getDashboardMetrics(): array
    {
        $statistics = $this->employeeRepository->getStatistics();
        $recentHires = $this->employeeRepository->getRecentHires(30, 5);

        // Format recent hires with formatted date
        $formattedRecentHires = $recentHires->map(function($emp) {
            $hireDate = new \DateTime($emp->date_hired);
            $now = new \DateTime();
            $interval = $now->diff($hireDate);
            
            if ($interval->days === 0) {
                $formattedDate = 'Today';
            } elseif ($interval->days === 1) {
                $formattedDate = 'Yesterday';
            } elseif ($interval->days < 7) {
                $formattedDate = $interval->days . ' days ago';
            } else {
                $formattedDate = $hireDate->format('M d, Y');
            }
            
            return [
                'id' => $emp->id,
                'name' => $emp->profile->first_name . ' ' . $emp->profile->last_name,
                'position' => $emp->position->name ?? 'Not Assigned',
                'department' => $emp->department->name ?? 'Not Assigned',
                'hire_date' => $emp->date_hired,
                'formatted_hire_date' => $formattedDate,
                'profile_picture_path' => $emp->profile->profile_picture_path,
            ];
        })->toArray();

        return [
            'totalEmployees' => [
                'count' => $statistics['total'],
                'trend' => 0,
                'label' => 'Total Employees',
            ],
            'activeEmployees' => [
                'count' => $statistics['active'],
                'percentage' => round(($statistics['active'] / max($statistics['total'], 1)) * 100, 1),
                'label' => 'Active Employees',
            ],
            'departmentBreakdown' => [
                'data' => $statistics['department_breakdown'] ?? [],
                'label' => 'Department Breakdown',
            ],
            'recentHires' => [
                'data' => $formattedRecentHires,
                'count' => count($formattedRecentHires),
                'label' => 'Recent Hires',
            ],
            'pendingActions' => [
                'count' => 0,
                'label' => 'Pending Actions',
                'items' => [],
            ],
        ];
    }
}
