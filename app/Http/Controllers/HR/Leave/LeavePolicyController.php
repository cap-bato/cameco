<?php

namespace App\Http\Controllers\HR\Leave;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeavePolicy;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeavePolicyController extends Controller
{
    /**
     * Display a listing of leave policies.
     * Shows all available leave types and their annual entitlements.
     */
    public function index(Request $request): Response
    {
        // Temporarily disabled for testing
        // $this->authorize('viewAny', Employee::class);

        // Fetch policies from database
        $policies = LeavePolicy::active()->orderBy('name')->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'description' => $p->description,
                'annual_entitlement' => (float) $p->annual_entitlement,
                'max_carryover' => (float) $p->max_carryover,
                'can_carry_forward' => (bool) $p->can_carry_forward,
                'is_paid' => (bool) $p->is_paid,
            ];
        })->toArray();

        return Inertia::render('HR/Leave/Policies', [
            'policies' => $policies,
            'canEdit' => auth()->user()->can('hr.employees.update'),
        ]);
    }

    /**
     * Store a newly created leave policy.
     */
    public function store(Request $request)
    {
        $this->authorize('create', LeavePolicy::class);

        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:leave_policies,code',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'annual_entitlement' => 'required|numeric|min:0|max:365',
            'max_carryover' => 'nullable|numeric|min:0|max:365',
            'can_carry_forward' => 'boolean',
            'is_paid' => 'boolean',
        ]);

        $policy = LeavePolicy::create($validated);

        activity()
            ->performedOn($policy)
            ->causedBy(auth()->user())
            ->withProperties(['attributes' => $validated])
            ->log('Created leave policy: ' . $policy->name);

        return redirect()->route('hr.leave.policies')->with('success', 'Leave policy created successfully.');
    }

    /**
     * Update the specified leave policy.
     */
    public function update(Request $request, LeavePolicy $policy)
    {
        $this->authorize('update', $policy);

        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:leave_policies,code,' . $policy->id,
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'annual_entitlement' => 'required|numeric|min:0|max:365',
            'max_carryover' => 'nullable|numeric|min:0|max:365',
            'can_carry_forward' => 'boolean',
            'is_paid' => 'boolean',
        ]);

        $oldAttributes = $policy->getAttributes();
        $policy->update($validated);

        activity()
            ->performedOn($policy)
            ->causedBy(auth()->user())
            ->withProperties([
                'old' => $oldAttributes,
                'attributes' => $validated
            ])
            ->log('Updated leave policy: ' . $policy->name);

        return redirect()->route('hr.leave.policies')->with('success', 'Leave policy updated successfully.');
    }

    /**
     * Remove the specified leave policy.
     */
    public function destroy(LeavePolicy $policy)
    {
        $this->authorize('delete', $policy);

        // Check if policy has active leave balances
        $activeBalances = $policy->leaveBalances()->where('remaining_days', '>', 0)->count();
        
        if ($activeBalances > 0) {
            return back()->withErrors([
                'policy' => 'Cannot delete leave policy with active leave balances. Please archive instead.'
            ]);
        }

        $policyName = $policy->name;
        $policy->delete();

        activity()
            ->performedOn($policy)
            ->causedBy(auth()->user())
            ->withProperties(['attributes' => $policy->getAttributes()])
            ->log('Deleted leave policy: ' . $policyName);

        return redirect()->route('hr.leave.policies')->with('success', 'Leave policy deleted successfully.');
    }
}
