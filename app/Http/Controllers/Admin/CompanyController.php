<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Traits\LogsActivity;

class CompanyController extends Controller
{
    /**
     * Display company configuration form.
     * 
     * Shows current company information including:
     * - Basic info (name, address, contact)
     * - Tax details (TIN, BIR registration)
     * - Government numbers (SSS, PhilHealth, Pag-IBIG)
     * - Company logo
     */
    public function index(Request $request): Response
    {
        // Get all company-related settings
        $companySettings = SystemSetting::where('key', 'LIKE', 'company.%')
            ->get()
            ->pluck('value', 'key')
            ->toArray();

        // Prepare company data with defaults
        $companyData = [
            // Basic Information
            'name' => $companySettings['company.name'] ?? '',
            'address' => $companySettings['company.address'] ?? '',
            'city' => $companySettings['company.city'] ?? '',
            'province' => $companySettings['company.province'] ?? '',
            'postal_code' => $companySettings['company.postal_code'] ?? '',
            'phone' => $companySettings['company.phone'] ?? '',
            'email' => $companySettings['company.email'] ?? '',
            'website' => $companySettings['company.website'] ?? '',
            
            // Tax & Registration
            'tin' => $companySettings['company.tin'] ?? '',
            'bir_registration_number' => $companySettings['company.bir_registration_number'] ?? '',
            'bir_registration_date' => $companySettings['company.bir_registration_date'] ?? '',
            'business_permit_number' => $companySettings['company.business_permit_number'] ?? '',
            'sec_registration_number' => $companySettings['company.sec_registration_number'] ?? '',
            
            // Government Numbers
            'sss_number' => $companySettings['company.sss_number'] ?? '',
            'philhealth_number' => $companySettings['company.philhealth_number'] ?? '',
            'pagibig_number' => $companySettings['company.pagibig_number'] ?? '',
            
            // Logo
            'logo_url' => $companySettings['company.logo_url'] ?? null,
        ];

        return Inertia::render('Admin/Company/Index', [
            'company' => $companyData,
        ]);
    }

