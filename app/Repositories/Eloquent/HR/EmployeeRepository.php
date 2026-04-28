<?php

namespace App\Repositories\Eloquent\HR;

use App\Models\Employee;
use App\Repositories\Contracts\HR\EmployeeRepositoryInterface;
use Illuminate\Support\Facades\DB;

class EmployeeRepository implements EmployeeRepositoryInterface
{
    /**
     * Get all employees with optional filters and pagination.
     */
    public function all(array $filters = [], int $perPage = 15)
    {
        $query = Employee::with(['profile', 'department', 'position', 'supervisor.profile']);

        // Handle archived employees (soft deletes)
        if (!empty($filters['status']) && $filters['status'] === 'archived') {
            // Include soft-deleted (archived) records
            $query->withTrashed();
            $query->where('status', 'archived');
        } else if (!empty($filters['status'])) {
            // For other statuses, exclude soft-deleted records
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (!empty($filters['employment_type'])) {
            $query->where('employment_type', $filters['employment_type']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('employee_number', 'like', "%{$search}%")
                  ->orWhereHas('profile', function ($profileQuery) use ($search) {
                      $profileQuery->where('first_name', 'like', "%{$search}%")
                                   ->orWhere('last_name', 'like', "%{$search}%")
                                   ->orWhere('middle_name', 'like', "%{$search}%");
                  });
            });
        }

        // Sorting - map column names to actual database columns
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        // Map sortable columns, handling related table sorts
        $columnMap = [
            'name' => 'profiles.first_name', // Sort by first name when "name" is requested
            'department' => 'departments.name',
            'position' => 'positions.title',
            'employee_number' => 'employees.employee_number',
            'email' => 'employees.email',
            'status' => 'employees.status',
            'employment_type' => 'employees.employment_type',
            'date_hired' => 'employees.date_hired',
            'recent' => 'employees.date_hired', // Sort by date_hired for recent hires
        ];
        
        // Use mapped column or default to the requested column (if it exists in employees table)
        $actualColumn = $columnMap[$sortBy] ?? "employees.{$sortBy}";
        
        // If sorting by related table column, join the table
        if (strpos($actualColumn, '.') !== false && strpos($actualColumn, 'employees.') === false) {
            $tableName = explode('.', $actualColumn)[0];
            if ($tableName === 'profiles') {
                $query->join('profiles', 'employees.profile_id', '=', 'profiles.id');
            } elseif ($tableName === 'departments') {
                $query->join('departments', 'employees.department_id', '=', 'departments.id');
            } elseif ($tableName === 'positions') {
                $query->join('positions', 'employees.position_id', '=', 'positions.id');
            }
        }
        
        $query->orderBy($actualColumn, $sortOrder);
        $query->distinct(); // Prevent duplicate rows from joins

        return $query->paginate($perPage);
    }

    /**
     * Find an employee by ID.
     */
    public function find(int $id)
    {
        return Employee::with([
            'profile',
            'department',
            'position',
            'supervisor.profile',
            'subordinates.profile',
            'dependents',
            'remarks.createdBy'
        ])->find($id);
    }

    /**
     * Find an employee by employee number.
     */
    public function findByEmployeeNumber(string $employeeNumber)
    {
        return Employee::with(['profile', 'department', 'position', 'supervisor.profile'])
            ->where('employee_number', $employeeNumber)
            ->first();
    }

    /**
     * Create a new employee record.
     */
    public function create(array $data)
    {
        return Employee::create($data);
    }

    /**
     * Update an existing employee record.
     */
    public function update(int $id, array $data)
    {
        $employee = Employee::findOrFail($id);
        $employee->update($data);
        return $employee->fresh(['profile', 'department', 'position', 'supervisor.profile']);
    }

    /**
     * Soft delete an employee (archive).
     */
    public function delete(int $id)
    {
        $employee = Employee::findOrFail($id);
        $employee->update(['status' => 'archived']);
        return $employee->delete();
    }

