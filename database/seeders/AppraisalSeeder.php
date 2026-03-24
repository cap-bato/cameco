<?php

namespace Database\Seeders;

use App\Models\Appraisal;
use App\Models\AppraisalCycle;
use App\Models\AppraisalScore;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class AppraisalSeeder extends Seeder
{
    public function run(): void
    {
        // Get or find the Annual Review 2025 cycle
        $cycle = AppraisalCycle::where('name', 'Annual Review 2025')->first();
        if (!$cycle) {
            $this->command->warn('AppraisalCycle "Annual Review 2025" not found. Run AppraisalCycleSeeder first.');
            return;
        }

        // Get up to 10 active employees with profile and department
        $employees = Employee::with(['profile', 'department', 'dailyAttendanceSummaries'])
            ->where('status', 'active')->limit(10)->get();
        if ($employees->isEmpty()) {
            $this->command->warn('No active employees found.');
            return;
        }

        // Get all criteria for this cycle
        $criteria = $cycle->criteria;
        if ($criteria->isEmpty()) {
            $this->command->warn('No appraisal criteria found for cycle ' . $cycle->name);
            return;
        }

        $this->command->info('Creating story-driven appraisals for ' . $employees->count() . ' employees...');

        foreach ($employees as $employee) {
            $existing = Appraisal::where('appraisal_cycle_id', $cycle->id)
                ->where('employee_id', $employee->id)
                ->exists();
            if ($existing) {
                $this->command->warn("Appraisal already exists for {$employee->employee_number}");
                continue;
            }

            // Attendance analysis (last 3 months)
            $attendance = $employee->dailyAttendanceSummaries
                ->where('attendance_date', '>=', now()->subMonths(3)->toDateString());
            $daysPresent = $attendance->where('is_present', true)->count();
            $daysLate = $attendance->where('is_late', true)->count();
            $totalDays = $attendance->count();
            $attendanceRate = $totalDays > 0 ? round(($daysPresent / $totalDays) * 100, 1) : 0.0;
            $latenessRate = $totalDays > 0 ? round(($daysLate / $totalDays) * 100, 1) : 0.0;

            // Story logic: high, average, or low performer
            $statuses = ['completed', 'acknowledged'];
            $story = '';
            $scoreProfile = [];
            if ($attendanceRate >= 97 && $latenessRate < 5) {
                $story = 'Consistently punctual and present, a model employee.';
                $scoreProfile = ['high' => [8.5, 10], 'mid' => [8, 9.5], 'low' => [7.5, 8.5]];
            } elseif ($attendanceRate >= 90) {
                $story = 'Generally reliable, with occasional absences or lateness.';
                $scoreProfile = ['high' => [7.5, 9], 'mid' => [7, 8.5], 'low' => [6, 7.5]];
            } elseif ($attendanceRate >= 80) {
                $story = 'Attendance needs improvement, but shows potential.';
                $scoreProfile = ['high' => [6.5, 8], 'mid' => [6, 7.5], 'low' => [5, 6.5]];
            } else {
                $story = 'Frequent absences or lateness have impacted performance.';
                $scoreProfile = ['high' => [5, 7], 'mid' => [4, 6], 'low' => [3, 5.5]];
            }

            // Assign scores based on attendance story
            $criteriaCount = $criteria->count();
            $totalScore = 0;
            $scores = [];
            foreach ($criteria as $criterion) {
                // Vary scores by criterion type
                $band = 'mid';
                if (stripos($criterion->name, 'Attendance') !== false) {
                    $band = $attendanceRate >= 97 ? 'high' : ($attendanceRate >= 90 ? 'mid' : 'low');
                } elseif (stripos($criterion->name, 'Productivity') !== false) {
                    $band = $attendanceRate >= 90 ? 'high' : 'mid';
                } elseif (stripos($criterion->name, 'Team') !== false) {
                    $band = $latenessRate < 5 ? 'high' : 'mid';
                }
                $range = $scoreProfile[$band];
                $score = round(mt_rand($range[0] * 10, $range[1] * 10) / 10, 1);
                $scores[] = [
                    'appraisal_criteria_id' => $criterion->id,
                    'score' => $score,
                    'comments' => $story . ' (' . $criterion->name . ')',
                ];
                $totalScore += $score;
            }
            $overallScore = round($totalScore / $criteriaCount, 2);

            // Feedback message
            $feedback = $story . ' Attendance rate: ' . $attendanceRate . '%. Lateness rate: ' . $latenessRate . '%. Overall score reflects attendance and performance.';

            $appraisal = Appraisal::create([
                'appraisal_cycle_id' => $cycle->id,
                'employee_id' => $employee->id,
                'appraiser_id' => 1, // Assuming user ID 1 exists
                'status' => $statuses[array_rand($statuses)],
                'created_by' => 1,
                'overall_score' => $overallScore,
                'submitted_at' => now()->subDays(mt_rand(1, 30)),
                'feedback' => $feedback,
            ]);

            foreach ($scores as $scoreData) {
                AppraisalScore::create([
                    'appraisal_id' => $appraisal->id,
                    'appraisal_criteria_id' => $scoreData['appraisal_criteria_id'],
                    'score' => $scoreData['score'],
                    'comments' => $scoreData['comments'],
                ]);
            }

            $this->command->line("✓ {$employee->employee_number} - {$employee->profile?->full_name} - {$appraisal->status} - Score: {$overallScore} - Attendance: {$attendanceRate}%");
        }

        $this->command->info('Story-driven appraisal seeding completed successfully.');
    }
}