    /**
     * Update company configuration.
     * 
     * Saves all company information and logs the changes for audit trail.
     * Uses SystemSettings table to store key-value pairs.
     */
    public function update(Request $request)
    {
        // Validate request data
        $validated = $request->validate([
            // Basic Information
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'phone' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'website' => 'nullable|url|max:255',
            
            // Tax & Registration
            'tin' => 'required|string|max:50',
            'bir_registration_number' => 'nullable|string|max:100',
            'bir_registration_date' => 'nullable|date',
            'business_permit_number' => 'nullable|string|max:100',
            'sec_registration_number' => 'nullable|string|max:100',
            
            // Government Numbers
            'sss_number' => 'required|string|max:50',
            'philhealth_number' => 'required|string|max:50',
            'pagibig_number' => 'required|string|max:50',
        ]);

        // Map form fields to settings keys
        $settingsMap = [
            'name' => 'company.name',
            'address' => 'company.address',
            'city' => 'company.city',
            'province' => 'company.province',
            'postal_code' => 'company.postal_code',
            'phone' => 'company.phone',
            'email' => 'company.email',
            'website' => 'company.website',
            'tin' => 'company.tin',
            'bir_registration_number' => 'company.bir_registration_number',
            'bir_registration_date' => 'company.bir_registration_date',
            'business_permit_number' => 'company.business_permit_number',
            'sec_registration_number' => 'company.sec_registration_number',
            'sss_number' => 'company.sss_number',
            'philhealth_number' => 'company.philhealth_number',
            'pagibig_number' => 'company.pagibig_number',
        ];

        // Update or create each setting
        foreach ($settingsMap as $field => $key) {
            $value = $validated[$field] ?? null;
            
            if ($value !== null) {
                $setting = SystemSetting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $value,
                        'type' => 'string',
                        'category' => 'company',
                        'description' => $this->getSettingDescription($key),
                    ]
                );

                // Log the change using Spatie Activity Log
                activity('company_configuration')
                    ->causedBy($request->user())
                    ->performedOn($setting)
                    ->withProperties([
                        'key' => $key,
                        'old_value' => $setting->getOriginal('value'),
                        'new_value' => $value,
                    ])
                    ->log('Updated company setting: ' . $key);
            }
        }

        return redirect()->route('admin.company.index')
            ->with('success', 'Company information updated successfully.');
    }

    /**
     * Upload company logo.
     * 
     * Handles logo file upload to storage/app/public/company directory.
     * Updates company.logo_url setting with the file path.
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,svg|max:2048', // 2MB max
        ]);

        try {
            // Delete old logo if exists
            $oldLogoSetting = SystemSetting::where('key', 'company.logo_url')->first();
            if ($oldLogoSetting && $oldLogoSetting->value) {
                Storage::disk('public')->delete($oldLogoSetting->value);
            }

            // Store new logo
            $file = $request->file('logo');
            $filename = 'company-logo-' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('company', $filename, 'public');

            // Update setting
            $setting = SystemSetting::updateOrCreate(
                ['key' => 'company.logo_url'],
                [
                    'value' => $path,
                    'type' => 'string',
                    'category' => 'company',
                    'description' => 'Company logo file path',
                ]
            );

            // Log the change
            activity('company_configuration')
                ->causedBy($request->user())
                ->performedOn($setting)
                ->withProperties([
                    'filename' => $filename,
                    'path' => $path,
                ])
                ->log('Uploaded company logo');

            return redirect()->route('admin.company.index')
                ->with('success', 'Company logo uploaded successfully.');

        } catch (\Exception $e) {
            \Log::error('Company logo upload failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return redirect()->route('admin.company.index')
                ->with('error', 'Failed to upload logo. Please try again.');
        }
    }

    /**
     * Delete company logo.
     * 
     * Removes the logo file from storage and clears the setting.
     */
    public function deleteLogo(Request $request)
    {
        try {
            $logoSetting = SystemSetting::where('key', 'company.logo_url')->first();
            
            if ($logoSetting && $logoSetting->value) {
                // Delete file from storage
                Storage::disk('public')->delete($logoSetting->value);
                
                // Clear setting
                $logoSetting->update(['value' => null]);

                // Log the change
                activity('company_configuration')
                    ->causedBy($request->user())
                    ->performedOn($logoSetting)
                    ->log('Deleted company logo');

                return redirect()->route('admin.company.index')
                    ->with('success', 'Company logo deleted successfully.');
            }

            return redirect()->route('admin.company.index')
                ->with('error', 'No logo found to delete.');

        } catch (\Exception $e) {
            \Log::error('Company logo deletion failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
            ]);

            return redirect()->route('admin.company.index')
                ->with('error', 'Failed to delete logo. Please try again.');
        }
    }

    /**
     * Get human-readable description for setting key.
     * 
     * @param string $key
     * @return string
     */
    private function getSettingDescription(string $key): string
    {
        $descriptions = [
            'company.name' => 'Company legal name',
            'company.address' => 'Company business address',
            'company.city' => 'City',
            'company.province' => 'Province',
            'company.postal_code' => 'Postal code',
            'company.phone' => 'Company contact phone number',
            'company.email' => 'Company contact email',
            'company.website' => 'Company website URL',
            'company.tin' => 'Tax Identification Number',
            'company.bir_registration_number' => 'BIR registration number',
            'company.bir_registration_date' => 'BIR registration date',
            'company.business_permit_number' => 'Business permit number',
            'company.sec_registration_number' => 'SEC registration number',
            'company.sss_number' => 'SSS employer number',
            'company.philhealth_number' => 'PhilHealth employer number',
            'company.pagibig_number' => 'Pag-IBIG employer number',
        ];

        return $descriptions[$key] ?? ucwords(str_replace(['.', '_'], ' ', $key));
    }
}
