# Accountability Report — PDF Export Implementation Plan

**Feature:** Proper PDF generation for Cash Payment Accountability Report  
**Page:** `/payroll/payments/cash/accountability-report`  
**Created:** February 20, 2026  
**Status:** Planning  

---

## Current Problem

The accountability report page has **two redundant buttons** (PDF + Print) that both call `window.print()`. Issues:

1. **PDF ≠ PDF** — clicking "PDF" just opens the browser print dialog (same as Print)
2. **Sidebar leaks into print** — the `@media print` style only hides `<nav>` but AppLayout wraps the entire page with a sidebar div, header, and layout chrome that still appear
3. **No PDF template** — the output is a screenshot-quality browser print, not a proper document
4. **Title swap hack** is fragile — swapping `document.title` before `window.print()` affects the browser tab briefly and only works on some browsers for the filename suggestion

---

## Options Evaluated

### Option A — `window.print()` with full `@media print` CSS (current + improved)

**How it works:** Keep `window.print()` but add aggressive print-only CSS that hides the entire AppLayout shell.

**Pros:**
- Zero new dependencies
- No backend changes

**Cons:**
- Output is always a browser screenshot, quality varies by browser
- The "PDF download" is just browser "Print → Save as PDF" — user has to do this manually
- Tailwind `print:` utilities only target elements you add them to; the AppLayout sidebar div that wraps everything is in a shared layout file and would need to be touched globally
- Fragile — any layout refactor breaks it

**Verdict:** ❌ Not a proper solution. Works as a quick patch only.

---

### Option B — `html2canvas` + `jsPDF` (client-side screenshot to PDF)

**How it works:** Capture a `div` as a canvas image, embed it in a PDF via jsPDF.

```
npm install jspdf html2canvas
```

**Pros:**
- Pure client-side
- Can capture exactly what you see on screen (with layout chrome stripped by targeting just the report div)

