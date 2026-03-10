<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Certificate of Employment</title>
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
            padding: 0.75in;
            background: white;
        }
        .header {
            text-align: center;
            margin-bottom: 0.4in;
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
            margin: 0.3in 0 0.1in 0;
            text-decoration: underline;
        }
        .content {
            margin: 0.3in 0;
            font-size: 11pt;
            line-height: 1.8;
        }
        .greeting {
            margin-bottom: 0.2in;
        }
        .body-text {
            text-align: justify;
            margin: 0.2in 0;
        }
        .employee-details {
            margin: 0.2in 0;
        }
        .detail-line {
            margin: 0.1in 0;
        }
        .label {
            font-weight: bold;
            display: inline-block;
            width: 1.5in;
        }
        .value {
            display: inline;
        }
        .separation-details {
            margin: 0.2in 0;
            background-color: #f9f9f9;
            padding: 0.15in;
            border-left: 3px solid #333;
        }
        .rehire-note {
            font-weight: bold;
            font-size: 10pt;
            margin-top: 0.1in;
            padding: 0.1in;
            background-color: #fffacd;
        }
        .closing {
            margin-top: 0.3in;
            text-align: justify;
        }
        .signature-section {
            margin-top: 0.5in;
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
            <div class="cert-title">CERTIFICATE OF EMPLOYMENT</div>
        </div>

        <div class="content">
            <div class="greeting">
                To Whom It May Concern:
            </div>

            <div class="body-text">
                This is to certify that {{ $employee_name }} (Employee Number: {{ $employee_number }}) was an employee of this company in the capacity of {{ $position }} under the {{ $department }} Department.
            </div>

            <div class="employee-details">
                <div class="detail-line">
                    <span class="label">Date of Hire:</span>
                    <span class="value">{{ $date_hired }}</span>
                </div>
                <div class="detail-line">
                    <span class="label">Last Working Day:</span>
                    <span class="value">{{ $last_working_day }}</span>
                </div>
                <div class="detail-line">
                    <span class="label">Length of Service:</span>
                    <span class="value">
                        @if($employment_years > 0)
                            {{ $employment_years }} year(s)
                        @endif
                        @if($employment_months > 0)
                            {{ $employment_months }} month(s)
                        @endif
                        @if($employment_days > 0)
                            {{ $employment_days }} day(s)
                        @endif
                    </span>
                </div>
            </div>

            <div class="separation-details">
                <div class="detail-line">
                    <span class="label">Separation Type:</span>
                    <span class="value">{{ $separation_type }}</span>
                </div>
                @if($separation_reason)
                <div class="detail-line">
                    <span class="label">Reason:</span>
                    <span class="value">{{ $separation_reason }}</span>
                </div>
                @endif
                @if($rehire_eligible !== null)
                <div class="rehire-note">
                    @if($rehire_eligible)
                        ✓ Employee is eligible for rehire
                        @if($rehire_note)
                            ({{ $rehire_note }})
                        @endif
                    @else
                        ✗ Employee is not eligible for rehire
                        @if($rehire_note)
                            ({{ $rehire_note }})
                        @endif
                    @endif
                </div>
                @endif
            </div>

            <div class="closing">
                During {{ $employee_name }}'s employment with us, {{ strtolower($employee_name) }} demonstrated dedication and commitment to the organization. This certificate is issued upon request and is valid for all purposes where a Certificate of Employment is required.
            </div>
        </div>

        <div class="signature-section">
            <div class="signature-block">
                <div class="sig-line">
                    <div>HR Manager / Officer</div>
                    <div class="stamp-area">{{ $issued_by }}</div>
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
