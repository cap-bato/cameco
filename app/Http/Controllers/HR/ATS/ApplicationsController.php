<?php

namespace App\Http\Controllers\HR\ATS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\JobPosting;
use App\Models\Application;
use App\Models\Candidate;
use Illuminate\Support\Facades\Storage;

class ApplicationsController extends Controller
{
    /**
     * Display a listing of applications for a specific job posting.
     */
    public function index(Request $request, int $jobId): Response
    {
        $jobPosting = JobPosting::findOrFail($jobId);
        
        $status = $request->input('status');
        $sortBy = $request->input('sort_by', 'applied_at');
        $sortOrder = $request->input('sort_order', 'desc');
        
        $applications = Application::where('job_posting_id', $jobId)
            ->with(['candidate.profile'])
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderBy($sortBy, $sortOrder)
            ->paginate(15)
            ->through(fn($app) => [
                'id' => $app->id,
                'candidate_id' => $app->candidate_id,
                'candidate_name' => $app->candidate->profile->full_name ?? 'N/A',
                'candidate_email' => $app->candidate->profile->email,
                'candidate_phone' => $app->candidate->profile->phone,
                'status' => $app->status,
                'applied_at' => $app->applied_at?->format('F d, Y H:i A'),
                'resume_path' => $app->resume_path,
                'has_resume' => !empty($app->resume_path),
            ]);
        
        return Inertia::render('HR/ATS/Applications/Index', [
            'jobPosting' => [
                'id' => $jobPosting->id,
                'title' => $jobPosting->title,
            ],
            'applications' => $applications,
            'filters' => [
                'status' => $status,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
            'applicationStatuses' => [
                'submitted' => 'Submitted',
                'reviewed' => 'Reviewed',
                'shortlisted' => 'Shortlisted',
                'rejected' => 'Rejected',
                'hired' => 'Hired',
            ],
        ]);
    }
    
    /**
     * Display detailed information about a specific application and candidate.
     */
    public function show(int $applicationId): Response
    {
        $application = Application::with(['candidate.profile', 'jobPosting'])
            ->findOrFail($applicationId);
        
        $resumeUrl = null;
        if ($application->resume_path && Storage::disk('public')->exists($application->resume_path)) {
            $resumeUrl = Storage::disk('public')->url($application->resume_path);
        }
        
        return Inertia::render('HR/ATS/Applications/Show', [
            'application' => [
                'id' => $application->id,
                'status' => $application->status,
                'applied_at' => $application->applied_at?->format('F d, Y H:i A'),
                'cover_letter' => $application->cover_letter,
                'resume_path' => $application->resume_path,
                'resume_url' => $resumeUrl,
            ],
            'candidate' => [
                'id' => $application->candidate->id,
                'first_name' => $application->candidate->profile->first_name,
                'last_name' => $application->candidate->profile->last_name,
                'full_name' => $application->candidate->profile->full_name,
                'email' => $application->candidate->profile->email,
                'phone' => $application->candidate->profile->phone,
                'address' => $application->candidate->profile->address ?? 'N/A',
                'city' => $application->candidate->profile->city ?? 'N/A',
                'state' => $application->candidate->profile->state ?? 'N/A',
                'postal_code' => $application->candidate->profile->postal_code ?? 'N/A',
                'country' => $application->candidate->profile->country ?? 'N/A',
                'date_of_birth' => $application->candidate->profile->date_of_birth?->format('F d, Y'),
            ],
            'jobPosting' => [
                'id' => $application->jobPosting->id,
                'title' => $application->jobPosting->title,
            ],
            'applicationStatuses' => [
                'submitted' => 'Submitted',
                'reviewed' => 'Reviewed',
                'shortlisted' => 'Shortlisted',
                'rejected' => 'Rejected',
                'hired' => 'Hired',
            ],
        ]);
    }
    
    /**
     * Update application status.
     */
    public function updateStatus(Request $request, int $applicationId)
    {
        $validated = $request->validate([
            'status' => 'required|in:submitted,reviewed,shortlisted,rejected,hired',
            'notes' => 'nullable|string|max:1000',
        ]);
        
        $application = Application::findOrFail($applicationId);
        $application->update([
            'status' => $validated['status'],
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Application status updated successfully.',
        ]);
    }
    
    /**
     * Download resume file.
     */
    public function downloadResume(int $applicationId)
    {
        $application = Application::findOrFail($applicationId);
        
        if (!$application->resume_path || !Storage::disk('public')->exists($application->resume_path)) {
            abort(404, 'Resume file not found.');
        }
        
        return Storage::disk('public')->download(
            $application->resume_path,
            "Resume-{$application->candidate->profile->full_name}.pdf"
        );
    }
}
