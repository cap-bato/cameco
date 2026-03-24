<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\GovernmentReport;
use App\Models\GovernmentRemittance;
use App\Models\PayrollPeriod;
use App\Models\User;

class GovernmentReportTest extends TestCase
{
    use RefreshDatabase;

    private PayrollPeriod $period;
    private GovernmentRemittance $remittance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->period = $this->makePeriod('2026-01', 'A');

        $this->remittance = GovernmentRemittance::create([
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
        ]);
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

    private function makeReport(array $overrides = []): GovernmentReport
    {
        return GovernmentReport::create(array_merge([
            'payroll_period_id'        => $this->period->id,
            'government_remittance_id' => $this->remittance->id,
            'agency'                   => 'sss',
            'report_type'              => 'r3',
            'report_name'              => 'SSS R3 Report',
            'report_period'            => '2026-01',
            'file_name'                => 'report.csv',
            'file_path'                => '/reports/report.csv',
            'file_type'                => 'csv',
            'status'                   => 'draft',
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function test_belongs_to_payroll_period(): void
    {
        $report = $this->makeReport();
        $this->assertTrue($report->payrollPeriod->is($this->period));
    }

    public function test_belongs_to_government_remittance(): void
    {
        $report = $this->makeReport();
        $this->assertTrue($report->governmentRemittance->is($this->remittance));
    }

    public function test_belongs_to_generated_by_user(): void
    {
        $user   = User::factory()->create();
        $report = $this->makeReport(['generated_by' => $user->id]);

        $this->assertTrue($report->generatedBy->is($user));
    }

    public function test_belongs_to_submitted_by_user(): void
    {
        $user   = User::factory()->create();
        $report = $this->makeReport(['submitted_by' => $user->id, 'status' => 'submitted']);

        $this->assertTrue($report->submittedBy->is($user));
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function test_scope_by_agency_filters_correctly(): void
    {
        $this->makeReport(['agency' => 'sss']);
        $this->makeReport(['agency' => 'philhealth']);
        $this->makeReport(['agency' => 'philhealth']);

        $sss      = GovernmentReport::byAgency('sss')->get();
        $philHealth = GovernmentReport::byAgency('philhealth')->get();

        $this->assertCount(1, $sss);
        $this->assertCount(2, $philHealth);
    }

    public function test_scope_by_report_type_filters_correctly(): void
    {
        $this->makeReport(['report_type' => 'r3']);
        $this->makeReport(['report_type' => 'ml2']);
        $this->makeReport(['report_type' => 'ml2']);

        $r3  = GovernmentReport::byReportType('r3')->get();
        $ml2 = GovernmentReport::byReportType('ml2')->get();

        $this->assertCount(1, $r3);
        $this->assertCount(2, $ml2);
    }

    public function test_scope_draft_returns_only_draft_records(): void
    {
        $this->makeReport(['status' => 'draft']);
        $this->makeReport(['status' => 'submitted']);
        $this->makeReport(['status' => 'draft']);

        $drafts = GovernmentReport::draft()->get();

        $this->assertCount(2, $drafts);
        foreach ($drafts as $r) {
            $this->assertEquals('draft', $r->status);
        }
    }

    public function test_scope_submitted_returns_only_submitted_records(): void
    {
        $this->makeReport(['status' => 'submitted']);
        $this->makeReport(['status' => 'draft']);
        $this->makeReport(['status' => 'submitted']);

        $submitted = GovernmentReport::submitted()->get();

        $this->assertCount(2, $submitted);
        foreach ($submitted as $r) {
            $this->assertEquals('submitted', $r->status);
        }
    }

    // -------------------------------------------------------------------------
    // Casts
    // -------------------------------------------------------------------------

    public function test_submitted_at_cast_returns_carbon(): void
    {
        $report = $this->makeReport(['submitted_at' => '2026-01-31 12:00:00', 'status' => 'submitted']);
        $this->assertInstanceOf(\Carbon\Carbon::class, $report->fresh()->submitted_at);
    }
}
