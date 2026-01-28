<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DocumentTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get or create a system admin user for document templates
        $adminUser = User::whereHas('roles', function ($q) {
            $q->where('name', 'admin');
        })->first() ?? User::where('email', 'admin@syncingsteel.com')->first() ?? User::first();

        if (!$adminUser) {
            $this->command->warn('No admin user found. Seeders will fail. Create an admin user first.');
            return;
        }

        $templates = [
            [
                'name' => 'Employment Contract',
                'description' => 'Standard full-time employment contract for Philippine employees',
                'template_type' => 'contract',
                'status' => 'approved',
                'is_active' => true,
                'is_locked' => true,
                'version' => 1,
                'variables' => [
                    'employee_name',
                    'employee_address',
                    'position',
                    'department',
                    'start_date',
                    'salary',
                    'payment_frequency',
                    'company_name',
                    'company_address',
                    'supervisor_name',
                    'employment_type',
                    'end_date',
                    'probation_period',
                    'contract_number',
                    'contract_date',
                ],
            ],
            [
                'name' => 'Job Offer Letter',
                'description' => 'Job offer letter for new hires before employment contract',
                'template_type' => 'offer_letter',
                'status' => 'approved',
                'is_active' => true,
                'is_locked' => true,
                'version' => 1,
                'variables' => [
                    'candidate_name',
                    'candidate_address',
                    'position',
                    'department',
                    'start_date',
                    'salary',
                    'benefits',
                    'company_name',
                    'company_address',
                    'hiring_manager_name',
                    'hiring_manager_title',
                    'job_offer_date',
                    'acceptance_deadline',
                    'job_description_summary',
                    'reporting_to',
                ],
            ],
            [
                'name' => 'Certificate of Employment',
                'description' => 'Certificate of Employment (COE) for employee credential requests',
                'template_type' => 'coe',
                'status' => 'approved',
                'is_active' => true,
                'is_locked' => true,
                'version' => 1,
                'variables' => [
                    'employee_name',
                    'employee_address',
                    'employee_number',
                    'position',
                    'department',
                    'start_date',
                    'end_date',
                    'employment_status',
                    'salary',
                    'company_name',
                    'company_address',
                    'company_phone',
                    'ceo_name',
                    'ceo_title',
                    'hr_manager_name',
                    'purpose',
                    'issued_date',
                ],
            ],
            [
                'name' => 'Non-Disclosure Agreement',
                'description' => 'NDA for protecting company confidential information',
                'template_type' => 'other',
                'status' => 'approved',
                'is_active' => true,
                'is_locked' => true,
                'version' => 1,
                'variables' => [
                    'employee_name',
                    'employee_address',
                    'position',
                    'department',
                    'start_date',
                    'company_name',
                    'company_address',
                    'nda_effective_date',
                    'confidential_information_definition',
                    'term_years',
                    'authorized_representative_name',
                    'authorized_representative_title',
                    'acknowledgment_date',
                    'witness_name',
                ],
            ],
            [
                'name' => 'Memorandum',
                'description' => 'Memorandum for official communication and notices to employees',
                'template_type' => 'memo',
                'status' => 'approved',
                'is_active' => true,
                'is_locked' => true,
                'version' => 1,
                'variables' => [
                    'memo_to',
                    'employee_name',
                    'employee_position',
                    'department',
                    'memo_from',
                    'from_position',
                    'memo_date',
                    'subject',
                    'effective_date',
                    'memo_body',
                    'company_name',
                    'reference_number',
                ],
            ],
            [
                'name' => 'Warning Letter',
                'description' => 'Formal warning letter for disciplinary action and documentation',
                'template_type' => 'warning',
                'status' => 'approved',
                'is_active' => true,
                'is_locked' => true,
                'version' => 1,
                'variables' => [
                    'employee_name',
                    'employee_number',
                    'employee_address',
                    'position',
                    'department',
                    'violation_date',
                    'warning_issued_date',
                    'violation_description',
                    'policy_violated',
                    'corrective_action',
                    'corrective_action_deadline',
                    'consequences',
                    'hr_manager_name',
                    'supervisor_name',
                    'warning_number',
                    'notice_to_cure',
                ],
            ],
            [
                'name' => 'Clearance Form',
                'description' => 'Exit clearance form for employee separation and turnover',
                'template_type' => 'clearance',
                'status' => 'approved',
                'is_active' => true,
                'is_locked' => true,
                'version' => 1,
                'variables' => [
                    'employee_name',
                    'employee_number',
                    'employee_address',
                    'position',
                    'department',
                    'last_working_day',
                    'resignation_date',
                    'clearance_date',
                    'company_name',
                    'equipment_list',
                    'equipment_returned',
                    'outstanding_amounts',
                    'final_settlement_date',
                    'hr_manager_name',
                    'finance_manager_name',
                    'department_head_name',
                    'separation_type',
                ],
            ],
            [
                'name' => 'Resignation Acceptance Letter',
                'description' => 'Formal acceptance of employee resignation',
                'template_type' => 'resignation',
                'status' => 'approved',
                'is_active' => true,
                'is_locked' => true,
                'version' => 1,
                'variables' => [
                    'employee_name',
                    'employee_number',
                    'position',
                    'department',
                    'resignation_date',
                    'effective_date',
                    'last_working_day',
                    'company_name',
                    'company_address',
                    'ceo_name',
                    'hr_manager_name',
                    'separation_benefits',
                    'final_paycheck_date',
                    'acceptance_date',
                ],
            ],
            [
                'name' => 'Termination Letter',
                'description' => 'Formal termination notice for involuntary separation',
                'template_type' => 'termination',
                'status' => 'approved',
                'is_active' => true,
                'is_locked' => true,
                'version' => 1,
                'variables' => [
                    'employee_name',
                    'employee_number',
                    'employee_address',
                    'position',
                    'department',
                    'termination_date',
                    'effective_date',
                    'last_working_day',
                    'termination_reason',
                    'termination_type',
                    'company_name',
                    'company_address',
                    'ceo_name',
                    'hr_manager_name',
                    'separation_benefits',
                    'final_paycheck_date',
                    'severance_amount',
                    'notice_period_days',
                    'termination_letter_date',
                ],
            ],
        ];

        foreach ($templates as $template) {
            // Check if template already exists by name
            if (!DocumentTemplate::where('name', $template['name'])->exists()) {
                $documentTemplate = DocumentTemplate::create([
                    'name' => $template['name'],
                    'description' => $template['description'],
                    'template_type' => $template['template_type'],
                    'status' => $template['status'],
                    'is_active' => $template['is_active'],
                    'is_locked' => $template['is_locked'],
                    'version' => $template['version'],
                    'variables' => $template['variables'], // Don't json_encode - model cast handles it
                    'created_by' => $adminUser->id,
                    'approved_by' => $adminUser->id,
                    'approved_at' => now(),
                    'file_path' => null,
                ]);

                // Create template file content
                $templateContent = $this->generateTemplateContent($template['name'], $template['variables']);
                $filePath = "templates/{$documentTemplate->id}/{$documentTemplate->id}_" . Str::slug($template['name']) . ".txt";
                
                \Storage::put($filePath, $templateContent);
                
                // Update template with file path
                $documentTemplate->update(['file_path' => $filePath]);

                $this->command->info("Created template: {$template['name']}");
            } else {
                $this->command->info("Template already exists: {$template['name']}");
            }
        }

        $this->command->info('Document templates seeded successfully!');
    }

    /**
     * Generate template content with placeholder variables
     */
    private function generateTemplateContent($templateName, $variables)
    {
        $content = "=== {$templateName} ===\n\n";
        $content .= "Document generated on: {{current_date}}\n\n";
        
        switch ($templateName) {
            case 'Employment Contract':
                $content .= "EMPLOYMENT CONTRACT\n\n";
                $content .= "This Employment Contract (\"Agreement\") is entered into between:\n\n";
                $content .= "EMPLOYER:\n";
                $content .= "Company: {{company_name}}\n";
                $content .= "Address: {{company_address}}\n\n";
                $content .= "EMPLOYEE:\n";
                $content .= "Name: {{employee_name}}\n";
                $content .= "Address: {{employee_address}}\n";
                $content .= "Position: {{position}}\n";
                $content .= "Department: {{department}}\n\n";
                $content .= "TERMS AND CONDITIONS:\n";
                $content .= "1. Start Date: {{start_date}}\n";
                $content .= "2. Employment Type: {{employment_type}}\n";
                $content .= "3. Probation Period: {{probation_period}}\n";
                $content .= "4. Salary: {{salary}}\n";
                $content .= "5. Payment Frequency: {{payment_frequency}}\n";
                $content .= "6. Reporting To: {{supervisor_name}}\n\n";
                $content .= "Employee acknowledges receipt and understanding of this contract.\n";
                break;
                
            case 'Certificate of Employment':
                $content .= "CERTIFICATE OF EMPLOYMENT\n\n";
                $content .= "TO WHOM IT MAY CONCERN:\n\n";
                $content .= "This is to certify that {{employee_name}} of {{employee_address}}\n";
                $content .= "is/was employed by {{company_name}} at {{company_address}} in the position\n";
                $content .= "of {{position}} under the {{department}} Department.\n\n";
                $content .= "Employment Details:\n";
                $content .= "Employee Number: {{employee_number}}\n";
                $content .= "Date Started: {{start_date}}\n";
                $content .= "Date Ended: {{end_date}}\n";
                $content .= "Employment Status: {{employment_status}}\n";
                $content .= "Salary: {{salary}}\n\n";
                $content .= "This certificate is issued at the request of the employee for whatever\n";
                $content .= "purpose it may serve.\n\n";
                $content .= "Issued on: {{current_date}}\n";
                break;
                
            case 'Memo':
                $content .= "MEMORANDUM\n\n";
                $content .= "TO: {{recipient_name}}\n";
                $content .= "FROM: {{sender_name}}\n";
                $content .= "DATE: {{current_date}}\n";
                $content .= "SUBJECT: {{subject}}\n\n";
                $content .= "{{message_body}}\n";
                break;
                
            case 'Warning Letter':
                $content .= "WARNING LETTER\n\n";
                $content .= "Date: {{current_date}}\n\n";
                $content .= "TO: {{employee_name}}\n";
                $content .= "Position: {{position}}\n";
                $content .= "Department: {{department}}\n\n";
                $content .= "SUBJECT: Disciplinary Action - {{violation_type}}\n\n";
                $content .= "Dear {{employee_name}},\n\n";
                $content .= "This letter serves as a formal warning regarding your recent conduct/performance.\n\n";
                $content .= "Violation: {{violation_details}}\n\n";
                $content .= "Date of Incident: {{incident_date}}\n\n";
                $content .= "Expected Corrective Action:\n";
                $content .= "{{corrective_action}}\n\n";
                $content .= "Failure to comply may result in further disciplinary action up to and including\n";
                $content .= "termination of employment.\n\n";
                $content .= "Acknowledged by:\n\n";
                $content .= "Employee: _______________     Date: _______________\n";
                $content .= "Manager: ________________     Date: _______________\n";
                break;
                
            default:
                $content .= "Template: {{employee_name}}\n";
                $content .= "Created: {{current_date}}\n";
                foreach ($variables as $variable) {
                    $content .= "- {{" . $variable . "}}\n";
                }
                break;
        }

        return $content;
    }
}
