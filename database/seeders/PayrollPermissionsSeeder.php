<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PayrollPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $this->command->info('Creating payroll permissions...');

        // Define all payroll permissions with descriptions
        $permissions = [
            // Dashboard
            'payroll.dashboard.view' => 'View payroll dashboard',

            // Payroll Periods
            'payroll.periods.view' => 'View payroll periods',
            'payroll.periods.create' => 'Create payroll periods',
            'payroll.periods.edit' => 'Edit payroll periods',
            'payroll.periods.delete' => 'Delete payroll periods',
            'payroll.periods.calculate' => 'Calculate payroll periods',
            'payroll.periods.approve' => 'Approve payroll periods',
            'payroll.periods.finalize' => 'Finalize payroll periods',
            'payroll.periods.close' => 'Close payroll periods',

            // Payroll Calculations
            'payroll.calculations.view' => 'View payroll calculations',
            'payroll.calculations.create' => 'Create payroll calculations',
            'payroll.calculations.adjust' => 'Adjust payroll calculations',
            'payroll.calculations.recalculate' => 'Recalculate payroll',
            'payroll.calculations.export' => 'Export payroll calculations',

            // Salary Components
            'payroll.components.view' => 'View salary components',
            'payroll.components.create' => 'Create salary components',
            'payroll.components.edit' => 'Edit salary components',
            'payroll.components.delete' => 'Delete salary components',
            'payroll.components.assign' => 'Assign components to employees',

            // Employee Payroll Info
            'payroll.employees.view' => 'View employee payroll information',
            'payroll.employees.edit' => 'Edit employee payroll information',
            'payroll.employees.update-salary' => 'Update employee salary',
            'payroll.employees.update-tax' => 'Update employee tax information',
            'payroll.employees.update-bank' => 'Update employee bank information',

            // Allowances & Deductions (legacy separate permissions)
            'payroll.allowances.view' => 'View allowances',
            'payroll.allowances.create' => 'Create allowances',
            'payroll.allowances.edit' => 'Edit allowances',
            'payroll.allowances.delete' => 'Delete allowances',
            'payroll.deductions.view' => 'View deductions',
            'payroll.deductions.create' => 'Create deductions',
            'payroll.deductions.edit' => 'Edit deductions',
            'payroll.deductions.delete' => 'Delete deductions',

            // Allowances & Deductions (consolidated permissions)
            'payroll.allowances-deductions.view' => 'View allowances and deductions',
            'payroll.allowances-deductions.manage' => 'Manage allowances and deductions (create/edit/delete)',

            'payroll.adjustments.view' => 'View adjustments',
            'payroll.adjustments.create' => 'Create adjustments',
            'payroll.adjustments.approve' => 'Approve adjustments',

            // Government Compliance - BIR
            'payroll.government.bir.view' => 'View BIR reports',
            'payroll.government.bir.generate-1601c' => 'Generate BIR 1601C',
            'payroll.government.bir.generate-2316' => 'Generate BIR 2316',
            'payroll.government.bir.generate-alphalist' => 'Generate BIR Alphalist',
            'payroll.government.bir.submit' => 'Submit BIR reports',

            // Government Compliance - SSS
            'payroll.government.sss.view' => 'View SSS reports',
            'payroll.government.sss.generate-r3' => 'Generate SSS R3',
            'payroll.government.sss.submit' => 'Submit SSS reports',
            'payroll.government.sss.reconcile' => 'Reconcile SSS contributions',

            // Government Compliance - PhilHealth
            'payroll.government.philhealth.view' => 'View PhilHealth reports',
            'payroll.government.philhealth.generate-rf1' => 'Generate PhilHealth RF1',
            'payroll.government.philhealth.submit' => 'Submit PhilHealth reports',
            'payroll.government.philhealth.reconcile' => 'Reconcile PhilHealth contributions',

            // Government Compliance - Pag-IBIG
            'payroll.government.pagibig.view' => 'View Pag-IBIG reports',
            'payroll.government.pagibig.generate-mcrf' => 'Generate Pag-IBIG MCRF',
            'payroll.government.pagibig.submit' => 'Submit Pag-IBIG reports',
            'payroll.government.pagibig.reconcile' => 'Reconcile Pag-IBIG contributions',

            // Government Compliance - 13th Month Pay
            'payroll.government.13th-month.calculate' => 'Calculate 13th month pay',
            'payroll.government.13th-month.generate' => 'Generate 13th month pay',
            'payroll.government.13th-month.distribute' => 'Distribute 13th month pay',

            // Government Remittances
            'payroll.government.remittances.view' => 'View government remittances',
            'payroll.government.remittances.create' => 'Create remittance records',
            'payroll.government.remittances.track' => 'Track remittance payments',

            // Bank & Payment
            'payroll.bank-files.view' => 'View bank files',
            'payroll.bank-files.generate' => 'Generate bank files',
            'payroll.bank-files.upload' => 'Upload bank files',
            'payroll.bank-files.validate' => 'Validate bank files',
            'payroll.payments.view' => 'View payments',
            'payroll.payments.track' => 'Track payment status',
            'payroll.payments.confirm' => 'Confirm payments',
            'payroll.cash-payments.view' => 'View cash payments',
            'payroll.cash-payments.prepare' => 'Prepare cash payments',
            'payroll.cash-payments.distribute' => 'Distribute cash payments',

            // Loans & Advances
            'payroll.loans.view' => 'View loans',
            'payroll.loans.create' => 'Create loans',
            'payroll.loans.update' => 'Update loans',
            'payroll.loans.edit' => 'Edit loans',
            'payroll.loans.delete' => 'Delete loans',
            'payroll.loans.process' => 'Process loan deductions',
            'payroll.advances.view' => 'View advances',
            'payroll.advances.create' => 'Create advances',
            'payroll.advances.edit' => 'Edit advances',
            'payroll.advances.delete' => 'Delete advances',
            'payroll.advances.process' => 'Process advance deductions',

            // Payslips
            'payroll.payslips.view' => 'View payslips',
            'payroll.payslips.generate' => 'Generate payslips',
            'payroll.payslips.distribute' => 'Distribute payslips',
            'payroll.payslips.reprint' => 'Reprint payslips',
            'payroll.payslips.email' => 'Email payslips',

            // Reports & Analytics
            'payroll.reports.view' => 'View payroll reports',
            'payroll.reports.generate' => 'Generate payroll reports',
            'payroll.reports.export' => 'Export payroll reports',
            'payroll.reports.payroll-register' => 'View payroll register',
            'payroll.reports.department-summary' => 'View department summary',
            'payroll.reports.cost-center' => 'View cost center reports',
            'payroll.reports.labor-cost' => 'View labor cost analysis',
            'payroll.reports.government' => 'View government reports',
            'payroll.reports.tax' => 'View tax reports',
            'payroll.analytics.view' => 'View payroll analytics',
            'payroll.analytics.trends' => 'View payroll trends',
            'payroll.analytics.cost-analysis' => 'View cost analysis',
            'payroll.analytics.budget-variance' => 'View budget variance',
            'payroll.analytics.forecasting' => 'View labor cost forecasting',

            // Audit & Compliance
            'payroll.audit.view' => 'View payroll audit trail',
            'payroll.audit.export' => 'Export audit logs',
            'payroll.compliance.view' => 'View compliance monitoring',
            'payroll.compliance.minimum-wage' => 'Monitor minimum wage compliance',
            'payroll.compliance.overtime' => 'Monitor overtime compliance',
            'payroll.compliance.holiday-pay' => 'Monitor holiday pay compliance',
            'payroll.compliance.labor-law' => 'Monitor labor law compliance',
            'payroll.reconciliation.view' => 'View reconciliations',
            'payroll.reconciliation.bank' => 'Perform bank reconciliation',
            'payroll.reconciliation.government' => 'Perform government remittance reconciliation',
            'payroll.reconciliation.gl' => 'Perform GL reconciliation',
            'payroll.reconciliation.variance' => 'Perform payroll variance analysis',

            // Integration Management
            'payroll.timekeeping.view' => 'View timekeeping integration',
            'payroll.timekeeping.import' => 'Import attendance data',
            'payroll.timekeeping.validate' => 'Validate attendance data',
            'payroll.timekeeping.correct' => 'Correct attendance data',
            'payroll.hr.view' => 'View HR data sync',
            'payroll.hr.sync' => 'Sync employee master data',
            'payroll.hr.new-hire' => 'Sync new hire data',
            'payroll.hr.separation' => 'Sync separation data',
            'payroll.accounting.view' => 'View accounting integration',
            'payroll.accounting.export' => 'Export journal entries',
            'payroll.accounting.cost-center' => 'Allocate cost centers',
            'payroll.accounting.gl-posting' => 'Post to GL',
        ];

        // Create permissions
        $createdCount = 0;
        foreach ($permissions as $name => $description) {
            $permission = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );
            
            if ($permission->wasRecentlyCreated) {
                $createdCount++;
            }
        }

        $this->command->info("✓ Created {$createdCount} new permissions");
        $this->command->info("✓ Total payroll permissions: " . count($permissions));

        // Assign all permissions to Payroll Officer role
        $payrollOfficerRole = Role::firstOrCreate(
            ['name' => 'Payroll Officer'],
            ['guard_name' => 'web']
        );

        $payrollOfficerRole->syncPermissions(array_keys($permissions));

        $this->command->info('✓ All permissions assigned to Payroll Officer role');

        // Also assign view-only payroll permissions to HR Manager (if role exists)
        $hrManagerRole = Role::where('name', 'hr_manager')->first();
        if ($hrManagerRole) {
            $hrManagerViewPermissions = array_filter(array_keys($permissions), function($permission) {
                // HR Manager gets view-only access to most payroll features
                return str_contains($permission, '.view') || 
                       str_contains($permission, '.dashboard') ||
                       $permission === 'payroll.reports.generate' ||
                       $permission === 'payroll.reports.export' ||
                       $permission === 'payroll.analytics.view';
            });

            $hrManagerRole->givePermissionTo($hrManagerViewPermissions);
            $this->command->info('✓ View-only payroll permissions assigned to HR Manager role');
        }

        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('  Payroll Permissions Created Successfully!');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('  Total Permissions: ' . count($permissions));
        $this->command->info('  Assigned to: Payroll Officer');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->newLine();
    }
}