**Cons:**
- Output is a **rasterized image** inside a PDF — blurry when zoomed, large file size
- Slow on large tables (canvas rendering is CPU-heavy)
- Text in the PDF is not selectable (it's an image)
- Tables longer than one page require manual canvas slicing / pagination logic
- Not accessible (screen readers can't read the PDF content)

**Verdict:** ❌ Produces low-quality output. Not suitable for official accountability documents.

---

### Option C — `@react-pdf/renderer` (client-side vector PDF)

**How it works:** Declare the PDF layout in JSX using react-pdf primitives (`Document`, `Page`, `View`, `Text`, `Image`). The library generates a real vector PDF entirely in the browser.

```
npm install @react-pdf/renderer
```

**Pros:**
- Pure client-side (no server, no new PHP dependencies)
- Output is a **real vector PDF** — text is selectable, resolution-independent, small file size
- Template lives in a `.tsx` file alongside the page
- Can export via `PDFDownloadLink` (renders a download `<a>` tag)

**Cons:**
- react-pdf uses **its own layout primitives** — you cannot use Tailwind, shadcn, or any HTML element inside the PDF template. Everything must be `View`, `Text`, `StyleSheet`, etc.
- Fonts must be explicitly registered (for Philippine peso sign ₱, a custom font may be needed)
- The PDF template is a completely separate component from the screen view — you maintain two layouts
- Can be slow to render the PDF JS bundle (~400KB extra) on first load

**Verdict:** ✅ Good approach. Clean, modern, proper PDF. Trade-off: maintaining two layouts.

---

### Option D — `barryvdh/laravel-dompdf` (server-side Blade-to-PDF) ⭐ RECOMMENDED

**How it works:** Install the DomPDF Laravel wrapper. Add a new controller method `downloadPdf()`. It receives the same data as `generateAccountabilityReport()`, renders a dedicated Blade template, and streams it as a `.pdf` file download. The React button becomes a plain `<a href="/payroll/payments/cash/accountability-report/pdf?period_id=...">` link.

```
composer require barryvdh/laravel-dompdf
```

**Pros:**
- **Real server-rendered PDF** — not a screenshot, text is selectable
- Blade template is plain HTML + inline CSS — easy to read and maintain
- **Completely separate from the screen view** — you design the PDF layout independently (proper document formatting, logo, header, footer, page numbers)
- The React page stays untouched except swapping the PDF button to an `<a>` tag
- PHP can embed the company logo, add page numbers, footers, signatures section, etc.
- DomPDF supports Philippine peso sign ₱ natively (UTF-8)
- The PDF template lives at `resources/views/payroll/payments/cash/accountability-report-pdf.blade.php` — a standard Laravel file, not React magic
- Can be unit tested server-side

**Cons:**
- Requires `composer require` (one command)
- Requires a Blade template (extra file, but that's the actual "PDF template" the user asked for)
- DomPDF struggles with complex CSS (flexbox not supported, floats can be tricky) — but for a table-based report this is fine; use `<table>` HTML layout

**Verdict:** ✅✅ Best fit for this project. Matches the request for "its own PDF templating", clean separation, proper output.

---

## Recommendation: Option D — DomPDF + Blade Template

### Why not Option C (@react-pdf)?

Both Option C and D produce proper vector PDFs. The deciding factor:

| Concern | @react-pdf (C) | DomPDF + Blade (D) |
|---|---|---|
| Maintain two layouts | In React (tsx) | In Blade (html) |
| Table data access | From Inertia props (already there) | From controller (already there) |
| Company logo/header | `<Image src={...}/>` (react-pdf) | `<img>` in Blade |
| Page numbers | Plugin needed | DomPDF built-in |
| Font for ₱ | Manual font registration | UTF-8 default |
| Bundle size | +400KB JS | 0KB JS added |
| Debug experience | Hard — PDF renders in JS | Easy — just view Blade in browser |
| Future customization | React knowledge required | HTML/CSS knowledge sufficient |

For a **payroll document** that will be used for official accountability, DomPDF + Blade is more maintainable and produces more document-like output.

---

## Implementation Plan

### Phase 1 — Install DomPDF

```bash
composer require barryvdh/laravel-dompdf
```

Publish config (optional but useful):
```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

Set A4 paper size and enable Unicode in `config/dompdf.php`:
```php
'options' => [
    'default_paper_size' => 'a4',
    'default_font'       => 'sans-serif',
    'enable_css_float'   => false,  // use table layout only
    'enable_html5_parser'=> true,
    'chroot'             => realpath(base_path()),
],
```

---

### Phase 2 — Add PDF Route

In `routes/payroll.php`, inside the payments section:

```php
Route::get('/payments/cash/accountability-report/pdf', [CashPaymentController::class, 'downloadAccountabilityReportPdf'])
    ->name('payments.cash.accountability-report.pdf');
```

---

### Phase 3 — Add Controller Method

In `CashPaymentController`, add `downloadAccountabilityReportPdf(Request $request)`:

```php
public function downloadAccountabilityReportPdf(Request $request): Response
{
    $periodId = $request->input('period_id', 'all');
    // ... same data-building logic as generateAccountabilityReport() ...
    // Extract to a shared private method: buildReportData($periodId): array

    $pdf = app('dompdf.wrapper');
    $pdf->loadView('payroll.payments.cash.accountability-report-pdf', [
        'report'    => $reportData,
        'employees' => $employees,
    ]);
    $pdf->setPaper('a4', 'portrait');

    $filename = 'cash-accountability-report-' . now()->format('Y-m-d') . '.pdf';
    return $pdf->download($filename);
}
```

> **Refactor tip:** Extract the shared data-building logic from `generateAccountabilityReport()` into a private `buildReportData(string $periodId): array` method to avoid duplication.

---

### Phase 4 — Create Blade PDF Template

File: `resources/views/payroll/payments/cash/accountability-report-pdf.blade.php`

The template should be plain HTML with `<style>` block. Use `<table>` for layout (DomPDF has limited CSS support).

**Template structure:**
```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        /* See full template below */
    </style>
</head>
<body>
    <!-- Header with company name + report title -->
    <!-- Summary table (4-column grid done as table) -->
    <!-- Distribution breakdown table -->
    <!-- Distributed employees table -->
    <!-- Unclaimed employees table -->
    <!-- Footer: generated date, signature lines -->
</body>
</html>
```

Key DomPDF-compatible CSS notes:
- Use `width: 25%; float: left;` or `<table>` for column layout (no flexbox/grid)
- `page-break-inside: avoid;` works on `<table>` and `<div>`
- `@page { margin: 1.5cm; }` for page margins
- `{ page-break-after: always; }` for page breaks
- DomPDF page variables: `{PAGE_NUM}` and `{PAGE_COUNT}` in `position: fixed` footer

---

### Phase 5 — Update AccountabilityReport.tsx

**Remove:**
- `handlePrint()` function (or keep if you want both, but simplify)
- `handleDownloadPDF()` with the `document.title` hack
- `<style>{`@media print { nav { display: none !important; } }`}</style>`
- The redundant PDF button

**Replace with a single download link:**
```tsx
<a
    href={`/payroll/payments/cash/accountability-report/pdf?period_id=${report.period_id}`}
    download
    className={buttonVariants({ variant: 'outline', size: 'sm' })}
>
    <Download className="h-4 w-4 mr-2" />
    Download PDF
</a>
```

**If keeping Print** (optional, for quick reference printing):

Fix the `@media print` to hide AppLayout chrome properly. The AppLayout wraps with a `<div>` not just `<nav>`. Add to the `<style>` tag:

```tsx
<style>{`
    @media print {
        /* Hide the entire layout shell, only show the page content div */
        body > div > aside,
        body > div > header,
        nav,
        [data-sidebar],
        .sidebar {
            display: none !important;
        }
        /* Expand content to full width */
        main, .main-content, [data-main] {
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }
    }
`}</style>
```

> **Inspect the DOM** at `/payroll/payments/cash/accountability-report` to find the exact class/data-attribute names the AppLayout sidebar uses, then target them precisely.

Alternatively and more robustly: before calling `window.print()`, use JS to toggle a CSS class on `document.body` that hides the sidebar:

```ts
const handlePrint = () => {
    document.body.classList.add('printing');
    window.print();
    window.onafterprint = () => document.body.classList.remove('printing');
};
```

Then in the global CSS:
```css
body.printing aside,
body.printing header,
body.printing nav { display: none !important; }
body.printing main { width: 100% !important; margin: 0 !important; }
```

---

## Final Button Layout (Post-Implementation)

```
[ ← Back ]    [ Download PDF ]    (optional: [ Print ])
```

Only **one** action for PDF — it calls the server, returns an actual `.pdf` file. No browser print dialog involvement.

---

## Files to Create / Modify

| File | Action | Notes |
|---|---|---|
| `composer.json` | Install `barryvdh/laravel-dompdf` | `composer require` |
| `config/dompdf.php` | Configure paper size + Unicode | After `vendor:publish` |
| `routes/payroll.php` | Add `GET /payments/cash/accountability-report/pdf` | Named `payments.cash.accountability-report.pdf` |
| `app/Http/Controllers/Payroll/Payments/CashPaymentController.php` | Add `downloadAccountabilityReportPdf()`, extract `buildReportData()` | ~50 lines added |
| `resources/views/payroll/payments/cash/accountability-report-pdf.blade.php` | **Create** PDF Blade template | New file — the actual "PDF template" |
| `resources/js/pages/Payroll/Payments/Cash/AccountabilityReport.tsx` | Replace PDF button with `<a>` link, remove `handleDownloadPDF()`, fix print CSS | Minor changes |

---

## Out of Scope (Future)

- **Email delivery** — send the PDF report to payroll officer's email via `Mailable::attachData()`
- **Storage** — save generated PDFs to `storage/app/reports/` for audit trail
- **Scheduled generation** — auto-generate accountability report at end of pay period via scheduled job
- **Digital signature** — embed QR code or timestamp for official signatory purposes
