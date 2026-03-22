<?php

namespace App\Http\Controllers\HR\ATS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\JobPosting;
use App\Models\Application;
use App\Models\Department;
use App\Services\Social\FacebookService;
use Illuminate\Support\Facades\Auth;

class JobPostingController extends Controller
{
    protected FacebookService $facebookService;

    /**
     * Inject FacebookService via constructor
     */
    public function __construct(FacebookService $facebookService)
    {
        $this->facebookService = $facebookService;
    }
    /**
     * Display a listing of job postings with real database queries.
     */
    public function index(Request $request): Response
    {
        $status = $request->input('status', 'all');
        $departmentId = $request->input('department_id');
        $search = $request->input('search');

        $jobPostings = JobPosting::with(['department', 'createdBy'])
            ->withCount('applications')
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when($departmentId, fn($q) => $q->where('department_id', $departmentId))
            ->when($search, fn($q) => $q->where('title', 'like', "%{$search}%"))
            ->latest('created_at')
            ->get();

        $statistics = [
            'total_jobs' => JobPosting::count(),
            'open_jobs' => JobPosting::where('status', 'open')->count(),
            'closed_jobs' => JobPosting::where('status', 'closed')->count(),
            'draft_jobs' => JobPosting::where('status', 'draft')->count(),
            'total_applications' => Application::count(),
        ];

        $filters = [
            'search' => $search,
            'status' => $status,
            'department_id' => $departmentId,
        ];

        $departments = Department::select('id', 'name')->get();

        return Inertia::render('HR/ATS/JobPostings/Index', [
            'job_postings' => $jobPostings->map(fn($j) => [
                'id' => $j->id,
                'title' => $j->title,
                'department_id' => $j->department_id,
                'department_name' => $j->department?->name,
                'description' => $j->description,
                'requirements' => $j->requirements,
                'status' => $j->status,
                'posted_at' => $j->posted_at?->format('Y-m-d'),
                'closed_at' => $j->closed_at?->format('Y-m-d'),
                'applications_count' => $j->applications_count,
                'created_by' => $j->created_by,
                'created_by_name' => $j->createdBy?->name,
                'created_at' => $j->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $j->updated_at->format('Y-m-d H:i:s'),
            ]),
            'statistics' => $statistics,
            'filters' => $filters,
            'departments' => $departments,
        ]);
    }

    /**
     * Show the form for creating a new job posting.
     */
    public function create(): Response
    {
        $departments = Department::select('id', 'name')->get();

        return Inertia::render('HR/ATS/JobPostings/Create', [
            'departments' => $departments,
        ]);
    }

