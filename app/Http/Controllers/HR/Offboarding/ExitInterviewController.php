<?php

namespace App\Http\Controllers\HR\Offboarding;

use App\Http\Controllers\Controller;
use App\Models\OffboardingCase;
use App\Models\ExitInterview;
use App\Services\HR\OffboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExitInterviewController extends Controller
{
    protected OffboardingService $offboardingService;

    public function __construct(OffboardingService $offboardingService)
    {
        $this->offboardingService = $offboardingService;
    }

    /**
     * Display exit interview form for employee.
     * 
     * Shows a questionnaire with pre-populated employee information.
     * Allows saving progress as draft.
     */
    public function show($caseId): Response
    {
        $case = OffboardingCase::with([
            'employee.profile',
            'employee.department',
            'exitInterview',
        ])->findOrFail($caseId);

        // Get or create exit interview for this case
        $interview = $case->exitInterview ?? ExitInterview::create([
            'offboarding_case_id' => $case->id,
            'employee_id' => $case->employee_id,
            'status' => 'pending',
            'interview_method' => 'written_form',
        ]);

        Log::info('Exit interview form accessed', [
            'case_number' => $case->case_number,
            'employee_id' => $case->employee_id,
            'interview_id' => $interview->id,
        ]);

        return Inertia::render('HR/Offboarding/ExitInterview/Show', [
            'case' => [
                'id' => $case->id,
                'case_number' => $case->case_number,
                'status' => $case->status,
                'last_working_day' => $case->last_working_day->format('Y-m-d'),
                'employee' => [
                    'id' => $case->employee->id,
                    'employee_number' => $case->employee->employee_number,
                    'name' => $case->employee->profile?->first_name . ' ' . $case->employee->profile?->last_name,
                    'position' => $case->employee->position?->name,
                    'department' => $case->employee->department?->name,
                    'email' => $case->employee->user?->email,
                ]
            ],
            'interview' => [
                'id' => $interview->id,
                'status' => $interview->status,
                'reason_for_leaving' => $interview->reason_for_leaving,
                'overall_satisfaction' => $interview->overall_satisfaction,
                'work_environment_rating' => $interview->work_environment_rating,
                'management_rating' => $interview->management_rating,
                'compensation_rating' => $interview->compensation_rating,
                'career_growth_rating' => $interview->career_growth_rating,
                'work_life_balance_rating' => $interview->work_life_balance_rating,
                'liked_most' => $interview->liked_most,
                'liked_least' => $interview->liked_least,
                'suggestions_for_improvement' => $interview->suggestions_for_improvement,
                'would_recommend_company' => $interview->would_recommend_company,
                'would_consider_returning' => $interview->would_consider_returning,
                'questions_responses' => $interview->questions_responses ?? [],
            ],
            'isCompleted' => $interview->status === 'completed',
        ]);
    }

    /**
     * Submit exit interview responses.
     * 
     * Validates all required questions, saves responses, performs sentiment analysis,
     * and notifies HR coordinator.
     */
    public function submit(Request $request, $caseId)
    {
        $case = OffboardingCase::with('exitInterview')->findOrFail($caseId);
        $interview = $case->exitInterview;

        if (!$interview) {
            return redirect()->back()->with('error', 'Exit interview not found.');
        }

        // Validate required fields
        $validated = $request->validate([
            'reason_for_leaving' => 'required|string|min:10|max:1000',
            'overall_satisfaction' => 'required|integer|between:1,5',
            'work_environment_rating' => 'required|integer|between:1,5',
            'management_rating' => 'required|integer|between:1,5',
            'compensation_rating' => 'required|integer|between:1,5',
            'career_growth_rating' => 'required|integer|between:1,5',
            'work_life_balance_rating' => 'required|integer|between:1,5',
            'liked_most' => 'required|string|min:10|max:500',
            'liked_least' => 'required|string|min:10|max:500',
            'suggestions_for_improvement' => 'nullable|string|max:1000',
            'would_recommend_company' => 'required|boolean',
            'would_consider_returning' => 'required|boolean',
            'questions_responses' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            // Perform sentiment analysis on text responses
            $sentimentScore = $this->analyzeSentiment(
                $validated['reason_for_leaving'] . ' ' .
                $validated['liked_most'] . ' ' .
                $validated['liked_least']
            );

            // Extract key themes from responses
            $keyThemes = $this->extractKeyThemes(
                $validated['reason_for_leaving'],
                $validated['suggestions_for_improvement']
            );

            // Update exit interview with responses
            $interview->update([
                'reason_for_leaving' => $validated['reason_for_leaving'],
                'overall_satisfaction' => $validated['overall_satisfaction'],
                'work_environment_rating' => $validated['work_environment_rating'],
                'management_rating' => $validated['management_rating'],
                'compensation_rating' => $validated['compensation_rating'],
                'career_growth_rating' => $validated['career_growth_rating'],
                'work_life_balance_rating' => $validated['work_life_balance_rating'],
                'liked_most' => $validated['liked_most'],
                'liked_least' => $validated['liked_least'],
                'suggestions_for_improvement' => $validated['suggestions_for_improvement'],
                'would_recommend_company' => $validated['would_recommend_company'],
                'would_consider_returning' => $validated['would_consider_returning'],
                'questions_responses' => $validated['questions_responses'] ?? [],
                'sentiment_score' => $sentimentScore,
                'key_themes' => $keyThemes,
                'status' => 'completed',
                'completed_at' => now(),
                'interview_method' => 'written_form',
            ]);

            // Update case timestamp
            $case->update(['exit_interview_completed_at' => now()]);

            // Send notification to HR coordinator
            $this->offboardingService->notifyExitInterviewCompleted($case, $interview);

            DB::commit();

            Log::info('Exit interview submitted successfully', [
                'case_number' => $case->case_number,
                'interview_id' => $interview->id,
                'sentiment_score' => $sentimentScore,
            ]);

            return redirect()
                ->route('hr.offboarding.cases.show', $case->id)
                ->with('success', 'Exit interview submitted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to submit exit interview', [
                'case_number' => $case->case_number,
                'interview_id' => $interview->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Failed to submit exit interview. Please try again.');
        }
    }

    /**
     * View exit interview results (HR only).
     * 
     * Displays submitted responses with ratings visualization,
     * sentiment analysis results, and extracted themes.
     */
    public function viewResults($caseId): Response
    {
        $case = OffboardingCase::with([
            'employee.profile',
            'employee.department',
            'exitInterview',
        ])->findOrFail($caseId);

        $interview = $case->exitInterview;

        if (!$interview || $interview->status !== 'completed') {
            abort(404, 'Exit interview not found or not completed.');
        }

        // Calculate statistics
        $averageRating = $interview->getAverageRating();
        $sentimentLevel = $this->getSentimentLevel($interview->sentiment_score);

        Log::info('Exit interview results viewed', [
            'case_number' => $case->case_number,
            'viewed_by' => auth()->id(),
        ]);

        return Inertia::render('HR/Offboarding/ExitInterview/Results', [
            'case' => [
                'id' => $case->id,
                'case_number' => $case->case_number,
                'employee' => [
                    'id' => $case->employee->id,
                    'employee_number' => $case->employee->employee_number,
                    'name' => $case->employee->profile?->first_name . ' ' . $case->employee->profile?->last_name,
                    'position' => $case->employee->position?->name,
                    'department' => $case->employee->department?->name,
                ]
            ],
            'interview' => [
                'id' => $interview->id,
                'completed_at' => $interview->completed_at?->format('Y-m-d H:i'),
                'reason_for_leaving' => $interview->reason_for_leaving,
                'liked_most' => $interview->liked_most,
                'liked_least' => $interview->liked_least,
                'suggestions_for_improvement' => $interview->suggestions_for_improvement,
                'would_recommend_company' => $interview->would_recommend_company,
                'would_consider_returning' => $interview->would_consider_returning,
            ],
            'ratings' => [
                'overall_satisfaction' => $interview->overall_satisfaction,
                'work_environment_rating' => $interview->work_environment_rating,
                'management_rating' => $interview->management_rating,
                'compensation_rating' => $interview->compensation_rating,
                'career_growth_rating' => $interview->career_growth_rating,
                'work_life_balance_rating' => $interview->work_life_balance_rating,
                'average' => round($averageRating, 2),
            ],
            'analysis' => [
                'sentiment_score' => round($interview->sentiment_score, 2),
                'sentiment_level' => $sentimentLevel,
                'key_themes' => $interview->key_themes ?? [],
            ],
        ]);
    }

    /**
     * Exit interview analytics dashboard.
     * 
     * Displays aggregate exit interview data with trends,
     * departmental comparisons, and satisfaction trends.
     */
    public function analytics(Request $request): Response
    {
        $startDate = $request->input('start_date', now()->subMonths(12)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $department = $request->input('department', 'all');
        $sentimentFilter = $request->input('sentiment', 'all');

        // Build query
        $query = ExitInterview::with([
            'offboardingCase',
            'employee.department',
        ])
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate]);

        // Apply department filter
        if ($department !== 'all') {
            $query->whereHas('employee', fn($q) => $q->where('department_id', $department));
        }

        // Apply sentiment filter
        if ($sentimentFilter === 'positive') {
            $query->positiveSentiment();
        } elseif ($sentimentFilter === 'negative') {
            $query->negativeSentiment();
        }

        $interviews = $query->get();

        // Calculate statistics
        $totalInterviews = $interviews->count();
        $avgOverallSatisfaction = $interviews->avg('overall_satisfaction') ?? 0;
        $avgSentiment = $interviews->avg('sentiment_score') ?? 0;

        // Satisfaction trends by category
        $satisfactionTrends = [
            'overall' => round($interviews->avg('overall_satisfaction') ?? 0, 2),
            'work_environment' => round($interviews->avg('work_environment_rating') ?? 0, 2),
            'management' => round($interviews->avg('management_rating') ?? 0, 2),
            'compensation' => round($interviews->avg('compensation_rating') ?? 0, 2),
            'career_growth' => round($interviews->avg('career_growth_rating') ?? 0, 2),
            'work_life_balance' => round($interviews->avg('work_life_balance_rating') ?? 0, 2),
        ];

        // Top reasons for leaving
        $reasonsForLeaving = $interviews
            ->pluck('reason_for_leaving')
            ->filter()
            ->map(fn($reason) => mb_substr($reason, 0, 100))
            ->unique()
            ->values()
            ->take(5);

        // Key themes analysis
        $allThemes = collect();
        $interviews->each(function ($interview) use (&$allThemes) {
            if ($interview->key_themes) {
                $allThemes = $allThemes->concat((array)$interview->key_themes);
            }
        });

        $topThemes = $allThemes
            ->countBy()
            ->sort()
            ->reverse()
            ->take(10)
            ->toArray();

        // Sentiment distribution
        $sentimentDistribution = [
            'positive' => $interviews->where('sentiment_score', '>=', 0.6)->count(),
            'neutral' => $interviews->whereBetween('sentiment_score', [0.4, 0.59])->count(),
            'negative' => $interviews->where('sentiment_score', '<', 0.4)->count(),
        ];

        // Rehire consideration
        $wouldReturnCount = $interviews->where('would_consider_returning', true)->count();
        $wouldRecommendCount = $interviews->where('would_recommend_company', true)->count();

        // Departmental breakdown
        $departmentalData = $interviews
            ->groupBy(fn($interview) => $interview->employee->department?->name ?? 'Unknown')
            ->map(function ($deptInterviews) {
                return [
                    'count' => $deptInterviews->count(),
                    'avg_satisfaction' => round($deptInterviews->avg('overall_satisfaction') ?? 0, 2),
                    'avg_sentiment' => round($deptInterviews->avg('sentiment_score') ?? 0, 2),
                    'would_return' => $deptInterviews->where('would_consider_returning', true)->count(),
                ];
            })
            ->toArray();

        Log::info('Exit interview analytics accessed', [
            'total_interviews' => $totalInterviews,
            'date_range' => "{$startDate} to {$endDate}",
        ]);

        return Inertia::render('HR/Offboarding/ExitInterview/Analytics', [
            'summary' => [
                'total_interviews' => $totalInterviews,
                'avg_overall_satisfaction' => round($avgOverallSatisfaction, 2),
                'avg_sentiment' => round($avgSentiment, 2),
                'would_return_percentage' => $totalInterviews > 0 
                    ? round(($wouldReturnCount / $totalInterviews) * 100, 1) 
                    : 0,
                'would_recommend_percentage' => $totalInterviews > 0 
                    ? round(($wouldRecommendCount / $totalInterviews) * 100, 1) 
                    : 0,
            ],
            'satisfactionTrends' => $satisfactionTrends,
            'reasonsForLeaving' => $reasonsForLeaving->values()->toArray(),
            'topThemes' => $topThemes,
            'sentimentDistribution' => $sentimentDistribution,
            'departmentalData' => $departmentalData,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'department' => $department,
                'sentiment' => $sentimentFilter,
            ],
        ]);
    }

    /**
     * Analyze sentiment of text responses.
     * 
     * Simple sentiment analysis based on negative/positive word patterns.
     * Returns score between 0 and 1.
     */
    private function analyzeSentiment(string $text): float
    {
        $negativeWords = [
            'bad', 'terrible', 'awful', 'horrible', 'poor', 'inadequate',
            'frustrated', 'disappointed', 'angry', 'upset', 'unhappy',
            'lack', 'missing', 'problem', 'issue', 'concern', 'limited',
            'no', 'not', 'never', 'worse', 'worst', 'fail', 'failed',
        ];

        $positiveWords = [
            'good', 'great', 'excellent', 'amazing', 'wonderful', 'fantastic',
            'happy', 'satisfied', 'pleased', 'appreciate', 'appreciate',
            'learn', 'grow', 'support', 'helpful', 'collaborative', 'best',
            'yes', 'love', 'enjoy', 'grateful', 'grateful', 'proud',
        ];

        $textLower = strtolower($text);
        $words = str_word_count($textLower, 1);

        $negativeCount = 0;
        $positiveCount = 0;

        foreach ($words as $word) {
            $word = trim($word, '.,!?;:');
            if (in_array($word, $negativeWords)) {
                $negativeCount++;
            } elseif (in_array($word, $positiveWords)) {
                $positiveCount++;
            }
        }

        $totalSentimentWords = $negativeCount + $positiveCount;
        if ($totalSentimentWords === 0) {
            return 0.5; // Neutral
        }

        return min(1.0, max(0.0, $positiveCount / $totalSentimentWords));
    }

    /**
     * Extract key themes from text responses.
     */
    private function extractKeyThemes(string ...$texts): array
    {
        $commonThemes = [];
        $themePatterns = [
            'compensation' => ['salary', 'pay', 'bonus', 'raise', 'wage', 'benefit'],
            'career_growth' => ['career', 'growth', 'advancement', 'opportunity', 'development', 'promotion'],
            'work_life_balance' => ['balance', 'hours', 'flexible', 'time', 'family', 'stress'],
            'management' => ['manager', 'leadership', 'boss', 'supervisor', 'communication'],
            'team' => ['team', 'colleagues', 'coworkers', 'culture', 'environment'],
            'workload' => ['workload', 'pressure', 'deadline', 'busy', 'overwhelming'],
            'remote' => ['remote', 'work from home', 'office', 'location', 'commute'],
        ];

        $fullText = strtolower(implode(' ', $texts));

        foreach ($themePatterns as $theme => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($fullText, $keyword)) {
                    $commonThemes[$theme] = true;
                    break;
                }
            }
        }

        return array_keys($commonThemes);
    }

    /**
     * Get sentiment level description.
     */
    private function getSentimentLevel(float $score): string
    {
        if ($score >= 0.6) {
            return 'Positive';
        } elseif ($score >= 0.4) {
            return 'Neutral';
        }
        return 'Negative';
    }
}
