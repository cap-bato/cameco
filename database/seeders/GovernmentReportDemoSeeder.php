<?php

namespace Database\Seeders;

use App\Models\GovernmentReport;
use App\Models\PayrollPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GovernmentReportDemoSeeder extends Seeder
{
    /**
     * Seed demo GovernmentReport records for all periods (BIR, SSS, PhilHealth, Pag-IBIG).
     */
    public function run(): void
    {
        $this->command->info('Seeding GovernmentReport demo data for all periods...');

        $periods = PayrollPeriod::all();
        if ($periods->isEmpty()) {
            $this->command->warn('No periods found. Run PayrollPeriodsSeeder first.');
            return;
        }

        $agencies = [
            ['agency' => 'bir', 'report_type' => '1601c', 'report_name' => 'BIR 1601C', 'file_type' => 'pdf'],
            ['agency' => 'bir', 'report_type' => '2316',  'report_name' => 'BIR 2316',  'file_type' => 'pdf'],
            ['agency' => 'bir', 'report_type' => 'alphalist', 'report_name' => 'BIR Alphalist', 'file_type' => 'dat'],
            ['agency' => 'sss', 'report_type' => 'r3',    'report_name' => 'SSS R3',    'file_type' => 'csv'],
            ['agency' => 'philhealth', 'report_type' => 'rf1', 'report_name' => 'PhilHealth RF1', 'file_type' => 'csv'],
            ['agency' => 'pagibig', 'report_type' => 'm1', 'report_name' => 'Pag-IBIG M1', 'file_type' => 'csv'],
        ];

        $total = 0;
        foreach ($periods as $period) {
            foreach ($agencies as $agency) {
                $exists = GovernmentReport::where('payroll_period_id', $period->id)
                    ->where('agency', $agency['agency'])
                    ->where('report_type', $agency['report_type'])
                    ->exists();
                if ($exists) continue;

                $fileName = strtolower($agency['agency'] . '_' . $agency['report_type'] . '_' . $period->period_month . '.' . $agency['file_type']);
                $filePath = 'reports/demo/' . $fileName;

                $report = GovernmentReport::create([
                    'payroll_period_id' => $period->id,
                    'agency'            => $agency['agency'],
                    'report_type'       => $agency['report_type'],
                    'report_name'       => $agency['report_name'],
                    'report_period'     => $period->period_month,
                    'file_name'         => $fileName,
                    'file_path'         => $filePath,
                    'file_type'         => $agency['file_type'],
                    'file_size'         => rand(10000, 50000),
                    'status'            => 'draft',
                    'total_employees'   => rand(10, 50),
                    'total_compensation'=> rand(500000, 2000000),
                    'total_amount'      => rand(10000, 100000),
                    'total_tax_withheld'=> $agency['agency'] === 'bir' ? rand(5000, 50000) : null,
                    'rdo_code'          => $agency['agency'] === 'bir' ? '043' : null,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                // Create placeholder file for demo BIR reports
                if ($agency['agency'] === 'bir') {
                    $absPath = base_path('storage/app/' . $filePath);
                    $dir = dirname($absPath);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    if (!file_exists($absPath)) {
                        file_put_contents($absPath, "This is a demo placeholder for {$fileName}\n");
                    }
                }
                $total++;
            }
        }
        $this->command->info("✓ Seeded {$total} GovernmentReport demo records");
    }
}
