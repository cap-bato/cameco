<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\JobPosting;
use App\Models\Candidate;
use App\Models\Application;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class JobPostingsController extends Controller
{
    /**
     * Display a listing of open job postings (public access).
     */
    public function index(Request $request): Response
    {
        $department = $request->input('department');
        $search = $request->input('search');
        
        $jobPostings = JobPosting::with('department')
            ->where('status', 'open')
            ->when($department, fn($q) => $q->where('department_id', $department))
            ->when($search, fn($q) => $q->where('title', 'like', "%{$search}%"))
            ->orderBy('posted_at', 'desc')
            ->get()
            ->map(fn($job) => [
                'id' => $job->id,
                'title' => $job->title,
                'department_name' => $job->department?->name,
                'department_id' => $job->department_id,
                'description' => $job->description,
                'requirements' => $job->requirements,
                'posted_at' => $job->posted_at?->format('F d, Y'),
                'applications_count' => $job->applications()->count(),
            ]);
        
        $departments = JobPosting::where('status', 'open')
            ->with('department:id,name')
            ->get()
            ->pluck('department')
            ->unique('id')
            ->filter()
            ->values();
        
        return Inertia::render('Public/JobPostings/Index', [
            'jobPostings' => $jobPostings,
            'departments' => $departments,
            'filters' => [
                'department' => $department,
                'search' => $search,
            ],
        ]);
    }
    
    /**
     * Display the specified job posting (public access).
     */
    public function show(int $id): Response
    {
        $jobPosting = JobPosting::with('department')
            ->where('status', 'open')
            ->findOrFail($id);
        
        return Inertia::render('Public/JobPostings/Show', [
            'jobPosting' => [
                'id' => $jobPosting->id,
                'title' => $jobPosting->title,
                'department_name' => $jobPosting->department?->name,
                'description' => $jobPosting->description,
                'requirements' => $jobPosting->requirements,
                'posted_at' => $jobPosting->posted_at?->format('F d, Y'),
            ],
        ]);
    }
    
    /**
     * Store a new application submission (public access).
     */
    public function apply(Request $request, int $jobId)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'resume' => 'required|file|mimes:pdf,doc,docx|max:5120', // 5MB max
            'cover_letter' => 'nullable|string|max:2000',
        ]);
        
        // Verify job exists and is open
        $jobPosting = JobPosting::where('status', 'open')->findOrFail($jobId);
        
        DB::beginTransaction();
        
        try {
            // Check if candidate already exists by email
            $candidate = Candidate::whereHas('profile', function($q) use ($validated) {
                $q->where('email', $validated['email']);
            })->first();
            
            if (!$candidate) {
                // Create profile
                $profile = \App\Models\Profile::create([
                    'first_name' => $validated['first_name'],
                    'last_name' => $validated['last_name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                ]);
                
                // Create candidate
                $candidate = Candidate::create([
                    'profile_id' => $profile->id,
                    'source' => 'job_board',
                    'status' => 'new',
                    'applied_at' => now(),
                ]);
            }
            
            // Check if candidate already applied to this job
            $existingApplication = Application::where('candidate_id', $candidate->id)
                ->where('job_posting_id', $jobPosting->id)
                ->first();
            
            if ($existingApplication) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'You have already applied to this position.',
                ], 422);
            }
            
            // Upload resume
            $resumePath = null;
            if ($request->hasFile('resume')) {
                $resumePath = $request->file('resume')->store('resumes', 'public');
            }
            
            // Create application
            $application = Application::create([
                'candidate_id' => $candidate->id,
                'job_posting_id' => $jobPosting->id,
                'status' => 'submitted',
                'resume_path' => $resumePath,
                'cover_letter' => $validated['cover_letter'],
                'applied_at' => now(),
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Your application has been submitted successfully!',
                'application_id' => $application->id,
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while submitting your application. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
