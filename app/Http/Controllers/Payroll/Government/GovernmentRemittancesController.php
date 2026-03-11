<?php

namespace App\Http\Controllers\Payroll\Government;

use App\Http\Controllers\Controller;
use App\Models\GovernmentRemittance;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * GovernmentRemittancesController
 * Manages consolidated government remittance tracking and payment coordination
 *
 * Tracks remittances to 4 agencies:
 * - BIR (Bureau of Internal Revenue) - Withholding Tax
 * - SSS (Social Security System) - Contributions via R3
 * - PhilHealth (Philippine Health Insurance) - Premiums via RF1
 * - Pag-IBIG (Home Development Mutual Fund) - Contributions via MCRF
 *
 * Due Date: 10th of following month for all agencies
 * Late Payment Penalties: 5% per month (BIR, SSS, PhilHealth), 3% (Pag-IBIG)
 */
class GovernmentRemittancesController extends Controller
{
    public function index()
    {
        $remittances = GovernmentRemittance::with('payrollPeriod')
            ->orderByDesc('due_date')
            ->get()
            ->map(fn ($r) => $this->mapRemittance($r))
            ->values()
            ->all();

        $periods = PayrollPeriod::orderByDesc('start_date')
            ->limit(6)
            ->get()
            ->map(fn ($p) => [
                'id'         => $p->id,
                'name'       => $p->name,
                'month'      => Carbon::parse($p->start_date)->format('Y-m'),
                'start_date' => Carbon::parse($p->start_date)->toDateString(),
                'end_date'   => Carbon::parse($p->end_date)->toDateString(),
                'status'     => $p->status ?? 'open',
            ])
            ->values()
            ->all();

        $summary = $this->buildSummary();

        $calendarEvents = collect($remittances)->map(fn ($r) => [
            'id'             => $r['id'],
            'remittance_id'  => $r['id'],
            'date'           => $r['due_date'],
            'agency'         => $r['agency'],
            'status'         => $r['status'],
            'amount'         => $r['remittance_amount'],
            'report_type'    => $r['report_type'],
        ])->values()->all();

        return Inertia::render('Payroll/Government/Remittances/Index', [
            'remittances'    => $remittances,
            'periods'        => $periods,
            'summary'        => $summary,
            'calendarEvents' => $calendarEvents,
        ]);
    }

