<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Final Pay Computation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial', sans-serif;
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
        }
        .doc-title {
            font-size: 14pt;
            font-weight: bold;
            margin: 0.2in 0;
            text-decoration: underline;
        }
        .employee-info {
            font-size: 10pt;
            margin: 0.2in 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0.2in 0;
            font-size: 10pt;
        }
        table.info-table {
            margin-bottom: 0.2in;
        }
        table.info-table td {
            padding: 0.08in 0.1in;
        }
        table.info-table .label {
            font-weight: bold;
            width: 2in;
            border: none;
        }
        table.info-table .value {
            border: none;
        }
        table.breakdown th {
            background-color: #d9d9d9;
            border: 1px solid #999;
            padding: 0.1in;
            text-align: left;
            font-weight: bold;
            font-size: 9pt;
        }
        table.breakdown td {
            border: 1px solid #999;
            padding: 0.1in;
            font-size: 9pt;
        }
        table.breakdown .desc {
            text-align: left;
        }
        table.breakdown .amount {
            text-align: right;
            width: 1.2in;
        }
        .section-title {
            font-weight: bold;
            font-size: 10pt;
            margin-top: 0.15in;
            margin-bottom: 0.1in;
            background-color: #f0f0f0;
            padding: 0.05in 0.1in;
            border-left: 3px solid #333;
        }
        .total-row {
            font-weight: bold;
            background-color: #f0f0f0;
        }
        .grand-total {
            font-weight: bold;
            font-size: 11pt;
            background-color: #d9d9d9;
        }
        .final-amount {
            font-size: 12pt;
            font-weight: bold;
            padding: 0.1in;
            background-color: #e8f5e9;
            margin-top: 0.15in;
        }
        .final-amount .label {
            display: inline-block;
            width: 2.5in;
        }
        .final-amount .value {
            display: inline-block;
            text-align: right;
            width: 1.2in;
        }
        .notes {
            font-size: 9pt;
            margin-top: 0.2in;
            margin-bottom: 0.2in;
            border-top: 1px solid #999;
            padding-top: 0.1in;
        }
        .notes-title {
            font-weight: bold;
            margin-bottom: 0.05in;
        }
        .signature-section {
            margin-top: 0.3in;
            display: flex;
            justify-content: space-between;
            font-size: 9pt;
        }
        .signature-block {
            width: 2in;
            text-align: center;
        }
        .sig-line {
            border-top: 1px solid #333;
            margin-top: 0.25in;
            padding-top: 0.05in;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="company-name">COMPANY NAME</div>
            <div class="doc-title">FINAL PAY COMPUTATION</div>
        </div>

        <div class="employee-info">
            <table class="info-table">
                <tr>
                    <td class="label">Employee Name:</td>
                    <td class="value">{{ $employee_name }}</td>
                </tr>
                <tr>
                    <td class="label">Employee Number:</td>
                    <td class="value">{{ $employee_number }}</td>
                </tr>
                <tr>
                    <td class="label">Position:</td>
                    <td class="value">{{ $position }}</td>
                </tr>
                <tr>
                    <td class="label">Department:</td>
                    <td class="value">{{ $department }}</td>
                </tr>
                <tr>
                    <td class="label">Last Working Day:</td>
                    <td class="value">{{ $last_working_day }}</td>
                </tr>
                <tr>
                    <td class="label">Separation Type:</td>
                    <td class="value">{{ $separation_type }}</td>
                </tr>
            </table>
        </div>

        <div class="section-title">EARNINGS</div>
        <table class="breakdown">
            <tbody>
                <tr>
                    <td class="desc">Pro-rata Salary (Final Month)</td>
                    <td class="amount">{{ number_format($pro_rata_salary, 2) }}</td>
                </tr>
                <tr>
                    <td class="desc">Unused Leave Credits</td>
                    <td class="amount">{{ number_format($leave_value, 2) }}</td>
                </tr>
                <tr>
                    <td class="desc">Thirteenth Month Pay (1/12 of Annual Salary)</td>
                    <td class="amount">{{ number_format($thirteenth_month, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td class="desc">TOTAL EARNINGS</td>
                    <td class="amount">{{ number_format($gross_amount, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="section-title">DEDUCTIONS</div>
        <table class="breakdown">
            <tbody>
                @if($asset_liability > 0)
                <tr>
                    <td class="desc">Asset Liability (Lost/Damaged Items)</td>
                    <td class="amount">{{ number_format($asset_liability, 2) }}</td>
                </tr>
                @endif
                @if($loan_deduction > 0)
                <tr>
                    <td class="desc">Loan/Advance Deduction</td>
                    <td class="amount">{{ number_format($loan_deduction, 2) }}</td>
                </tr>
                @endif
                @if($asset_liability == 0 && $loan_deduction == 0)
                <tr>
                    <td class="desc">No Deductions</td>
                    <td class="amount">0.00</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td class="desc">TOTAL DEDUCTIONS</td>
                    <td class="amount">{{ number_format($total_deductions, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="final-amount">
            <span class="label">NET AMOUNT DUE:</span>
            <span class="value">{{ number_format($net_amount, 2) }}</span>
        </div>

        <div class="notes">
            <div class="notes-title">NOTES:</div>
            <ul style="margin-left: 0.3in; font-size: 9pt; line-height: 1.4;">
                <li>This computation is based on the employee's employment records as of the separation date.</li>
                <li>All deductions have been verified and are in accordance with company policy and applicable laws.</li>
                <li>Payment of the above amount should be made within the period specified by applicable labor regulations.</li>
                <li>This document is for internal accounting and settlement purposes.</li>
            </ul>
        </div>

        <div class="signature-section">
            <div class="signature-block">
                <div class="sig-line">HR Manager</div>
            </div>
            <div class="signature-block">
                <div class="sig-line">Finance Manager</div>
            </div>
            <div class="signature-block">
                <div class="sig-line">{{ $current_date }}</div>
            </div>
        </div>
    </div>
</body>
</html>
