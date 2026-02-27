<?php

namespace Database\Seeders;

use App\Models\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PayrollPeriodsSeeder extends Seeder
{
    /**
     * Seed payroll periods for testing the payments module.
     * Creates periods for the last 6 months with various statuses.
     */
    public function run(): void
    {
        // Avoid duplicate seeding
        if (PayrollPeriod::count() > 0) {
            $this->command->warn('payroll_periods already seeded — skipping.');
            return;
        }

        $payrollOfficer = User::where('email', 'payroll@cameco.com')->first();
        $hrManager = User::where('email', 'hrmanager@cameco.com')->first();
        $creatorId = $payrollOfficer?->id ?? $hrManager?->id ?? 1;
        $approverId = $hrManager?->id ?? $creatorId;

        $now = Carbon::now();
        $periods = [];

        // Create 6 periods (last 6 months)
        for ($i = 5; $i >= 0; $i--) {
            $periodStart = $now->copy()->subMonths($i)->startOfMonth();
            $periodEnd = $periodStart->copy()->day(15);
            $paymentDate = $periodEnd->copy()->addDays(2);
            
            $periodNumber = sprintf('%s-%02d-1H', $periodStart->year, $periodStart->month);
            
            // Determine status based on how old the period is
            if ($i >= 3) {
                $status = 'completed'; // Older periods are completed
                $approvedAt = $periodEnd->copy()->subDays(2);
                $finalizedAt = $periodEnd->copy()->subDays(1);
                $lockedAt = $periodEnd->copy();
            } elseif ($i === 2) {
                $status = 'approved'; // Recently approved
                $approvedAt = $periodEnd->copy()->subDays(2);
                $finalizedAt = null;
                $lockedAt = null;
            } elseif ($i === 1) {
                $status = 'under_review'; // Under review
                $approvedAt = null;
                $finalizedAt = null;
                $lockedAt = null;
            } else {
                $status = 'calculated'; // Current period just calculated
                $approvedAt = null;
                $finalizedAt = null;
                $lockedAt = null;
            }

            $periods[] = [
                'period_number' => $periodNumber,
                'period_name' => $periodStart->format('F Y') . ' - 1st Half',
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'payment_date' => $paymentDate->toDateString(),
                'period_month' => $periodStart->month,
                'period_year' => $periodStart->year,
                'period_type' => 'regular',
                'timekeeping_cutoff_date' => $periodEnd->copy()->subDays(1)->toDateString(),
                'leave_cutoff_date' => $periodEnd->copy()->subDays(1)->toDateString(),
                'adjustment_deadline' => $periodEnd->copy()->subDays(3)->toDateString(),
                'total_employees' => rand(80, 120),
                'active_employees' => rand(75, 115),
                'excluded_employees' => rand(0, 5),
                'total_gross_pay' => rand(2500000, 3500000),
                'total_deductions' => rand(400000, 600000),
                'total_net_pay' => rand(2000000, 3000000),
                'total_government_contributions' => rand(150000, 250000),
                'total_loan_deductions' => rand(50000, 100000),
                'total_adjustments' => rand(-10000, 10000),
                'status' => $status,
                'calculation_started_at' => $periodEnd->copy()->subDays(5),
                'calculation_completed_at' => $periodEnd->copy()->subDays(4),
                'submitted_for_review_at' => $status !== 'calculated' ? $periodEnd->copy()->subDays(3) : null,
                'reviewed_at' => in_array($status, ['approved', 'closed']) ? $periodEnd->copy()->subDays(2) : null,
                'approved_at' => $approvedAt,
                'finalized_at' => $finalizedAt,
                'locked_at' => $lockedAt,
                'exceptions_count' => rand(0, 5),
                'adjustments_count' => rand(0, 10),
                'timekeeping_data_locked' => in_array($status, ['approved', 'completed']),
                'leave_data_locked' => in_array($status, ['approved', 'completed']),
                'created_by' => $creatorId,
                'reviewed_by' => in_array($status, ['approved', 'completed']) ? $approverId : null,
                'approved_by' => in_array($status, ['approved', 'completed']) ? $approverId : null,
                'locked_by' => $status === 'completed' ? $approverId : null,
                'created_at' => $periodStart->copy()->subDays(10),
                'updated_at' => $now,
            ];

            // Second half of the month
            $periodStart2 = $periodEnd->copy()->addDay();
            $periodEnd2 = $periodStart->copy()->endOfMonth();
            $paymentDate2 = $periodEnd2->copy()->addDays(2);
            
            $periodNumber2 = sprintf('%s-%02d-2H', $periodStart->year, $periodStart->month);
            
            // Second half typically has same status completion pattern
            if ($i >= 3) {
                $status2 = 'completed';
                $approvedAt2 = $periodEnd2->copy()->subDays(2);
                $finalizedAt2 = $periodEnd2->copy()->subDays(1);
                $lockedAt2 = $periodEnd2->copy();
            } elseif ($i === 2) {
                $status2 = 'completed';
                $approvedAt2 = $periodEnd2->copy()->subDays(2);
                $finalizedAt2 = $periodEnd2->copy()->subDays(1);
                $lockedAt2 = $periodEnd2->copy();
            } elseif ($i === 1) {
                $status2 = 'approved';
                $approvedAt2 = $periodEnd2->copy()->subDays(2);
                $finalizedAt2 = null;
                $lockedAt2 = null;
            } else {
                $status2 = 'under_review';
                $approvedAt2 = null;
                $finalizedAt2 = null;
                $lockedAt2 = null;
            }

            $periods[] = [
                'period_number' => $periodNumber2,
                'period_name' => $periodStart->format('F Y') . ' - 2nd Half',
                'period_start' => $periodStart2->toDateString(),
                'period_end' => $periodEnd2->toDateString(),
                'payment_date' => $paymentDate2->toDateString(),
                'period_month' => $periodStart->month,
                'period_year' => $periodStart->year,
                'period_type' => 'regular',
                'timekeeping_cutoff_date' => $periodEnd2->copy()->subDays(1)->toDateString(),
                'leave_cutoff_date' => $periodEnd2->copy()->subDays(1)->toDateString(),
                'adjustment_deadline' => $periodEnd2->copy()->subDays(3)->toDateString(),
                'total_employees' => rand(80, 120),
                'active_employees' => rand(75, 115),
                'excluded_employees' => rand(0, 5),
                'total_gross_pay' => rand(2500000, 3500000),
                'total_deductions' => rand(400000, 600000),
                'total_net_pay' => rand(2000000, 3000000),
                'total_government_contributions' => rand(150000, 250000),
                'total_loan_deductions' => rand(50000, 100000),
                'total_adjustments' => rand(-10000, 10000),
                'status' => $status2,
                'calculation_started_at' => $periodEnd2->copy()->subDays(5),
                'calculation_completed_at' => $periodEnd2->copy()->subDays(4),
                'submitted_for_review_at' => $status2 !== 'calculated' ? $periodEnd2->copy()->subDays(3) : null,
                'reviewed_at' => in_array($status2, ['approved', 'closed']) ? $periodEnd2->copy()->subDays(2) : null,
                'approved_at' => $approvedAt2,
                'finalized_at' => $finalizedAt2,
                'locked_at' => $lockedAt2,
                'exceptions_count' => rand(0, 5),
                'adjustments_count' => rand(0, 10),
                'timekeeping_data_locked' => in_array($status2, ['approved', 'completed']),
                'leave_data_locked' => in_array($status2, ['approved', 'completed']),
                'created_by' => $creatorId,
                'reviewed_by' => in_array($status2, ['approved', 'completed']) ? $approverId : null,
                'approved_by' => in_array($status2, ['approved', 'completed']) ? $approverId : null,
                'locked_by' => $status2 === 'completed' ? $approverId : null,
                'created_at' => $periodStart2->copy()->subDays(10),
                'updated_at' => $now,
            ];
        }

        PayrollPeriod::insert($periods);

        $this->command->info('✅ Seeded ' . count($periods) . ' payroll periods (6 months, semi-monthly)');
    }
}
