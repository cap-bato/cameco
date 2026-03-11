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

        $this->period = PayrollPeriod::create([
            'period_name'  => 'January 2026',
            'period_month' => '2026-01',
            'period_start' => '2026-01-01',
            'period_end'   => '2026-01-31',
            'status'       => 'finalized',
        ]);

        $this->remittance = GovernmentRemittance::create([
            'payroll_period_id' => $this->period->id,
            'agency'            => 'SSS',
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

    private function makeReport(array $overrides = []): GovernmentReport
    {
        return GovernmentReport::create(array_merge([
            'payroll_period_id'        => $this->period->id,
            'government_remittance_id' => $this->remittance->id,
            'agency'                   => 'SSS',
            'report_type'              => 'R3',
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
        $this->makeReport(['agency' => 'SSS']);
        $this->makeReport(['agency' => 'PhilHealth']);
        $this->makeReport(['agency' => 'PhilHealth']);

        $sss      = GovernmentReport::byAgency('SSS')->get();
        $philHealth = GovernmentReport::byAgency('PhilHealth')->get();

        $this->assertCount(1, $sss);
        $this->assertCount(2, $philHealth);
    }

    public function test_scope_by_report_type_filters_correctly(): void
    {
        $this->makeReport(['report_type' => 'R3']);
        $this->makeReport(['report_type' => 'ML2']);
        $this->makeReport(['report_type' => 'ML2']);

        $r3  = GovernmentReport::byReportType('R3')->get();
        $ml2 = GovernmentReport::byReportType('ML2')->get();

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

    public function test_report_date_cast_returns_carbon(): void
    {
        $report = $this->makeReport(['report_date' => '2026-01-31']);
        $this->assertInstanceOf(\Carbon\Carbon::class, $report->fresh()->report_date);
    }
}
