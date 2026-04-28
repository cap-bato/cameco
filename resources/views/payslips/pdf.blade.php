<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payslip - {{ $employee['full_name'] }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #2c3e50;
        }
        
        .container {
            max-width: 8.5in;
            height: 11in;
            margin: 0 auto;
            padding: 0.3in;
            background: white;
        }
        
        /* Professional Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.15in;
            padding-bottom: 0.1in;
            border-bottom: 3px solid #1a5490;
        }
        
        .header-left {
            flex: 1;
        }
        
        .company-name {
            font-size: 13pt;
            font-weight: 700;
            color: #1a5490;
            margin-bottom: 0.02in;
            letter-spacing: 0.5px;
        }
        
        .company-info {
            font-size: 8px;
            color: #666;
        }
        
        .header-right {
            text-align: right;
        }
        
        .document-title {
            font-size: 12pt;
            font-weight: 700;
            color: #1a5490;
            margin-bottom: 0.03in;
        }
        
        .pay-period {
            font-size: 9px;
            color: #666;
            margin-bottom: 0.02in;
        }
        
        /* Employee Information Section */
        .info-section {
            margin: 0.1in 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.1in;
        }
        
        .info-block {
            display: flex;
            flex-direction: column;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 0.03in;
            font-size: 9px;
        }
        
        .info-label {
            width: 45%;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .info-value {
            width: 55%;
            color: #555;
        }
        
        /* Main Content - Two Columns */
        .content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.1in;
            margin: 0.08in 0;
        }
        
        .earnings-section,
        .deductions-section {
            background: #f8f9fa;
            border: 1px solid #e3e8ef;
            border-radius: 4px;
            padding: 0.08in;
        }
        
        .section-title {
            font-weight: 700;
            background-color: #1a5490;
            color: white;
            padding: 0.05in 0.08in;
            margin-bottom: 0.05in;
            font-size: 10px;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.04in 0.08in;
            border-bottom: 1px solid #e8ecf1;
            font-size: 9px;
            align-items: center;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            flex: 1;
            color: #555;
        }
        
        .detail-amount {
            width: 35%;
            text-align: right;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 0.06in 0.08in;
            border-top: 2px solid #1a5490;
            border-bottom: 2px solid #1a5490;
            margin-top: 0.04in;
            font-weight: 700;
            font-size: 10px;
            background-color: #f0f4f8;
        }
        
        .total-label {
            flex: 1;
        }
        
        .total-amount {
            width: 35%;
            text-align: right;
            color: #1a5490;
        }
        
        /* Summary Section */
        .summary-section {
            margin-top: 0.08in;
            padding: 0.08in;
            background: linear-gradient(135deg, #f0f4f8 0%, #e8ecf1 100%);
            border: 1.5px solid #1a5490;
            border-radius: 4px;
        }
        
        .net-pay-row {
            display: flex;
            justify-content: space-between;
            padding: 0.06in 0.08in;
            font-size: 11pt;
            font-weight: 700;
            color: #fff;
            background-color: #1a5490;
            margin-bottom: 0.05in;
            border-radius: 3px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.03in 0;
            font-size: 9px;
            margin-bottom: 0.02in;
            color: #555;
        }
        
        .summary-label {
            font-weight: 600;
        }
        
        .summary-amount {
            text-align: right;
            min-width: 1.2in;
        }
        
        /* Footer */
        .footer {
            margin-top: 0.1in;
            padding-top: 0.05in;
            border-top: 1px solid #ddd;
            font-size: 8px;
            text-align: center;
            color: #999;
            line-height: 1.2;
        }
        
        .footer-note {
            color: #666;
            margin-bottom: 0.02in;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Professional Header -->
        <div class="header">
            <div class="header-left">
                <div class="company-name">CATHAY METAL CORPORATION</div>
                <div class="company-info">Professional Employee Payslip</div>
            </div>
            <div class="header-right">
                <div class="document-title">PAYSLIP</div>
                <div class="pay-period">
                    {{ $payslip['pay_period_start'] }} to {{ $payslip['pay_period_end'] }}
                </div>
                <div style="font-size: 8px; color: #999;">Pay Date: {{ $payslip['pay_date'] }}</div>
            </div>
        </div>

        <!-- Employee Information -->
        <div class="info-section">
            <div class="info-block">
                <div class="info-row">
                    <div class="info-label">Employee Name:</div>
                    <div class="info-value"><strong>{{ $employee['full_name'] }}</strong></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Employee ID:</div>
                    <div class="info-value">{{ $employee['employee_number'] }}</div>
                </div>
            </div>
            <div class="info-block">
                <div class="info-row">
                    <div class="info-label">Department:</div>
                    <div class="info-value">{{ $employee['department'] }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Position:</div>
                    <div class="info-value">{{ $employee['position'] }}</div>
                </div>
            </div>
        </div>

        <!-- Earnings and Deductions Side by Side -->
        <div class="content">
            <!-- Earnings Section -->
            <div class="earnings-section">
                <div class="section-title">EARNINGS</div>
                
                <div class="detail-row">
                    <div class="detail-label">Basic Salary</div>
                    <div class="detail-amount">P {{ number_format($payslip['basic_salary'], 2) }}</div>
                </div>

                @foreach ($payslip['allowances'] as $allowance)
                    <div class="detail-row">
                        <div class="detail-label">{{ $allowance['name'] }}</div>
                        <div class="detail-amount">P {{ number_format($allowance['amount'], 2) }}</div>
                    </div>
                @endforeach

                <div class="total-row">
                    <div class="total-label">GROSS PAY</div>
                    <div class="total-amount">P {{ number_format($payslip['gross_pay'], 2) }}</div>
                </div>
            </div>

            <!-- Deductions Section -->
            <div class="deductions-section">
                <div class="section-title">DEDUCTIONS</div>

                @foreach ($payslip['deductions'] as $deduction)
                    <div class="detail-row">
                        <div class="detail-label">{{ $deduction['name'] }}</div>
                        <div class="detail-amount">P {{ number_format($deduction['amount'], 2) }}</div>
                    </div>
                @endforeach

                <div class="total-row">
                    <div class="total-label">TOTAL DEDUCTIONS</div>
                    <div class="total-amount">P {{ number_format(collect($payslip['deductions'])->sum('amount'), 2) }}</div>
                </div>
            </div>
        </div>

        <!-- Summary Section -->
        <div class="summary-section">
            <div class="net-pay-row">
                <div>NET PAY (Take-Home Salary)</div>
                <div style="font-size: 11pt;">P {{ number_format($payslip['net_pay'], 2) }}</div>
            </div>
            
            <div style="margin-top: 0.05in;">
                <div class="summary-row">
                    <div class="summary-label">Year-to-Date Gross:</div>
                    <div class="summary-amount">P {{ number_format($payslip['year_to_date_gross'], 2) }}</div>
                </div>
                <div class="summary-row">
                    <div class="summary-label">Year-to-Date Deductions:</div>
                    <div class="summary-amount">P {{ number_format($payslip['year_to_date_deductions'], 2) }}</div>
                </div>
                <div class="summary-row" style="border-top: 1px solid #ccc; padding-top: 0.04in; margin-top: 0.02in;">
                    <div class="summary-label"><strong>Year-to-Date Net:</strong></div>
                    <div class="summary-amount"><strong>P {{ number_format($payslip['year_to_date_net'], 2) }}</strong></div>
                </div>
            </div>
        </div>

        <!-- Professional Footer -->
        <div class="footer">
            <div class="footer-note"><strong>Important Notice:</strong> This is a confidential payslip. Please verify all details for accuracy.</div>
            <div>If you notice any discrepancies, please contact the Payroll Department immediately.</div>
            <div style="margin-top: 0.03in; color: #999;">Confidential - For Employee Use Only</div>
        </div>
    </div>
</body>
</html>
