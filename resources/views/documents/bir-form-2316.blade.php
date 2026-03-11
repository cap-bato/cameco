<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>BIR Form 2316</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; margin: 24px; }
        h1 { text-align: center; }
        .section { margin-bottom: 10px; }
    </style>
</head>
<body>
    <h1>BIR Form 2316 - {{ $year }}</h1>

    <div class="section">
        <strong>Employee:</strong> {{ $profile->full_name ?? 'N/A' }} ({{ $employee->employee_number ?? 'N/A' }})
    </div>

    <div class="section">
        <p>This is a generated BIR Form 2316 placeholder for {{ $year }}.</p>
        <p>Tax details must be pulled from payroll for accurate values.</p>
    </div>

    <div class="section">
        <small>Generated: {{ $generated_date }}</small>
    </div>
</body>
</html>