    /**
     * Store a newly created job posting.
     */
    public function store(Request $request)
    {
        \Log::debug('JobPostingController@store: method entered', [
            'user_id' => $request->user()?->id,
            'email' => $request->user()?->email,
        ]);

        // Log just before authorization
        \Log::debug('JobPostingController@store: before authorize', [
            'user_id' => $request->user()?->id,
            'email' => $request->user()?->email,
        ]);
        $this->authorize('create', JobPosting::class);

        \Log::debug('JobPostingController@store: after authorize', [
            'user_id' => $request->user()?->id,
            'email' => $request->user()?->email,
        ]);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'department_id' => 'required|exists:departments,id',
            'description' => 'required|string',
            'requirements' => 'required|string',
            'status' => 'required|in:draft,open',
        ]);

        \Log::debug('JobPostingController@store: after validate', [
            'user_id' => $request->user()?->id,
            'email' => $request->user()?->email,
        ]);

        $jobPosting = JobPosting::create(array_merge($validated, [
            'created_by' => Auth::id(),
            'posted_at' => $validated['status'] === 'open' ? now() : null,
        ]));

        return redirect()->route('hr.ats.job-postings.index')
            ->with('success', 'Job posting created successfully.');
    }

    /**
     * Show the form for editing the specified job posting.
     */
    public function edit(JobPosting $jobPosting): Response
    {
        $departments = Department::select('id', 'name')->get();

        return Inertia::render('HR/ATS/JobPostings/Edit', [
            'jobPosting' => $jobPosting,
            'departments' => $departments,
        ]);
    }

    /**
     * Update the specified job posting.
     */
    public function update(Request $request, JobPosting $jobPosting)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'department_id' => 'required|exists:departments,id',
            'description' => 'required|string',
            'requirements' => 'required|string',
            'status' => 'required|in:draft,open,closed',
            'closed_at' => 'nullable|date',
        ]);

        $jobPosting->update($validated);

        return redirect()->route('hr.ats.job-postings.index')
            ->with('success', 'Job posting updated successfully.');
    }

    /**
     * Publish a job posting (change status to 'open').
     */
    public function publish(JobPosting $jobPosting)
    {
        $jobPosting->update([
            'status' => 'open',
            'posted_at' => now(),
        ]);

        // Auto-post to Facebook if enabled
        if ($this->facebookService->isEnabled() && $jobPosting->auto_post_facebook) {
            $this->facebookService->postJob($jobPosting, Auth::id(), true);
        }

        return redirect()->back()->with('success', 'Job posting published.');
    }

    /**
     * Close a job posting (change status to 'closed').
     */
    public function close(JobPosting $jobPosting)
    {
        $jobPosting->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Job posting closed.');
    }

    /**
     * Remove the specified job posting.
     */
    public function destroy(JobPosting $jobPosting)
    {
        $jobPosting->delete();

        return redirect()->route('hr.ats.job-postings.index')
            ->with('success', 'Job posting deleted successfully.');
    }

    /**
     * Post job to Facebook Page
     */
    public function postToFacebook(JobPosting $jobPosting)
    {
        // Check if already posted
        if ($jobPosting->isPostedToFacebook()) {
            return response()->json([
                'success' => false,
                'message' => 'This job has already been posted to Facebook.',
            ], 400);
        }

        try {
            // Post to Facebook
            $result = $this->facebookService->postJob($jobPosting, Auth::id(), false);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Job posted to Facebook successfully!',
                    'data' => $result,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to post to Facebook: ' . ($result['error'] ?? 'Unknown error'),
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview Facebook post message
     */
    public function previewFacebookPost(JobPosting $jobPosting)
    {
        try {
            // Use reflection to access protected formatJobMessage method
            $reflectionClass = new \ReflectionClass($this->facebookService);
            $method = $reflectionClass->getMethod('formatJobMessage');
            $method->setAccessible(true);

            $message = $method->invoke($this->facebookService, $jobPosting);
            $link = url("/job-postings/{$jobPosting->id}");

            return response()->json([
                'success' => true,
                'preview' => [
                    'message' => $message,
                    'link' => $link,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate preview: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Facebook post logs for a job posting
     */
    public function getFacebookLogs(JobPosting $jobPosting)
    {
        try {
            $logs = $jobPosting->facebookPostLogs()
                ->with('postedBy:id,name')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn($log) => [
                    'id' => $log->id,
                    'facebook_post_id' => $log->facebook_post_id,
                    'facebook_post_url' => $log->facebook_post_url,
                    'post_type' => $log->post_type,
                    'status' => $log->status,
                    'error_message' => $log->error_message,
                    'engagement_metrics' => $log->engagement_metrics,
                    'metrics_updated_at' => $log->metrics_updated_at?->format('Y-m-d H:i:s'),
                    'posted_by' => $log->postedBy?->name ?? 'Unknown',
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                ]);

            return response()->json([
                'success' => true,
                'logs' => $logs,
                'count' => $logs->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve logs: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh engagement metrics for a Facebook post
     */
    public function refreshEngagementMetrics(JobPosting $jobPosting)
    {
        try {
            if (!$jobPosting->isPostedToFacebook()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job has not been posted to Facebook.',
                ], 400);
            }

            $updated = $this->facebookService->updateEngagementMetrics($jobPosting);

            if ($updated) {
                $log = $jobPosting->latestFacebookPost();
                return response()->json([
                    'success' => true,
                    'message' => 'Engagement metrics updated successfully.',
                    'metrics' => $log?->engagement_metrics,
                    'metrics_updated_at' => $log?->metrics_updated_at?->format('Y-m-d H:i:s'),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update engagement metrics.',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a Facebook post
     */
    public function deleteFacebookPost(JobPosting $jobPosting)
    {
        try {
            if (!$jobPosting->isPostedToFacebook()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job has not been posted to Facebook.',
                ], 400);
            }

            $facebookPostId = $jobPosting->facebook_post_id;
            $deleted = $this->facebookService->deletePost($facebookPostId);

            if ($deleted) {
                // Update job posting to clear Facebook data
                $jobPosting->update([
                    'facebook_post_id' => null,
                    'facebook_post_url' => null,
                    'facebook_posted_at' => null,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Facebook post deleted successfully.',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete Facebook post.',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get Facebook integration status
     */
    public function getFacebookStatus(JobPosting $jobPosting)
    {
        try {
            return response()->json([
                'success' => true,
                'integration_enabled' => $this->facebookService->isEnabled(),
                'is_posted' => $jobPosting->isPostedToFacebook(),
                'post_id' => $jobPosting->facebook_post_id,
                'post_url' => $jobPosting->facebook_post_url,
                'posted_at' => $jobPosting->facebook_posted_at?->format('Y-m-d H:i:s'),
                'auto_post_enabled' => $jobPosting->auto_post_facebook,
                'latest_log' => $jobPosting->latestFacebookPostLog()?->load('postedBy')->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get status: ' . $e->getMessage(),
            ], 500);
        }
    }
}
