<?php

namespace App\Http\Controllers\HR\Appraisal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

/**
 * AppraisalCycleController
 *
 * Manages appraisal cycles - performance review periods (e.g., Annual Review 2025)
 * HR Managers create cycles, define criteria and scoring rubrics, and manage employee assignments.
 *
 * Workflow:
 * 1. Create cycle with name, date range, and criteria
 * 2. Assign employees to cycle (individually or by department)
 * 3. Monitor completion progress as appraisals are filled out
 * 4. Close cycle when all appraisals are completed and acknowledged
 */

class AppraisalCycleController extends Controller
{
    /**
     * Update the specified appraisal cycle in storage.
     */
    public function update(Request $request, $id)
    {
        $cycle = \App\Models\AppraisalCycle::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'criteria' => 'nullable|array',
            'criteria.*' => 'string',
        ]);

        $cycle->name = $validated['name'];
        $cycle->start_date = $validated['start_date'];
        $cycle->end_date = $validated['end_date'];
        $cycle->criteria = $validated['criteria'] ?? [];
        $cycle->save();

        return redirect()->route('hr.appraisals.cycles.show', $cycle->id)
            ->with('success', 'Appraisal cycle updated successfully.');
    }

    /**
     * Show the form for editing the specified appraisal cycle.
     */
    public function edit($id)
    {
        $cycle = \App\Models\AppraisalCycle::with(['createdBy'])
            ->findOrFail($id);

        $cycleData = [
            'id' => $cycle->id,
            'name' => $cycle->name,
            'start_date' => $cycle->start_date,
            'end_date' => $cycle->end_date,
            'status' => $cycle->status,
            'criteria' => $cycle->criteria,
            'created_by' => $cycle->createdBy ? [
                'id' => $cycle->createdBy->id,
                'name' => $cycle->createdBy->name,
                'email' => $cycle->createdBy->email,
            ] : null,
        ];

        return Inertia::render('HR/Appraisals/Cycles/Edit', [
            'cycle' => $cycleData,
        ]);
    }

    /**
     * Close the specified appraisal cycle.
     */
    public function close($id)
    {
        $cycle = \App\Models\AppraisalCycle::findOrFail($id);
        $cycle->status = 'closed';
        $cycle->save();
        return redirect()->route('hr.appraisals.cycles.show', $id)->with('success', 'Appraisal cycle closed successfully.');
    }

    /**
     * Remove the specified appraisal cycle from storage.
     */
    public function destroy($id)
    {
        $cycle = \App\Models\AppraisalCycle::findOrFail($id);
        // Optionally: check for related appraisals and handle as needed (cascade or restrict)
        $cycle->delete();
        return redirect()->route('hr.appraisals.cycles.index')->with('success', 'Appraisal cycle deleted successfully.');
    }


    /**
     * Display the specified appraisal cycle details
     */
    public function show($id)
    {
        $cycle = \App\Models\AppraisalCycle::with(['createdBy', 'appraisals.employee.profile', 'appraisals.employee.department', 'appraisals.employee.position'])
            ->findOrFail($id);

        // Format cycle data for frontend
        $cycleData = [
            'id' => $cycle->id,
            'name' => $cycle->name,
            'start_date' => $cycle->start_date,
            'end_date' => $cycle->end_date,
            'status' => $cycle->status,
            'criteria' => $cycle->criteria,
            'created_by' => $cycle->createdBy ? [
                'id' => $cycle->createdBy->id,
                'name' => $cycle->createdBy->name,
                'email' => $cycle->createdBy->email,
            ] : null,
            'appraisals' => $cycle->appraisals->map(function ($a) {
                return [
                    'id' => $a->id,
                    'employee_id' => $a->employee_id,
                    'employee_name' => $a->employee && $a->employee->profile ? ($a->employee->profile->first_name . ' ' . $a->employee->profile->last_name) : '',
                    'employee_number' => $a->employee ? $a->employee->employee_number : '',
                    'department' => $a->employee && $a->employee->department ? $a->employee->department->name : '',
                    'position' => $a->employee && $a->employee->position ? $a->employee->position->title : '',
                    'status' => $a->status,
                    'overall_score' => $a->overall_score,
                ];
            }),
        ];

        return Inertia::render('HR/Appraisals/Cycles/Show', [
            'cycle' => $cycleData,
        ]);
    }

    /**
     * Assign employees to an appraisal cycle
     */
    public function storeAssignment(Request $request, $id)
    {
        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'integer|exists:employees,id',
            'due_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        // Create Appraisal records for each assigned employee if not already present for this cycle
        $created = 0;
        foreach ($validated['employee_ids'] as $employeeId) {
            $exists = \App\Models\Appraisal::where('appraisal_cycle_id', $id)
                ->where('employee_id', $employeeId)
                ->exists();
            if (!$exists) {
                \App\Models\Appraisal::create([
                    'appraisal_cycle_id' => $id,
                    'employee_id' => $employeeId,
                    'status' => 'draft',
                    'created_by' => auth()->id(),
                ]);
                $created++;
            }
        }

        return redirect()->back()->with('success', $created . ' employees assigned successfully.');
    }

    /**
     * Display a listing of appraisal cycles
     */
    public function index(Request $request)
    {
        // --- DB-driven implementation ---
        $status = $request->input('status', 'all');
        $year = $request->input('year', date('Y'));

        $query = \App\Models\AppraisalCycle::withCount(['appraisals'])
            ->with(['createdBy'])
            ->orderByDesc('start_date');

        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($year) {
            $query->whereYear('start_date', $year);
        }

        $cycles = $query->get();

        // Stats
        $totalCycles = $cycles->count();
        $activeCycles = $cycles->where('status', 'open')->count();
        $avgCompletion = 0; // Placeholder, implement if you have completion data
        $pendingAppraisals = 0; // Placeholder, implement if you have appraisals data

        // Get active employees for assignment modal
        $employees = \App\Models\Employee::with(['profile', 'department', 'position'])
            ->whereNull('termination_date')
            ->where('status', 'active')
            ->get()
            ->map(function ($e) {
                return [
                    'id' => $e->id,
                    'name' => $e->profile ? ($e->profile->first_name . ' ' . $e->profile->last_name) : $e->employee_number,
                    'employee_number' => $e->employee_number,
                    'department' => $e->department ? $e->department->name : '',
                    'position' => $e->position ? $e->position->title : '',
                ];
            });

        return Inertia::render('HR/Appraisals/Cycles/Index', [
            'cycles' => $cycles,
            'stats' => [
                'total_cycles' => $totalCycles,
                'active_cycles' => $activeCycles,
                'avg_completion_rate' => $avgCompletion,
                'pending_appraisals' => $pendingAppraisals,
            ],
            'filters' => compact('status', 'year'),
            'employees' => $employees,
        ]);
    }
}