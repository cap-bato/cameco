<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Government Compliance</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; margin: 24px; }
        .section { margin-bottom: 12px; }
    </style>
</head>
<body>
    <h2>Government Contributions Summary</h2>

    <div class="section">
        <strong>Employee:</strong> {{ $profile->full_name ?? 'N/A' }} ({{ $employee->employee_number ?? 'N/A' }})
    </div>

    <div class="section">
        <p>This document summarizes SSS, PhilHealth, and Pag-IBIG contribution records. Actual data should be populated from the contributions system.</p>
    </div>

    <div class="section">
        <small>Generated: {{ $generated_date }}</small>
    </div>
</body>
</html>
