<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'profile_id',
        'employee_number',
        'department_id',
        'position_id',
        'employment_type',
        'date_hired',
        'regularization_date',
        'immediate_supervisor_id',
        'status',
        'termination_date',
        'termination_reason',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_hired' => 'date:Y-m-d',
        'regularization_date' => 'date:Y-m-d',
        'termination_date' => 'date:Y-m-d',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Normalize employment_type to lowercase for storage.
     */
    public function setEmploymentTypeAttribute($value)
    {
        $this->attributes['employment_type'] = strtolower($value);
    }

    /**
     * Get employment_type in lowercase for consistency.
     */
    public function getEmploymentTypeAttribute($value)
    {
        return strtolower($value);
    }

    /**
     * Get the user associated with the employee (if linked).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the profile (personal identity) associated with the employee.
     */
    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Get the department this employee belongs to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the position that the employee holds.
     */
    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    /**
     * Get the immediate supervisor of this employee.
     */
    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'immediate_supervisor_id');
    }

    /**
     * Get all subordinates (employees supervised by this employee).
     */
    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'immediate_supervisor_id');
    }

    /**
     * Get the user who created this employee record.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this employee record.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get all dependent records for this employee.
     */
    public function dependents()
    {
        return $this->hasMany(EmployeeDependent::class);
    }

    /**
     * Get all remarks for this employee.
     */
    public function remarks()
    {
        return $this->hasMany(EmployeeRemark::class);
    }

    /**
     * Scope a query to only include active employees.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include archived employees.
     */
    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    /**
     * Scope a query to filter by department.
     */
    public function scopeByDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by employment type.
     */
    public function scopeByEmploymentType($query, string $employmentType)
    {
        return $query->where('employment_type', $employmentType);
    }

    /**
     * Get the full name of the employee (via profile).
     */
    public function getFullNameAttribute(): string
    {
        if ($this->profile) {
            return trim("{$this->profile->first_name} {$this->profile->middle_name} {$this->profile->last_name}");
        }
        return 'Unknown Employee';
    }

    /**
     * Check if employee is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if employee is terminated.
     */
    public function isTerminated(): bool
    {
        return $this->status === 'terminated';
    }

    /**
     * Check if employee is on probation.
     */
    public function isProbationary(): bool
    {
        return $this->employment_type === 'probationary';
    }

    /**
     * Check if employee is regularized.
     */
    public function isRegularized(): bool
    {
        return $this->date_regularized !== null;
    }
}
