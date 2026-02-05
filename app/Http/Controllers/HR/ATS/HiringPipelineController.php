<?php

namespace App\Http\Controllers\HR\ATS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Interview;
use App\Models\JobPosting;

class HiringPipelineController extends Controller
{
    /**
     * Display the hiring pipeline (Kanban + list view).
     */
    public function index(Request $request)
    {
        $applications = Application::with(['candidate', 'jobPosting'])->get()->map(function ($app) {

            $candidate = $app->candidate;

            // Build full name
            $fullName = $candidate
                ? trim(
                    ($candidate->first_name ?? '') . ' ' .
                    ($candidate->middle_name ?? '') . ' ' .
                    ($candidate->last_name ?? '')
                )
                : 'Unknown';

            // Map candidate source to label
            $sourceLabels = [
                'referral' => 'Referral',
                'job_board' => 'Job Board',
                'walk_in' => 'Walk-in',
                'agency' => 'Agency',
                'internal' => 'Internal',
                'facebook' => 'Facebook',
                'other' => 'Other',
            ];
            $source = $candidate?->source ?? 'other';
            $candidateSourceLabel = $sourceLabels[$source] ?? 'Other';

return [
    'id' => $app->id,
    'status' => $app->status,
    'applied_at' => $app->applied_at ? $app->applied_at->format('Y-m-d') : null,

    'candidate_id' => $candidate?->id,
    'candidate_name' => $fullName ?: 'Unknown',
    'candidate_email' => $candidate?->email ?? null,
    'candidate_phone' => $candidate?->phone ?? null,
    'candidate_source' => $candidate?->source ?? 'other',

    'job_id' => $app->jobPosting?->id,
    'job_title' => $app->jobPosting?->title ?? 'N/A',

    'created_at' => $app->created_at->toDateTimeString(),
    'updated_at' => $app->updated_at->toDateTimeString(),
];
        });

        // Prepare pipeline columns
        $statuses = ['submitted', 'shortlisted', 'interviewed', 'offered', 'hired', 'rejected', 'withdrawn'];

        $pipeline = collect($statuses)->map(function ($status) use ($applications) {
            $filtered = $applications->where('status', $status)->values();
            return [
                'status' => $status,
                'label' => ucfirst($status),
                'count' => $filtered->count(),
                'applications' => $filtered,
            ];
        });

        // Summary cards
        $summary = [
            'total_candidates' => Candidate::count(),
            'active_applications' => Application::whereIn('status', ['submitted','shortlisted','interviewed','offered'])->count(),
            'interviews_this_week' => Interview::whereBetween('scheduled_date',[now()->startOfWeek(), now()->endOfWeek()])->count(),
            'offers_pending' => Application::where('status','offered')->count(),
            'hires_this_month' => Application::where('status','hired')->whereMonth('updated_at',now()->month)->count(),
        ];

        $jobPostings = JobPosting::where('status','open')->get();

        return Inertia::render('HR/ATS/HiringPipeline/Index', [
            'pipeline' => $pipeline,
            'summary' => $summary,
            'jobPostings' => $jobPostings,
            'viewMode' => $request->query('view','kanban'),
            'filters' => [
                'job_posting_id' => $request->query('job_posting_id'),
                'source' => $request->query('source'),
            ],
            'sources' => [
                ['value'=>'referral','label'=>'Referral'],
                ['value'=>'job_board','label'=>'Job Board'],
                ['value'=>'walk_in','label'=>'Walk-in'],
                ['value'=>'agency','label'=>'Agency'],
                ['value'=>'internal','label'=>'Internal'],
                ['value'=>'facebook','label'=>'Facebook'],
                ['value'=>'other','label'=>'Other'],
            ],
        ]);
    }

    /**
     * Move application to a different status.
     */
    public function moveApplication(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:submitted,shortlisted,interviewed,offered,hired,rejected,withdrawn',
            'notes' => 'nullable|string',
        ]);

        $application = Application::findOrFail($id);
        $application->status = $validated['status'];
        $application->save();   

        if (!empty($validated['notes'])) {
            $application->notes()->create([
                'note' => $validated['notes'],
                'created_by' => 1,
            ]);
        }

        return back()->with('success', 'Application moved successfully');
    }
}
