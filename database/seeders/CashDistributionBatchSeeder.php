<?php

namespace Database\Seeders;

use App\Models\CashDistributionBatch;
use App\Models\PayrollPayment;
use App\Models\PayrollPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CashDistributionBatchSeeder extends Seeder
{
    /**
     * Seed cash distribution batches for completed payroll periods.
     * Creates distribution records for periods where cash payments were made.
     */
    public function run(): void
    {
        // Avoid duplicate seeding
        if (CashDistributionBatch::count() > 0) {
            $this->command->warn('cash_distribution_batches already seeded — skipping.');
            return;
        }

        // Get completed periods
        $periods = PayrollPeriod::where('status', 'completed')->get();
        
        if ($periods->isEmpty()) {
            $this->command->warn('No completed payroll periods found. Run PayrollPeriodsSeeder first.');
            return;
        }

        $payrollOfficer = User::where('email', 'payroll@cameco.com')->first();
        $financeManager = User::where('email', 'finance@cameco.com')->first() 
            ?? User::where('email', 'hrmanager@cameco.com')->first();
            
        $withdrawnById = $financeManager?->id ?? 1;
        $countedById = $payrollOfficer?->id ?? 1;
        $witnessedById = $financeManager?->id ?? 1;
        $preparedById = $payrollOfficer?->id ?? 1;

        $batches = [];
        $now = Carbon::now();

        foreach ($periods as $period) {
            // Get cash payments for this period
            $cashPayments = PayrollPayment::where('payroll_period_id', $period->id)
                ->whereHas('paymentMethod', function ($query) {
                    $query->where('method_type', 'cash');
                })
                ->get();

            if ($cashPayments->isEmpty()) {
                continue; // Skip if no cash payments
            }

            $totalEmployees = $cashPayments->count();
            $distributedPayments = $cashPayments->where('status', 'paid');
            $unclaimedPayments = $cashPayments->where('status', 'unclaimed');

            $totalCashAmount = $cashPayments->sum('net_pay');
            $amountDistributed = $distributedPayments->sum('net_pay');
            $amountUnclaimed = $unclaimedPayments->sum('net_pay');

            // Generate denomination breakdown
            $denominationBreakdown = $this->generateDenominationBreakdown($totalCashAmount);

            $distributionDate = Carbon::parse($period->payment_date);
            $withdrawalDate = $distributionDate->copy()->subDays(1);

            $batches[] = [
                'payroll_period_id' => $period->id,
                'batch_number' => 'CASH-' . $period->period_number,
                'distribution_date' => $distributionDate->toDateString(),
                'distribution_location' => 'Main Office - Payroll Counter',
                'total_cash_amount' => $totalCashAmount,
                'total_employees' => $totalEmployees,
                'denomination_breakdown' => json_encode($denominationBreakdown),
                'withdrawal_source' => 'BDO Makati Branch',
                'withdrawal_reference' => 'WD-' . $withdrawalDate->format('Ymd') . '-' . rand(10000, 99999),
                'withdrawal_date' => $withdrawalDate->toDateString(),
                'withdrawn_by' => $withdrawnById,
                'counted_by' => $countedById,
                'witnessed_by' => $witnessedById,
                'verification_at' => $withdrawalDate->addHours(2),
                'verification_notes' => 'Cash counted and verified. All denominations match withdrawal request.',
                'envelopes_prepared' => $totalEmployees,
                'envelopes_distributed' => $distributedPayments->count(),
                'envelopes_unclaimed' => $unclaimedPayments->count(),
                'amount_distributed' => $amountDistributed,
                'amount_unclaimed' => $amountUnclaimed,
                'distribution_started_at' => $distributionDate->copy()->setTime(9, 0),
                'distribution_completed_at' => $distributionDate->copy()->setTime(17, 0),
                'unclaimed_deadline' => $distributionDate->copy()->addDays(5)->toDateString(),
                'unclaimed_disposition' => $amountUnclaimed > 0 ? 'pending_redeposit' : null,
                'redeposit_date' => $amountUnclaimed > 0 ? $distributionDate->copy()->addDays(7)->toDateString() : null,
                'redeposit_reference' => $amountUnclaimed > 0 ? 'RD-' . $distributionDate->copy()->addDays(7)->format('Ymd') . '-' . rand(10000, 99999) : null,
                'status' => 'completed',
                'accountability_report_path' => 'reports/cash-accountability/' . $period->period_number . '.pdf',
                'report_generated_at' => $distributionDate->copy()->addHours(18),
                'report_approved_by' => $witnessedById,
                'notes' => $amountUnclaimed > 0 
                    ? "Distribution completed. {$unclaimedPayments->count()} envelopes unclaimed, scheduled for redeposit."
                    : 'Distribution completed successfully. All envelopes claimed.',
                'prepared_by' => $preparedById,
                'created_at' => $withdrawalDate->copy()->subHours(24),
                'updated_at' => $now,
            ];
        }

        if (count($batches) > 0) {
            CashDistributionBatch::insert($batches);
            $this->command->info('✅ Seeded ' . count($batches) . ' cash distribution batches');
        } else {
            $this->command->warn('No cash payments found to create distribution batches.');
        }
    }

    /**
     * Generate realistic denomination breakdown for cash withdrawal.
     */
    private function generateDenominationBreakdown(float $totalAmount): array
    {
        // Philippine peso denominations
        $breakdown = [
            '1000' => 0,
            '500' => 0,
            '200' => 0,
            '100' => 0,
            '50' => 0,
            '20' => 0,
            '10' => 0,
            '5' => 0,
            '1' => 0,
        ];

        $remaining = (int)$totalAmount;

        // Prioritize larger bills but keep some smaller denominations for change
        $breakdown['1000'] = (int)($remaining * 0.6 / 1000);
        $remaining -= $breakdown['1000'] * 1000;

        $breakdown['500'] = (int)($remaining * 0.5 / 500);
        $remaining -= $breakdown['500'] * 500;

        $breakdown['200'] = (int)($remaining * 0.3 / 200);
        $remaining -= $breakdown['200'] * 200;

        $breakdown['100'] = (int)($remaining / 100);
        $remaining -= $breakdown['100'] * 100;

        $breakdown['50'] = (int)($remaining / 50);
        $remaining -= $breakdown['50'] * 50;

        $breakdown['20'] = (int)($remaining / 20);
        $remaining -= $breakdown['20'] * 20;

        // Remaining goes to smaller bills/coins
        $breakdown['10'] = (int)($remaining / 10);
        $remaining -= $breakdown['10'] * 10;

        $breakdown['5'] = (int)($remaining / 5);
        $remaining -= $breakdown['5'] * 5;

        $breakdown['1'] = $remaining;

        return $breakdown;
    }
}
