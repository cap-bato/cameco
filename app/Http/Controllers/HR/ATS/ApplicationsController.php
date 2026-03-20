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
        $application = Application::with(['candidate', 'jobPosting'])
            ->findOrFail($applicationId);

        $resumeUrl = null;
        if ($application->resume_path && \Storage::disk('public')->exists($application->resume_path)) {
            $resumeUrl = \Storage::disk('public')->url($application->resume_path);
        }

        // Compose candidate name/email/phone from candidate fields
        $candidate = $application->candidate;
        $candidateName = trim(($candidate->first_name ?? '') . ' ' . ($candidate->last_name ?? '')) ?: 'Unknown Candidate';
        $candidateEmail = $candidate->email ?? 'N/A';
        $candidatePhone = $candidate->phone ?? 'N/A';

        return \Inertia\Inertia::render('HR/ATS/Applications/Show', [
            'application' => [
                'id' => $application->id,
                'status' => $application->status,
                'applied_at' => $application->applied_at?->format('F d, Y H:i A'),
                'cover_letter' => $application->cover_letter,
                'resume_path' => $application->resume_path,
                'resume_url' => $resumeUrl,
                'candidate_name' => $candidateName,
                'candidate_email' => $candidateEmail,
                'candidate_phone' => $candidatePhone,
                'job_title' => $application->jobPosting->title ?? 'N/A',
            ],
            'candidate' => [
                'id' => $candidate->id,
                'first_name' => $candidate->first_name,
                'last_name' => $candidate->last_name,
                'full_name' => trim(($candidate->first_name ?? '') . ' ' . ($candidate->last_name ?? '')),
                'email' => $candidate->email,
                'phone' => $candidate->phone,
                'address' => $candidate->address ?? 'N/A',
                'city' => $candidate->city ?? 'N/A',
                'state' => $candidate->state ?? 'N/A',
                'postal_code' => $candidate->postal_code ?? 'N/A',
                'country' => $candidate->country ?? 'N/A',
                'date_of_birth' => $candidate->birthdate ? date('F d, Y', strtotime($candidate->birthdate)) : null,
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
