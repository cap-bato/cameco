<?php

namespace App\Http\Controllers\HR\ATS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Candidate;
use App\Models\Application;
use App\Models\Note;

class CandidateController extends Controller
{
    /**
     * Display a listing of candidates with filters and statistics.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $source = $request->input('source');
        $status = $request->input('status');

        // Query candidates with counts
        $candidates = Candidate::query()
            ->withCount(['applications', 'interviews', 'notes'])
            ->when($source, fn($q) => $q->where('source', $source))
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($search, fn($q) => $q->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            }))
            ->latest('applied_at')
            ->get();

        // Statistics
        $statistics = [
            'total_candidates' => Candidate::count(),
            'new_candidates' => Candidate::where('status', 'new')->count(),
            'in_process' => Candidate::where('status', 'in_process')->count(),
            'interviewed' => Candidate::where('status', 'interviewed')->count(),
            'offered' => Candidate::where('status', 'offered')->count(),
            'hired' => Candidate::where('status', 'hired')->count(),
            'rejected' => Candidate::where('status', 'rejected')->count(),
        ];

        return Inertia::render('HR/ATS/Candidates/Index', [
            'candidates' => $candidates,
            'statistics' => $statistics,
            'filters' => [
                'search' => $search,
                'source' => $source,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Display a specific candidate with applications, interviews, and notes.
     */
    public function show(Candidate $candidate): Response
    {
        $candidate->load([
            'applications.jobPosting',
            'interviews.application',
            'notes.user',
        ]);

        $stats = [
            'total_applications' => $candidate->applications->count(),
            'total_interviews' => $candidate->interviews->count(),
            'average_score' => $candidate->applications->avg('score'),
        ];

        return Inertia::render('HR/ATS/Candidates/Show', [
            'candidate' => $candidate,
            'applications' => $candidate->applications,
            'interviews' => $candidate->interviews,
            'notes' => $candidate->notes,
            'stats' => $stats,
        ]);
    }

    /**
     * Store a newly created candidate.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:candidates,email',
            'phone' => 'nullable|string|max:50',
            'source' => 'required|in:referral,job_board,walk_in,agency,internal,facebook,other',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $validated['status'] = 'new';
        $validated['applied_at'] = now();
        
        $candidate = Candidate::create($validated);

        if ($request->hasFile('resume')) {
            $file = $request->file('resume');
            $path = $file->store('resumes', 'public');
            $candidate->update(['resume_path' => "/storage/{$path}"]);
        }

        return redirect()->route('hr.ats.candidates.index')
            ->with('success', 'Candidate added successfully.');
    }

    /**
     * Update an existing candidate.
     */
    public function update(Request $request, Candidate $candidate)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => "required|email|unique:candidates,email,{$candidate->id}",
            'phone' => 'nullable|string|max:50',
            'source' => 'required|in:referral,job_board,walk_in,agency,internal,facebook,other',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $candidate->update($validated);

        if ($request->hasFile('resume')) {
            $file = $request->file('resume');
            $path = $file->store('resumes', 'public');
            $candidate->update(['resume_path' => "/storage/{$path}"]);
        }

        return redirect()->route('hr.ats.candidates.show', $candidate->id)
            ->with('success', 'Candidate updated successfully.');
    }

    /**
     * Add a note to a candidate.
     */
public function addNote(Request $request, Candidate $candidate)
{
    $validated = $request->validate([
        'note' => 'required|string',
        'is_private' => 'boolean',
    ]);

    $candidate->notes()->create([
        'note' => $validated['note'],
        'is_private' => $validated['is_private'] ?? false,
        'user_id' => 1, 
    ]);

    return back()->with('success', 'Note added successfully.');
}

public function destroy(Candidate $candidate)
{
    $candidate->delete();

    return response()->json(['message' => 'Candidate deleted successfully']);
}


}
