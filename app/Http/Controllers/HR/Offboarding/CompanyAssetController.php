<?php

namespace App\Http\Controllers\HR\Offboarding;

use App\Http\Controllers\Controller;
use App\Models\CompanyAsset;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\ClearanceItem;
use App\Services\HR\OffboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CompanyAssetController extends Controller
{
    protected OffboardingService $offboardingService;

    public function __construct(OffboardingService $offboardingService)
    {
        $this->offboardingService = $offboardingService;
    }

    /**
     * List all assets assigned to an employee (or during offboarding).
     * 
     * Shows issued assets and their return status.
     * Filters by asset type and return status.
     */
    public function index($employeeId): Response
    {
        $employee = Employee::with([
            'profile',
            'department',
            'companyAssets' => fn($q) => $q->where('status', '!=', 'written_off')
                ->orderBy('assigned_date', 'desc'),
        ])->findOrFail($employeeId);

        // Get issued assets
        $issuedAssets = $employee->companyAssets()
            ->where('status', 'issued')
            ->get()
            ->map(fn($asset) => $this->transformAsset($asset))
            ->toArray();

        // Get returned assets
        $returnedAssets = $employee->companyAssets()
            ->where('status', 'returned')
            ->get()
            ->map(fn($asset) => $this->transformAsset($asset))
            ->toArray();

        // Get lost/damaged assets
        $lostDamagedAssets = $employee->companyAssets()
            ->whereIn('status', ['lost', 'damaged'])
            ->get()
            ->map(fn($asset) => $this->transformAsset($asset))
            ->toArray();

        // Calculate summary
        $totalIssued = count($issuedAssets);
        $totalReturned = count($returnedAssets);
        $totalLostDamaged = count($lostDamagedAssets);
        $totalValue = $employee->companyAssets()->sum('value_at_issuance');
        $totalLiability = $employee->companyAssets()->where('status', '!=', 'returned')->sum('liability_amount');

        Log::info('Asset inventory accessed', [
            'employee_id' => $employeeId,
            'issued_count' => $totalIssued,
            'returned_count' => $totalReturned,
        ]);

        return Inertia::render('HR/Offboarding/CompanyAsset/Index', [
            'employee' => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->profile?->first_name . ' ' . $employee->profile?->last_name,
                'department' => $employee->department?->name,
            ],
            'assets' => [
                'issued' => $issuedAssets,
                'returned' => $returnedAssets,
                'lost_damaged' => $lostDamagedAssets,
            ],
            'summary' => [
                'total_issued' => $totalIssued,
                'total_returned' => $totalReturned,
                'total_lost_damaged' => $totalLostDamaged,
                'total_value' => round($totalValue, 2),
                'total_liability' => round($totalLiability, 2),
            ],
        ]);
    }

    /**
     * Assign a new asset to an employee.
     * 
     * Records initial condition, value, and upload photos.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'asset_type' => 'required|in:laptop,desktop,phone,tablet,id_card,access_card,keys,uniform,tools,documents,other',
            'asset_name' => 'required|string|max:200',
            'serial_number' => 'nullable|string|max:200',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'condition_at_issuance' => 'required|in:new,excellent,good,fair',
            'value_at_issuance' => 'required|numeric|min:0',
            'photo_at_issuance' => 'nullable|image|max:10240',
        ]);

        try {
            DB::beginTransaction();

            $photoPath = null;
            if ($request->hasFile('photo_at_issuance')) {
                $photoPath = $request->file('photo_at_issuance')->store(
                    'company-assets/issuance',
                    'public'
                );
            }

            $asset = CompanyAsset::create([
                'employee_id' => $validated['employee_id'],
                'asset_type' => $validated['asset_type'],
                'asset_name' => $validated['asset_name'],
                'serial_number' => $validated['serial_number'],
                'brand' => $validated['brand'],
                'model' => $validated['model'],
                'assigned_date' => now()->toDateString(),
                'assigned_by' => auth()->id(),
                'condition_at_issuance' => $validated['condition_at_issuance'],
                'value_at_issuance' => $validated['value_at_issuance'],
                'photo_at_issuance' => $photoPath,
                'status' => 'issued',
            ]);

            DB::commit();

            Log::info('Asset assigned to employee', [
                'asset_id' => $asset->id,
                'employee_id' => $validated['employee_id'],
                'asset_type' => $validated['asset_type'],
                'value' => $validated['value_at_issuance'],
            ]);

            return redirect()->back()->with('success', 'Asset assigned successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to assign asset', [
                'employee_id' => $validated['employee_id'],
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to assign asset. Please try again.');
        }
    }

    /**
     * Mark an asset as returned during offboarding.
     * 
     * Records return date, condition, photos.
     * Calculates liability if damaged.
     * Updates associated clearance item.
     */
    public function markReturned(Request $request, $assetId)
    {
        $asset = CompanyAsset::findOrFail($assetId);

        $validated = $request->validate([
            'condition_at_return' => 'required|in:excellent,good,fair,poor,damaged,lost',
            'return_notes' => 'nullable|string|max:500',
            'photo_at_return' => 'nullable|image|max:10240',
        ]);

        try {
            DB::beginTransaction();

            $photoPath = null;
            if ($request->hasFile('photo_at_return')) {
                $photoPath = $request->file('photo_at_return')->store(
                    "company-assets/return/{$asset->id}",
                    'public'
                );
            }

            // Calculate liability if damaged/lost
            $liabilityAmount = 0;
            if ($validated['condition_at_return'] === 'damaged') {
                $liabilityAmount = $asset->value_at_issuance * 0.5; // 50% of value
            } elseif ($validated['condition_at_return'] === 'lost') {
                $liabilityAmount = $asset->value_at_issuance; // Full value
            }

            $asset->update([
                'status' => $validated['condition_at_return'] === 'lost' ? 'lost' : 
                           ($validated['condition_at_return'] === 'damaged' ? 'damaged' : 'returned'),
                'return_date' => now()->toDateString(),
                'condition_at_return' => $validated['condition_at_return'],
                'return_notes' => $validated['return_notes'],
                'photo_at_return' => $photoPath,
                'received_by' => auth()->id(),
                'liability_amount' => $liabilityAmount,
            ]);

            // Update clearance item if associated
            if ($asset->clearance_item_id) {
                $clearanceItem = ClearanceItem::find($asset->clearance_item_id);
                if ($clearanceItem && $validated['condition_at_return'] !== 'lost') {
                    $clearanceItem->update([
                        'status' => 'approved',
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ]);
                }
            }

            // If liability calculated, notify finance
            if ($liabilityAmount > 0) {
                $this->offboardingService->notifyAssetLiability(
                    $asset,
                    $liabilityAmount,
                    $validated['condition_at_return']
                );
            }

            DB::commit();

            Log::info('Asset marked as returned', [
                'asset_id' => $assetId,
                'employee_id' => $asset->employee_id,
                'condition' => $validated['condition_at_return'],
                'liability_amount' => $liabilityAmount,
            ]);

            return redirect()->back()->with('success', 'Asset return recorded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to mark asset as returned', [
                'asset_id' => $assetId,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to record asset return. Please try again.');
        }
    }

    /**
     * Report an asset as lost or damaged during offboarding.
     * 
     * Calculates liability amount based on condition.
     * Flags for deduction from final pay.
     * Notifies finance and HR.
     */
    public function reportIssue(Request $request, $assetId)
    {
        $asset = CompanyAsset::findOrFail($assetId);

        $validated = $request->validate([
            'issue_status' => 'required|in:lost,damaged',
            'issue_description' => 'required|string|min:10|max:500',
            'estimated_value' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Calculate liability
            $liabilityAmount = $validated['estimated_value'] ?? $asset->value_at_issuance;

            $asset->update([
                'status' => $validated['issue_status'],
                'liability_amount' => $liabilityAmount,
                'deducted_from_final_pay' => true,
                'return_notes' => $validated['issue_description'],
            ]);

            // Update clearance item to issue status
            if ($asset->clearance_item_id) {
                $clearanceItem = ClearanceItem::find($asset->clearance_item_id);
                if ($clearanceItem) {
                    $clearanceItem->update([
                        'status' => 'issues',
                        'issue_description' => $validated['issue_description'],
                        'has_issues' => true,
                    ]);
                }
            }

            // Notify finance and HR
            $this->offboardingService->notifyAssetIssue(
                $asset,
                $validated['issue_status'],
                $liabilityAmount,
                $validated['issue_description']
            );

            DB::commit();

            Log::info('Asset issue reported', [
                'asset_id' => $assetId,
                'employee_id' => $asset->employee_id,
                'issue_status' => $validated['issue_status'],
                'liability_amount' => $liabilityAmount,
            ]);

            return redirect()->back()->with('success', 'Asset issue reported and flagged for final pay deduction.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to report asset issue', [
                'asset_id' => $assetId,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to report asset issue. Please try again.');
        }
    }

    /**
     * Display company asset inventory report.
     * 
     * Shows all assets across organization.
     * Filters by status, type, and employee.
     * Provides export capability.
     */
    public function inventory(Request $request): Response
    {
        $status = $request->input('status', 'all');
        $assetType = $request->input('asset_type', 'all');
        $department = $request->input('department');

        // Build query
        $query = CompanyAsset::with([
            'employee.profile',
            'employee.department',
            'assignedBy',
            'receivedBy',
        ]);

        // Apply filters
        if ($status !== 'all') {
            if ($status === 'issued') {
                $query->where('status', 'issued');
            } elseif ($status === 'returned') {
                $query->where('status', 'returned');
            } elseif ($status === 'lost_damaged') {
                $query->whereIn('status', ['lost', 'damaged']);
            }
        }

        if ($assetType !== 'all') {
            $query->where('asset_type', $assetType);
        }

        if ($department) {
            $query->whereHas('employee', fn($q) => $q->where('department_id', $department));
        }

        $assets = $query->orderBy('created_at', 'desc')->get();

        // Calculate statistics
        $statistics = [
            'total_assets' => $assets->count(),
            'total_value' => round($assets->sum('value_at_issuance'), 2),
            'total_liability' => round($assets->where('status', '!=', 'returned')->sum('liability_amount'), 2),
            'issued_count' => $assets->where('status', 'issued')->count(),
            'returned_count' => $assets->where('status', 'returned')->count(),
            'lost_damaged_count' => $assets->whereIn('status', ['lost', 'damaged'])->count(),
        ];

        // Group by asset type for summary
        $byType = $assets->groupBy('asset_type')->map(fn($items) => [
            'count' => $items->count(),
            'value' => round($items->sum('value_at_issuance'), 2),
            'liability' => round($items->sum('liability_amount'), 2),
        ])->toArray();

        // Group by status
        $byStatus = [
            'issued' => $assets->where('status', 'issued')->count(),
            'returned' => $assets->where('status', 'returned')->count(),
            'lost' => $assets->where('status', 'lost')->count(),
            'damaged' => $assets->where('status', 'damaged')->count(),
            'written_off' => $assets->where('status', 'written_off')->count(),
        ];

        Log::info('Asset inventory report accessed', [
            'total_assets' => $statistics['total_assets'],
            'filters' => compact('status', 'assetType', 'department'),
        ]);

        // Transform assets for display
        $transformedAssets = $assets->map(fn($asset) => $this->transformAsset($asset))->toArray();

        return Inertia::render('HR/Offboarding/CompanyAsset/Inventory', [
            'assets' => $transformedAssets,
            'statistics' => $statistics,
            'summary' => [
                'by_type' => $byType,
                'by_status' => $byStatus,
            ],
            'filters' => [
                'status' => $status,
                'asset_type' => $assetType,
                'department' => $department,
            ],
            'availableAssetTypes' => [
                'laptop', 'desktop', 'phone', 'tablet', 'id_card', 'access_card',
                'keys', 'uniform', 'tools', 'documents', 'other'
            ],
        ]);
    }

    /**
     * Transform asset data for frontend display.
     */
    private function transformAsset(CompanyAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'asset_type' => $asset->asset_type,
            'asset_name' => $asset->asset_name,
            'serial_number' => $asset->serial_number,
            'brand' => $asset->brand,
            'model' => $asset->model,
            'status' => $asset->status,
            'assigned_date' => $asset->assigned_date?->format('Y-m-d'),
            'return_date' => $asset->return_date?->format('Y-m-d'),
            'condition_at_issuance' => $asset->condition_at_issuance,
            'condition_at_return' => $asset->condition_at_return,
            'value_at_issuance' => round($asset->value_at_issuance, 2),
            'liability_amount' => round($asset->liability_amount, 2),
            'deducted_from_final_pay' => $asset->deducted_from_final_pay,
            'photo_at_issuance' => $asset->photo_at_issuance,
            'photo_at_return' => $asset->photo_at_return,
            'return_notes' => $asset->return_notes,
            'employee' => $asset->employee ? [
                'id' => $asset->employee->id,
                'employee_number' => $asset->employee->employee_number,
                'name' => $asset->employee->profile?->first_name . ' ' . $asset->employee->profile?->last_name,
                'department' => $asset->employee->department?->name,
            ] : null,
            'assigned_by' => $asset->assignedBy ? [
                'id' => $asset->assignedBy->id,
                'name' => $asset->assignedBy->name,
            ] : null,
            'received_by' => $asset->receivedBy ? [
                'id' => $asset->receivedBy->id,
                'name' => $asset->receivedBy->name,
            ] : null,
        ];
    }
}
