# Office Admin Module - Audit Logging & Validation Documentation

## Overview
This document details the audit logging setup and validation patterns used across the Office Admin module to ensure comprehensive tracking of all configuration changes and proper authorization.

---

## Audit Logging Setup

### Spatie Activity Log Configuration

All models in the Office Admin module use Spatie's `laravel-activitylog` package to track changes. This provides a comprehensive audit trail for compliance and troubleshooting.

#### Models with Activity Logging

**1. SystemSetting Model**
- **Location**: `app/Models/SystemSetting.php`
- **Logged Attributes**: `key`, `value`, `type`, `category`
- **Description Format**: "System setting {event}"
- **Usage**: Tracks all configuration changes (company info, business rules, payroll rates, etc.)

**2. LeavePolicy Model**
- **Location**: `app/Models/LeavePolicy.php`
- **Logged Attributes**: `code`, `name`, `description`, `annual_entitlement`, `max_carryover`, `can_carry_forward`, `is_paid`, `is_active`, `effective_date`
- **Description Format**: "Leave policy '{name}' {event}"
- **Usage**: Tracks creation, updates, and archival of leave policies

**3. Department Model**
- **Location**: `app/Models/Department.php`
- **Logged Attributes**: `name`, `description`, `parent_id`, `manager_id`, `code`, `budget`, `is_active`
- **Description Format**: "Department '{name}' {event}"
- **Usage**: Tracks organizational structure changes

**4. Position Model**
- **Location**: `app/Models/Position.php`
- **Logged Attributes**: `title`, `description`, `department_id`, `reports_to`, `level`, `min_salary`, `max_salary`, `is_active`
- **Description Format**: "Position '{title}' {event}"
- **Usage**: Tracks position definition changes

### Activity Log Features

**Enabled Features:**
- ✅ `logOnlyDirty()` - Only logs attributes that actually changed
- ✅ `dontSubmitEmptyLogs()` - Prevents logging when no changes occurred
- ✅ Automatic timestamps with causer tracking
- ✅ Old and new values stored in properties
- ✅ Event descriptions (created, updated, deleted)

**Activity Log Table Structure:**
```
activity_log
├── id
├── log_name (e.g., "default")
├── description (e.g., "System setting updated")
├── subject_type (e.g., "App\Models\SystemSetting")
├── subject_id
├── causer_type (e.g., "App\Models\User")
├── causer_id (Office Admin user ID)
├── properties (JSON: old_values, new_values)
├── created_at
└── updated_at
```

### Controller Activity Logging

All Admin controllers explicitly log actions using the `activity()` helper:

**Example from CompanyController:**
```php
activity('company_configuration')
    ->causedBy($request->user())
    ->performedOn($setting)
    ->withProperties([
        'key' => $key,
        'old_value' => $setting->getOriginal('value'),
        'new_value' => $value,
    ])
    ->log('Updated company setting: ' . $key);
```

**Activity Log Names by Controller:**
- `company_configuration` - CompanyController
- `business_rules` - BusinessRulesController
- `payroll_configuration` - PayrollRulesController
- `system_configuration` - SystemConfigController
- `workflow_configuration` - ApprovalWorkflowController
- `system_integration` - SystemConfigController (RFID testing)

---

## Authorization & Validation

### Authorization Strategy

**Multi-Layer Authorization:**

1. **Middleware Layer** (Route Level)
   - `EnsureOfficeAdmin` middleware on all `/admin` routes
   - Permission-based middleware on each route (e.g., `permission:admin.company.view`)

2. **Policy Layer** (Controller Level)
   - Department and Position controllers use policies
   - Policies check both HR and Admin permissions
   - Example: `DepartmentPolicy` checks `hr.departments.*` OR `admin.departments.*`

3. **Role Layer** (User Level)
   - Office Admin role has 23 permissions
   - Permission naming convention: `admin.{module}.{action}`

### Permission Structure

**Office Admin Permissions (23 total):**

```
Dashboard:
- admin.dashboard.view

Company Setup:
- admin.company.view
- admin.company.edit

Business Rules:
- admin.business-rules.view
- admin.business-rules.edit

Departments:
- admin.departments.view
- admin.departments.create
- admin.departments.edit
- admin.departments.delete

Positions:
- admin.positions.view
- admin.positions.create
- admin.positions.edit
- admin.positions.delete

Leave Policies:
- admin.leave-policies.view
- admin.leave-policies.create
- admin.leave-policies.edit
- admin.leave-policies.delete

Payroll Rules:
- admin.payroll-rules.view
- admin.payroll-rules.edit

System Configuration:
- admin.system-config.view
- admin.system-config.edit

Approval Workflows:
- admin.approval-workflows.view
- admin.approval-workflows.edit
```

