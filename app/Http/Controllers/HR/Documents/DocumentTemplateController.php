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
        // Fetch templates from database
        $templates = \App\Models\DocumentTemplate::where('is_active', true)
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('category'), fn($q) => $q->where('template_type', $request->category))
            ->when($request->filled('search'), fn($q) => 
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%')
            )
            ->get()
            ->map(function($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'category' => $template->template_type,
                    'description' => $template->description,
                    'version' => 'v' . $template->version,
                    'variables' => $template->variables ?? [],
                    'status' => $template->status,
                    'created_by' => $template->createdBy->name ?? 'System',
                    'created_at' => $template->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $template->updated_at->format('Y-m-d H:i:s'),
                ];
            });

        // Get employees list for document generation
        $employees = \App\Models\Employee::with('profile:id,first_name,last_name')
            ->select('id', 'employee_number', 'profile_id')
            ->orderBy('employee_number')
            ->get()
            ->map(fn($emp) => [
                'id' => $emp->id,
                'employee_number' => $emp->employee_number,
                'first_name' => $emp->profile->first_name ?? '',
                'last_name' => $emp->profile->last_name ?? '',
                'department' => 'N/A',
            ]);

        // Log security audit
        $this->logAudit(
            'document_templates.view',
            'info',
            ['filters' => $request->only(['status', 'category', 'search'])]
        );

        return Inertia::render('HR/Documents/Templates/Index', [
            'templates' => $templates,
            'employees' => $employees,
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

    /**
     * API endpoint to list templates as JSON.
     * Used by frontend for AJAX requests to fetch template list.
     */
    public function apiList(Request $request)
    {
        // Get templates list from database
        $templates = \App\Models\DocumentTemplate::where('is_active', true)
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('category'), fn($q) => $q->where('template_type', $request->category))
            ->when($request->filled('search'), fn($q) => 
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%')
            )
            ->get()
            ->map(function($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'category' => $template->template_type,
                    'description' => $template->description,
                    'version' => 'v' . $template->version,
                    'variables' => $template->variables ?? [],
                    'status' => $template->status,
                    'created_by' => $template->createdBy->name ?? 'System',
                    'created_at' => $template->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $template->updated_at->format('Y-m-d H:i:s'),
                ];
            });

        $this->logAudit(
            'document_templates.api_list',
            'info',
            ['filters' => $request->only(['status', 'category', 'search'])]
        );

        return response()->json([
            'success' => true,
            'data' => $templates->values(),
            'meta' => [
                'total_templates' => $templates->count(),
                'active_templates' => $templates->where('status', 'approved')->count(),
            ]
        ]);
    }

    /**
     * API endpoint for generating documents from templates.
     * Used by frontend for AJAX requests with blob response for download.
     */
    public function apiGenerate(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'required|integer',
            'employee_id' => 'required|integer',
            'variables' => 'required|array',
            'output_format' => 'required|in:pdf,docx',
            'send_email' => 'boolean',
            'email_subject' => 'nullable|string|max:255',
            'email_message' => 'nullable|string|max:1000',
        ]);

        // Get employee data for variable substitution
        $employee = \App\Models\Employee::with('profile')
            ->find($validated['employee_id']);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found',
            ], 404);
        }

        // Get template data
        $template = $this->getTemplateById($validated['template_id']);
        
        if (!$template) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
            ], 404);
        }

        // Build substitution variables
        $substitutions = [
            'employee_name' => $employee->profile->first_name . ' ' . $employee->profile->last_name,
            'employee_number' => $employee->employee_number,
            'position' => $employee->position ?? 'N/A',
            'department' => $employee->department ?? 'N/A',
            'date_hired' => $employee->date_hired ?? 'N/A',
            'current_date' => now()->format('Y-m-d'),
        ];

        // Merge with user-provided variables
        $substitutions = array_merge($substitutions, $validated['variables']);

        // Generate document content (mock implementation)
        $documentContent = $this->generateDocumentContent($template, $substitutions);

        // Create filename
        $filename = 'document_' . $employee->employee_number . '_' . now()->format('Ymd_His');
        $filename .= $validated['output_format'] === 'pdf' ? '.pdf' : '.docx';

        // Log audit
        $this->logAudit(
            'document_templates.api_generate',
            'info',
            [
                'template_id' => $validated['template_id'],
                'employee_id' => $validated['employee_id'],
                'output_format' => $validated['output_format'],
                'send_email' => $validated['send_email'] ?? false,
            ]
        );

        // For now, return mock blob data (in production, generate actual PDF/DOCX)
        $mockContent = "Mock {$validated['output_format']} document content for {$employee->profile->first_name}";

        if ($validated['send_email'] ?? false) {
            // In production: Send email with document attachment
            // For now: Just return success JSON
            return response()->json([
                'success' => true,
                'message' => 'Document generated and sent via email successfully',
                'filename' => $filename,
            ]);
        }

        // Return blob response for download
        // Generate actual PDF or DOCX content
        if ($validated['output_format'] === 'pdf') {
            $fileContent = $this->generatePdfContent($template['name'], $documentContent);
            $contentType = 'application/pdf';
        } else {
            $fileContent = $this->generateDocxContent($template['name'], $documentContent);
            $contentType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        }

        return response($fileContent)
            ->header('Content-Type', $contentType)
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Content-Length', strlen($fileContent));
    }

    /**
     * Get template by ID from database
     */
    private function getTemplateById($id)
    {
        $template = \App\Models\DocumentTemplate::find($id);
        
        if (!$template) {
            return null;
        }

        // Load template file content if it exists
        $content = '';
        if ($template->file_path && \Storage::exists($template->file_path)) {
            $content = \Storage::get($template->file_path);
        }

        return [
            'id' => $template->id,
            'name' => $template->name,
            'content' => $content ?: 'Template for {{employee_name}}',
            'variables' => $template->variables ?? [],
        ];
    }

    /**
     * Mock helper: Generate document content with variable substitution
     */
    private function generateDocumentContent($template, $substitutions)
    {
        $content = $template['content'];

        foreach ($substitutions as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }

        return $content;
    }

    /**
     * Generate PDF content as binary data.
     * This is a minimal PDF generator - in production, use a proper library like MPDF or DOMPDF.
     */
    private function generatePdfContent($title, $content)
    {
        // Minimal valid PDF structure
        $pdf = "%PDF-1.4\n";
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /MediaBox [0 0 612 792] /Contents 5 0 R >>\nendobj\n";
        $pdf .= "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $pdf .= "5 0 obj\n<< /Length " . (strlen("BT\n/F1 12 Tf\n100 700 Td\n(" . str_replace(['(', ')'], ['\\(', '\\)'], $title) . ") Tj\n100 680 Td\n(" . str_replace(['(', ')'], ['\\(', '\\)'], substr($content, 0, 200)) . ") Tj\nET\n")) . " >>\nstream\n";
        $pdf .= "BT\n/F1 12 Tf\n100 700 Td\n(" . str_replace(['(', ')'], ['\\(', '\\)'], $title) . ") Tj\n100 680 Td\n(" . str_replace(['(', ')'], ['\\(', '\\)'], substr($content, 0, 200)) . ") Tj\nET\n";
        $pdf .= "endstream\nendobj\n";
        $pdf .= "xref\n0 6\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000247 00000 n \n0000000333 00000 n \n";
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . (strlen($pdf) + 100) . "\n%%EOF\n";

        return $pdf;
    }

    /**
     * Generate DOCX content as binary data.
     * This creates a minimal valid DOCX (which is a ZIP file with XML).
     */
    private function generateDocxContent($title, $content)
    {
        // Create a temporary directory for DOCX files
        $tempDir = storage_path('app/temp-docx');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipPath = $tempDir . '/' . uniqid('doc_') . '.docx';
        $extractPath = $tempDir . '/' . uniqid('extract_') . '/';
        mkdir($extractPath);

        // Create minimal DOCX structure
        mkdir($extractPath . 'word');
        mkdir($extractPath . '_rels');
        mkdir($extractPath . 'word/_rels');

        // Create [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>';
        file_put_contents($extractPath . '[Content_Types].xml', $contentTypes);

        // Create .rels file
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>';
        file_put_contents($extractPath . '_rels/.rels', $rels);

        // Create word/document.xml
        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:body>
<w:p><w:r><w:t>' . htmlspecialchars($title) . '</w:t></w:r></w:p>
<w:p><w:r><w:t>' . htmlspecialchars(substr($content, 0, 500)) . '</w:t></w:r></w:p>
</w:body>
</w:document>';
        file_put_contents($extractPath . 'word/document.xml', $document);

        // Create ZIP file
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $this->addFilesToZip($zip, $extractPath, '');
        $zip->close();

        // Read the zip content
        $content = file_get_contents($zipPath);

        // Cleanup
        array_map('unlink', glob($extractPath . '*'));
        rmdir($extractPath);
        unlink($zipPath);

        return $content;
    }

    /**
     * Helper to recursively add files to ZIP archive
     */
    private function addFilesToZip($zip, $dir, $base)
    {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            $path = $dir . $file;
            if (is_dir($path)) {
                $this->addFilesToZip($zip, $path . '/', $base . $file . '/');
            } else {
                $zip->addFile($path, $base . $file);
            }
        }
    }
}
