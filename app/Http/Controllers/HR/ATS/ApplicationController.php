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
use Illuminate\Support\Facades\Auth;

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
            ->paginate(20)
            ->through(function ($app) {
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
        'application' => $application,
        'candidate' => $application->candidate,
        'job' => $application->jobPosting,
        'interviews' => $application->interviews,
        'status_history' => $application->statusHistory,
        'notes' => $application->notes ?? [],
        'can_schedule_interview' => $canScheduleInterview,
        'can_generate_offer' => $canGenerateOffer,
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
            'changed_by'     => Auth::id(),
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
            'changed_by'     => Auth::id(),
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
            'changed_by'     => Auth::id(),
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
            'location_type'  => 'required|in:office,video_call,phone',
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
        $validated = $request->validate([
            'salary'     => 'required|numeric|min:0',
            'start_date' => 'required|date|after_or_equal:today',
            'notes'      => 'nullable|string|max:2000',
        ]);

        Offer::create([
            'application_id' => $application->id,
            'title'          => $application->jobPosting->title ?? 'Job Offer',
            'salary'         => $validated['salary'],
            'start_date'     => $validated['start_date'],
            'notes'          => $validated['notes'] ?? null,
            'created_by'     => Auth::id(),
        ]);

        $application->status = 'offered';
        $application->save();

        ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'status'         => 'offered',
            'changed_by'     => Auth::id(),
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
            'changed_by'     => Auth::id(),
            'notes'          => $validated['notes'] ?? null,
        ]);

        return response()->json(['success' => true, 'status' => $validated['status']]);
    }

    /**
     * Add a note to an application.
     */
    public function addNote(Request $request, Application $application)
    {
        $validated = $request->validate([
            'note'       => 'required|string|max:5000',
            'is_private' => 'boolean',
        ]);

        $application->notes()->create([
            'note'       => $validated['note'],
            'is_private' => $validated['is_private'] ?? false,
            'user_id'    => Auth::id(),
        ]);

        return back()->with('success', 'Note added.');
    }

}
