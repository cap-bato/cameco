<?php

namespace App\Http\Controllers\HR\Appraisal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * RehireRecommendationController
 *
 * Manages rehire recommendations - eligibility assessments for employee rehiring.
 * System auto-recommends based on appraisal scores and attendance metrics.
 * HR Managers can override recommendations with justification.
 *
 * Recommendation Logic:
 * - Eligible: Score >= 7.5 AND Attendance >= 90% AND No violations
 * - Not Recommended: Score < 5.0 OR Violations > 3
 * - Review Required: All other cases (edge cases requiring manual review)
 *
 * HR can override any recommendation with notes for audit trail.
 */
class RehireRecommendationController extends Controller
{
    /**
     * Display a listing of rehire recommendations
     */
    public function index(Request $request)
    {
        $recommendation = $request->input('recommendation', '');
        $departmentId = $request->input('department_id', '');
        $search = $request->input('search', '');

        // Mock rehire recommendations
        $mockRecommendations = $this->getMockRehireRecommendations();

        // Apply filters
        if ($recommendation) {
            $mockRecommendations = array_filter(
                $mockRecommendations,
                fn($r) => $r['recommendation'] === $recommendation
            );
        }
        if ($departmentId) {
            $mockRecommendations = array_filter(
                $mockRecommendations,
                fn($r) => (string)$r['department_id'] === $departmentId
            );
        }
        if ($search) {
            $mockRecommendations = array_filter($mockRecommendations, function ($r) use ($search) {
                return stripos($r['employee_name'], $search) !== false ||
                       stripos($r['employee_number'], $search) !== false;
            });
        }

        // Get departments for filter
        $departments = $this->getMockDepartments();

        return Inertia::render('HR/RehireRecommendations/Index', [
            'recommendations' => array_values($mockRecommendations),
            'departments' => $departments,
            'filters' => [
                'recommendation' => $recommendation,
                'department_id' => $departmentId,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Display the specified rehire recommendation details
     */
    public function show($id)
    {
        // Mock recommendation with details
        $recommendation = [
            'id' => 1,
            'employee_id' => 1,
            'employee_name' => 'Juan dela Cruz',
            'employee_number' => 'EMP-2023-001',
            'department_id' => 1,
            'department_name' => 'Engineering',
            'appraisal_id' => 1,
            'cycle_name' => 'Annual Review 2025',
            'recommendation' => 'eligible',
            'recommendation_label' => 'Eligible for Rehire',
            'recommendation_color' => 'bg-green-100 text-green-800',
            'overall_score' => 8.2,
            'attendance_rate' => 94.5,
            'violation_count' => 0,
            'notes' => 'Strong performer with excellent attendance record',
            'is_overridden' => false,
            'overridden_by' => null,
            'created_at' => '2025-11-18 14:30:00',
            'updated_at' => '2025-11-18 14:30:00',
        ];

        // Mock appraisal data for breakdown
        $appraisal = [
            'id' => 1,
            'overall_score' => 8.2,
            'scores' => [
                ['criterion' => 'Quality of Work', 'score' => 8.0],
                ['criterion' => 'Attendance & Punctuality', 'score' => 9.0],
                ['criterion' => 'Behavior & Conduct', 'score' => 8.5],
                ['criterion' => 'Productivity', 'score' => 7.5],
                ['criterion' => 'Teamwork', 'score' => 8.0],
            ],
        ];

        // Mock employee data
        $employee = [
            'id' => 1,
            'employee_number' => 'EMP-2023-001',
            'first_name' => 'Juan',
            'last_name' => 'dela Cruz',
            'full_name' => 'Juan dela Cruz',
            'department_id' => 1,
            'department_name' => 'Engineering',
            'email' => 'juan.delacruz@company.com',
        ];

        // Mock attendance metrics
        $attendanceMetrics = [
            'attendance_rate' => 94.5,
            'lateness_count' => 2,
            'violation_count' => 0,
        ];

        // Mock override history
        $overrideHistory = [];

        return Inertia::render('HR/RehireRecommendations/Show', [
            'recommendation' => $recommendation,
            'appraisal' => $appraisal,
            'employee' => $employee,
            'attendanceMetrics' => $attendanceMetrics,
            'overrideHistory' => $overrideHistory,
        ]);
    }

    /**
     * Override a rehire recommendation
     */
    public function override(Request $request, $id)
    {
        $validated = $request->validate([
            'recommendation' => 'required|in:eligible,not_recommended,review_required',
            'notes' => 'required|string|min:10|max:1000',
        ]);

        // In production, update recommendation and store override record
        return back()->with('success', 'Rehire recommendation override recorded successfully');
    }

    /**
     * Bulk approve recommendations
     */
    public function bulkApprove(Request $request)
    {
        $validated = $request->validate([
            'recommendation_ids' => 'required|array|min:1',
            'recommendation_ids.*' => 'required|integer',
        ]);

        $count = count($validated['recommendation_ids']);

        // In production, update status for selected recommendations
        return back()->with('success', "Successfully processed {$count} rehire recommendation(s)");
    }

    /**
     * Get mock rehire recommendations
     */
    private function getMockRehireRecommendations()
    {
        return [
            // Eligible recommendations
            [
                'id' => 1,
                'employee_id' => 1,
                'employee_name' => 'Juan dela Cruz',
                'employee_number' => 'EMP-2023-001',
                'department_id' => 1,
                'department_name' => 'Engineering',
                'appraisal_id' => 1,
                'cycle_name' => 'Annual Review 2025',
                'recommendation' => 'eligible',
                'recommendation_label' => 'Eligible for Rehire',
                'recommendation_color' => 'bg-green-100 text-green-800',
                'overall_score' => 8.2,
                'attendance_rate' => 94.5,
                'violation_count' => 0,
                'notes' => 'Strong performer with excellent attendance',
                'is_overridden' => false,
                'overridden_by' => null,
                'created_at' => '2025-11-18 14:30:00',
                'updated_at' => '2025-11-18 14:30:00',
            ],
            [
                'id' => 2,
                'employee_id' => 2,
                'employee_name' => 'Maria Santos',
                'employee_number' => 'EMP-2023-002',
                'department_id' => 2,
                'department_name' => 'Finance',
                'appraisal_id' => 2,
                'cycle_name' => 'Annual Review 2025',
                'recommendation' => 'eligible',
                'recommendation_label' => 'Eligible for Rehire',
                'recommendation_color' => 'bg-green-100 text-green-800',
                'overall_score' => 7.8,
                'attendance_rate' => 91.0,
                'violation_count' => 0,
                'notes' => 'Consistent performer, good attendance record',
                'is_overridden' => false,
                'overridden_by' => null,
                'created_at' => '2025-11-19 10:15:00',
                'updated_at' => '2025-11-19 10:15:00',
            ],
            [
                'id' => 3,
                'employee_id' => 4,
                'employee_name' => 'Ana Garcia',
                'employee_number' => 'EMP-2023-004',
                'department_id' => 4,
                'department_name' => 'Sales',
                'appraisal_id' => 4,
                'cycle_name' => 'Annual Review 2025',
                'recommendation' => 'eligible',
                'recommendation_label' => 'Eligible for Rehire',
                'recommendation_color' => 'bg-green-100 text-green-800',
                'overall_score' => 7.5,
                'attendance_rate' => 90.0,
                'violation_count' => 0,
                'notes' => 'Meets all criteria for rehire',
                'is_overridden' => false,
                'overridden_by' => null,
                'created_at' => '2025-11-17 15:45:00',
                'updated_at' => '2025-11-17 15:45:00',
            ],
            [
                'id' => 4,
                'employee_id' => 6,
                'employee_name' => 'Linda Rodriguez',
                'employee_number' => 'EMP-2023-006',
                'department_id' => 5,
                'department_name' => 'HR',
                'appraisal_id' => 6,
                'cycle_name' => 'Annual Review 2025',
                'recommendation' => 'eligible',
                'recommendation_label' => 'Eligible for Rehire',
                'recommendation_color' => 'bg-green-100 text-green-800',
                'overall_score' => 8.5,
                'attendance_rate' => 95.0,
                'violation_count' => 0,
                'notes' => 'Outstanding performer, top candidate',
                'is_overridden' => false,
                'overridden_by' => null,
                'created_at' => '2025-11-20 11:00:00',
                'updated_at' => '2025-11-20 11:00:00',
            ],
            [
                'id' => 5,
                'employee_id' => 7,
                'employee_name' => 'Ramon Martinez',
                'employee_number' => 'EMP-2023-007',
                'department_id' => 3,
                'department_name' => 'Operations',
                'appraisal_id' => 8,
                'cycle_name' => 'Annual Review 2025',
                'recommendation' => 'eligible',
                'recommendation_label' => 'Eligible for Rehire',
                'recommendation_color' => 'bg-green-100 text-green-800',
                'overall_score' => 7.3,
                'attendance_rate' => 92.0,
                'violation_count' => 0,
                'notes' => 'Solid performer meeting all criteria',
                'is_overridden' => false,
                'overridden_by' => null,
                'created_at' => '2025-11-19 13:20:00',
                'updated_at' => '2025-11-19 13:20:00',
            ],
            [
                'id' => 6,
                'employee_id' => 9,
                'employee_name' => 'Daniel Perez',
                'employee_number' => 'EMP-2023-009',
                'department_id' => 4,
                'department_name' => 'Sales',
                'appraisal_id' => 9,
                'cycle_name' => 'Annual Review 2025',
                'recommendation' => 'eligible',
                'recommendation_label' => 'Eligible for Rehire',
                'recommendation_color' => 'bg-green-100 text-green-800',
                'overall_score' => 7.6,
                'attendance_rate' => 93.5,
                'violation_count' => 0,
                'notes' => 'Good performer with consistent results',
                'is_overridden' => false,
                'overridden_by' => null,
                'created_at' => '2025-11-18 09:30:00',
                'updated_at' => '2025-11-18 09:30:00',
            ],
            // Review Required recommendations
            [
                'id' => 7,
                'employee_id' => 3,
                'employee_name' => 'Carlos Reyes',
                'employee_number' => 'EMP-2023-003',
                'department_id' => 3,
                'department_name' => 'Operations',
                'appraisal_id' => 3,
                'cycle_name' => 'Annual Review 2025',
                'recommendation' => 'review_required',
                'recommendation_label' => 'Requires Review',
                'recommendation_color' => 'bg-yellow-100 text-yellow-800',
                'overall_score' => 6.8,
                'attendance_rate' => 87.3,
                'violation_count' => 1,
                'notes' => 'Below average performance, needs improvement plan',
                'is_overridden' => false,
                'overridden_by' => null,
                'created_at' => '2025-11-17 11:45:00',
                'updated_at' => '2025-11-17 11:45:00',
            ],
            [
                'id' => 8,
                'employee_id' => 5,
                'employee_name' => 'Miguel Torres',
                'employee_number' => 'EMP-2023-005',
                'department_id' => 1,
                'department_name' => 'Engineering',
                'appraisal_id' => 5,
                'cycle_name' => 'Annual Review 2025',
                'recommendation' => 'review_required',
                'recommendation_label' => 'Requires Review',
                'recommendation_color' => 'bg-yellow-100 text-yellow-800',
                'overall_score' => 6.9,
                'attendance_rate' => 85.2,
                'violation_count' => 2,
                'notes' => 'Declining performance, may need counseling',
                'is_overridden' => false,
                'overridden_by' => null,
                'created_at' => '2025-11-16 14:00:00',
                'updated_at' => '2025-11-16 14:00:00',
            ],
            // Not Recommended
            [
                'id' => 9,
                'employee_id' => 8,
                'employee_name' => 'Sophie Mercado',
                'employee_number' => 'EMP-2023-008',
                'department_id' => 2,
                'department_name' => 'Finance',
                'appraisal_id' => 10,
                'cycle_name' => 'Annual Review 2025',
                'recommendation' => 'not_recommended',
                'recommendation_label' => 'Not Recommended',
                'recommendation_color' => 'bg-red-100 text-red-800',
                'overall_score' => 5.2,
                'attendance_rate' => 78.5,
                'violation_count' => 3,
                'notes' => 'Poor performance and attendance issues',
                'is_overridden' => false,
                'overridden_by' => null,
                'created_at' => '2025-11-15 16:30:00',
                'updated_at' => '2025-11-15 16:30:00',
            ],
            [
                'id' => 10,
                'employee_id' => 10,
                'employee_name' => 'Rebecca Lopez',
                'employee_number' => 'EMP-2023-010',
                'department_id' => 1,
                'department_name' => 'Engineering',
                'appraisal_id' => 11,
                'cycle_name' => 'Annual Review 2025',
                'recommendation' => 'not_recommended',
                'recommendation_label' => 'Not Recommended',
                'recommendation_color' => 'bg-red-100 text-red-800',
                'overall_score' => 4.8,
                'attendance_rate' => 76.0,
                'violation_count' => 4,
                'notes' => 'Unsatisfactory performance, multiple violations',
                'is_overridden' => false,
                'overridden_by' => null,
                'created_at' => '2025-11-14 10:15:00',
                'updated_at' => '2025-11-14 10:15:00',
            ],
        ];
    }

    /**
     * Get mock departments
     */
    private function getMockDepartments()
    {
        return [
            ['id' => 1, 'name' => 'Engineering'],
            ['id' => 2, 'name' => 'Finance'],
            ['id' => 3, 'name' => 'Operations'],
            ['id' => 4, 'name' => 'Sales'],
            ['id' => 5, 'name' => 'HR'],
        ];
    }
}
