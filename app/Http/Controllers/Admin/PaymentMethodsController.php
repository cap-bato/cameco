<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\PaymentMethodPolicy;
use App\Models\PaymentMethodProvider;
use App\Models\PaymentMethodUsageLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:Office Admin|Superadmin']);
    }

    /**
     * Display payment methods configuration page.
     */
    public function index(): Response
    {
        $paymentMethods = PaymentMethodProvider::with('paymentMethod')
            ->withCount(['usageLogs', 'employeePaymentMethods'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($provider) => [
                'id' => $provider->id,
                'code' => $provider->code,
                'name' => $provider->name,
                'type' => $provider->paymentMethod?->method_type,
                'category' => $provider->category,
                'description' => $provider->description,
                'is_enabled' => $provider->is_enabled,
                'is_available' => $provider->is_available,
                'formatted_fee' => $provider->formatted_fee,
                'processing_time_hours' => $provider->processing_time_hours,
                'usage_count' => $provider->usage_logs_count,
                'active_employees_count' => $provider->employee_payment_methods_count,
            ])
            ->values();

        $statistics = [
            'total_methods' => $paymentMethods->count(),
            'enabled_methods' => $paymentMethods->where('is_enabled', true)->count(),
            'total_banks' => $paymentMethods->where('type', 'bank')->count(),
            'total_ewallets' => $paymentMethods->where('type', 'ewallet')->count(),
        ];

        $policies = PaymentMethodPolicy::with(['department', 'defaultPaymentMethodProvider'])
            ->get()
            ->map(fn ($policy) => [
                'id' => $policy->id,
                'department_id' => $policy->department_id,
                'department_name' => $policy->department?->name ?? 'All Departments',
                'employee_level' => $policy->employee_level ?? 'All Levels',
                'default_payment_method_provider_id' => $policy->default_payment_method_provider_id,
                'default_method' => $policy->defaultPaymentMethodProvider?->name,
                'allowed_methods_count' => count($policy->allowed_payment_method_providers ?? []),
                'allow_employee_change' => $policy->allow_employee_change,
                'approval_required_for_change' => $policy->approval_required_for_change,
            ])
            ->values();

        return Inertia::render('Admin/PaymentMethods/Index', [
            'paymentMethods' => $paymentMethods,
            'statistics' => $statistics,
            'policies' => $policies,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Update payment method provider configuration.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'is_enabled' => 'sometimes|boolean',
            'is_available' => 'sometimes|boolean',
            'transaction_fee' => 'nullable|numeric|min:0',
            'fee_type' => 'sometimes|in:fixed,percentage',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'daily_limit' => 'nullable|integer|min:0',
            'monthly_limit' => 'nullable|integer|min:0',
            'processing_time_hours' => 'nullable|integer|min:0',
            'sort_order' => 'nullable|integer|min:0',
            'configuration' => 'nullable|array',
        ]);

        $paymentMethodProvider = PaymentMethodProvider::findOrFail($id);
        $paymentMethodProvider->update($validated);

        activity()
            ->causedBy($request->user())
            ->performedOn($paymentMethodProvider)
            ->withProperties(['changes' => $validated])
            ->log('Payment method provider configuration updated');

        return response()->json([
            'success' => true,
            'message' => 'Payment method updated successfully.',
        ]);
    }

    /**
     * Bulk enable or disable payment method providers.
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_method_ids' => 'required|array|min:1',
            'payment_method_ids.*' => 'integer|exists:payment_method_providers,id',
            'is_enabled' => 'required|boolean',
        ]);

        PaymentMethodProvider::whereIn('id', $validated['payment_method_ids'])
            ->update(['is_enabled' => $validated['is_enabled']]);

        activity()
            ->causedBy($request->user())
            ->withProperties([
                'payment_method_provider_ids' => $validated['payment_method_ids'],
                'is_enabled' => $validated['is_enabled'],
            ])
            ->log('Bulk payment method providers update');

        return response()->json([
            'success' => true,
            'message' => 'Payment methods updated successfully.',
        ]);
    }

    /**
     * Get payment method analytics.
     */
    public function analytics(): JsonResponse
    {
        $usageByMethod = PaymentMethodUsageLog::with('paymentMethodProvider')
            ->completed()
            ->select('payment_method_provider_id')
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('SUM(amount) as total_amount')
            ->groupBy('payment_method_provider_id')
            ->get()
            ->map(fn ($log) => [
                'method_name' => $log->paymentMethodProvider?->name,
                'transaction_count' => (int) $log->transaction_count,
                'total_amount' => number_format((float) $log->total_amount, 2),
            ])
            ->values();

        $monthlyTrends = PaymentMethodUsageLog::completed()
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM') as month")
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('SUM(amount) as total_amount')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy(DB::raw("TO_CHAR(created_at, 'YYYY-MM')"))
            ->orderBy('month')
            ->get();

        return response()->json([
            'usage_by_method' => $usageByMethod,
            'monthly_trends' => $monthlyTrends,
        ]);
    }

    /**
     * Store payment method policy.
     */
    public function storePolicy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => 'nullable|exists:departments,id',
            'employee_level' => 'nullable|in:rank_and_file,supervisory,managerial,executive',
            'default_payment_method_provider_id' => 'required|exists:payment_method_providers,id',
            'allowed_payment_method_providers' => 'required|array|min:1',
            'allowed_payment_method_providers.*' => 'integer|exists:payment_method_providers,id',
            'allow_employee_change' => 'sometimes|boolean',
            'approval_required_for_change' => 'sometimes|in:none,supervisor,office_admin',
        ]);

        $policy = PaymentMethodPolicy::create($validated);

        activity()
            ->causedBy($request->user())
            ->performedOn($policy)
            ->log('Payment method policy created');

        return response()->json([
            'success' => true,
            'message' => 'Payment method policy created successfully.',
            'policy' => $policy->load(['department', 'defaultPaymentMethodProvider']),
        ]);
    }

    /**
     * Update payment method policy.
     */
    public function updatePolicy(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'default_payment_method_provider_id' => 'required|exists:payment_method_providers,id',
            'allowed_payment_method_providers' => 'required|array|min:1',
            'allowed_payment_method_providers.*' => 'integer|exists:payment_method_providers,id',
            'allow_employee_change' => 'sometimes|boolean',
            'approval_required_for_change' => 'sometimes|in:none,supervisor,office_admin',
        ]);

        $policy = PaymentMethodPolicy::findOrFail($id);
        $policy->update($validated);

        activity()
            ->causedBy($request->user())
            ->performedOn($policy)
            ->log('Payment method policy updated');

        return response()->json([
            'success' => true,
            'message' => 'Payment method policy updated successfully.',
        ]);
    }

    /**
     * Delete payment method policy.
     */
    public function destroyPolicy(Request $request, int $id): JsonResponse
    {
        $policy = PaymentMethodPolicy::findOrFail($id);
        $policy->delete();

        activity()
            ->causedBy($request->user())
            ->log('Payment method policy deleted');

        return response()->json([
            'success' => true,
            'message' => 'Payment method policy deleted successfully.',
        ]);
    }
}
