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

        return Inertia::render('HR/Appraisals/Cycles/Index', [
            'cycles' => $cycles,
            'stats' => [
                'total_cycles' => $totalCycles,
                'active_cycles' => $activeCycles,
                'avg_completion_rate' => $avgCompletion,
                'pending_appraisals' => $pendingAppraisals,
            ],
            'filters' => compact('status', 'year'),
        ]);
    }
}