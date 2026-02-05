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
        // Get all mock recommendations
        $mockRecommendations = $this->getMockRehireRecommendations();
        
        // Find the recommendation by ID
        $recommendation = collect($mockRecommendations)->firstWhere('id', (int)$id);
        
        if (!$recommendation) {
            abort(404, 'Rehire recommendation not found');
        }

        // Mock appraisal data for breakdown (varies by employee)
        $appraisal = [
            'id' => $recommendation['appraisal_id'],
            'overall_score' => $recommendation['overall_score'],
            'scores' => $this->getMockAppraisalScores($recommendation['overall_score']),
        ];

        // Mock employee data
        $employee = [
            'id' => $recommendation['employee_id'],
            'employee_number' => $recommendation['employee_number'],
            'first_name' => explode(' ', $recommendation['employee_name'])[0],
            'last_name' => substr($recommendation['employee_name'], strpos($recommendation['employee_name'], ' ') + 1),
            'full_name' => $recommendation['employee_name'],
            'department_id' => $recommendation['department_id'],
            'department_name' => $recommendation['department_name'],
            'email' => strtolower(str_replace(' ', '.', $recommendation['employee_name'])) . '@company.com',
        ];

        // Mock attendance metrics
        $attendanceMetrics = [
            'attendance_rate' => $recommendation['attendance_rate'],
            'lateness_count' => $this->calculateLatenessCount($recommendation['attendance_rate']),
            'violation_count' => $recommendation['violation_count'],
        ];

        // Mock violation logs based on violation count
        $violations = $this->getMockViolations($recommendation['violation_count'], $recommendation['employee_name']);

        // Add violations to recommendation
        $recommendation['violations'] = $violations;

        // Mock override history
        $overrideHistory = $recommendation['is_overridden'] ? [[
            'id' => 1,
            'action' => 'Recommendation Override',
            'reason' => 'Manual override by HR Manager',
            'created_by' => $recommendation['overridden_by'],
            'created_at' => $recommendation['updated_at'],
        ]] : [];

        return Inertia::render('HR/RehireRecommendations/Show', [
            'recommendation' => $recommendation,
            'appraisal' => $appraisal,
            'employee' => $employee,
            'attendanceMetrics' => $attendanceMetrics,
            'overrideHistory' => $overrideHistory,
        ]);
    }

    /**
     * Generate mock appraisal scores based on overall score
     */
    private function getMockAppraisalScores($overallScore)
    {
        // Generate varied scores around the overall score
        $base = $overallScore;
        $variance = 1.5;
        
        return [
            ['criterion' => 'Quality of Work', 'score' => round($base + rand(-10, 10) / 10, 1)],
            ['criterion' => 'Attendance & Punctuality', 'score' => round($base + rand(-5, 15) / 10, 1)],
            ['criterion' => 'Behavior & Conduct', 'score' => round($base + rand(-8, 12) / 10, 1)],
            ['criterion' => 'Productivity', 'score' => round($base + rand(-15, 10) / 10, 1)],
            ['criterion' => 'Teamwork', 'score' => round($base + rand(-10, 10) / 10, 1)],
        ];
    }

    /**
     * Calculate lateness count based on attendance rate
     */
    private function calculateLatenessCount($attendanceRate)
    {
        if ($attendanceRate >= 95) return rand(0, 2);
        if ($attendanceRate >= 90) return rand(2, 5);
        if ($attendanceRate >= 85) return rand(5, 10);
        return rand(10, 20);
    }

    /**
     * Generate mock violations based on count
     */
    private function getMockViolations($count, $employeeName)
    {
        if ($count === 0) {
            return [];
        }

        $violationTypes = [
            [
                'type' => 'Unauthorized Absence',
                'description' => 'Employee failed to report to work without prior notice or approval for 2 consecutive days.',
                'severity' => 'high',
            ],
            [
                'type' => 'Late Clock-in',
                'description' => 'Employee clocked in 45 minutes late without valid reason or prior notification.',
                'severity' => 'medium',
            ],
            [
                'type' => 'Early Departure',
                'description' => 'Left workplace 30 minutes before scheduled end time without supervisor approval.',
                'severity' => 'medium',
            ],
            [
                'type' => 'Dress Code Violation',
                'description' => 'Failed to comply with company dress code policy during client meeting.',
                'severity' => 'low',
            ],
            [
                'type' => 'Insubordination',
                'description' => 'Refused to follow direct instructions from immediate supervisor regarding project deadline.',
                'severity' => 'high',
            ],
            [
                'type' => 'Policy Non-Compliance',
                'description' => 'Did not follow proper documentation procedures as outlined in company handbook.',
                'severity' => 'medium',
            ],
            [
                'type' => 'Workplace Misconduct',
                'description' => 'Engaged in unprofessional behavior during team meeting, creating hostile work environment.',
                'severity' => 'high',
            ],
        ];

        $violations = [];
        $dates = [
            '2025-10-15 09:30:00',
            '2025-09-22 14:15:00',
            '2025-08-10 11:00:00',
            '2025-07-05 16:45:00',
            '2025-06-18 08:20:00',
        ];

        for ($i = 0; $i < min($count, 5); $i++) {
            $violation = $violationTypes[$i % count($violationTypes)];
            $violations[] = [
                'id' => $i + 1,
                'type' => $violation['type'],
                'description' => $violation['description'],
                'severity' => $violation['severity'],
                'occurred_at' => $dates[$i] ?? date('Y-m-d H:i:s', strtotime("-{$i} months")),
            ];
        }

        return $violations;
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