    /**
     * Restore a soft-deleted employee.
     */
    public function restore(int $id)
    {
        $employee = Employee::withTrashed()->findOrFail($id);
        $employee->update(['status' => 'active']);
        return $employee->restore();
    }

    /**
     * Get employees by department.
     */
    public function getByDepartment(int $departmentId, int $perPage = 15)
    {
        return Employee::with(['profile', 'department', 'position', 'supervisor.profile'])
            ->where('department_id', $departmentId)
            ->paginate($perPage);
    }

    /**
     * Get employees by status.
     */
    public function getByStatus(string $status, int $perPage = 15)
    {
        return Employee::with(['profile', 'department', 'position', 'supervisor.profile'])
            ->where('status', $status)
            ->paginate($perPage);
    }

    /**
     * Search employees by name, employee number, or department.
     */
    public function search(string $query, int $perPage = 15)
    {
        return Employee::with(['profile', 'department', 'position', 'supervisor.profile'])
            ->where(function ($q) use ($query) {
                $q->where('employee_number', 'like', "%{$query}%")
                  ->orWhereHas('profile', function ($profileQuery) use ($query) {
                      $profileQuery->where('first_name', 'like', "%{$query}%")
                                   ->orWhere('last_name', 'like', "%{$query}%")
                                   ->orWhere('middle_name', 'like', "%{$query}%");
                  })
                  ->orWhereHas('department', function ($deptQuery) use ($query) {
                      $deptQuery->where('name', 'like', "%{$query}%");
                  });
            })
            ->paginate($perPage);
    }

    /**
     * Get employee statistics for dashboard.
     */
    public function getStatistics(): array
    {
        $total = Employee::withTrashed()->count();
        $active = Employee::where('status', 'active')->count();
        $archived = Employee::withTrashed()->where('status', 'archived')->count();
        $onLeave = Employee::where('status', 'on_leave')->count();
        $terminated = Employee::where('status', 'terminated')->count();

        // Get department breakdown
        $departmentBreakdown = Employee::select('department_id', DB::raw('count(*) as count'))
            ->with('department:id,name')
            ->whereNotNull('department_id')
            ->where('status', 'active')
            ->groupBy('department_id')
            ->get()
            ->map(function ($item) use ($active) {
                return [
                    'department_id' => $item->department_id,
                    'name' => $item->department->name ?? 'Unknown',
                    'count' => $item->count,
                    'percentage' => $active > 0 ? round(($item->count / $active) * 100, 1) : 0,
                ];
            })
            ->toArray();

        // Get employment type breakdown
        $employmentTypeBreakdown = Employee::select('employment_type', DB::raw('count(*) as count'))
            ->where('status', 'active')
            ->whereNotNull('employment_type')
            ->groupBy('employment_type')
            ->get()
            ->pluck('count', 'employment_type')
            ->toArray();

        return [
            'total' => $total,
            'active' => $active,
            'archived' => $archived,
            'on_leave' => $onLeave,
            'terminated' => $terminated,
            'active_percentage' => $total > 0 ? round(($active / $total) * 100, 1) : 0,
            'department_breakdown' => $departmentBreakdown,
            'employment_type_breakdown' => $employmentTypeBreakdown,
        ];
    }

    /**
     * Get employees with their profiles and departments.
     */
    public function getAllWithRelations(array $filters = [], int $perPage = 15)
    {
        return $this->all($filters, $perPage);
    }

    /**
     * Get recent hires (last N days).
     */
    public function getRecentHires(int $days = 30, int $limit = 10)
    {
        return Employee::with(['profile', 'department', 'position'])
            ->where('date_hired', '>=', now()->subDays($days))
            ->orderBy('date_hired', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get employees by supervisor.
     */
    public function getBySupervisor(int $supervisorId)
    {
        return Employee::with(['profile', 'department'])
            ->where('immediate_supervisor_id', $supervisorId)
            ->get();
    }
}
