<?php

// Payroll Officer Routes
use Illuminate\Support\Facades\Route;
// use App\Http\Middleware\EnsureProfileComplete; for future useronboarding workflow
use App\Http\Middleware\EnsurePayrollOfficer;
use App\Http\Controllers\Payroll\DashboardController as PayrollDashboardController;
use App\Http\Controllers\Payroll\PayrollProcessing\PayrollPeriodController;
use App\Http\Controllers\Payroll\PayrollProcessing\PayrollCalculationController;
use App\Http\Controllers\Payroll\PayrollProcessing\PayrollAdjustmentController;
use App\Http\Controllers\Payroll\PayrollProcessing\PayrollReviewController;
use App\Http\Controllers\Payroll\EmployeePayroll\EmployeePayrollInfoController;
use App\Http\Controllers\Payroll\EmployeePayroll\SalaryComponentController;
use App\Http\Controllers\Payroll\EmployeePayroll\AllowancesDeductionsController;
use App\Http\Controllers\Payroll\Government\BIRController;
use App\Http\Controllers\Payroll\Government\SSSController;
use App\Http\Controllers\Payroll\Government\PhilHealthController;
use App\Http\Controllers\Payroll\Government\PagIbigController;
use App\Http\Controllers\Payroll\Government\GovernmentRemittancesController;
use App\Http\Controllers\Payroll\Payments\BankFilesController;
use App\Http\Controllers\Payroll\Payments\PayslipsController;
use App\Http\Controllers\Payroll\Payments\PaymentTrackingController;
use App\Http\Controllers\Payroll\Payments\CashPaymentController;
use App\Http\Controllers\Payroll\Reports\PayrollRegisterController;
use App\Http\Controllers\Payroll\Reports\PayrollGovernmentReportsController;
use App\Http\Controllers\Payroll\Reports\PayrollAnalyticsController;
use App\Http\Controllers\Payroll\Reports\PayrollAuditController;
use App\Http\Controllers\Payroll\EmployeePayroll\LoansController;
use App\Http\Controllers\Payroll\AdvancesController;

