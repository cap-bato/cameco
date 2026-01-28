<?php

namespace App\Http\Controllers\HR\Documents;

use App\Http\Controllers\Controller;
use App\Traits\LogsSecurityAudits;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DocumentTemplateController extends Controller
{
    use LogsSecurityAudits;

    /**
     * Display a listing of document templates.
     */
    public function index(Request $request)
    {
        // Mock data for testing
        $templates = collect([
            [
                'id' => 1,
                'name' => 'Certificate of Employment',
                'category' => 'employment',
                'description' => 'Standard COE template with employment details',
                'version' => '1.2',
                'variables' => ['employee_name', 'position', 'date_hired', 'current_date'],
                'status' => 'active',
                'created_by' => 'HR Admin',
                'created_at' => now()->subMonths(6)->format('Y-m-d H:i:s'),
                'updated_at' => now()->subWeeks(2)->format('Y-m-d H:i:s'),
            ],
            [
                'id' => 2,
                'name' => 'BIR Form 2316',
                'category' => 'government',
                'description' => 'Annual tax certificate template',
                'version' => '2.0',
                'variables' => ['employee_name', 'tin', 'tax_year', 'gross_compensation', 'tax_withheld'],
                'status' => 'active',
                'created_by' => 'Payroll Manager',
                'created_at' => now()->subMonths(12)->format('Y-m-d H:i:s'),
                'updated_at' => now()->subMonths(1)->format('Y-m-d H:i:s'),
            ],
            [
                'id' => 3,
                'name' => 'Monthly Payslip',
                'category' => 'payroll',
                'description' => 'Monthly compensation statement',
                'version' => '1.5',
                'variables' => ['employee_name', 'employee_number', 'pay_period', 'basic_salary', 'deductions', 'net_pay'],
                'status' => 'active',
                'created_by' => 'Payroll Manager',
                'created_at' => now()->subMonths(8)->format('Y-m-d H:i:s'),
                'updated_at' => now()->subDays(15)->format('Y-m-d H:i:s'),
            ],
            [
                'id' => 4,
                'name' => 'SSS E-1 Form',
                'category' => 'government',
                'description' => 'SSS employment report template',
                'version' => '1.0',
                'variables' => ['employee_name', 'ss_number', 'date_hired', 'salary'],
                'status' => 'active',
                'created_by' => 'HR Staff',
                'created_at' => now()->subMonths(4)->format('Y-m-d H:i:s'),
                'updated_at' => now()->subMonths(4)->format('Y-m-d H:i:s'),
            ],
            [
                'id' => 5,
                'name' => 'Employment Contract',
                'category' => 'contracts',
                'description' => 'Standard employment contract template',
                'version' => '3.1',
                'variables' => ['employee_name', 'position', 'start_date', 'salary', 'department', 'reporting_to'],
                'status' => 'active',
                'created_by' => 'HR Manager',
                'created_at' => now()->subYear()->format('Y-m-d H:i:s'),
                'updated_at' => now()->subMonths(3)->format('Y-m-d H:i:s'),
            ],
            [
                'id' => 6,
                'name' => 'Clearance Form',
                'category' => 'separation',
                'description' => 'Exit clearance checklist',
                'version' => '1.1',
                'variables' => ['employee_name', 'separation_date', 'department', 'position'],
                'status' => 'active',
                'created_by' => 'HR Manager',
                'created_at' => now()->subMonths(9)->format('Y-m-d H:i:s'),
                'updated_at' => now()->subMonths(5)->format('Y-m-d H:i:s'),
            ],
            [
                'id' => 7,
                'name' => 'Inactive Notice Memo',
                'category' => 'communication',
                'description' => 'Memo template for inactivity notices',
                'version' => '1.0',
                'variables' => ['employee_name', 'absent_days', 'last_work_date', 'contact_deadline'],
                'status' => 'archived',
                'created_by' => 'HR Admin',
                'created_at' => now()->subMonths(18)->format('Y-m-d H:i:s'),
                'updated_at' => now()->subMonths(12)->format('Y-m-d H:i:s'),
            ],
        ]);

        // Apply filters
        if ($request->filled('status')) {
            $templates = $templates->where('status', $request->status);
        }

        if ($request->filled('category')) {
            $templates = $templates->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $templates = $templates->filter(function ($template) use ($search) {
                return str_contains(strtolower($template['name']), $search) ||
                       str_contains(strtolower($template['description']), $search);
            });
        }

        // Log security audit
        $this->logAudit(
            'document_templates.view',
            'info',
            ['filters' => $request->only(['status', 'category', 'search'])]
        );

        return Inertia::render('HR/Documents/Templates/Index', [
            'templates' => $templates->values(),
            'filters' => $request->only(['status', 'category', 'search']),
            'categories' => [
                'employment' => 'Employment',
                'government' => 'Government',
                'payroll' => 'Payroll',
                'contracts' => 'Contracts',
                'separation' => 'Separation',
                'communication' => 'Communication',
                'benefits' => 'Benefits',
                'performance' => 'Performance',
            ],
        ]);
    }

    /**
     * Show the form for creating a new template.
     */
    public function create()
    {
        $availableVariables = [
            'employee_name' => 'Employee Full Name',
            'employee_number' => 'Employee Number',
            'position' => 'Job Position',
            'department' => 'Department',
            'date_hired' => 'Date Hired',
            'separation_date' => 'Separation Date',
            'salary' => 'Basic Salary',
            'gross_compensation' => 'Gross Compensation',
            'net_pay' => 'Net Pay',
            'tin' => 'TIN',
            'ss_number' => 'SSS Number',
            'philhealth_number' => 'PhilHealth Number',
            'pagibig_number' => 'Pag-IBIG Number',
            'current_date' => 'Current Date',
            'tax_year' => 'Tax Year',
            'pay_period' => 'Pay Period',
        ];

        $this->logAudit(
            'document_templates.create_form',
            'info',
            []
        );

        return Inertia::render('HR/Documents/Templates/CreateEdit', [
            'availableVariables' => $availableVariables,
            'categories' => [
                'employment' => 'Employment',
                'government' => 'Government',
                'payroll' => 'Payroll',
                'contracts' => 'Contracts',
                'separation' => 'Separation',
                'communication' => 'Communication',
                'benefits' => 'Benefits',
                'performance' => 'Performance',
            ],
        ]);
    }

    /**
     * Store a newly created template in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string',
            'description' => 'nullable|string|max:500',
            'file' => 'required|file|mimes:docx|max:10240', // 10MB max
            'variables' => 'required|array',
            'variables.*' => 'required|string',
        ]);

        // In production, save file and create database record
        // For now, just log the action

        $this->logAudit(
            'document_templates.create',
            'info',
            [
                'template_name' => $validated['name'],
                'category' => $validated['category'],
                'variables' => $validated['variables'],
            ]
        );

        return redirect()->route('hr.documents.templates.index')
            ->with('success', 'Template created successfully');
    }

    /**
     * Show the form for editing the specified template.
     */
    public function edit($id)
    {
        // Mock template data
        $template = [
            'id' => $id,
            'name' => 'Certificate of Employment',
            'category' => 'employment',
            'description' => 'Standard COE template with employment details',
            'version' => '1.2',
            'variables' => ['employee_name', 'position', 'date_hired', 'current_date'],
            'status' => 'active',
        ];

        $availableVariables = [
            'employee_name' => 'Employee Full Name',
            'employee_number' => 'Employee Number',
            'position' => 'Job Position',
            'department' => 'Department',
            'date_hired' => 'Date Hired',
            'salary' => 'Basic Salary',
            'current_date' => 'Current Date',
        ];

        $this->logAudit(
            'document_templates.edit_form',
            'info',
            ['template_id' => $id]
        );

        return Inertia::render('HR/Documents/Templates/CreateEdit', [
            'template' => $template,
            'availableVariables' => $availableVariables,
            'categories' => [
                'employment' => 'Employment',
                'government' => 'Government',
                'payroll' => 'Payroll',
                'contracts' => 'Contracts',
            ],
        ]);
    }

    /**
     * Update the specified template in storage.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string',
            'description' => 'nullable|string|max:500',
            'file' => 'nullable|file|mimes:docx|max:10240',
            'variables' => 'required|array',
            'variables.*' => 'required|string',
        ]);

        // In production, update file and database record, increment version

        $this->logAudit(
            'document_templates.update',
            'info',
            [
                'template_id' => $id,
                'template_name' => $validated['name'],
                'changes' => $request->only(['name', 'category', 'description']),
            ]
        );

        return redirect()->route('hr.documents.templates.index')
            ->with('success', 'Template updated successfully (version incremented)');
    }

    /**
     * Generate a document from template by replacing variables.
     */
    public function generate(Request $request, $id)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer',
            'variables' => 'required|array',
            'format' => 'required|in:pdf,docx',
        ]);

        // In production:
        // 1. Fetch template file
        // 2. Replace variables with actual values
        // 3. Generate PDF or DOCX
        // 4. Return download response

        // Mock response
        $generatedDocument = [
            'filename' => 'COE_Juan_dela_Cruz_' . now()->format('Ymd') . '.' . $validated['format'],
            'size' => '125 KB',
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];

        $this->logAudit(
            'document_templates.generate',
            'info',
            [
                'template_id' => $id,
                'employee_id' => $validated['employee_id'],
                'format' => $validated['format'],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Document generated successfully',
            'document' => $generatedDocument,
        ]);
    }
}
