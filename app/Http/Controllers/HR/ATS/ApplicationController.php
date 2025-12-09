<?php

namespace App\Http\Controllers\HR\ATS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Application;
use App\Models\JobPosting;
use App\Models\ApplicationStatusHistory;
use App\Models\Interview;
use App\Models\Offer;
use App\Models\Note;

use function Laravel\Prompts\alert;

class ApplicationController extends Controller
{
    /**
     * Display a listing of applications.
     */
    public function index(Request $request): Response
    {
        $status   = $request->input('status');
        $jobId    = $request->input('job_id');
        $minScore = $request->input('min_score');
        $maxScore = $request->input('max_score');

        $applications = Application::with(['candidate', 'jobPosting', 'interviews'])
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($jobId, fn($q) => $q->where('job_posting_id', $jobId))
            ->when($minScore, fn($q) => $q->where('score', '>=', $minScore))
            ->when($maxScore, fn($q) => $q->where('score', '<=', $maxScore))
            ->latest('applied_at')
            ->get()
            ->map(function ($app) {
                return [
                    'id' => $app->id,
                    'status' => $app->status,
                    'score' => $app->score ?? 'N/A',
                    'applied_at' => $app->applied_at,
                    'candidate_name' => $app->candidate
                        ? trim(
                            ($app->candidate->first_name ?? '') . ' ' .
                            ($app->candidate->middle_name ?? '') . ' ' .
                            ($app->candidate->last_name ?? '')
                        )
                        : 'Unknown',
                    'candidate_email' => $app->candidate->email ?? 'N/A',
                    'candidate_phone' => $app->candidate->phone ?? 'N/A',
                    'job_title' => $app->jobPosting->title ?? 'Unknown',
                    'interviews_count' => $app->interviews->count(),
                ];
            });

        $statistics = [
            'total_applications' => Application::count(),
            'submitted'   => Application::where('status', 'submitted')->count(),
            'shortlisted' => Application::where('status', 'shortlisted')->count(),
            'interviewed' => Application::where('status', 'interviewed')->count(),
            'offered'     => Application::where('status', 'offered')->count(),
            'hired'       => Application::where('status', 'hired')->count(),
            'rejected'    => Application::where('status', 'rejected')->count(),
        ];

        return Inertia::render('HR/ATS/Applications/Index', [
            'applications' => $applications,
            'filters' => [
                'status' => $status,
                'job_id' => $jobId,
            ],
            'statistics' => $statistics,
            'jobPostings' => JobPosting::select('id', 'title')->get(),
        ]);
    }

    /**
     * Show a single application with full details.
     */
public function show(Application $application): Response
{
    $application->load([
        'candidate',
        'jobPosting.department',
        'interviews',
        'statusHistory',
        'notes'
    ]);

    // Calculate permissions
    $canScheduleInterview = in_array($application->status, ['shortlisted', 'interviewed']);
    $canGenerateOffer = $application->status === 'interviewed';
return Inertia::render('HR/ATS/Applications/Show', [
    'application' => [
        'id' => $application->id,
        'candidate_name' => trim(
            ($application->candidate->first_name ?? '') . ' ' .
            ($application->candidate->middle_name ?? '') . ' ' .
            ($application->candidate->last_name ?? '')
        ) ?: 'Unknown',
        'candidate_email' => $application->candidate->email ?? null,
        'candidate_phone' => $application->candidate->phone ?? null,
        'job_title' => $application->jobPosting->title ?? 'Unknown Position',
        'status' => match($application->status) {
            'new' => 'submitted',
            'in_process' => 'shortlisted',
            default => $application->status,
        },
        'original_status' => $application->status, // <— send original DB status
        'score' => $application->score,
        'applied_at' => $application->applied_at,
    ],
    'interviews' => $application->interviews,
    'status_history' => $application->statusHistory,
    'notes' => $application->notes ?? [],
    'can_schedule_interview' => in_array($application->status, ['shortlisted', 'interviewed']),
    'can_generate_offer' => $application->status === 'interviewed',
]);

}


    /**
     * Update application status.
     */
    public function updateStatus(Request $request, Application $application)
    {
        $validated = $request->validate([
            'status' => 'required|in:submitted,shortlisted,interviewed,offered,hired,rejected,withdrawn',
            'reason' => 'required_if:status,rejected|nullable|string',
        ]);

        $application->status = $validated['status'];
        $application->save();

        ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'status'         => $validated['status'],
            'changed_by'     => 1,
            'notes'          => $validated['reason'] ?? null,
        ]);

        return back()->with('success', 'Application status updated.');
    }

    /**
     * Shortlist application.
     */
    public function shortlist(Application $application)
    {
        $application->status = 'shortlisted';
        $application->save();

        ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'status'         => 'shortlisted',
            'changed_by'     => 1,
        ]);

        return back()->with('success', 'Application shortlisted.');
    }

    /**
     * Reject application.
     */
    public function reject(Request $request, Application $application)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $application->status = 'rejected';
        $application->save();

        ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'status'         => 'rejected',
            'changed_by'     => 1,
            'notes'          => $validated['reason'],
        ]);

        return redirect()->route('hr.ats.applications.index')
            ->with('success', 'Application rejected.');
    }

    /**
     * Schedule interview.
     */
    public function scheduleInterview(Request $request, Application $application)
    {
        $validated = $request->validate([
            'scheduled_date' => 'required|date',
            'scheduled_time' => 'required',
            'location_type'  => 'required|in:office,virtual',
            'interviewer_name' => 'required|string',
        ]);

        Interview::create([
            'application_id'  => $application->id,
            'job_title'       => $application->jobPosting->title,
            'scheduled_date'  => $validated['scheduled_date'],
            'scheduled_time'  => $validated['scheduled_time'],
            'location_type'   => $validated['location_type'],
            'interviewer_name'=> $validated['interviewer_name'],
        ]);

        return back()->with('success', 'Interview scheduled.');
    }

    /**
     * Generate offer.
     */
    public function generateOffer(Request $request, Application $application)
    {
        Offer::create([
            'application_id' => $application->id,
            'title'          => $application->jobPosting->title,
            'created_by'     => 1,
        ]);

        $application->status = 'offered';
        $application->save();

        ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'status'         => 'offered',
            'changed_by'     => 1,
        ]);

        return back()->with('success', 'Offer generated.');
    }

    public function move(Request $request, Application $application)
{
    $validated = $request->validate([
        'status' => 'required|in:submitted,shortlisted,interviewed,offered,hired,rejected,withdrawn',
        'notes'  => 'nullable|string|max:1000',
    ]);

    $application->status = $validated['status'];
    $application->save();

    // Save to status history
    ApplicationStatusHistory::create([
        'application_id' => $application->id,
        'status'         => $validated['status'],
        'changed_by'     =>  1, // use logged-in user if available
        'notes'          => $validated['notes'] ?? null,
    ]);

    alert('Success', 'Application moved successfully.', 'success');
}

}
