<?php

namespace App\Services\HR;

use App\Models\EmployeeDocument;
use App\Models\Employee;
use App\Models\User;
use App\Models\DocumentAuditLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class DocumentExpiryReminderService
{
    /**
     * Days threshold for expiry warning
     */
    const EXPIRY_DAYS_THRESHOLD = 30;
    const EXPIRY_DAYS_SEVERE = 7;
    const EXPIRY_DAYS_MODERATE = 14;
    const EXPIRY_DAYS_WARNING = 30;

    /**
     * Days to wait before sending another reminder
     */
    const REMINDER_COOLDOWN_DAYS = 7;

    /**
     * Find documents expiring within threshold with employee information
     *
     * @param int $daysThreshold Number of days to check (default: 30)
     * @return Collection Documents expiring with full details
     */
    public function checkExpiringDocuments(int $daysThreshold = self::EXPIRY_DAYS_THRESHOLD): Collection
    {
        $today = Carbon::today();
        $expiryDate = $today->copy()->addDays($daysThreshold);
        $cooldownDate = $today->copy()->subDays(self::REMINDER_COOLDOWN_DAYS);

        return EmployeeDocument::query()
            ->with(['employee', 'employee.profile', 'uploadedBy', 'approvedBy'])
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$today, $expiryDate])
            ->approved()
            ->active()
            ->where(function ($query) use ($cooldownDate) {
                // Either no reminder sent yet, or last reminder was more than 7 days ago
                $query->whereNull('reminder_sent_at')
                      ->orWhere('reminder_sent_at', '<=', $cooldownDate);
            })
            ->orderBy('expires_at', 'asc')
            ->get();
    }

    /**
     * Send email reminders for expiring documents
     *
     * @param Collection|null $documents Documents to send reminders for (uses checkExpiringDocuments if null)
     * @param User|null $user User initiating reminders (for audit logging)
     * @return int Count of reminders sent
     */

    public function sendReminderNotifications(?Collection $documents = null, ?User $user = null): int
    {
        try { 
            // Fetch expiring documents if not provided
            if ($documents === null) {
                $documents = $this->checkExpiringDocuments();
            }

            $remindersSent = 0;
            $hrManagerRole = User::query()
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'HR Manager');
                })
                ->get();
            $hrStaffRole = User::query()
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'HR Staff');
                })
                ->get();
            $recipients = $hrManagerRole->merge($hrStaffRole)->unique('id');

            if ($recipients->isEmpty()) {
                Log::warning('No HR Staff or HR Manager users found for document expiry reminders');
                return 0;
            }

            // Group documents by employee for batch processing
            $documentsByEmployee = $documents->groupBy('employee_id');

            foreach ($documentsByEmployee as $employeeId => $employeeDocuments) {
                try {
                    $employee = Employee::find($employeeId);
                    if (!$employee) {
                        Log::warning("Employee {$employeeId} not found for expiry reminders");
                        continue;
                    }

                    // Send email to HR staff/managers
                    $this->sendExpiryEmailToHR($employee, $employeeDocuments, $recipients);

                    // Update reminder_sent_at and log action for each document
                    foreach ($employeeDocuments as $document) {
                        $document->markReminderSent($user);
                        $remindersSent++;

                        // Log the reminder sent action
                        DocumentAuditLog::log(
                            $document,
                            'reminder_sent',
                            $user,
                            [
                                'days_until_expiry' => $document->days_until_expiry,
                                'expiry_date' => $document->expires_at?->format('Y-m-d'),
                            ]
                        );
                    }
                } catch (\Exception $e) {
                    Log::error("Error sending expiry reminders for employee {$employeeId}: " . $e->getMessage());
                    continue;
                }
            }

            Log::info("Document expiry reminders sent: {$remindersSent} documents");
            return $remindersSent;

        } catch (\Exception $e) {
            Log::error('Error in sendReminderNotifications: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Send email notification to HR staff about expiring employee documents
     *
     * @param Employee $employee Employee with expiring documents
     * @param Collection $documents Expiring documents collection
     * @param Collection $recipients HR staff/managers to notify
     * @return void
     */
    protected function sendExpiryEmailToHR(Employee $employee, Collection $documents, Collection $recipients): void
    {
        $employeeName = $employee->profile?->full_name ?? $employee->user?->name ?? 'Unknown Employee';
        $employeeNumber = $employee->employee_number ?? 'N/A';

        // Categorize documents by severity
        $severe = $documents->filter(fn($doc) => $doc->days_until_expiry <= self::EXPIRY_DAYS_SEVERE);
        $moderate = $documents->filter(fn($doc) => 
            $doc->days_until_expiry > self::EXPIRY_DAYS_SEVERE && 
            $doc->days_until_expiry <= self::EXPIRY_DAYS_MODERATE
        );
        $warning = $documents->filter(fn($doc) => 
            $doc->days_until_expiry > self::EXPIRY_DAYS_MODERATE && 
            $doc->days_until_expiry <= self::EXPIRY_DAYS_WARNING
        );

        // Build email content
        $emailData = [
            'employee_name' => $employeeName,
            'employee_number' => $employeeNumber,
            'total_documents' => $documents->count(),
            'severe_documents' => $severe->values(),
            'moderate_documents' => $moderate->values(),
            'warning_documents' => $warning->values(),
            'documents' => $documents->values(),
            'action_url' => route('hr.documents.index', [], true),
        ];

        // Send email to all HR staff/managers
        foreach ($recipients as $recipient) {
            try {
                Mail::html($this->buildEmailHtml($emailData), function ($message) use ($recipient, $employeeName) {
                    $message->to($recipient->email)
                            ->subject("Document Expiry Alert - {$employeeName}")
                            ->from(config('mail.from.address'), config('mail.from.name'));
                });
            } catch (\Exception $e) {
                Log::error("Failed to send expiry reminder email to {$recipient->email}: " . $e->getMessage());
            }
        }
    }

    /**
     * Build HTML email content for document expiry reminders
     *
     * @param array $data Email data with employee and document information
     * @return string HTML email body
     */
    protected function buildEmailHtml(array $data): string
    {
        $severe = count($data['severe_documents']) > 0 ? $this->formatDocumentListHtml($data['severe_documents'], '#dc2626') : '';
        $moderate = count($data['moderate_documents']) > 0 ? $this->formatDocumentListHtml($data['moderate_documents'], '#f97316') : '';
        $warning = count($data['warning_documents']) > 0 ? $this->formatDocumentListHtml($data['warning_documents'], '#eab308') : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Expiry Alert</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #1e40af; color: white; padding: 20px; border-radius: 5px 5px 0 0; text-align: center; }
        .content { background-color: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; }
        .footer { background-color: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 0 0 5px 5px; font-size: 12px; color: #666; }
        .alert { padding: 12px; margin-bottom: 15px; border-left: 4px solid; border-radius: 4px; }
        .alert-severe { background-color: #fee2e2; border-color: #dc2626; }
        .alert-moderate { background-color: #ffedd5; border-color: #f97316; }
        .alert-warning { background-color: #fef9c3; border-color: #eab308; }
        .severity-badge { display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; margin-right: 8px; }
        .badge-severe { background-color: #dc2626; color: white; }
        .badge-moderate { background-color: #f97316; color: white; }
        .badge-warning { background-color: #eab308; color: #000; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background-color: #e9ecef; font-weight: bold; }
        .button { display: inline-block; padding: 10px 20px; background-color: #1e40af; color: white; text-decoration: none; border-radius: 4px; margin-top: 15px; }
        .section-title { font-weight: bold; margin: 20px 0 10px 0; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2 style="margin: 0;">Document Expiry Alert</h2>
        </div>
        <div class="content">
            <p>Hello HR Team,</p>
            
            <p>The following documents for <strong>{$data['employee_name']}</strong> (Employee #: {$data['employee_number']}) will expire soon:</p>

            {$severe}
            {$moderate}
            {$warning}

            <div style="margin-top: 20px; padding: 15px; background-color: white; border-left: 4px solid #1e40af;">
                <strong>Summary:</strong><br>
                Total expiring documents: {$data['total_documents']}<br>
                Severe (expires within 7 days): {$data['severe_documents']->count()}<br>
                Moderate (expires within 14 days): {$data['moderate_documents']->count()}<br>
                Warning (expires within 30 days): {$data['warning_documents']->count()}
            </div>

            <p style="margin-top: 20px;">Please review and renew or replace these documents as soon as possible to maintain compliance.</p>

            <a href="{$data['action_url']}" class="button">View in System</a>
        </div>
        <div class="footer">
            <p>This is an automated notification from the Document Management System.<br>
            Do not reply to this email. For assistance, contact HR.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Format document list as HTML table for email
     *
     * @param Collection $documents Documents to format
     * @param string $color Color for severity badge
     * @return string HTML table markup
     */
    protected function formatDocumentListHtml(Collection $documents, string $color): string
    {
        $rows = $documents->map(function ($doc) use ($color) {
            $severityClass = $doc->days_until_expiry <= 7 
                ? 'badge-severe' 
                : ($doc->days_until_expiry <= 14 ? 'badge-moderate' : 'badge-warning');
            
            $severityText = $doc->days_until_expiry <= 7 
                ? 'SEVERE' 
                : ($doc->days_until_expiry <= 14 ? 'MODERATE' : 'WARNING');

            return <<<ROW
            <tr>
                <td>{$doc->document_type}</td>
                <td>{$doc->document_category}</td>
                <td>{$doc->expires_at?->format('M d, Y')}</td>
                <td>
                    <span class="severity-badge {$severityClass}">{$severityText}</span>
                    {$doc->days_until_expiry} days
                </td>
            </tr>
ROW;
        })->join('');

        return <<<HTML
        <div class="section-title">Expiring Documents ({$documents->count()})</div>
        <table>
            <thead>
                <tr>
                    <th>Document Type</th>
                    <th>Category</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                {$rows}
            </tbody>
        </table>
HTML;
    }

    /**
     * Generate expiry report for dashboard widget
     * Groups documents by category and expiry window
     *
     * @return array Report data structured for dashboard
     */
    public function generateExpiryReport(): array
    {
        $today = Carbon::today();
        $severe = $today->copy()->addDays(self::EXPIRY_DAYS_SEVERE);
        $moderate = $today->copy()->addDays(self::EXPIRY_DAYS_MODERATE);
        $warning = $today->copy()->addDays(self::EXPIRY_DAYS_WARNING);

        $report = [
            'total' => 0,
            'expired' => 0,
            'severe' => 0,
            'moderate' => 0,
            'warning' => 0,
            'by_category' => [],
            'by_employee' => [],
            'generated_at' => Carbon::now(),
            'summary' => [],
        ];

        // Count expired documents
        $expiredCount = EmployeeDocument::query()
            ->expired()
            ->active()
            ->count();
        $report['expired'] = $expiredCount;
        $report['total'] += $expiredCount;

        // Count documents in each expiry window
        $severeCount = EmployeeDocument::query()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$today, $severe])
            ->active()
            ->count();
        $report['severe'] = $severeCount;
        $report['total'] += $severeCount;

        $moderateCount = EmployeeDocument::query()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$severe->copy()->addDay(), $moderate])
            ->active()
            ->count();
        $report['moderate'] = $moderateCount;
        $report['total'] += $moderateCount;

        $warningCount = EmployeeDocument::query()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$moderate->copy()->addDay(), $warning])
            ->active()
            ->count();
        $report['warning'] = $warningCount;
        $report['total'] += $warningCount;

        // Group by category
        foreach (EmployeeDocument::CATEGORIES as $categoryKey => $categoryLabel) {
            $categoryExpired = EmployeeDocument::query()
                ->byCategory($categoryKey)
                ->expired()
                ->active()
                ->count();

            $categorySevere = EmployeeDocument::query()
                ->byCategory($categoryKey)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [$today, $severe])
                ->active()
                ->count();

            $categoryModerate = EmployeeDocument::query()
                ->byCategory($categoryKey)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [$severe->copy()->addDay(), $moderate])
                ->active()
                ->count();

            $categoryWarning = EmployeeDocument::query()
                ->byCategory($categoryKey)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [$moderate->copy()->addDay(), $warning])
                ->active()
                ->count();

            $categoryTotal = $categoryExpired + $categorySevere + $categoryModerate + $categoryWarning;

            if ($categoryTotal > 0) {
                $report['by_category'][$categoryKey] = [
                    'label' => $categoryLabel,
                    'total' => $categoryTotal,
                    'expired' => $categoryExpired,
                    'severe' => $categorySevere,
                    'moderate' => $categoryModerate,
                    'warning' => $categoryWarning,
                ];
            }
        }

        // Generate summary statistics
        $report['summary'] = [
            'status_breakdown' => [
                'expired' => [
                    'count' => $report['expired'],
                    'label' => 'Expired',
                    'severity' => 'critical',
                    'color' => '#dc2626',
                ],
                'severe' => [
                    'count' => $report['severe'],
                    'label' => 'Expires within 7 days',
                    'severity' => 'severe',
                    'color' => '#dc2626',
                ],
                'moderate' => [
                    'count' => $report['moderate'],
                    'label' => 'Expires within 14 days',
                    'severity' => 'moderate',
                    'color' => '#f97316',
                ],
                'warning' => [
                    'count' => $report['warning'],
                    'label' => 'Expires within 30 days',
                    'severity' => 'warning',
                    'color' => '#eab308',
                ],
            ],
            'action_items' => [
                'immediate' => [
                    'count' => $report['expired'] + $report['severe'],
                    'label' => 'Requires immediate action',
                    'recommendation' => 'Contact employees to renew these documents urgently',
                ],
                'upcoming' => [
                    'count' => $report['moderate'],
                    'label' => 'Action needed within 2 weeks',
                    'recommendation' => 'Schedule document renewal meetings',
                ],
                'planned' => [
                    'count' => $report['warning'],
                    'label' => 'Plan for renewal',
                    'recommendation' => 'Add to monthly HR calendar',
                ],
            ],
        ];

        return $report;
    }

    /**
     * Get critical expiry statistics for quick dashboard view
     *
     * @return array Critical metrics
     */
    public function getCriticalStats(): array
    {
        $today = Carbon::today();
        $sevenDaysFromNow = $today->copy()->addDays(7);

        return [
            'expired_count' => EmployeeDocument::query()
                ->expired()
                ->active()
                ->count(),
            'expiring_soon_count' => EmployeeDocument::query()
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [$today, $sevenDaysFromNow])
                ->active()
                ->count(),
            'total_documents_with_expiry' => EmployeeDocument::query()
                ->whereNotNull('expires_at')
                ->active()
                ->count(),
            'action_required' => EmployeeDocument::query()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $sevenDaysFromNow)
                ->active()
                ->count(),
        ];
    }
}
