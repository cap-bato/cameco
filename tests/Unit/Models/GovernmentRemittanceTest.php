<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\GovernmentRemittance;
use App\Models\GovernmentReport;
use App\Models\PayrollPeriod;
use App\Models\User;

class GovernmentRemittanceTest extends TestCase
{
    use RefreshDatabase;

    private PayrollPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->period = $this->makePeriod('2026-01', 'A');
    }

    private function makePeriod(string $month, string $suffix = 'A'): PayrollPeriod
    {
        [$year, $m] = explode('-', $month);
        $start = "{$year}-{$m}-01";
        $end   = date('Y-m-t', strtotime($start));
        return PayrollPeriod::create([
            'period_number'           => "{$month}-{$suffix}",
            'period_name'             => date('F Y', strtotime($start)) . " – Period {$suffix}",
            'period_start'            => $start,
            'period_end'              => $end,
            'payment_date'            => date('Y-m-d', strtotime($end . ' +5 days')),
            'period_month'            => $month,
            'period_year'             => (int) $year,
            'timekeeping_cutoff_date' => $end,
            'leave_cutoff_date'       => $end,
            'adjustment_deadline'     => date('Y-m-d', strtotime($end . ' +2 days')),
        ]);
    }

    private function makeRemittance(array $overrides = []): GovernmentRemittance
    {
        return GovernmentRemittance::create(array_merge([
            'payroll_period_id' => $this->period->id,
            'agency'            => 'sss',
            'remittance_type'   => 'monthly',
            'status'            => 'pending',
            'remittance_month'  => '2026-01',
            'period_start'      => '2026-01-01',
            'period_end'        => '2026-01-31',
            'due_date'          => '2026-02-15',
            'total_amount'      => 5000.00,
            'employee_share'    => 2000.00,
            'employer_share'    => 3000.00,
            'is_late'           => false,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function test_belongs_to_payroll_period(): void
    {
        $remittance = $this->makeRemittance();
        $this->assertTrue($remittance->payrollPeriod->is($this->period));
    }

    public function test_has_many_reports(): void
    {
        $remittance = $this->makeRemittance();

        GovernmentReport::create([
            'payroll_period_id'        => $this->period->id,
            'government_remittance_id' => $remittance->id,
            'agency'                   => 'sss',
            'report_type'              => 'r3',
            'report_name'              => 'SSS R3 Report',
            'report_period'            => '2026-01',
            'file_name'                => 'sss-r3-2026-01.csv',
            'file_path'                => '/reports/sss-r3-2026-01.csv',
            'file_type'                => 'csv',
            'status'                   => 'draft',
        ]);
        GovernmentReport::create([
            'payroll_period_id'        => $this->period->id,
            'government_remittance_id' => $remittance->id,
            'agency'                   => 'sss',
            'report_type'              => 'ml2',
            'report_name'              => 'SSS ML2 Report',
            'report_period'            => '2026-01',
            'file_name'                => 'sss-ml2-2026-01.csv',
            'file_path'                => '/reports/sss-ml2-2026-01.csv',
            'file_type'                => 'csv',
            'status'                   => 'draft',
        ]);

        $this->assertCount(2, $remittance->reports);
    }

    public function test_belongs_to_prepared_by_user(): void
    {
        $user       = User::factory()->create();
        $remittance = $this->makeRemittance(['prepared_by' => $user->id]);

        $this->assertTrue($remittance->preparedBy->is($user));
    }

    public function test_belongs_to_submitted_by_user(): void
    {
        $user       = User::factory()->create();
        $remittance = $this->makeRemittance(['submitted_by' => $user->id, 'status' => 'submitted']);

        $this->assertTrue($remittance->submittedBy->is($user));
    }

    public function test_belongs_to_paid_by_user(): void
    {
        $user       = User::factory()->create();
        $remittance = $this->makeRemittance(['paid_by' => $user->id, 'status' => 'paid']);

        $this->assertTrue($remittance->paidBy->is($user));
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function test_scope_by_agency_filters_by_agency(): void
    {
        $this->makeRemittance(['agency' => 'sss']);
        $this->makeRemittance(['agency' => 'philhealth']);
        $this->makeRemittance(['agency' => 'philhealth']);

        $sss      = GovernmentRemittance::byAgency('sss')->get();
        $philHealth = GovernmentRemittance::byAgency('philhealth')->get();

        $this->assertCount(1, $sss);
        $this->assertCount(2, $philHealth);
    }

    public function test_scope_pending_returns_pending_and_ready_statuses(): void
    {
        $this->makeRemittance(['status' => 'pending']);
        $this->makeRemittance(['status' => 'ready']);
        $this->makeRemittance(['status' => 'paid']);

        $pending = GovernmentRemittance::pending()->get();

        $this->assertCount(2, $pending);
        foreach ($pending as $r) {
            $this->assertContains($r->status, ['pending', 'ready']);
        }
    }

    public function test_scope_overdue_returns_overdue_status(): void
    {
        $this->makeRemittance(['status' => 'overdue']);
        $this->makeRemittance(['status' => 'paid']);
        $this->makeRemittance(['status' => 'pending']);

        $overdue = GovernmentRemittance::overdue()->get();

        $this->assertGreaterThanOrEqual(1, $overdue->count());
        $filtered = $overdue->filter(fn ($r) => $r->status === 'overdue');
        $this->assertCount(1, $filtered);
    }

    public function test_scope_overdue_includes_late_non_paid_records(): void
    {
        $this->makeRemittance(['is_late' => true,  'status' => 'pending']); // should appear
        $this->makeRemittance(['is_late' => true,  'status' => 'paid']);    // should NOT appear
        $this->makeRemittance(['is_late' => false, 'status' => 'pending']); // should NOT appear

        $overdue = GovernmentRemittance::overdue()->get();

        $this->assertCount(1, $overdue);
        $this->assertTrue((bool) $overdue->first()->is_late);
        $this->assertNotEquals('paid', $overdue->first()->status);
    }
}