### Validation Patterns

**Inline Validation Approach:**

The Office Admin module uses inline validation in controllers rather than separate Form Request classes. This approach was chosen for:
- ✅ Conciseness (validation rules close to business logic)
- ✅ Flexibility (easy to adjust rules per method)
- ✅ Readability (clear what each endpoint validates)

**Example Validation Patterns:**

**1. Company Information Validation**
```php
$validated = $request->validate([
    'company_name' => 'required|string|max:255',
    'company_address' => 'required|string|max:500',
    'company_email' => 'required|email|max:255',
    'company_phone' => 'required|string|max:20',
    'company_tin' => 'required|string|max:50',
    'sss_employer_number' => 'nullable|string|max:50',
    'philhealth_employer_number' => 'nullable|string|max:50',
    'pagibig_employer_number' => 'nullable|string|max:50',
]);
```

**2. Time Format Validation**
```php
$validated = $request->validate([
    'start_time' => 'required|date_format:H:i',
    'end_time' => 'required|date_format:H:i|after:start_time',
    'break_duration' => 'required|integer|min:0|max:240',
]);
```

**3. Numeric Range Validation**
```php
$validated = $request->validate([
    'annual_entitlement' => 'required|numeric|min:0|max:365',
    'max_carryover' => 'required|numeric|min:0|max:365',
    'can_carry_forward' => 'boolean',
]);
```

**4. Enum Validation**
```php
$validated = $request->validate([
    'deduction_type' => 'required|in:per_minute,per_bracket,fixed',
    'payment_schedule' => 'required|in:weekly,bi-monthly,monthly',
    'blackout_action' => 'required|in:require_manager,block_all,warning_only',
]);
```

**5. Array Validation**
```php
$validated = $request->validate([
    'blackout_periods' => 'nullable|array',
    'blackout_periods.*.start_date' => 'required|date',
    'blackout_periods.*.end_date' => 'required|date|after_or_equal:blackout_periods.*.start_date',
    'blackout_periods.*.reason' => 'required|string|max:255',
]);
```

**6. File Upload Validation**
```php
$validated = $request->validate([
    'logo' => 'required|image|mimes:jpeg,png,jpg,svg|max:2048',
]);
```

### HTTP Response Codes

**Success Responses:**
- `200 OK` - Successful GET requests
- `201 Created` - Successful POST requests
- `302 Found` - Successful redirects with flash messages

**Error Responses:**
- `403 Forbidden` - Authorization failure (missing permission)
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Validation failure
- `500 Internal Server Error` - Server-side error

### Flash Messages

All controller mutations return flash messages for user feedback:

**Success Messages:**
```php
return redirect()->route('admin.company.index')
    ->with('success', 'Company information updated successfully.');
```

**Error Messages:**
```php
return redirect()->route('admin.business-rules.index')
    ->with('error', 'Failed to update business rules.');
```

**Validation Errors:**
```php
return back()->withErrors(['field' => 'Error message']);
```

---

## Testing Checklist

### Authorization Testing
- [ ] Verify Office Admin can access all `/admin` routes
- [ ] Verify non-Office Admin users are blocked by middleware
- [ ] Verify permission checks on each route
- [ ] Test policy authorization for Department and Position controllers

### Validation Testing
- [ ] Test required field validation
- [ ] Test data type validation (string, integer, boolean, date)
- [ ] Test format validation (email, date_format, IP)
- [ ] Test range validation (min, max, between)
- [ ] Test enum validation (in:value1,value2)
- [ ] Test array validation with nested rules
- [ ] Test file upload validation (size, mime types)
- [ ] Test custom validation logic (e.g., date comparisons)

### Activity Log Testing
- [ ] Verify SystemSetting changes are logged
- [ ] Verify LeavePolicy changes are logged
- [ ] Verify Department changes are logged
- [ ] Verify Position changes are logged
- [ ] Verify causer (user) is tracked correctly
- [ ] Verify old and new values are stored
- [ ] Verify timestamps are accurate
- [ ] Verify soft deletes are logged

### Error Handling Testing
- [ ] Test 403 Forbidden for unauthorized access
- [ ] Test 404 Not Found for missing resources
- [ ] Test 422 Validation errors with proper messages
- [ ] Test 500 errors are logged appropriately
- [ ] Test flash messages appear correctly

