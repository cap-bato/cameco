<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Clearance Certificate</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            line-height: 1.6;
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
        .cert-title {
            font-size: 16pt;
            font-weight: bold;
            margin: 0.3in 0 0.2in 0;
            text-decoration: underline;
        }
        .cert-number {
            font-size: 10pt;
            color: #666;
            margin-bottom: 0.3in;
        }
        .content {
            margin: 0.3in 0;
            font-size: 11pt;
        }
        .employee-info {
            margin: 0.2in 0;
            line-height: 1.8;
        }
        .label {
            font-weight: bold;
            display: inline-block;
            width: 1.5in;
        }
        .value {
            display: inline-block;
        }
        .clearance-list {
            margin: 0.3in 0;
            font-size: 10pt;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0.2in 0;
            font-size: 10pt;
        }
        table th {
            background-color: #f0f0f0;
            border: 1px solid #999;
            padding: 0.1in;
            text-align: left;
            font-weight: bold;
        }
        table td {
            border: 1px solid #999;
            padding: 0.1in;
        }
        .signature-section {
            margin-top: 0.4in;
            display: flex;
            justify-content: space-between;
        }
        .signature-block {
            width: 2.5in;
            text-align: center;
        }
        .sig-line {
            border-top: 1px solid #333;
            margin-top: 0.3in;
            padding-top: 0.05in;
            font-size: 9pt;
        }
        .stamp-area {
            margin-top: 0.2in;
            font-style: italic;
            color: #666;
            font-size: 9pt;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="company-name">COMPANY NAME</div>
            <div class="cert-title">CLEARANCE CERTIFICATE</div>
            <div class="cert-number">Certificate No: {{ $case_number }}</div>
        </div>

        <div class="content">
            <p>This is to certify that the following employee has been cleared and approved for separation from the company:</p>

            <div class="employee-info">
                <div><span class="label">Employee Name:</span><span class="value">{{ $employee_name }}</span></div>
                <div><span class="label">Employee Number:</span><span class="value">{{ $employee_number }}</span></div>
                <div><span class="label">Department:</span><span class="value">{{ $department }}</span></div>
                <div><span class="label">Last Working Day:</span><span class="value">{{ $last_working_day }}</span></div>
                <div><span class="label">Separation Type:</span><span class="value">{{ $separation_type }}</span></div>
                @if($separation_reason)
                <div><span class="label">Reason:</span><span class="value">{{ $separation_reason }}</span></div>
                @endif
            </div>

            <div class="clearance-list">
                <p><strong>Clearance Status by Department:</strong></p>
                <table>
                    <thead>
                        <tr>
                            <th>Department/Category</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Approved By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($clearance_items as $item)
                        <tr>
                            <td>{{ ucfirst($item['category']) }}</td>
                            <td>{{ $item['description'] }}</td>
                            <td>{{ ucfirst($item['status']) }}</td>
                            <td>{{ $item['approved_by'] ?? 'N/A' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p style="margin-top: 0.3in;">
                The above-named employee has successfully completed all clearance requirements and is authorized to separate from the company. All company property has been returned, and all outstanding obligations have been settled.
            </p>
        </div>

        <div class="signature-section">
            <div class="signature-block">
                <div class="sig-line">
                    <div>HR Coordinator / Manager</div>
                    <div class="stamp-area">{{ $hr_coordinator }}</div>
                </div>
            </div>
            <div class="signature-block">
                <div class="sig-line">
                    <div>Date</div>
                    <div class="stamp-area">{{ $current_date }}</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
