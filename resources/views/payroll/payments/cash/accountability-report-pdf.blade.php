<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cash Accountability Report - {{ $report['period_label'] }}</title>
    <style>
        @page {
            margin: 1.5cm 1cm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #333;
            line-height: 1.4;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .header h1 {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 5px;
            color: #1a1a1a;
        }

        .header .company-name {
            font-size: 18pt;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 8px;
        }

        .header .period {
            font-size: 11pt;
            color: #666;
            margin-top: 5px;
        }

        /* Summary Section */
        .summary-section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        .summary-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .summary-box {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            background-color: #f9fafb;
        }

        .summary-box .label {
            font-size: 9pt;
            color: #666;
            margin-bottom: 5px;
        }

        .summary-box .value {
            font-size: 14pt;
            font-weight: bold;
            color: #1a1a1a;
        }

        .summary-box .amount {
            font-size: 9pt;
            color: #666;
            margin-top: 3px;
        }

        .summary-box.distributed .value {
            color: #16a34a;
        }

        .summary-box.unclaimed .value {
            color: #dc2626;
        }

        /* Distribution Summary Table */
        .section-title {
            font-size: 12pt;
            font-weight: bold;
            margin: 20px 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            page-break-inside: avoid;
        }

        table.data-table th {
            background-color: #f3f4f6;
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            font-size: 9pt;
        }

        table.data-table td {
            border: 1px solid #e5e7eb;
            padding: 6px 8px;
            font-size: 9pt;
        }

        table.data-table tr:nth-child(even) {
            background-color: #f9fafb;
        }

        table.data-table .text-right {
            text-align: right;
        }

        table.data-table .text-center {
            text-align: center;
        }

        table.data-table .font-bold {
            font-weight: bold;
        }

        table.data-table .total-row {
            background-color: #f3f4f6;
            font-weight: bold;
            border-top: 2px solid #9ca3af;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: 600;
        }

        .status-distributed {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-unclaimed {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .amount-distributed {
            color: #16a34a;
            font-weight: bold;
        }

        .amount-unclaimed {
            color: #dc2626;
            font-weight: bold;
        }

        /* Employee Tables */
        .employee-section {
            margin-top: 25px;
            page-break-inside: avoid;
        }

        .employee-section.unclaimed {
            border: 2px solid #fee2e2;
            padding: 10px;
            background-color: #fef2f2;
        }

        .employee-section.unclaimed .section-title {
            color: #dc2626;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 8pt;
            color: #666;
        }

        .footer .generated-date {
            margin-bottom: 10px;
        }

        .footer .note {
            font-style: italic;
            margin-bottom: 5px;
            line-height: 1.6;
        }

        .signature-section {
            margin-top: 40px;
            page-break-inside: avoid;
        }

        .signature-row {
            width: 100%;
            margin-top: 50px;
        }

        .signature-box {
            display: inline-block;
            width: 45%;
            text-align: center;
            vertical-align: top;
        }

        .signature-box.left {
            margin-right: 8%;
        }

        .signature-line {
            border-top: 1px solid #333;
            margin-top: 40px;
            padding-top: 5px;
            font-size: 9pt;
        }

        .signature-label {
            font-size: 9pt;
            color: #666;
            margin-top: 3px;
        }

        /* Page numbers */
        .page-number {
            position: fixed;
            bottom: 0.5cm;
            right: 1cm;
            font-size: 8pt;
            color: #999;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="company-name">Cathay Metal Corporation</div>
        <h1>Cash Payment Accountability Report</h1>
        <div class="period">Period: {{ $report['period_label'] }}</div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-section">
        <table class="summary-grid">
            <tr>
                <td class="summary-box" width="25%">
                    <div class="label">Total Employees</div>
                    <div class="value">{{ $report['total_cash_employees'] }}</div>
                    <div class="amount">{{ $report['formatted_total'] }}</div>
                </td>
                <td class="summary-box distributed" width="25%">
                    <div class="label">Distributed</div>
                    <div class="value">{{ $report['distributed_count'] }}</div>
                    <div class="amount">{{ $report['formatted_distributed'] }}</div>
                </td>
                <td class="summary-box unclaimed" width="25%">
                    <div class="label">Unclaimed</div>
                    <div class="value">{{ $report['unclaimed_count'] }}</div>
                    <div class="amount">{{ $report['formatted_unclaimed'] }}</div>
                </td>
                <td class="summary-box" width="25%">
                    <div class="label">Distribution Rate</div>
                    <div class="value">{{ number_format($report['distribution_rate'], 1) }}%</div>
                    <div class="amount">of total amount</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Distribution Summary Table -->
    <div class="section-title">Distribution Summary</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Status</th>
                <th class="text-right">Count</th>
                <th class="text-right">Percentage</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <span class="status-badge status-distributed">Distributed</span>
                </td>
                <td class="text-right font-bold">{{ $report['distributed_count'] }}</td>
                <td class="text-right">
                    {{ $report['total_cash_employees'] > 0 ? number_format(($report['distributed_count'] / $report['total_cash_employees']) * 100, 1) : 0 }}%
                </td>
                <td class="text-right amount-distributed">{{ $report['formatted_distributed'] }}</td>
            </tr>
            <tr>
                <td>
                    <span class="status-badge status-unclaimed">Unclaimed</span>
                </td>
                <td class="text-right font-bold">{{ $report['unclaimed_count'] }}</td>
                <td class="text-right">
                    {{ $report['total_cash_employees'] > 0 ? number_format(($report['unclaimed_count'] / $report['total_cash_employees']) * 100, 1) : 0 }}%
                </td>
                <td class="text-right amount-unclaimed">{{ $report['formatted_unclaimed'] }}</td>
            </tr>
            <tr class="total-row">
                <td>Total</td>
                <td class="text-right">{{ $report['total_cash_employees'] }}</td>
                <td class="text-right">100%</td>
                <td class="text-right">{{ $report['formatted_total'] }}</td>
            </tr>
        </tbody>
    </table>

    @php
        $distributedEmployees = array_filter($employees, fn($e) => $e['distribution_status'] === 'distributed');
        $unclaimedEmployees = array_filter($employees, fn($e) => $e['distribution_status'] === 'unclaimed');
    @endphp

    <!-- Distributed Employees -->
    @if(count($distributedEmployees) > 0)
        <div class="employee-section">
            <div class="section-title">Distributed Employees ({{ count($distributedEmployees) }})</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee Name</th>
                        <th>Employee #</th>
                        <th class="text-right">Net Pay</th>
                        <th>Distributed By</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($distributedEmployees as $emp)
                        <tr>
                            <td>{{ $emp['employee_name'] }}</td>
                            <td>{{ $emp['employee_number'] }}</td>
                            <td class="text-right amount-distributed">{{ $emp['formatted_net_pay'] }}</td>
                            <td>{{ $emp['distributed_by'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Unclaimed Employees -->
    @if(count($unclaimedEmployees) > 0)
        <div class="employee-section unclaimed">
            <div class="section-title">Unclaimed Employees ({{ count($unclaimedEmployees) }})</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee Name</th>
                        <th>Employee #</th>
                        <th class="text-right">Amount</th>
                        <th>Department</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($unclaimedEmployees as $emp)
                        <tr>
                            <td>{{ $emp['employee_name'] }}</td>
                            <td>{{ $emp['employee_number'] }}</td>
                            <td class="text-right amount-unclaimed">{{ $emp['formatted_net_pay'] }}</td>
                            <td>{{ $emp['department'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <div class="generated-date">
            <strong>Generated on:</strong> {{ now()->format('F d, Y \a\t g:i A') }}
        </div>
        <div class="note">
            This report provides a summary of cash payment distribution for accounting and reconciliation purposes.
        </div>
        <div class="note">
            All amounts are in Philippine Pesos (₱).
        </div>
        <div class="note">
            This is a system-generated document. No signature is required for electronic copies.
        </div>
    </div>

    <!-- Signature Section (for printed copies) -->
    <div class="signature-section">
        <table style="width: 100%;">
            <tr>
                <td width="45%" style="vertical-align: top;">
                    <div class="signature-box">
                        <div class="signature-line">Prepared By</div>
                        <div class="signature-label">Payroll Officer</div>
                    </div>
                </td>
                <td width="10%"></td>
                <td width="45%" style="vertical-align: top;">
                    <div class="signature-box">
                        <div class="signature-line">Reviewed By</div>
                        <div class="signature-label">Finance Manager</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Page Number -->
    <div class="page-number">
        Page <script type="text/php">
            if (isset($pdf)) {
                $text = "Page {PAGE_NUM} of {PAGE_COUNT}";
                $font = $fontMetrics->get_font("sans-serif");
                $size = 8;
                $pageText = $text;
                $pdf->page_text(520, 820, $pageText, $font, $size, array(0.6, 0.6, 0.6));
            }
        </script>
    </div>
</body>
</html>
