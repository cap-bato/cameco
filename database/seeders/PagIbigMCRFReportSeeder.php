<?php

namespace Database\Seeders;

use App\Models\GovernmentReport;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class PagIbigMCRFReportSeeder extends Seeder
{
    public function run(): void
    {
        $periods = PayrollPeriod::orderByDesc('period_start')->limit(6)->get();

        if ($periods->isEmpty()) {
            $this->command->warn('No payroll periods found. Run PayrollPeriodSeeder first.');
            return;
        }

        $generatedBy = User::first()?->id;

        $statuses = ['draft', 'draft', 'ready', 'submitted', 'accepted', 'rejected'];

        foreach ($periods as $index => $period) {
            $status        = $statuses[$index] ?? 'draft';
            $reportPeriod  = Carbon::parse($period->period_start)->format('Y-m');
            $employeeCount = rand(30, 80);

            // Pag-IBIG: EE max ₱100, ER max ₱100
            $avgCompensation   = rand(18000, 45000);
            $totalCompensation = $avgCompensation * $employeeCount;

            // EE: 2% of compensation, capped at ₱100 per employee
            $totalEmployeeShare = $employeeCount * min(100, $avgCompensation * 0.02);
            // ER: 2% of compensation, capped at ₱100 per employee
            $totalEmployerShare = $employeeCount * min(100, $avgCompensation * 0.02);
            $totalAmount        = $totalEmployeeShare + $totalEmployerShare;

            $fileName = "pagibig_mcrf_{$reportPeriod}.csv";
            $fileSize = rand(15000, 80000);

            $submittedAt        = null;
            $submissionRef      = null;
            $rejectionReason    = null;
            $isValidated        = false;
            $validatedAt        = null;

            if ($status === 'submitted') {
                $submittedAt   = Carbon::parse($period->period_end)->addDays(rand(3, 8));
                $submissionRef = 'PAGIBIG-' . strtoupper($reportPeriod) . '-' . rand(10000, 99999);
                $isValidated   = true;
                $validatedAt   = $submittedAt->copy()->addHours(rand(1, 24));
            }

            if ($status === 'accepted') {
                $submittedAt   = Carbon::parse($period->period_end)->addDays(rand(2, 7));
                $submissionRef = 'PAGIBIG-' . strtoupper($reportPeriod) . '-' . rand(10000, 99999);
                $isValidated   = true;
                $validatedAt   = $submittedAt->copy()->addHours(rand(1, 12));
            }

            if ($status === 'rejected') {
                $submittedAt     = Carbon::parse($period->period_end)->addDays(rand(2, 5));
                $submissionRef   = 'PAGIBIG-' . strtoupper($reportPeriod) . '-' . rand(10000, 99999);
                $isValidated     = true;
                $validatedAt     = $submittedAt->copy()->addHours(rand(1, 6));
                $rejectionReason = 'Invalid Pag-IBIG member numbers detected. Please verify and resubmit.';
            }

            GovernmentReport::updateOrCreate(
                [
                    'agency'            => 'pagibig',
                    'report_type'       => 'mcrf',
                    'payroll_period_id' => $period->id,
                ],
                [
                    'report_name'          => "Pag-IBIG MCRF {$reportPeriod}",
                    'report_period'        => $reportPeriod,
                    'file_name'            => $fileName,
                    'file_path'            => "government-reports/pagibig/mcrf/{$fileName}",
                    'file_type'            => 'csv',
                    'file_size'            => $fileSize,
                    'file_hash'            => hash('sha256', $fileName . $period->id),
                    'total_employees'      => $employeeCount,
                    'total_compensation'   => round($totalCompensation, 2),
                    'total_employee_share' => round($totalEmployeeShare, 2),
                    'total_employer_share' => round($totalEmployerShare, 2),
                    'total_amount'         => round($totalAmount, 2),
                    'status'               => $status,
                    'submitted_at'         => $submittedAt,
                    'submission_reference' => $submissionRef,
                    'rejection_reason'     => $rejectionReason,
                    'is_validated'         => $isValidated,
                    'validated_at'         => $validatedAt,
                    'generated_by'         => $generatedBy,
                    'submitted_by'         => in_array($status, ['submitted', 'accepted', 'rejected'])
                                                ? $generatedBy
                                                : null,
                    'notes'                => "Auto-seeded MCRF report for period {$reportPeriod}",
                ]
            );

            $this->command->info("Created Pag-IBIG MCRF report for {$reportPeriod} [{$status}]");
        }

        $this->command->info('Pag-IBIG MCRF seeder completed.');
    }
}