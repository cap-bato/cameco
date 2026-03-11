<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>BIR 2316 — Certificate of Creditable Tax Withheld at Source</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
        }
        .container {
            max-width: 8.5in;
            height: 11in;
            margin: 0 auto;
            padding: 0.5in;
            background: white;
        }
        .form-header {
            text-align: center;
            margin-bottom: 0.2in;
            border-bottom: 1px solid #000;
            padding-bottom: 0.1in;
        }
        .form-title {
            font-weight: bold;
            font-size: 12px;
        }
        .form-subtitle {
            font-size: 10px;
            margin-top: 0.05in;
        }
        .section {
            margin: 0.15in 0;
        }
        .section-title {
            font-weight: bold;
            background-color: #e8e8e8;
            padding: 0.05in 0.1in;
            margin-bottom: 0.1in;
            font-size: 10px;
            border: 1px solid #999;
        }
        .form-row {
            display: flex;
            margin-bottom: 0.08in;
        }
        .form-field {
            flex: 1;
            padding: 0.05in;
        }
        .form-field .label {
            font-weight: bold;
            font-size: 9px;
            margin-bottom: 0.02in;
        }
        .form-field .value {
            font-size: 10px;
            min-height: 0.18in;
            border-bottom: 1px dotted #666;
            padding: 0.02in 0.05in;
        }
        .half-width {
            width: 48%;
            margin-right: 2%;
        }
        .full-width {
            width: 100%;
        }
        .amount-row {
            display: flex;
            justify-content: space-between;
            padding: 0.05in 0.1in;
            border-bottom: 1px solid #ddd;
            margin-bottom: 0.05in;
        }
        .amount-label {
            font-weight: bold;
            width: 60%;
        }
        .amount-value {
            width: 35%;
            text-align: right;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 0.08in 0.1in;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            margin-bottom: 0.1in;
            font-weight: bold;
        }
        .total-label {
            width: 60%;
        }
        .total-value {
            width: 35%;
            text-align: right;
        }
        .signature-section {
            margin-top: 0.2in;
            display: flex;
            justify-content: space-between;
        }
        .signature-block {
            width: 45%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            margin-top: 0.3in;
            padding-top: 0.05in;
            font-size: 9px;
        }
        .footer-notes {
            font-size: 8px;
            margin-top: 0.1in;
            line-height: 1.3;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Form Header -->
        <div class="form-header">
            <div class="form-title">BUREAU OF INTERNAL REVENUE</div>
            <div class="form-subtitle">CERTIFICATE OF CREDITABLE TAX WITHHELD AT SOURCE</div>
            <div class="form-subtitle">(BIR Form 2316)</div>
        </div>

        <!-- Payor Information -->
        <div class="section">
            <div class="section-title">PAYOR / EMPLOYER INFORMATION</div>
            <div class="form-row">
                <div class="form-field full-width">
                    <div class="label">Employer Name</div>
                    <div class="value">CAMECO CORPORATION</div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-field half-width">
                    <div class="label">Employer TIN</div>
                    <div class="value"></div>
                </div>
                <div class="form-field half-width">
                    <div class="label">BIR Permit No.</div>
                    <div class="value"></div>
                </div>
            </div>
        </div>

        <!-- Payee Information -->
        <div class="section">
            <div class="section-title">PAYEE INFORMATION</div>
            <div class="form-row">
                <div class="form-field full-width">
                    <div class="label">Payee Full Name</div>
                    <div class="value">{{ $employee['full_name'] }}</div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-field half-width">
                    <div class="label">Employee TIN</div>
                    <div class="value">{{ $employee['tin'] ?? 'N/A' }}</div>
                </div>
                <div class="form-field half-width">
                    <div class="label">Employee No.</div>
                    <div class="value">{{ $employee['employee_number'] }}</div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-field half-width">
                    <div class="label">Department</div>
                    <div class="value">{{ $employee['department'] }}</div>
                </div>
                <div class="form-field half-width">
                    <div class="label">Tax Year</div>
                    <div class="value">{{ $year }}</div>
                </div>
            </div>
        </div>

        <!-- Income and Tax Information -->
        <div class="section">
            <div class="section-title">INCOME AND TAX INFORMATION</div>
            
            <div class="amount-row">
                <div class="amount-label">Gross Income (Compensation) for {{ $year }}</div>
                <div class="amount-value">₱ {{ number_format($total_gross, 2) }}</div>
            </div>
            
            <div class="amount-row">
                <div class="amount-label">Less: Non-Creditable Items</div>
                <div class="amount-value">₱ 0.00</div>
            </div>
            
            <div class="total-row">
                <div class="total-label">Total Taxable Income</div>
                <div class="total-value">₱ {{ number_format($total_gross, 2) }}</div>
            </div>
            
            <div class="amount-row">
                <div class="amount-label">Less: Income Tax Withheld</div>
                <div class="amount-value">(₱ {{ number_format($tax_withheld, 2) }})</div>
            </div>
            
            <div class="amount-row">
                <div class="amount-label">Net Income After Tax</div>
                <div class="amount-value">₱ {{ number_format($total_net, 2) }}</div>
            </div>

            <div class="total-row" style="margin-top: 0.1in;">
                <div class="total-label">TOTAL WITHHOLDING TAX FOR THE YEAR</div>
                <div class="total-value">₱ {{ number_format($tax_withheld, 2) }}</div>
            </div>
        </div>

        <!-- Certification and Signature -->
        <div class="section">
            <div class="section-title">CERTIFICATION</div>
            <p style="font-size: 9px; margin-bottom: 0.1in;">
                I hereby certify that the above information is true, correct, and complete based on the records of {{ date('Y', strtotime($year . '-01-01')) }}.
                This certificate is issued for purposes of income tax filing by the payee.
            </p>
        </div>

        <div class="signature-section">
            <div class="signature-block">
                <div style="font-size: 9px; margin-bottom: 0.05in;">Authorized Signatory</div>
                <div class="signature-line"></div>
                <div style="font-size: 8px; margin-top: 0.05in;">Name and Title</div>
            </div>
            <div class="signature-block">
                <div style="font-size: 9px; margin-bottom: 0.05in;">Date Issued</div>
                <div class="signature-line">{{ now()->format('F d, Y') }}</div>
                <div style="font-size: 8px; margin-top: 0.05in;"></div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer-notes">
            <p><strong>IMPORTANT NOTICE:</strong></p>
            <ul style="margin-left: 0.2in;">
                <li>This certificate is intended for the payee's income tax return filing purposes.</li>
                <li>The payee is responsible for reporting this income and withholding tax in the proper BIR forms.</li>
                <li>Keep this certificate for your income tax records and when applying for credit of withholding tax.</li>
            </ul>
        </div>
    </div>
</body>
</html>