---

## Query Examples

### Retrieve Activity Logs for a Specific User
```php
$activities = Activity::causedBy($user)
    ->orderBy('created_at', 'desc')
    ->get();
```

### Retrieve Activity Logs for a Specific Model
```php
$activities = Activity::forSubject($department)
    ->get();
```

### Retrieve Activity Logs by Log Name
```php
$activities = Activity::inLog('company_configuration')
    ->orderBy('created_at', 'desc')
    ->take(30)
    ->get();
```

### Retrieve Activity Logs with Changed Attributes
```php
$activities = Activity::where('properties->old.company_name', '!=', null)
    ->get();
```

### Admin Dashboard Recent Changes Query
```php
$recentChanges = Activity::whereIn('log_name', [
        'company_configuration',
        'business_rules',
        'payroll_configuration',
        'system_configuration',
        'workflow_configuration',
    ])
    ->where('created_at', '>=', now()->subDays(30))
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();
```

---

## Security Best Practices

### Implemented Security Measures

1. **Sensitive Data Encryption**
   - SMTP passwords encrypted before storage
   - `$setting->value = encrypt($validated['smtp_password'])`

2. **Input Sanitization**
   - All user input validated before processing
   - HTML special characters escaped in Blade templates

3. **Authorization Checks**
   - Multi-layer authorization (middleware, permissions, policies)
   - No direct database writes without authorization

4. **Audit Trail**
   - All configuration changes logged with user and timestamp
   - Old and new values preserved for rollback

5. **Soft Deletes**
   - Leave policies, departments, and positions use soft deletes
   - Data preserved for audit and recovery

6. **File Upload Security**
   - File type validation (mimes:jpeg,png,jpg,svg)
   - File size limits (max:2048 KB)
   - Files stored outside web root (storage/app/public/company)

---

## Maintenance Guidelines

### Regular Maintenance Tasks

**Weekly:**
- Review activity logs for suspicious changes
- Check for failed authorization attempts

**Monthly:**
- Archive old activity logs (older than 12 months)
- Review and update permissions as needed

**Quarterly:**
- Audit all Office Admin accounts
- Review and update validation rules
- Test all authorization scenarios

**Annually:**
- Full security audit
- Update government rates (SSS, PhilHealth, Pag-IBIG, BIR)
- Review and archive old configurations

### Activity Log Cleanup

**Archive Old Logs:**
```php
// Archive logs older than 1 year
Activity::where('created_at', '<', now()->subYear())
    ->chunk(1000, function ($activities) {
        // Export to JSON or CSV
        // Then delete
        $activities->each->delete();
    });
```

**Prune Activity Log Table:**
```bash
php artisan activitylog:clean --days=365
```

---

## Compliance Notes

### Data Retention
- Activity logs retained for **12 months** minimum
- Configuration changes retained for **5 years** for audit purposes
- Deleted data (soft deletes) retained for **3 years**

### GDPR Compliance
- Activity logs include user identification (causer_id)
- Users can request deletion of their activity logs (right to be forgotten)
- Logs anonymized after employee termination (after retention period)

### Labor Law Compliance
- All salary changes logged with effective dates
- Government rate changes logged with official announcement dates
- Holiday calendar changes preserved for payroll audits

---

## Troubleshooting

### Common Issues

**Issue: Activity logs not being created**
- ✅ Verify model has `LogsActivity` trait
- ✅ Check `getActivitylogOptions()` method configuration
- ✅ Verify `activity_log` table exists in database
- ✅ Check if `logOnlyDirty()` is blocking (no actual changes)

**Issue: Authorization fails unexpectedly**
- ✅ Verify user has Office Admin role assigned
- ✅ Check if permission exists in `permissions` table
- ✅ Verify role has permission in `role_has_permissions` table
- ✅ Clear permission cache: `php artisan permission:cache-reset`

**Issue: Validation errors not displaying**
- ✅ Verify `@error` directives in Blade templates
- ✅ Check if flash messages are rendered in layout
- ✅ Verify validation rules are correct

---

## Related Documentation
- [Office Admin Workflow](../docs/workflows/02-office-admin-workflow.md)
- [RBAC Matrix](../docs/RBAC_MATRIX.md)
- [Spatie Activity Log Documentation](https://spatie.be/docs/laravel-activitylog)
- [Spatie Permission Documentation](https://spatie.be/docs/laravel-permission)

---

**Last Updated**: December 4, 2025  
**Module**: Office Admin  
**Phase**: 3 - Backend Controllers & Services
