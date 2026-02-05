<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\ProfileUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ProfileController extends Controller
{
    /**
     * Display the employee's profile (personal information, employment details, government IDs).
     * 
     * Shows all employee information including:
     * - Personal details (name, birthdate, gender, civil status, nationality)
     * - Contact information (phone, email, address)
     * - Employment details (employee number, position, department, hire date, status)
     * - Government IDs (SSS, PhilHealth, Pag-IBIG, TIN)
     * - Emergency contact information
     * 
     * Enforces "self-only" data access - employees can ONLY view their own profile.
     * 
     * @param Request $request
     * @return \Inertia\Response
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Get authenticated user's employee record
        $employee = $user->employee;
        
        if (!$employee) {
            Log::error('Employee profile access attempted by user without employee record', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            abort(403, 'No employee record found for your account. Please contact HR Staff.');
        }

        Log::info('Employee profile viewed', [
            'user_id' => $user->id,
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
        ]);

        // Load profile and relationships
        $employee->load([
            'profile',
            'department:id,name,code',
            'position:id,title',
            'supervisor:id,employee_number',
            'supervisor.profile:id,first_name,last_name',
        ]);

        $profile = $employee->profile;

        return Inertia::render('Employee/Profile/Index', [
            'employee' => [
                // Employment Information
                'employee_number' => $employee->employee_number,
                'email' => $employee->email,
                'department' => $employee->department ? [
                    'name' => $employee->department->name,
                    'code' => $employee->department->code,
                ] : null,
                'position' => $employee->position ? [
                    'title' => $employee->position->title,
                ] : null,
                'employment_type' => ucfirst($employee->employment_type),
                'status' => ucfirst($employee->status),
                'date_hired' => $employee->date_hired?->format('F d, Y'),
                'regularization_date' => $employee->regularization_date?->format('F d, Y'),
                'supervisor' => $employee->supervisor ? [
                    'employee_number' => $employee->supervisor->employee_number,
                    'full_name' => $employee->supervisor->profile->full_name ?? 'N/A',
                ] : null,

                // Personal Information (from Profile)
                'personal_info' => $profile ? [
                    'full_name' => $profile->full_name,
                    'first_name' => $profile->first_name,
                    'middle_name' => $profile->middle_name,
                    'last_name' => $profile->last_name,
                    'suffix' => $profile->suffix,
                    'birthdate' => $profile->birthdate?->format('F d, Y'),
                    'age' => $profile->birthdate ? now()->diffInYears($profile->birthdate) : null,
                    'gender' => ucfirst($profile->gender ?? 'N/A'),
                    'civil_status' => ucfirst($profile->civil_status ?? 'N/A'),
                    'nationality' => $profile->nationality ?? 'N/A',
                ] : null,

                // Contact Information (editable fields)
                'contact_info' => $profile ? [
                    'contact_number' => $profile->contact_number,
                    'email' => $profile->email ?? $employee->email,
                    'address' => $profile->address,
                    'city' => $profile->city,
                    'province' => $profile->province,
                    'postal_code' => $profile->postal_code,
                    'country' => $profile->country ?? 'Philippines',
                ] : null,

                // Government IDs
                'government_ids' => $profile ? [
                    'sss_number' => $profile->sss_number ?? 'Not set',
                    'philhealth_number' => $profile->philhealth_number ?? 'Not set',
                    'pagibig_number' => $profile->pagibig_number ?? 'Not set',
                    'tin' => $profile->tin ?? 'Not set',
                ] : null,

                // Emergency Contact
                'emergency_contact' => $profile ? [
                    'name' => $profile->emergency_contact_name ?? 'Not set',
                    'relationship' => $profile->emergency_contact_relationship ?? 'N/A',
                    'phone' => $profile->emergency_contact_phone ?? 'Not set',
                    'address' => $profile->emergency_contact_address ?? 'Not set',
                ] : null,
            ],
        ]);
    }

    /**
     * Submit a contact information update request for HR approval.
     * 
     * Employees can request updates to the following fields:
     * - contact_number
     * - email
     * - address (including city, province, postal_code)
     * - emergency_contact (name, relationship, phone, address)
     * 
     * All update requests are stored in the profile_update_requests table
     * with status 'pending' and require HR Staff approval before being applied.
     * 
     * Enforces "self-only" data access - employees can ONLY update their own profile.
     * 
     * @param ProfileUpdateRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function requestUpdate(ProfileUpdateRequest $request)
    {
        $user = $request->user();
        
        // Get authenticated user's employee record
        $employee = $user->employee;
        
        if (!$employee) {
            Log::error('Profile update request attempted by user without employee record', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            abort(403, 'No employee record found for your account. Please contact HR Staff.');
        }

        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $profile = $employee->profile;

            if (!$profile) {
                throw new \Exception('No profile record found for employee.');
            }

            $updateRequests = [];

            // Process each field update request
            $fieldsToUpdate = [
                'contact_number' => 'Contact Number',
                'email' => 'Email Address',
                'address' => 'Home Address',
                'city' => 'City',
                'province' => 'Province',
                'postal_code' => 'Postal Code',
                'emergency_contact_name' => 'Emergency Contact Name',
                'emergency_contact_relationship' => 'Emergency Contact Relationship',
                'emergency_contact_phone' => 'Emergency Contact Phone',
                'emergency_contact_address' => 'Emergency Contact Address',
            ];

            foreach ($fieldsToUpdate as $field => $displayName) {
                if (isset($validated[$field])) {
                    $oldValue = $profile->{$field};
                    $newValue = $validated[$field];

                    // Only create update request if value actually changed
                    if ($oldValue !== $newValue) {
                        $updateRequest = DB::table('profile_update_requests')->insert([
                            'employee_id' => $employee->id,
                            'field_name' => $field,
                            'old_value' => $oldValue,
                            'new_value' => $newValue,
                            'status' => 'pending',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $updateRequests[] = $displayName;

                        Log::info('Profile update request created', [
                            'employee_id' => $employee->id,
                            'field_name' => $field,
                            'old_value' => $oldValue,
                            'new_value' => $newValue,
                        ]);
                    }
                }
            }

            if (empty($updateRequests)) {
                DB::rollBack();
                return back()->with('info', 'No changes detected. Your profile information is already up to date.');
            }

            // TODO: Send notification to HR Staff
            // Use Laravel Notifications: App\Notifications\ProfileUpdateRequested
            // Notify all users with 'HR Staff' role about pending update request
            // Example:
            // $hrStaff = User::role('HR Staff')->get();
            // Notification::send($hrStaff, new ProfileUpdateRequested($employee, $updateRequests));

            DB::commit();

            Log::info('Profile update requests submitted successfully', [
                'employee_id' => $employee->id,
                'fields_requested' => $updateRequests,
            ]);

            return back()->with('success', 
                'Profile update request submitted successfully. ' .
                'HR Staff will review your changes: ' . implode(', ', $updateRequests) . '. ' .
                'You will be notified once your request is processed.'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Profile update request failed', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 
                'Failed to submit profile update request. Please try again or contact HR Staff if the issue persists.'
            );
        }
    }
}
