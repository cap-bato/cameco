<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificate of Employment</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; margin: 40px; line-height: 1.6; }
        .header { text-align: center; margin-bottom: 40px; }
        .company-name { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
        .company-address { font-size: 12px; color: #666; }
        .title { text-align: center; font-size: 18px; font-weight: bold; margin: 40px 0 30px 0; text-decoration: underline; }
        .content { text-align: justify; margin: 20px 0; }
        .signature-section { margin-top: 60px; }
        .signature-line { border-top: 1px solid #000; width: 250px; margin-top: 40px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ config('app.company_name', 'Company Name') }}</div>
        <div class="company-address">{{ config('app.company_address', 'Company Address') }}</div>
    </div>

    <div class="title">CERTIFICATE OF EMPLOYMENT</div>

    <p><strong>Date Issued:</strong> {{ $generated_date }}</p>

    <div class="content">
        <p>TO WHOM IT MAY CONCERN:</p>

        <p>This is to certify that <strong>{{ $profile->full_name ?? 'N/A' }}</strong> with employee number <strong>{{ $employee->employee_number ?? 'N/A' }}</strong> has been employed with this company since <strong>{{ $hire_date }}</strong>.</p>

        <p>During the period of employment, {{ $profile->first_name ?? 'the employee' }} held the position of <strong>{{ $position }}</strong> in the <strong>{{ $department }}</strong> department.</p>

        <p>This certification is issued upon the request of the above-named employee for <strong>{{ $purpose }}</strong>.</p>

        <p>Issued this {{ $generated_date }}.</p>
    </div>

    <div class="signature-section">
        <div class="signature-line"></div>
        <p><strong>HR Manager</strong><br>Human Resources Department</p>
    </div>
</body>
</html>
