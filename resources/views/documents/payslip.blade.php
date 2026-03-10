<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; margin: 24px; }
        .header { text-align: center; margin-bottom: 20px; }
        .title { font-weight: bold; margin-bottom: 10px; }
        .section { margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px; border: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ config('app.company_name', 'Company Name') }}</div>
        <div class="title">PAYSLIP - {{ $period }}</div>
    </div>

    <div class="section">
        <strong>Employee:</strong> {{ $profile->full_name ?? 'N/A' }} ({{ $employee->employee_number ?? 'N/A' }})
    </div>

    <div class="section">
        <table>
            <thead>
                <tr><th>Description</th><th>Amount</th></tr>
            </thead>
            <tbody>
                <tr><td>Basic Salary</td><td>—</td></tr>
                <tr><td>Allowances</td><td>—</td></tr>
                <tr><td>Deductions</td><td>—</td></tr>
                <tr><td><strong>Net Pay</strong></td><td><strong>—</strong></td></tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <small>Generated: {{ $generated_date }}</small>
    </div>
</body>
</html>
