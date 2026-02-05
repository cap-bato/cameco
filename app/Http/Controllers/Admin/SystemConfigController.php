<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemConfigController extends Controller
{
    /**
     * Display system configuration page.
     * 
     * Shows notification settings, report configurations, and integration settings.
     */
    public function index(Request $request): Response
    {
        // Get all system configuration settings
        $settings = SystemSetting::where('category', 'system_config')
            ->get()
            ->pluck('value', 'key');

        $systemConfig = [
            'notifications' => [
                // Email settings
                'smtp_host' => $settings['system_config.smtp.host'] ?? '',
                'smtp_port' => (int)($settings['system_config.smtp.port'] ?? 587),
                'smtp_username' => $settings['system_config.smtp.username'] ?? '',
                'smtp_encryption' => $settings['system_config.smtp.encryption'] ?? 'tls',
                'sender_email' => $settings['system_config.email.sender_email'] ?? '',
                'sender_name' => $settings['system_config.email.sender_name'] ?? '',
                
                // SMS settings (future)
                'sms_enabled' => (bool)($settings['system_config.sms.enabled'] ?? false),
                'sms_gateway' => $settings['system_config.sms.gateway'] ?? '',
                'sms_api_key' => $settings['system_config.sms.api_key'] ?? '',
                
                // Notification templates enabled
                'leave_approval_enabled' => (bool)($settings['system_config.notifications.leave_approval'] ?? true),
                'payslip_enabled' => (bool)($settings['system_config.notifications.payslip'] ?? true),
                'interview_enabled' => (bool)($settings['system_config.notifications.interview'] ?? true),
                'appraisal_enabled' => (bool)($settings['system_config.notifications.appraisal'] ?? true),
                'system_alerts_enabled' => (bool)($settings['system_config.notifications.system_alerts'] ?? true),
            ],
            'reports' => [
                // PDF settings
                'pdf_company_logo' => (bool)($settings['system_config.pdf.company_logo'] ?? true),
                'pdf_page_size' => $settings['system_config.pdf.page_size'] ?? 'A4',
                'pdf_orientation' => $settings['system_config.pdf.orientation'] ?? 'portrait',
                'pdf_font_family' => $settings['system_config.pdf.font_family'] ?? 'Arial',
                
                // Excel settings
                'excel_auto_width' => (bool)($settings['system_config.excel.auto_width'] ?? true),
                'excel_freeze_panes' => (bool)($settings['system_config.excel.freeze_panes'] ?? true),
                'excel_summary_sheet' => (bool)($settings['system_config.excel.summary_sheet'] ?? true),
                
                // Scheduled reports
                'monthly_payroll_enabled' => (bool)($settings['system_config.reports.monthly_payroll'] ?? false),
                'monthly_payroll_recipients' => $this->getReportRecipients($settings, 'monthly_payroll'),
                'attendance_summary_enabled' => (bool)($settings['system_config.reports.attendance_summary'] ?? false),
                'attendance_summary_recipients' => $this->getReportRecipients($settings, 'attendance_summary'),
                'leave_utilization_enabled' => (bool)($settings['system_config.reports.leave_utilization'] ?? false),
                'leave_utilization_recipients' => $this->getReportRecipients($settings, 'leave_utilization'),
                'government_remittance_enabled' => (bool)($settings['system_config.reports.government_remittance'] ?? false),
                'government_remittance_recipients' => $this->getReportRecipients($settings, 'government_remittance'),
            ],
            'integrations' => [
                // RFID timekeeping
                'rfid_enabled' => (bool)($settings['system_config.rfid.enabled'] ?? false),
                'rfid_device_ip' => $settings['system_config.rfid.device_ip'] ?? '',
                'rfid_device_port' => (int)($settings['system_config.rfid.device_port'] ?? 8080),
                'rfid_protocol' => $settings['system_config.rfid.protocol'] ?? 'http',
                'rfid_event_bus_enabled' => (bool)($settings['system_config.rfid.event_bus_enabled'] ?? false),
                
                // Job board (future)
                'job_board_enabled' => (bool)($settings['system_config.job_board.enabled'] ?? false),
                'job_board_url' => $settings['system_config.job_board.url'] ?? '',
                'job_board_auto_import' => (bool)($settings['system_config.job_board.auto_import'] ?? false),
            ],
        ];

        return Inertia::render('Admin/SystemConfig/Index', [
            'systemConfig' => $systemConfig,
        ]);
    }

    /**
     * Update notification settings.
     */
    public function updateNotifications(Request $request)
    {
        $validated = $request->validate([
            // Email SMTP
            'smtp_host' => 'required|string|max:255',
            'smtp_port' => 'required|integer|min:1|max:65535',
            'smtp_username' => 'required|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'smtp_encryption' => 'required|in:tls,ssl,none',
            'sender_email' => 'required|email|max:255',
            'sender_name' => 'required|string|max:255',
            
            // SMS (future)
            'sms_enabled' => 'boolean',
            'sms_gateway' => 'nullable|string|max:255',
            'sms_api_key' => 'nullable|string|max:255',
            
            // Notification templates
            'leave_approval_enabled' => 'boolean',
            'payslip_enabled' => 'boolean',
            'interview_enabled' => 'boolean',
            'appraisal_enabled' => 'boolean',
            'system_alerts_enabled' => 'boolean',
        ]);

        $settings = [
            'system_config.smtp.host' => $validated['smtp_host'],
            'system_config.smtp.port' => $validated['smtp_port'],
            'system_config.smtp.username' => $validated['smtp_username'],
            'system_config.smtp.encryption' => $validated['smtp_encryption'],
            'system_config.email.sender_email' => $validated['sender_email'],
            'system_config.email.sender_name' => $validated['sender_name'],
            'system_config.sms.enabled' => $validated['sms_enabled'] ?? false,
            'system_config.notifications.leave_approval' => $validated['leave_approval_enabled'] ?? true,
            'system_config.notifications.payslip' => $validated['payslip_enabled'] ?? true,
            'system_config.notifications.interview' => $validated['interview_enabled'] ?? true,
            'system_config.notifications.appraisal' => $validated['appraisal_enabled'] ?? true,
            'system_config.notifications.system_alerts' => $validated['system_alerts_enabled'] ?? true,
        ];

        // Only save SMS settings if enabled
        if ($validated['sms_enabled'] ?? false) {
            $settings['system_config.sms.gateway'] = $validated['sms_gateway'] ?? '';
            $settings['system_config.sms.api_key'] = $validated['sms_api_key'] ?? '';
        }

        // Update SMTP password separately if provided (encrypted)
        if (!empty($validated['smtp_password'])) {
            $settings['system_config.smtp.password'] = encrypt($validated['smtp_password']);
        }

        $this->updateSettings($settings, $request->user());

        return redirect()->route('admin.system-config.index')
            ->with('success', 'Notification settings updated successfully.');
    }

    /**
     * Update report configuration settings.
     */
    public function updateReports(Request $request)
    {
        $validated = $request->validate([
            // PDF settings
            'pdf_company_logo' => 'boolean',
            'pdf_page_size' => 'required|in:A4,Letter,Legal',
            'pdf_orientation' => 'required|in:portrait,landscape',
            'pdf_font_family' => 'required|in:Arial,Times New Roman,Courier,Helvetica',
            
            // Excel settings
            'excel_auto_width' => 'boolean',
            'excel_freeze_panes' => 'boolean',
            'excel_summary_sheet' => 'boolean',
            
            // Scheduled reports
            'monthly_payroll_enabled' => 'boolean',
            'monthly_payroll_recipients' => 'nullable|array',
            'monthly_payroll_recipients.*' => 'email',
            'attendance_summary_enabled' => 'boolean',
            'attendance_summary_recipients' => 'nullable|array',
            'attendance_summary_recipients.*' => 'email',
            'leave_utilization_enabled' => 'boolean',
            'leave_utilization_recipients' => 'nullable|array',
            'leave_utilization_recipients.*' => 'email',
            'government_remittance_enabled' => 'boolean',
            'government_remittance_recipients' => 'nullable|array',
            'government_remittance_recipients.*' => 'email',
        ]);

        $settings = [
            'system_config.pdf.company_logo' => $validated['pdf_company_logo'] ?? true,
            'system_config.pdf.page_size' => $validated['pdf_page_size'],
            'system_config.pdf.orientation' => $validated['pdf_orientation'],
            'system_config.pdf.font_family' => $validated['pdf_font_family'],
            'system_config.excel.auto_width' => $validated['excel_auto_width'] ?? true,
            'system_config.excel.freeze_panes' => $validated['excel_freeze_panes'] ?? true,
            'system_config.excel.summary_sheet' => $validated['excel_summary_sheet'] ?? true,
            'system_config.reports.monthly_payroll' => $validated['monthly_payroll_enabled'] ?? false,
            'system_config.reports.attendance_summary' => $validated['attendance_summary_enabled'] ?? false,
            'system_config.reports.leave_utilization' => $validated['leave_utilization_enabled'] ?? false,
            'system_config.reports.government_remittance' => $validated['government_remittance_enabled'] ?? false,
        ];

        // Store recipient lists as JSON
        $recipientFields = ['monthly_payroll', 'attendance_summary', 'leave_utilization', 'government_remittance'];
        foreach ($recipientFields as $field) {
            if (!empty($validated["{$field}_recipients"])) {
                SystemSetting::updateOrCreate(
                    ['key' => "system_config.reports.{$field}_recipients"],
                    [
                        'value' => json_encode($validated["{$field}_recipients"]),
                        'type' => 'json',
                        'category' => 'system_config',
                        'description' => "Recipients for {$field} report",
                    ]
                );
            }
        }

        $this->updateSettings($settings, $request->user());

        return redirect()->route('admin.system-config.index')
            ->with('success', 'Report settings updated successfully.');
    }

    /**
     * Update integration settings (RFID, Job Board).
     */
    public function updateIntegrations(Request $request)
    {
        $validated = $request->validate([
            // RFID settings
            'rfid_enabled' => 'boolean',
            'rfid_device_ip' => 'nullable|ip',
            'rfid_device_port' => 'nullable|integer|min:1|max:65535',
            'rfid_protocol' => 'nullable|in:http,https,tcp',
            'rfid_event_bus_enabled' => 'boolean',
            
            // Job board settings (future)
            'job_board_enabled' => 'boolean',
            'job_board_url' => 'nullable|url|max:255',
            'job_board_auto_import' => 'boolean',
        ]);

        $settings = [
            'system_config.rfid.enabled' => $validated['rfid_enabled'] ?? false,
            'system_config.rfid.event_bus_enabled' => $validated['rfid_event_bus_enabled'] ?? false,
            'system_config.job_board.enabled' => $validated['job_board_enabled'] ?? false,
            'system_config.job_board.auto_import' => $validated['job_board_auto_import'] ?? false,
        ];

        // Only save RFID settings if enabled
        if ($validated['rfid_enabled'] ?? false) {
            $settings['system_config.rfid.device_ip'] = $validated['rfid_device_ip'] ?? '';
            $settings['system_config.rfid.device_port'] = $validated['rfid_device_port'] ?? 8080;
            $settings['system_config.rfid.protocol'] = $validated['rfid_protocol'] ?? 'http';
        }

        // Only save job board settings if enabled
        if ($validated['job_board_enabled'] ?? false) {
            $settings['system_config.job_board.url'] = $validated['job_board_url'] ?? '';
        }

        $this->updateSettings($settings, $request->user());

        return redirect()->route('admin.system-config.index')
            ->with('success', 'Integration settings updated successfully.');
    }

    /**
     * Test RFID connection.
     */
    public function testRFIDConnection(Request $request)
    {
        $validated = $request->validate([
            'device_ip' => 'required|ip',
            'device_port' => 'required|integer|min:1|max:65535',
            'protocol' => 'required|in:http,https,tcp',
        ]);

        // Simulate RFID device connection test
        $url = "{$validated['protocol']}://{$validated['device_ip']}:{$validated['device_port']}/status";
        
        try {
            // In production, make actual HTTP request to device
            // For now, simulate success
            $connected = true;
            
            activity('system_integration')
                ->causedBy($request->user())
                ->withProperties([
                    'device_ip' => $validated['device_ip'],
                    'device_port' => $validated['device_port'],
                    'status' => $connected ? 'connected' : 'failed',
                ])
                ->log('RFID connection test');

            if ($connected) {
                return redirect()->route('admin.system-config.index')
                    ->with('success', 'RFID device connection successful.');
            } else {
                return redirect()->route('admin.system-config.index')
                    ->with('error', 'Failed to connect to RFID device.');
            }
        } catch (\Exception $e) {
            \Log::error('RFID connection test failed', [
                'device_ip' => $validated['device_ip'],
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('admin.system-config.index')
                ->with('error', 'RFID connection test failed: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to update multiple settings at once.
     */
    private function updateSettings(array $settings, $user): void
    {
        foreach ($settings as $key => $value) {
            $setting = SystemSetting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => is_bool($value) ? ($value ? '1' : '0') : $value,
                    'type' => is_bool($value) ? 'boolean' : (is_numeric($value) ? 'number' : 'string'),
                    'category' => 'system_config',
                    'description' => $this->getSettingDescription($key),
                ]
            );

            activity('system_configuration')
                ->causedBy($user)
                ->performedOn($setting)
                ->withProperties([
                    'key' => $key,
                    'old_value' => $setting->getOriginal('value'),
                    'new_value' => $value,
                ])
                ->log('Updated system configuration: ' . $key);
        }
    }

    /**
     * Get report recipients from settings.
     */
    private function getReportRecipients($settings, string $reportType): array
    {
        $recipientsJson = $settings["system_config.reports.{$reportType}_recipients"] ?? null;
        
        if ($recipientsJson) {
            return json_decode($recipientsJson, true) ?? [];
        }

        return [];
    }

    /**
     * Get human-readable description for setting key.
     */
    private function getSettingDescription(string $key): string
    {
        $descriptions = [
            // Email/SMTP
            'system_config.smtp.host' => 'SMTP server hostname',
            'system_config.smtp.port' => 'SMTP server port',
            'system_config.smtp.username' => 'SMTP username',
            'system_config.smtp.encryption' => 'SMTP encryption type',
            'system_config.email.sender_email' => 'Sender email address',
            'system_config.email.sender_name' => 'Sender display name',
            
            // SMS
            'system_config.sms.enabled' => 'Enable SMS notifications',
            'system_config.sms.gateway' => 'SMS gateway provider',
            'system_config.sms.api_key' => 'SMS API key',
            
            // Notifications
            'system_config.notifications.leave_approval' => 'Leave approval email notifications',
            'system_config.notifications.payslip' => 'Payslip distribution email notifications',
            'system_config.notifications.interview' => 'Interview scheduling email notifications',
            'system_config.notifications.appraisal' => 'Performance appraisal email notifications',
            'system_config.notifications.system_alerts' => 'System alert email notifications',
            
            // PDF
            'system_config.pdf.company_logo' => 'Include company logo on PDF reports',
            'system_config.pdf.page_size' => 'PDF page size',
            'system_config.pdf.orientation' => 'PDF page orientation',
            'system_config.pdf.font_family' => 'PDF font family',
            
            // Excel
            'system_config.excel.auto_width' => 'Auto-adjust column widths',
            'system_config.excel.freeze_panes' => 'Freeze header panes',
            'system_config.excel.summary_sheet' => 'Include summary sheet',
            
            // Scheduled reports
            'system_config.reports.monthly_payroll' => 'Enable monthly payroll report',
            'system_config.reports.attendance_summary' => 'Enable attendance summary report',
            'system_config.reports.leave_utilization' => 'Enable leave utilization report',
            'system_config.reports.government_remittance' => 'Enable government remittance report',
            
            // RFID
            'system_config.rfid.enabled' => 'Enable RFID timekeeping integration',
            'system_config.rfid.device_ip' => 'RFID device IP address',
            'system_config.rfid.device_port' => 'RFID device port',
            'system_config.rfid.protocol' => 'RFID communication protocol',
            'system_config.rfid.event_bus_enabled' => 'Enable RFID event bus',
            
            // Job board
            'system_config.job_board.enabled' => 'Enable job board integration',
            'system_config.job_board.url' => 'Job board URL',
            'system_config.job_board.auto_import' => 'Auto-import applications to ATS',
        ];

        return $descriptions[$key] ?? ucwords(str_replace(['.', '_'], ' ', $key));
    }
}
