<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exit Interview Insights Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #1f2937;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #8b5cf6;
            padding-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 20pt;
            color: #6d28d9;
        }
        .header .subtitle {
            margin-top: 5px;
            font-size: 12pt;
            color: #6b7280;
        }
        .meta-info {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f3f4f6;
            border-radius: 4px;
        }
        .meta-info table {
            width: 100%;
        }
        .meta-info td {
            padding: 3px 5px;
        }
        .meta-info .label {
            font-weight: bold;
            width: 150px;
        }
        .executive-summary {
            background: #faf5ff;
            border: 2px solid #8b5cf6;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 25px;
        }
        .executive-summary h2 {
            margin: 0 0 12px 0;
            color: #6d28d9;
            font-size: 14pt;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px;
            text-align: center;
        }
        .stat-card .value {
            font-size: 24pt;
            font-weight: bold;
            color: #6d28d9;
            margin-bottom: 5px;
        }
        .stat-card .label {
            font-size: 9pt;
            color: #6b7280;
            text-transform: uppercase;
        }
        .section-title {
            font-size: 14pt;
            font-weight: bold;
            color: #6d28d9;
            margin-top: 20px;
            margin-bottom: 10px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 5px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.data-table th {
            background-color: #8b5cf6;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 9pt;
            font-weight: bold;
        }
        table.data-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9pt;
        }
        table.data-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .satisfaction-bar {
            height: 20px;
            background-color: #e5e7eb;
            border-radius: 4px;
            position: relative;
            overflow: hidden;
        }
        .satisfaction-bar .fill {
            height: 100%;
            background: linear-gradient(to right, #dc2626, #f59e0b, #16a34a);
            transition: width 0.3s;
        }
        .satisfaction-bar .value {
            position: absolute;
            right: 5px;
            top: 2px;
            font-size: 8pt;
            font-weight: bold;
            color: #1f2937;
        }
        .insight-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px;
            margin-bottom: 15px;
            margin-top: 10px;
        }
        .insight-box strong {
            color: #92400e;
        }
        .recommendation-item {
            background: #eff6ff;
            border-left: 3px solid #3b82f6;
            padding: 10px;
            margin-bottom: 8px;
            font-size: 9pt;
        }
        .feedback-item {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .feedback-item .employee-info {
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 5px;
        }
        .feedback-item .feedback-text {
            font-style: italic;
            color: #4b5563;
            font-size: 9pt;
            line-height: 1.4;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #cbd5e1;
            font-size: 8pt;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Exit Interview Insights Report</h1>
        <div class="subtitle">{{ $start_date->format('F d, Y') }} - {{ $end_date->format('F d, Y') }}</div>
    </div>

    <div class="meta-info">
        <table>
            <tr>
                <td class="label">Report Date:</td>
                <td>{{ $generated_at->format('F d, Y h:i A') }}</td>
                <td class="label">Generated By:</td>
                <td>{{ $generated_by }}</td>
            </tr>
            <tr>
                <td class="label">Period Covered:</td>
                <td>{{ $start_date->format('M d, Y') }} to {{ $end_date->format('M d, Y') }}</td>
                <td class="label">Total Interviews:</td>
                <td>{{ $stats['total_interviews'] }}</td>
            </tr>
        </table>
    </div>

    <div class="executive-summary">
        <h2>Executive Summary</h2>
        <p style="margin: 0; line-height: 1.6;">
            During the reporting period, <strong>{{ $stats['total_interviews'] }} exit interviews</strong> were conducted. 
            The average overall satisfaction score was <strong>{{ $stats['average_overall_satisfaction'] }}/5.0</strong>.
            <strong>{{ $stats['would_recommend_percentage'] }}%</strong> of separating employees indicated they would recommend the company to others,
            with <strong>{{ $stats['would_recommend_count'] }}</strong> out of {{ $stats['total_interviews'] }} respondents providing a positive recommendation.
        </p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="value">{{ $stats['total_interviews'] }}</div>
            <div class="label">Interviews Conducted</div>
        </div>

        <div class="stat-card">
            <div class="value">{{ $stats['average_overall_satisfaction'] }}</div>
            <div class="label">Avg Satisfaction (1-5)</div>
        </div>

        <div class="stat-card">
            <div class="value">{{ $stats['would_recommend_percentage'] }}%</div>
            <div class="label">Would Recommend</div>
        </div>
    </div>

    <h2 class="section-title">Top Reasons for Leaving</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Reason</th>
                <th style="text-align: right;">Count</th>
                <th style="text-align: right;">Percentage</th>
            </tr>
        </thead>
        <tbody>
            @foreach(array_slice($reasons_count, 0, 10, true) as $reason => $count)
                <tr>
                    <td>{{ ucfirst(str_replace('_', ' ', $reason)) }}</td>
                    <td style="text-align: right;">{{ $count }}</td>
                    <td style="text-align: right;">{{ $stats['total_interviews'] > 0 ? round(($count / $stats['total_interviews']) * 100, 1) : 0 }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2 class="section-title">Satisfaction Metrics</h2>
    <table class="data-table">
        <thead>
            <tr>
                <th>Metric</th>
                <th>Average Score</th>
                <th width="40%">Rating</th>
            </tr>
        </thead>
        <tbody>
            @foreach($satisfaction_metrics as $metric => $score)
                <tr>
                    <td>{{ ucfirst(str_replace('_', ' ', $metric)) }}</td>
                    <td style="font-weight: bold; color: {{ $score >= 4 ? '#16a34a' : ($score >= 3 ? '#f59e0b' : '#dc2626') }};">
                        {{ number_format($score, 2) }} / 5.0
                    </td>
                    <td>
                        <div class="satisfaction-bar">
                            <div class="fill" style="width: {{ ($score / 5) * 100 }}%;"></div>
                            <span class="value">{{ number_format(($score / 5) * 100, 0) }}%</span>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if(count($reasons_count) > 0)
        @php
            $topReason = array_key_first($reasons_count);
            $topReasonCount = $reasons_count[$topReason];
            $topReasonPercentage = round(($topReasonCount / $stats['total_interviews']) * 100, 1);
        @endphp
        <div class="insight-box">
            <strong>📊 Key Insight:</strong> The top reason for leaving is <strong>{{ ucfirst(str_replace('_', ' ', $topReason)) }}</strong>, 
            accounting for {{ $topReasonPercentage }}% ({{ $topReasonCount }} out of {{ $stats['total_interviews'] }}) of all exits during this period.
        </div>
    @endif

    @if(count($recommendations) > 0)
    <h2 class="section-title">HR Recommendations</h2>
    @foreach($recommendations as $idx => $recommendation)
        <div class="recommendation-item">
            <strong>{{ $idx + 1 }}.</strong> {{ $recommendation }}
        </div>
    @endforeach
    @endif

    @if(count($feedback_themes) > 0)
    <h2 class="section-title">Notable Feedback Themes</h2>
    @foreach(array_slice($feedback_themes, 0, 8) as $theme)
        <div class="feedback-item">
            <div class="employee-info">
                {{ $theme['employee'] }} - {{ $theme['department'] }}
                <span style="float: right; font-weight: normal; color: #6b7280; font-size: 8pt;">{{ $theme['date'] }}</span>
            </div>
            <div class="feedback-text">"{{ $theme['feedback'] }}"</div>
        </div>
    @endforeach
    @if(count($feedback_themes) > 8)
        <p style="text-align: center; color: #6b7280; font-size: 9pt;">
            ... and {{ count($feedback_themes) - 8 }} more feedback entries
        </p>
    @endif
    @endif

    <div class="footer">
        <p>This is a computer-generated report from the HRIS Offboarding System.</p>
        <p>Exit Interview Insights Report | Generated on {{ $generated_at->format('F d, Y \a\t h:i A') }}</p>
        <p style="margin-top: 5px;">
            <strong>Confidential:</strong> This report contains sensitive employee feedback and should be handled with appropriate confidentiality.
        </p>
    </div>
</body>
</html>