    public function recordPayment(Request $request, int $remittanceId)
    {
        $validated = $request->validate([
            'payment_date'      => 'required|date',
            'payment_reference' => 'required|string|max:255',
            'payment_amount'    => 'required|numeric|min:0',
        ]);

        try {
            $remittance = GovernmentRemittance::findOrFail($remittanceId);
            $paymentDate = Carbon::parse($validated['payment_date']);
            $isLate = $remittance->due_date && $paymentDate->isAfter($remittance->due_date);

            $remittance->update([
                'payment_date'      => $paymentDate->toDateString(),
                'payment_reference' => $validated['payment_reference'],
                'amount_paid'       => $validated['payment_amount'],
                'status'            => 'paid',
                'is_late'           => $isLate,
                'days_overdue'      => $isLate && $remittance->due_date
                    ? (int) $remittance->due_date->diffInDays($paymentDate)
                    : 0,
            ]);

            return response()->json([
                'success'           => true,
                'message'           => 'Payment recorded successfully',
                'payment_date'      => $validated['payment_date'],
                'payment_reference' => $validated['payment_reference'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to record payment'], 500);
        }
    }

    public function sendReminder(Request $request, int $remittanceId)
    {
        try {
            $remittance = GovernmentRemittance::findOrFail($remittanceId);

            \Log::info('Government remittance reminder sent', [
                'remittance_id' => $remittance->id,
                'agency'        => $remittance->agency,
                'due_date'      => $remittance->due_date?->toDateString(),
                'total_amount'  => $remittance->total_amount,
            ]);

            return response()->json([
                'success'  => true,
                'message'  => 'Reminder sent successfully',
                'sent_at'  => now()->toDateTimeString(),
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to send reminder'], 500);
        }
    }

    private function mapRemittance($rem): array
    {
        $dueDate  = $rem->due_date?->toDateString() ?? now()->addDays(10)->toDateString();
        $daysUntilDue = (int) now()->startOfDay()->diffInDays(Carbon::parse($dueDate)->startOfDay(), false);
        $status = $this->mapStatus($rem->status ?? 'pending', (bool) $rem->is_late);

        $agencyDisplay = $this->mapAgencyDisplay($rem->agency ?? '');
        $defaultMethod = $this->defaultPaymentMethod($rem->agency ?? '');

        return [
            'id'               => $rem->id,
            'agency'           => $agencyDisplay,
            'month'            => $rem->remittance_month ?? ($rem->period_start ? Carbon::parse($rem->period_start)->format('Y-m') : ''),
            'remittance_amount' => (float) ($rem->total_amount ?? 0),
            'report_type'      => $this->mapReportType($rem->agency ?? '', $rem->remittance_type ?? ''),
            'due_date'         => $dueDate,
            'payment_date'     => $rem->payment_date?->toDateString(),
            'payment_reference' => $rem->payment_reference,
            'status'           => $status,
            'days_until_due'   => $daysUntilDue,
            'has_penalty'      => (bool) ($rem->has_penalty ?? false),
            'penalty_amount'   => (float) ($rem->penalty_amount ?? 0),
            'remittance_method' => $rem->payment_method ?? $defaultMethod,
            'notes'            => $rem->notes ?? '',
            'employees_covered' => (int) ($rem->total_employees ?? 0),
        ];
    }

    private function buildSummary(): array
    {
        $all = GovernmentRemittance::all();

        $pending   = $all->filter(fn ($r) => in_array($r->status, ['pending', 'ready']));
        $paid      = $all->filter(fn ($r) => in_array($r->status, ['paid', 'submitted']));
        $overdue   = $all->filter(fn ($r) => $r->status === 'overdue'
            || ($r->is_late && ! in_array($r->status, ['paid', 'submitted'])));

        $nextDueDate = GovernmentRemittance::whereNotIn('status', ['paid', 'submitted'])
            ->where('due_date', '>=', now())
            ->orderBy('due_date')
            ->value('due_date');

        $lastPaidDate = GovernmentRemittance::whereIn('status', ['paid', 'submitted'])
            ->whereNotNull('payment_date')
            ->orderByDesc('payment_date')
            ->value('payment_date');

        return [
            'total_to_remit'     => (float) $all->sum('total_amount'),
            'pending_amount'     => (float) $pending->sum('total_amount'),
            'paid_amount'        => (float) $paid->sum('total_amount'),
            'overdue_amount'     => (float) $overdue->sum('total_amount'),
            'bir_amount'         => (float) $all->where('agency', 'bir')->sum('total_amount'),
            'sss_amount'         => (float) $all->where('agency', 'sss')->sum('total_amount'),
            'philhealth_amount'  => (float) $all->where('agency', 'philhealth')->sum('total_amount'),
            'pagibig_amount'     => (float) $all->where('agency', 'pagibig')->sum('total_amount'),
            'total_remittances'  => $all->count(),
            'pending_count'      => $pending->count(),
            'paid_count'         => $paid->count(),
            'overdue_count'      => $overdue->count(),
            'next_due_date'      => $nextDueDate ? Carbon::parse($nextDueDate)->toDateString() : '',
            'last_paid_date'     => $lastPaidDate ? Carbon::parse($lastPaidDate)->toDateString() : '',
        ];
    }

    private function mapAgencyDisplay(string $agency): string
    {
        return match (strtolower($agency)) {
            'bir'        => 'BIR',
            'sss'        => 'SSS',
            'philhealth' => 'PhilHealth',
            'pagibig'    => 'Pag-IBIG',
            default      => strtoupper($agency),
        };
    }

    private function mapReportType(string $agency, string $type): string
    {
        if ($type) {
            return strtoupper($type);
        }
        return match (strtolower($agency)) {
            'bir'        => '1601C',
            'sss'        => 'R3',
            'philhealth' => 'RF1',
            'pagibig'    => 'MCRF',
            default      => strtoupper($agency),
        };
    }

    private function defaultPaymentMethod(string $agency): string
    {
        return match (strtolower($agency)) {
            'bir'        => 'eFPS',
            'sss'        => 'eR3',
            'philhealth' => 'EPRS',
            'pagibig'    => 'eSRS',
            default      => 'Online',
        };
    }

    private function mapStatus(string $dbStatus, bool $isLate): string
    {
        if ($dbStatus === 'submitted') {
            return 'paid';
        }
        if (in_array($dbStatus, ['paid']) && $isLate) {
            return 'late';
        }
        return match ($dbStatus) {
            'pending', 'ready' => 'pending',
            'paid'             => 'paid',
            'partially_paid'   => 'partially_paid',
            'overdue'          => 'overdue',
            default            => 'pending',
        };
    }
}
