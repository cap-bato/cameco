{{--
    Cash Payment Envelopes - PDF Template

    Generates printable envelopes for cash payment distribution.
    Layout: A4 landscape, 3 envelopes per page

    Variables:
    - $envelopes: Array of envelope data
    - Each envelope contains: employee_number, employee_name, position, department,
      period_name, period_start, period_end, net_pay, formatted_net_pay, print_date

    DomPDF Limitations:
    - No Flexbox support, use <table> for layout
    - Limited CSS support, use inline styles
    - Colors must be hex values
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Cash Payment Envelopes - {{ $period_name }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0.8cm 1cm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11pt;
            color: #1a1a1a;
            line-height: 1.3;
        }

        /* Header Section */
        .page-header {
            text-align: center;
            margin-bottom: 12px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 8px;
            page-break-after: avoid;
        }

        .page-header .company-name {
            font-size: 14pt;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 3px;
        }

        .page-header .title {
            font-size: 12pt;
            font-weight: bold;
            color: #1a1a1a;
            margin-bottom: 2px;
        }

        .page-header .period-info {
            font-size: 9pt;
            color: #666;
        }

        .page-header .batch-info {
            font-size: 8pt;
            color: #999;
            margin-top: 3px;
        }

        /* Envelope Grid Container */
        .envelope-grid {
            width: 100%;
            margin-bottom: 8px;
            page-break-inside: avoid;
            display: table;
            border-collapse: collapse;
        }

        .envelope-row {
            display: table-row;
        }

        .envelope-cell {
            display: table-cell;
            width: 33.33%;
            padding: 6px;
            vertical-align: top;
            border-collapse: collapse;
        }

        /* Envelope Card */
        .envelope {
            border: 2px solid #1a1a1a;
            padding: 10px;
            background-color: #ffffff;
            min-height: 280px;
            display: flex;
            flex-direction: column;
            page-break-inside: avoid;
        }

        .envelope-header {
            border-bottom: 1px solid #ccc;
            padding-bottom: 6px;
            margin-bottom: 8px;
            page-break-inside: avoid;
        }

        .envelope-header .employee-number {
            font-size: 9pt;
            color: #666;
            margin-bottom: 2px;
        }

        .envelope-header .employee-name {
            font-size: 13pt;
            font-weight: bold;
            color: #1a1a1a;
            word-break: break-word;
        }

        .envelope-body {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            page-break-inside: avoid;
        }

        .envelope-info {
            margin-bottom: 8px;
            page-break-inside: avoid;
        }

        .envelope-info-row {
            display: table;
            width: 100%;
            margin-bottom: 4px;
        }

        .envelope-label {
            display: table-cell;
            font-size: 8pt;
            color: #666;
            font-weight: bold;
            width: 45%;
            padding-right: 4px;
        }

        .envelope-value {
            display: table-cell;
            font-size: 9pt;
            color: #1a1a1a;
            word-break: break-word;
        }

        .envelope-amount {
            background-color: #f0f9ff;
            border: 2px solid #2563eb;
            padding: 8px;
            text-align: center;
            margin-bottom: 8px;
            page-break-inside: avoid;
        }

        .envelope-amount .label {
            font-size: 8pt;
            color: #666;
            margin-bottom: 2px;
        }

        .envelope-amount .value {
            font-size: 18pt;
            font-weight: bold;
            color: #2563eb;
        }

        /* Barcodes and QR Code */
        .barcode-section {
            display: flex;
            justify-content: space-around;
            align-items: center;
            margin-bottom: 8px;
            page-break-inside: avoid;
        }

        .barcode-item {
            text-align: center;
            flex: 1;
        }

        .barcode-item .barcode-code {
            font-family: 'DejaVu Mono', monospace;
            font-size: 8pt;
            color: #1a1a1a;
            word-break: break-all;
            margin-bottom: 3px;
        }

        .barcode-item img {
            max-width: 80px;
            max-height: 40px;
        }

        /* Footer Section */
        .envelope-footer {
            border-top: 1px solid #ccc;
            padding-top: 6px;
            font-size: 7pt;
            color: #999;
            page-break-inside: avoid;
        }

        .footer-row {
            display: table;
            width: 100%;
            margin-bottom: 2px;
        }

        .footer-label {
            display: table-cell;
            width: 50%;
        }

        .footer-value {
            display: table-cell;
            text-align: right;
        }

        /* Page Break Logic */
        .page-break {
            page-break-after: always;
        }

        /* Print Metadata */
        .print-metadata {
            page-break-after: avoid;
            text-align: center;
            font-size: 8pt;
            color: #999;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
    </style>
</head>
<body>
    @php
        $envelopes = $data['envelopes'] ?? [];
        $period_name = $data['period_name'] ?? 'Unknown Period';
        $period_start = $data['period_start'] ?? '';
        $period_end = $data['period_end'] ?? '';
        $batch_number = $data['batch_number'] ?? 'BATCH-' . now()->format('YmdHis');
        $total_envelopes = $data['total_envelopes'] ?? count($envelopes);
        $total_amount = $data['total_amount'] ?? 0;
        $formatted_total = $data['formatted_total'] ?? '₱0.00';
        $generated_by = $data['generated_by'] ?? 'Payroll Officer';
        $generated_at = now()->format('Y-m-d H:i:s');
        $pages_needed = ceil(count($envelopes) / 3);
    @endphp

    @foreach (array_chunk($envelopes, 3) as $page_index => $page_envelopes)
        <!-- Page Header -->
        <div class="page-header">
            <div class="company-name">CATHAY METAL CORPORATION</div>
            <div class="title">Cash Payment Envelopes</div>
            <div class="period-info">
                {{ $period_name }}
                @if ($period_start && $period_end)
                    ({{ $period_start }} to {{ $period_end }})
                @endif
            </div>
            <div class="batch-info">
                Batch #{{ $batch_number }} | Page {{ $page_index + 1 }} of {{ $pages_needed }}
            </div>
        </div>

        <!-- Envelope Grid -->
        <div class="envelope-grid">
            @foreach ($page_envelopes as $index => $envelope)
                @if ($index % 1 === 0 && $index !== 0)
                    </div>
                    <div class="envelope-row">
                @endif
                @if ($index % 1 === 0)
                    <div class="envelope-row">
                @endif

                <div class="envelope-cell">
                    <div class="envelope">
                        <!-- Envelope Header -->
                        <div class="envelope-header">
                            <div class="employee-number">{{ $envelope['employee_number'] }}</div>
                            <div class="employee-name">{{ $envelope['employee_name'] }}</div>
                        </div>

                        <!-- Envelope Body -->
                        <div class="envelope-body">
                            <!-- Employee Info -->
                            <div class="envelope-info">
                                <div class="envelope-info-row">
                                    <div class="envelope-label">Position:</div>
                                    <div class="envelope-value">{{ $envelope['position'] }}</div>
                                </div>
                                <div class="envelope-info-row">
                                    <div class="envelope-label">Department:</div>
                                    <div class="envelope-value">{{ $envelope['department'] }}</div>
                                </div>
                            </div>

                            <!-- Period Info -->
                            <div class="envelope-info">
                                <div class="envelope-info-row">
                                    <div class="envelope-label">Period:</div>
                                    <div class="envelope-value">{{ $envelope['period_name'] }}</div>
                                </div>
                                @if ($envelope['period_start_date'] && $envelope['period_end_date'])
                                    <div class="envelope-info-row">
                                        <div class="envelope-label">Dates:</div>
                                        <div class="envelope-value">
                                            {{ date('M d', strtotime($envelope['period_start_date'])) }} -
                                            {{ date('M d, Y', strtotime($envelope['period_end_date'])) }}
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <!-- Amount (Prominent) -->
                            <div class="envelope-amount">
                                <div class="label">NET PAY</div>
                                <div class="value">{{ $envelope['formatted_net_pay'] }}</div>
                            </div>

                            <!-- Barcode Section -->
                            <div class="barcode-section">
                                <div class="barcode-item">
                                    <div class="barcode-code">{{ $envelope['barcode'] }}</div>
                                </div>
                                @if (!empty($envelope['qr_code']))
                                    <div class="barcode-item">
                                        <img src="{{ $envelope['qr_code'] }}" alt="QR Code" />
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Envelope Footer -->
                        <div class="envelope-footer">
                            <div class="footer-row">
                                <div class="footer-label">Printed:</div>
                                <div class="footer-value">{{ date('M d, Y', strtotime($envelope['print_date'])) }}</div>
                            </div>
                            <div class="footer-row">
                                <div class="footer-label">Page:</div>
                                <div class="footer-value">{{ $envelope['page_number'] }}/{{ $envelope['total_pages'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                @if (($index + 1) % 3 === 0 || $index === count($page_envelopes) - 1)
                    </div>
                @endif
            @endforeach
        </div>

        <!-- Print Metadata -->
        <div class="print-metadata">
            <strong>Summary</strong><br>
            Total Envelopes: {{ count($page_envelopes) }} |
            Page {{ $page_index + 1 }} of {{ $pages_needed }}<br>
            Generated: {{ $generated_at }} | By: {{ $generated_by }}
        </div>

        <!-- Page Break (except for last page) -->
        @if ($page_index < $pages_needed - 1)
            <div class="page-break"></div>
        @endif
    @endforeach

    <!-- Final Summary Page (Optional but useful) -->
    <div class="page-break"></div>
    <div class="page-header">
        <div class="company-name">CATHAY METAL CORPORATION</div>
        <div class="title">Cash Payment Envelopes - Summary</div>
        <div class="period-info">{{ $period_name }}</div>
    </div>

    <div style="margin-top: 20px; page-break-inside: avoid;">
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
            <tr style="background-color: #f3f4f6; border-bottom: 2px solid #d1d5db;">
                <td style="border: 1px solid #d1d5db; padding: 10px; font-weight: bold;">Total Envelopes</td>
                <td style="border: 1px solid #d1d5db; padding: 10px; text-align: right;">{{ $total_envelopes }}</td>
            </tr>
            <tr>
                <td style="border: 1px solid #d1d5db; padding: 10px; font-weight: bold;">Total Amount</td>
                <td style="border: 1px solid #d1d5db; padding: 10px; text-align: right; color: #2563eb; font-weight: bold;">{{ $formatted_total }}</td>
            </tr>
            <tr style="background-color: #f3f4f6;">
                <td style="border: 1px solid #d1d5db; padding: 10px; font-weight: bold;">Pages</td>
                <td style="border: 1px solid #d1d5db; padding: 10px; text-align: right;">{{ $pages_needed }}</td>
            </tr>
            <tr>
                <td style="border: 1px solid #d1d5db; padding: 10px; font-weight: bold;">Batch Number</td>
                <td style="border: 1px solid #d1d5db; padding: 10px; text-align: right;">{{ $batch_number }}</td>
            </tr>
            <tr style="background-color: #f3f4f6;">
                <td style="border: 1px solid #d1d5db; padding: 10px; font-weight: bold;">Generated</td>
                <td style="border: 1px solid #d1d5db; padding: 10px; text-align: right;">{{ $generated_at }}</td>
            </tr>
        </table>

        <div style="margin-top: 40px; page-break-inside: avoid;">
            <p style="font-weight: bold; margin-bottom: 10px;">Prepared By:</p>
            <div style="margin-top: 50px; border-top: 1px solid #333; padding-top: 8px;">
                <p>{{ $generated_by }}</p>
                <p style="font-size: 9pt; color: #666;">Payroll Officer</p>
            </div>
        </div>

        <div style="margin-top: 30px; padding: 10px; background-color: #f0f9ff; border-left: 4px solid #2563eb;">
            <p style="font-size: 9pt; color: #666; margin-bottom: 5px;">
                <strong>Note:</strong> These envelopes are confidential and contain sensitive employee salary information.
                Handle with care and ensure secure distribution to authorized recipients only.
            </p>
        </div>
    </div>
</body>
</html>
