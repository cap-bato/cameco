<?php

namespace App\Http\Controllers\HR\ATS;

use App\Http\Controllers\Controller;
use App\Models\Interview;
use App\Models\Application;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
class InterviewController extends Controller
{
public function index(Request $request)
{
    // Optional filters
    $search = $request->input('search');
    $status = $request->input('status');
    $interviewerId = $request->input('interviewer_id');
    $date = $request->input('date');

    // Query interviews with candidate and application info
    $query = Interview::with([
        'application.candidate'
    ]);

    if ($status && $status !== 'all') {
        $query->where('status', $status);
    }

    if ($interviewerId && $interviewerId !== 'all') {
        $query->where('interviewer_id', $interviewerId);
    }

    if ($date) {
        $query->whereDate('scheduled_date', $date);
    }

    if ($search) {
        $query->whereHas('application.candidate', function ($q) use ($search) {
            $q->where('first_name', 'ilike', "%{$search}%")
              ->orWhere('last_name', 'ilike', "%{$search}%");
        });
    }

    $interviews = $query->get()->map(function ($interview) {
        return [
            'id' => $interview->id,
            'candidate_name' => $interview->application->candidate->first_name . ' ' . $interview->application->candidate->last_name,
            'interviewer_name' => $interview->interviewer_name,
            'job_title' => $interview->job_title,
            'status' => $interview->status,
            'scheduled_date' => $interview->scheduled_date->format('Y-m-d'),
            'scheduled_time' => Carbon::parse($interview->scheduled_time)->format('H:i'),
            'duration_minutes' => $interview->duration_minutes, // ✅ FIX ADDED HERE
            'location_type' => $interview->location_type,
            'score' => $interview->score,
            'recommendation' => $interview->recommendation,
            'feedback' => $interview->feedback,
        ];
    });

    // Statistics
    $statistics = [
        'total_interviews' => Interview::count(),
        'scheduled' => Interview::where('status', 'scheduled')->count(),
        'completed' => Interview::where('status', 'completed')->count(),
        'cancelled' => Interview::where('status', 'canceled')->count(),
        'no_show' => Interview::where('status', 'no_show')->count(),
        'upcoming_this_week' => Interview::whereBetween('scheduled_date', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ])->count(),
    ];

    return Inertia::render('HR/ATS/Interviews/Index', [
        'interviews' => $interviews,
        'statistics' => $statistics,
        'filters' => [
            'search' => $search,
            'status' => $status,
            'interviewer_id' => $interviewerId,
            'date' => $date,
        ],
    ]);
}


public function store(Request $request)
{
    $validated = $request->validate([
        'application_id' => 'required|exists:applications,id',
        'candidate_id' => 'nullable|exists:candidates,id',
        'job_title' => 'required|string|max:255',
        'scheduled_date' => 'required|date',
        'scheduled_time' => 'required',
        'duration_minutes' => 'nullable|integer|min:15|max:480',
        'location_type' => 'required|in:office,virtual',
        'interviewer_name' => 'required|string|max:255',
    ]);

    $interview = Interview::create([
        'application_id' => $validated['application_id'],
        'candidate_id' => $validated['candidate_id'] ?? null,
        'job_title' => $validated['job_title'],
        'scheduled_date' => $validated['scheduled_date'],
        'scheduled_time' => $validated['scheduled_time'],
        'duration_minutes' => $validated['duration_minutes'] ?? 30,
        'location_type' => $validated['location_type'],
        'status' => 'scheduled',
        'interviewer_name' => $validated['interviewer_name'],
    ]);

    return response()->json([
        'success' => true,
        'interview' => $interview,
    ]);
}

public function update(Request $request, Interview $interview)
{
    $validated = $request->validate([
        'scheduled_date' => 'nullable|date|after_or_equal:today',
        'scheduled_time' => 'nullable',
        'duration_minutes' => 'nullable|integer|min:1',
        'location_type' => 'nullable|string',
        'feedback' => 'nullable|string',
        'score' => 'nullable|numeric|min:0|max:100',
        'recommendation' => 'nullable|in:hire,pending,reject',
    ]);

    $interview->update($validated);

    return response()->json([
        'success' => true,
        'interview' => $interview,
    ]);
}

public function cancel(Request $request, Interview $interview)
{
    try {
        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:255',
        ]);

        $interview->update([
            'status' => 'cancelled',
            'cancellation_reason' => $validated['cancellation_reason'],
            'cancelled_at' => now(),
        ]);

            return response()->json([
                'success' => true,
                'message' => 'Interview cancelled successfully.',
                'interview' => [
                    'id' => $interview->id,
                    'status' => $interview->status,
                    'cancellation_reason' => $interview->cancellation_reason,
                    'cancelled_at' => $interview->cancelled_at,
                ],
            ]);
    } catch (\Throwable $e) {
        return response()->json([
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function show($id)
{
    $interview = Interview::with('application.candidate')->findOrFail($id);

    return Inertia::render('HR/ATS/Interviews/Show', [
        'interview' => [
            'id' => $interview->id,
            'candidate_name' => $interview->application->candidate->first_name . ' ' . $interview->application->candidate->last_name,
            'interviewer_name' => $interview->interviewer_name,
            'job_title' => $interview->job_title,
            'status' => $interview->status,
            'scheduled_date' => $interview->scheduled_date->format('Y-m-d'),
            'scheduled_time' => \Carbon\Carbon::parse($interview->scheduled_time)->format('H:i'),
            'duration_minutes' => $interview->duration_minutes,   // ✅ ADD THIS
            'location_type' => $interview->location_type,
            'score' => $interview->score,
            'recommendation' => $interview->recommendation,
            'feedback' => $interview->feedback,
        ]
    ]);
}

public function updateFeedback(Request $request, Interview $interview)
{
    $validated = $request->validate([
        'feedback' => 'nullable|string',
    ]);

    $interview->update([
        'feedback' => $validated['feedback']
    ]);

    return back()->with('success', 'Feedback saved!');
}
}