Route::prefix('payroll')->middleware(['auth', 'verified', EnsurePayrollOfficer::class])->group(function () {
    Route::name('payroll.')->group(function () {
        // Dashboard - Single Page (returns all widget data in one response)
        Route::get('/dashboard', [PayrollDashboardController::class, 'index'])
            ->middleware('permission:payroll.dashboard.view')
            ->name('dashboard');

        // Payroll Periods - Phase 1.1 & 1.2
        Route::get('/periods', [PayrollPeriodController::class, 'index'])->name('periods.index');
        Route::post('/periods', [PayrollPeriodController::class, 'store'])->name('periods.store');
        Route::get('/periods/{id}', [PayrollPeriodController::class, 'show'])->name('periods.show');
        Route::get('/periods/{id}/edit', [PayrollPeriodController::class, 'edit'])->name('periods.edit');
        Route::put('/periods/{id}', [PayrollPeriodController::class, 'update'])->name('periods.update');
        Route::delete('/periods/{id}', [PayrollPeriodController::class, 'destroy'])->name('periods.destroy');
        Route::post('/periods/{id}/calculate', [PayrollPeriodController::class, 'calculate'])->name('periods.calculate');
        Route::post('/periods/{id}/approve', [PayrollPeriodController::class, 'approve'])->name('periods.approve');

        // Payroll Calculations - Phase 1.3
        Route::get('/calculations', [PayrollCalculationController::class, 'index'])->name('calculations.index');
        Route::post('/calculations', [PayrollCalculationController::class, 'store'])->name('calculations.store');
        Route::get('/calculations/{id}', [PayrollCalculationController::class, 'show'])->name('calculations.show');
        Route::post('/calculations/{id}/recalculate', [PayrollCalculationController::class, 'recalculate'])->name('calculations.recalculate');
        Route::post('/calculations/{id}/approve', [PayrollCalculationController::class, 'approve'])->name('calculations.approve');
        Route::delete('/calculations/{id}', [PayrollCalculationController::class, 'destroy'])->name('calculations.destroy');

        // Payroll Adjustments - Phase 1.4
        Route::get('/adjustments', [PayrollAdjustmentController::class, 'index'])->name('adjustments.index');
        Route::post('/adjustments', [PayrollAdjustmentController::class, 'store'])->name('adjustments.store');
        Route::get('/adjustments/{id}', [PayrollAdjustmentController::class, 'show'])->name('adjustments.show');
        Route::put('/adjustments/{id}', [PayrollAdjustmentController::class, 'update'])->name('adjustments.update');
        Route::delete('/adjustments/{id}', [PayrollAdjustmentController::class, 'destroy'])->name('adjustments.destroy');
        Route::post('/adjustments/{id}/approve', [PayrollAdjustmentController::class, 'approve'])->name('adjustments.approve');
        Route::post('/adjustments/{id}/reject', [PayrollAdjustmentController::class, 'reject'])->name('adjustments.reject');
        Route::get('/adjustments/history/{employeeId}', [PayrollAdjustmentController::class, 'history'])->name('adjustments.history');

        // Employee Payroll Info - Phase 2.1
        Route::get('/employee-payroll-info', [EmployeePayrollInfoController::class, 'index'])->name('employee-payroll-info.index');
        Route::post('/employee-payroll-info', [EmployeePayrollInfoController::class, 'store'])->name('employee-payroll-info.store');
        Route::get('/employee-payroll-info/create', [EmployeePayrollInfoController::class, 'create'])->name('employee-payroll-info.create');
        Route::get('/employee-payroll-info/{id}', [EmployeePayrollInfoController::class, 'show'])->name('employee-payroll-info.show');
        Route::get('/employee-payroll-info/{id}/edit', [EmployeePayrollInfoController::class, 'edit'])->name('employee-payroll-info.edit');
        Route::put('/employee-payroll-info/{id}', [EmployeePayrollInfoController::class, 'update'])->name('employee-payroll-info.update');
        Route::delete('/employee-payroll-info/{id}', [EmployeePayrollInfoController::class, 'destroy'])->name('employee-payroll-info.destroy');

        // Salary Components - Phase 2.2
        Route::get('/salary-components', [SalaryComponentController::class, 'index'])->name('salary-components.index');
        Route::post('/salary-components', [SalaryComponentController::class, 'store'])->name('salary-components.store');
        Route::get('/salary-components/create', [SalaryComponentController::class, 'create'])->name('salary-components.create');
        Route::get('/salary-components/{id}', [SalaryComponentController::class, 'show'])->name('salary-components.show');
        Route::get('/salary-components/{id}/edit', [SalaryComponentController::class, 'edit'])->name('salary-components.edit');
        Route::put('/salary-components/{id}', [SalaryComponentController::class, 'update'])->name('salary-components.update');
        Route::delete('/salary-components/{id}', [SalaryComponentController::class, 'destroy'])->name('salary-components.destroy');

        // Allowances & Deductions - Phase 2.3
        Route::get('/allowances-deductions', [AllowancesDeductionsController::class, 'index'])->name('allowances-deductions.index');
        Route::post('/allowances-deductions', [AllowancesDeductionsController::class, 'store'])->name('allowances-deductions.store');
        Route::get('/allowances-deductions/bulk-assign', [AllowancesDeductionsController::class, 'bulkAssignPage'])->name('allowances-deductions.bulk-assign-page');
        Route::post('/allowances-deductions/bulk-assign', [AllowancesDeductionsController::class, 'bulkAssign'])->name('allowances-deductions.bulk-assign');
        Route::get('/allowances-deductions/{id}', [AllowancesDeductionsController::class, 'show'])->name('allowances-deductions.show');
        Route::put('/allowances-deductions/{id}', [AllowancesDeductionsController::class, 'update'])->name('allowances-deductions.update');
        Route::delete('/allowances-deductions/{id}', [AllowancesDeductionsController::class, 'destroy'])->name('allowances-deductions.destroy');
        Route::get('/allowances-deductions/{employeeId}/history', [AllowancesDeductionsController::class, 'history'])->name('allowances-deductions.history');

        // Loans & Advances - Phase 1.5 & 1.5b
        Route::get('/loans', [LoansController::class, 'index'])->name('loans.index');
        Route::get('/advances', [AdvancesController::class, 'index'])->name('advances.index');
        Route::post('/advances', [AdvancesController::class, 'store'])->name('advances.store');
        Route::post('/advances/{id}/approve', [AdvancesController::class, 'approve'])->name('advances.approve');
        Route::post('/advances/{id}/reject', [AdvancesController::class, 'reject'])->name('advances.reject');

        // Payroll Review & Approval - Phase 2.4
        Route::get('/review', [PayrollReviewController::class, 'index'])->name('review.index');
        Route::get('/review/{periodId}', [PayrollReviewController::class, 'index'])->name('review.show');
        Route::post('/review/{periodId}/approve', [PayrollReviewController::class, 'approve'])->name('review.approve');
        Route::post('/review/{periodId}/reject', [PayrollReviewController::class, 'reject'])->name('review.reject');
        Route::post('/review/{periodId}/lock', [PayrollReviewController::class, 'lock'])->name('review.lock');
        Route::post('/review/{periodId}/download-payslips', [PayrollReviewController::class, 'downloadPayslips'])->name('review.download-payslips');

        // BIR Reports - Phase 3.1
        Route::get('/government/bir', [BIRController::class, 'index'])->name('bir.index');
        Route::post('/government/bir/generate-1601c/{periodId}', [BIRController::class, 'generate1601C'])->name('bir.generate-1601c');
        Route::post('/government/bir/generate-2316/{periodId}', [BIRController::class, 'generate2316'])->name('bir.generate-2316');
        Route::post('/government/bir/generate-alphalist/{periodId}', [BIRController::class, 'generateAlphalist'])->name('bir.generate-alphalist');
        Route::get('/government/bir/download-1601c/{periodId}', [BIRController::class, 'download1601C'])->name('bir.download-1601c');
        Route::get('/government/bir/download-2316/{periodId}', [BIRController::class, 'download2316'])->name('bir.download-2316');
        Route::get('/government/bir/download-alphalist/{periodId}', [BIRController::class, 'downloadAlphalist'])->name('bir.download-alphalist');
        Route::post('/government/bir/submit-1601c/{periodId}', [BIRController::class, 'submit1601C'])->name('bir.submit-1601c');
        Route::get('/government/bir/download/{reportId}', [BIRController::class, 'download'])->name('bir.download');
        Route::post('/government/bir/submit/{reportId}', [BIRController::class, 'submit'])->name('bir.submit');

        // SSS Contributions - Phase 3.2
        Route::get('/government/sss', [SSSController::class, 'index'])->name('sss.index');
        Route::post('/government/sss/generate-r3/{periodId}', [SSSController::class, 'generateR3'])->name('sss.generate-r3');
        Route::get('/government/sss/download-r3/{reportId}', [SSSController::class, 'downloadR3'])->name('sss.download-r3');
        Route::get('/government/sss/download-contributions/{periodId}', [SSSController::class, 'downloadContributions'])->name('sss.download-contributions');
        Route::get('/government/sss/download/{reportId}', [SSSController::class, 'download'])->name('sss.download');
        Route::post('/government/sss/submit/{reportId}', [SSSController::class, 'submit'])->name('sss.submit');

        // PhilHealth Contributions - Phase 3.3
        Route::get('/government/philhealth', [PhilHealthController::class, 'index'])->name('philhealth.index');
        Route::post('/government/philhealth/generate-rf1/{periodId}', [PhilHealthController::class, 'generateRF1'])->name('philhealth.generate-rf1');
        Route::get('/government/philhealth/download-rf1/{reportId}', [PhilHealthController::class, 'downloadRF1'])->name('philhealth.download-rf1');
        Route::get('/government/philhealth/download-contributions/{periodId}', [PhilHealthController::class, 'downloadContributions'])->name('philhealth.download-contributions');
        Route::get('/government/philhealth/download/{reportId}', [PhilHealthController::class, 'download'])->name('philhealth.download');
        Route::post('/government/philhealth/submit/{reportId}', [PhilHealthController::class, 'submit'])->name('philhealth.submit');

        // Pag-IBIG Contributions - Phase 3.4
        Route::get('/government/pagibig', [PagIbigController::class, 'index'])->name('pagibig.index');
        Route::post('/government/pagibig/generate-mcrf/{periodId}', [PagIbigController::class, 'generateMCRF'])->name('pagibig.generate-mcrf');
        Route::get('/government/pagibig/download-mcrf/{reportId}', [PagIbigController::class, 'downloadMCRF'])->name('pagibig.download-mcrf');
        Route::get('/government/pagibig/download-contributions/{periodId}', [PagIbigController::class, 'downloadContributions'])->name('pagibig.download-contributions');
        Route::get('/government/pagibig/download/{reportId}', [PagIbigController::class, 'download'])->name('pagibig.download');
        Route::post('/government/pagibig/submit/{reportId}', [PagIbigController::class, 'submit'])->name('pagibig.submit');

        // Government Remittances - Phase 3.5
        Route::get('/government/remittances', [GovernmentRemittancesController::class, 'index'])->name('remittances.index');
        Route::post('/government/remittances/{id}/record-payment', [GovernmentRemittancesController::class, 'recordPayment'])->name('remittances.record-payment');
        Route::post('/government/remittances/{id}/send-reminder', [GovernmentRemittancesController::class, 'sendReminder'])->name('remittances.send-reminder');

        // Bank Files - Phase 4.1
        Route::get('/bank-files', [BankFilesController::class, 'index'])->name('bank-files.index');
        Route::post('/bank-files/generate', [BankFilesController::class, 'generateFile'])->name('bank-files.generate');
        Route::post('/bank-files/{id}/validate', [BankFilesController::class, 'validateFile'])->name('bank-files.validate');
        Route::post('/bank-files/{id}/upload', [BankFilesController::class, 'uploadFile'])->name('bank-files.upload');
        Route::get('/bank-files/{id}/download', [BankFilesController::class, 'downloadFile'])->name('bank-files.download');
        Route::post('/bank-files/{id}/regenerate', [BankFilesController::class, 'regenerateFile'])->name('bank-files.regenerate');

        // Payslips - Phase 4.2
        Route::get('/payslips', [PayslipsController::class, 'index'])->name('payslips.index');
        Route::post('/payslips/generate', [PayslipsController::class, 'generate'])->name('payslips.generate');
        Route::post('/payslips/distribute', [PayslipsController::class, 'distribute'])->name('payslips.distribute');
        Route::get('/payslips/{id}/preview', [PayslipsController::class, 'preview'])->name('payslips.preview');
        Route::get('/payslips/{id}/download', [PayslipsController::class, 'download'])->name('payslips.download');
        Route::post('/payslips/{id}/email', [PayslipsController::class, 'email'])->name('payslips.email');
        Route::get('/payslips/{id}/print', [PayslipsController::class, 'print'])->name('payslips.print');
        Route::post('/payslips/bulk-download', [PayslipsController::class, 'bulkDownload'])->name('payslips.bulk-download');
        Route::post('/payslips/bulk-email', [PayslipsController::class, 'bulkEmail'])->name('payslips.bulk-email');

        // Payment Tracking - Phase 4.3
        Route::get('/payments/tracking', [PaymentTrackingController::class, 'index'])->name('payments.tracking.index');
        Route::post('/payments/tracking/confirm', [PaymentTrackingController::class, 'confirm'])->name('payments.tracking.confirm');
        Route::post('/payments/tracking/mark-paid', [PaymentTrackingController::class, 'markPaid'])->name('payments.tracking.mark-paid');
        Route::post('/payments/tracking/retry', [PaymentTrackingController::class, 'retry'])->name('payments.tracking.retry');
        Route::post('/payments/tracking/change-method', [PaymentTrackingController::class, 'changeMethod'])->name('payments.tracking.change-method');

        // Cash Payments - Phase 4.4
        Route::get('/payments/cash', [CashPaymentController::class, 'index'])->name('payments.cash.index');
        Route::post('/payments/cash/generate-envelopes', [CashPaymentController::class, 'generateEnvelopes'])->name('payments.cash.generate-envelopes');
        Route::post('/payments/cash/record-distribution', [CashPaymentController::class, 'recordDistribution'])->name('payments.cash.record-distribution');
        Route::post('/payments/cash/mark-claimed', [CashPaymentController::class, 'markClaimed'])->name('payments.cash.mark-claimed');
        Route::post('/payments/cash/record-contact-attempt', [CashPaymentController::class, 'recordContactAttempt'])->name('payments.cash.record-contact-attempt');
        Route::get('/payments/cash/accountability-report', [CashPaymentController::class, 'generateAccountabilityReport'])->name('payments.cash.accountability-report');
        Route::get('/payments/cash/accountability-report/pdf', [CashPaymentController::class, 'downloadAccountabilityReportPdf'])->name('payments.cash.accountability-report.pdf');

        // Payroll Register Reports - Phase 5.1
        Route::get('/reports/register', [PayrollRegisterController::class, 'index'])->name('reports.register.index');

        // Government Reports Summary - Phase 5.2
        Route::get('/reports/government', [PayrollGovernmentReportsController::class, 'index'])->name('reports.government.index');
        Route::get('/reports/analytics', [PayrollAnalyticsController::class, 'index'])->name('reports.analytics.index');

        // Audit Trail & History - Phase 5.4
        Route::get('/reports/audit', [PayrollAuditController::class, 'index'])->name('reports.audit.index');
    });
});