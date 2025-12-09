<?php

namespace App\Http\Controllers\HR\ATS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\JobPosting;
use App\Models\Application;
use App\Models\Department;

class JobPostingController extends Controller
{
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
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'department_id' => 'required|exists:departments,id',
            'description' => 'required|string',
            'requirements' => 'required|string',
            'status' => 'required|in:draft,open',
        ]);

        $jobPosting = JobPosting::create(array_merge($validated, [
            'created_by' => '1',
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
}
