<?php

namespace App\Http\Controllers\HR\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StorePositionRequest;
use App\Http\Requests\HR\UpdatePositionRequest;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PositionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Position::class);
        $positions = Position::query()
            ->withCount('employees')
            ->orderBy('title')
            ->get()
            ->map(function (Position $pos) {
                return [
                    'id' => $pos->id,
                    'title' => $pos->title,
                    'code' => (string) ($pos->code ?? ''),
                    'description' => $pos->description,
                    'level' => (string) ($pos->level ?? ''),
                    'department_id' => $pos->department_id,
                    'reports_to' => $pos->reports_to,
                    // map DB fields to UI expectations
                    'salary_min' => $pos->min_salary,
                    'salary_max' => $pos->max_salary,
                    'is_active' => (bool) $pos->is_active,
                    'employee_count' => $pos->employees_count ?? 0,
                ];
            });

        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(function ($dept) {
                return [
                    'id' => $dept->id,
                    'name' => $dept->name,
                    'code' => (string) ($dept->code ?? ''),
                ];
            });

        $stats = [
            'total' => Position::count(),
            'active' => Position::where('is_active', true)->count(),
            'inactive' => Position::where('is_active', false)->count(),
        ];

        return Inertia::render('HR/Positions/Index', [
            'positions' => $positions,
            'departments' => $departments,
            'statistics' => $stats,
        ]);
    }

    /**
     * Show the form for creating a new position.
     */
    public function create(): Response
    {
        $this->authorize('create', Position::class);

        $departments = Department::where('is_active', true)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        $positions = Position::where('is_active', true)
            ->select('id', 'title', 'code')
            ->orderBy('title')
            ->get();

        return Inertia::render('HR/Positions/Create', [
            'departments' => $departments,
            'positions' => $positions,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePositionRequest $request): RedirectResponse
    {
        $this->authorize('create', Position::class);
        $data = $request->validated();

        // Map UI field names to DB columns
        $mapped = [
            'title' => $data['title'],
            'code' => $data['code'] ?? null,
            'description' => $data['description'] ?? null,
            'level' => $data['level'],
            'department_id' => $data['department_id'],
            'reports_to' => $data['reports_to'] ?? null,
            'min_salary' => $data['salary_min'] ?? null,
            'max_salary' => $data['salary_max'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];

        Position::create($mapped);

        return back()->with('success', 'Position created successfully.');
    }

    /**
     * Show the form for editing the specified position.
     */
    public function edit(Position $position): Response
    {
        $this->authorize('update', $position);

        $departments = Department::where('is_active', true)
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        $positions = Position::where('is_active', true)
            ->where('id', '!=', $position->id)
            ->select('id', 'title', 'code')
            ->orderBy('title')
            ->get();

        return Inertia::render('HR/Positions/Edit', [
            'position' => [
                'id' => $position->id,
                'title' => $position->title,
                'code' => (string) ($position->code ?? ''),
                'level' => (string) ($position->level ?? ''),
                'description' => $position->description,
                'department_id' => $position->department_id,
                'reports_to' => $position->reports_to,
                'salary_min' => $position->min_salary,
                'salary_max' => $position->max_salary,
                'is_active' => (bool) $position->is_active,
            ],
            'departments' => $departments,
            'positions' => $positions,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePositionRequest $request, Position $position): RedirectResponse
    {
        $this->authorize('update', $position);
        $data = $request->validated();

        $mapped = [
            'title' => $data['title'],
            'code' => $data['code'] ?? null,
            'description' => $data['description'] ?? null,
            'level' => $data['level'] ?? $position->level,
            'department_id' => $data['department_id'],
            'reports_to' => $data['reports_to'] ?? null,
            'min_salary' => $data['salary_min'] ?? null,
            'max_salary' => $data['salary_max'] ?? null,
            'is_active' => $data['is_active'] ?? $position->is_active,
        ];

        $position->update($mapped);

        return back()->with('success', 'Position updated successfully.');
    }

    /**
     * Remove the specified resource from storage (soft delete / archive).
     */
    public function destroy(Position $position): RedirectResponse
    {
        $this->authorize('delete', $position);
        // Optional: prevent delete if has employees assigned (if relation exists)
        if (method_exists($position, 'employees') && $position->employees()->exists()) {
            return back()->with('error', 'Cannot archive position with employees.');
        }

        $position->delete();

        return back()->with('success', 'Position archived successfully.');
    }
}
