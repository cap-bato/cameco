<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payslip</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #333;
        }
        .container {
            max-width: 8.5in;
            height: 11in;
            margin: 0 auto;
            padding: 0.5in;
            background: white;
        }
        .header {
            text-align: center;
            margin-bottom: 0.3in;
            border-bottom: 2px solid #333;
            padding-bottom: 0.2in;
        }
        .company-name {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 0.05in;
        }
        .document-title {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 0.05in;
        }
        .pay-period {
            font-size: 10px;
            color: #666;
        }
        .info-section {
            margin: 0.2in 0;
        }
        .info-row {
            display: flex;
            margin-bottom: 0.08in;
            font-size: 10px;
        }
        .info-label {
            width: 25%;
            font-weight: bold;
        }
        .info-value {
            width: 75%;
        }
        .earnings-section,
        .deductions-section {
            margin: 0.2in 0;
        }
        .section-title {
            font-weight: bold;
            background-color: #e8e8e8;
            padding: 0.05in 0.1in;
            margin-bottom: 0.08in;
            font-size: 11px;
            border: 1px solid #999;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.05in 0.1in;
            border-bottom: 1px solid #ddd;
            font-size: 10px;
        }
        .detail-label {
            flex: 1;
        }
        .detail-amount {
            width: 30%;
            text-align: right;
        }
        .subtotal-row {
            display: flex;
            justify-content: space-between;
            padding: 0.08in 0.1in;
            border-top: 1px solid #333;
            border-bottom: 1px solid #333;
            margin-bottom: 0.08in;
            font-weight: bold;
            font-size: 11px;
        }
        .subtotal-label {
            flex: 1;
        }
        .subtotal-amount {
            width: 30%;
            text-align: right;
        }
        .summary-section {
            margin-top: 0.2in;
            padding: 0.1in;
            background-color: #f9f9f9;
            border: 1px solid #ccc;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.05in 0;
            font-size: 10px;
            margin-bottom: 0.05in;
        }
        .net-pay-row {
            display: flex;
            justify-content: space-between;
            padding: 0.1in 0;
            font-size: 12px;
            font-weight: bold;
            border-top: 2px solid #333;
            border-bottom: 2px solid #333;
        }
        .footer {
            margin-top: 0.2in;
            font-size: 9px;
            text-align: center;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="company-name">CAMECO CORPORATION</div>
            <div class="document-title">PAYSLIP</div>
            <div class="pay-period">
                {{ $payslip['pay_period_start'] }} to {{ $payslip['pay_period_end'] }}
            </div>
        </div>

        <!-- Employee Information -->
        <div class="info-section">
            <div class="info-row">
                <div class="info-label">Employee Name:</div>
                <div class="info-value">{{ $employee['full_name'] }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Employee No.:</div>
                <div class="info-value">{{ $employee['employee_number'] }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Department:</div>
                <div class="info-value">{{ $employee['department'] }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Position:</div>
                <div class="info-value">{{ $employee['position'] }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Pay Date:</div>
                <div class="info-value">{{ $payslip['pay_date'] }}</div>
            </div>
        </div>

        <!-- Earnings Section -->
        <div class="earnings-section">
            <div class="section-title">EARNINGS</div>
            
            <div class="detail-row">
                <div class="detail-label">Basic Salary</div>
                <div class="detail-amount">₱ {{ number_format($payslip['basic_salary'], 2) }}</div>
            </div>

            @foreach ($payslip['allowances'] as $allowance)
                <div class="detail-row">
                    <div class="detail-label">{{ $allowance['name'] }}</div>
                    <div class="detail-amount">₱ {{ number_format($allowance['amount'], 2) }}</div>
                </div>
            @endforeach

            <div class="subtotal-row">
                <div class="subtotal-label">Gross Pay</div>
                <div class="subtotal-amount">₱ {{ number_format($payslip['gross_pay'], 2) }}</div>
            </div>
        </div>

        <!-- Deductions Section -->
        <div class="deductions-section">
            <div class="section-title">DEDUCTIONS</div>

            @foreach ($payslip['deductions'] as $deduction)
                <div class="detail-row">
                    <div class="detail-label">{{ $deduction['name'] }}</div>
                    <div class="detail-amount">₱ {{ number_format($deduction['amount'], 2) }}</div>
                </div>
            @endforeach

            <div class="subtotal-row">
                <div class="subtotal-label">Total Deductions</div>
                <div class="subtotal-amount">₱ {{ number_format(collect($payslip['deductions'])->sum('amount'), 2) }}</div>
            </div>
        </div>

        <!-- Summary -->
        <div class="summary-section">
            <div class="net-pay-row">
                <div>NET PAY (Take-Home)</div>
                <div>₱ {{ number_format($payslip['net_pay'], 2) }}</div>
            </div>
            
            <div class="summary-row" style="margin-top: 0.1in;">
                <div>Year-to-Date Gross</div>
                <div>₱ {{ number_format($payslip['year_to_date_gross'], 2) }}</div>
            </div>
            <div class="summary-row">
                <div>Year-to-Date Net</div>
                <div>₱ {{ number_format($payslip['year_to_date_net'], 2) }}</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>This is an official payslip. Please review for accuracy and contact Payroll if there are discrepancies.</p>
        </div>
    </div>
</body>
</html>
