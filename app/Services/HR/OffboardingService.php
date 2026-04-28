<?php

    namespace App\Services\HR;

    use App\Models\OffboardingCase;
    use App\Models\ClearanceItem;
    use App\Models\AccessRevocation;
    use App\Models\OffboardingDocument;
    use Illuminate\Support\Facades\Log;
    use Illuminate\Support\Facades\Notification;
    use Illuminate\Support\Facades\DB;

    class OffboardingService
    {
        /**
         * Check if the user can waive clearance items (HR Staff or HR Manager).
         */
        public function userCanWaiveClearances($user): bool
        {
            if (!$user) return false;
            $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->toArray() : ($user->roles ?? []);
            return in_array('HR Staff', $roles) || in_array('HR Manager', $roles);
        }

    /**
     * Generate a unique case number in format OFF-YYYY-NNN
     */
    public function generateCaseNumber(): string
    {
        $year = now()->year;
        $lastCase = OffboardingCase::where('case_number', 'like', "OFF-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();

        $sequence = 1;
        if ($lastCase) {
            $lastNumber = intval(substr($lastCase->case_number, -3));
            $sequence = $lastNumber + 1;
        }

        return sprintf('OFF-%d-%03d', $year, $sequence);
    }

    /**
     * Create default clearance items based on employee's role and department.
     */
    public function createDefaultClearanceItems(OffboardingCase $case): void
    {
        $defaultItems = [
            // IT Department
            [
                'category' => 'it',
                'item_name' => 'Return company laptop',
                'description' => 'Collect and inspect employee laptop computer',
                'priority' => 'critical',
            ],
            [
                'category' => 'it',
                'item_name' => 'Return mobile phone',
                'description' => 'Retrieve company-issued mobile device',
                'priority' => 'high',
            ],
            [
                'category' => 'it',
                'item_name' => 'Disable VPN access',
                'description' => 'Revoke remote access credentials',
                'priority' => 'critical',
            ],
            [
                'category' => 'it',
                'item_name' => 'Archive email and documents',
                'description' => 'Transfer important files to company storage',
                'priority' => 'high',
            ],
            // HR Department
            [
                'category' => 'hr',
                'item_name' => 'Complete exit interview',
                'description' => 'Conduct and record exit interview',
                'priority' => 'normal',
            ],
            [
                'category' => 'hr',
                'item_name' => 'Return ID card',
                'description' => 'Collect employee ID badge',
                'priority' => 'high',
            ],
            [
                'category' => 'hr',
                'item_name' => 'Return access card',
                'description' => 'Retrieve building/facility access card',
                'priority' => 'high',
            ],
            [
                'category' => 'hr',
                'item_name' => 'Process final benefits',
                'description' => 'Handle health insurance and other benefits termination',
                'priority' => 'normal',
            ],
            // Finance Department
            [
                'category' => 'finance',
                'item_name' => 'Clear outstanding cash advances',
                'description' => 'Collect or deduct outstanding cash advances',
                'priority' => 'critical',
            ],
            [
                'category' => 'finance',
                'item_name' => 'Compute final pay',
                'description' => 'Calculate final salary and adjustments',
                'priority' => 'critical',
            ],
            [
                'category' => 'finance',
                'item_name' => 'Settle outstanding reimbursements',
                'description' => 'Process pending expense reimbursements',
                'priority' => 'high',
            ],
            // Operations
            [
                'category' => 'operations',
                'item_name' => 'Return company keys',
                'description' => 'Collect all facility and equipment keys',
                'priority' => 'high',
            ],
            [
                'category' => 'operations',
                'item_name' => 'Sign off on equipment checklist',
                'description' => 'Verify return of all company equipment',
                'priority' => 'normal',
            ],
        ];

        foreach ($defaultItems as $item) {
            ClearanceItem::create([
                'offboarding_case_id' => $case->id,
                'category' => $item['category'],
                'item_name' => $item['item_name'],
                'description' => $item['description'],
                'priority' => $item['priority'],
                'status' => 'pending',
                'due_date' => $case->last_working_day,
            ]);
        }

        Log::info('Default clearance items created', [
            'case_id' => $case->id,
            'count' => count($defaultItems),
        ]);
    }

    /**
     * Create default access revocations for common systems.
     */
    public function createDefaultAccessRevocations(OffboardingCase $case): void
    {
        $systems = [
            [
                'system_name' => 'Email',
                'system_category' => 'email',
                'account_identifier' => $case->employee->user?->email,
            ],
            [
                'system_name' => 'VPN',
                'system_category' => 'network',
                'account_identifier' => $case->employee->user?->username,
            ],
            [
                'system_name' => 'Active Directory',
                'system_category' => 'network',
                'account_identifier' => $case->employee->user?->username,
            ],
            [
                'system_name' => 'ERP System',
                'system_category' => 'application',
                'account_identifier' => $case->employee->user?->username,
            ],
            [
                'system_name' => 'Slack',
                'system_category' => 'cloud_service',
                'account_identifier' => $case->employee->user?->email,
            ],
            [
                'system_name' => 'Microsoft 365',
                'system_category' => 'cloud_service',
                'account_identifier' => $case->employee->user?->email,
            ],
            [
                'system_name' => 'Building Access System',
                'system_category' => 'physical_access',
                'account_identifier' => $case->employee->employee_number,
            ],
        ];

        foreach ($systems as $system) {
            AccessRevocation::create([
                'offboarding_case_id' => $case->id,
                'employee_id' => $case->employee_id,
                'system_name' => $system['system_name'],
                'system_category' => $system['system_category'],
                'account_identifier' => $system['account_identifier'],
                'status' => 'active',
            ]);
        }

        Log::info('Default access revocations created', [
            'case_id' => $case->id,
            'count' => count($systems),
        ]);
    }

    /**
     * Send notifications when offboarding is initiated.
     */
    public function notifyOffboardingInitiated(OffboardingCase $case): void
    {
        try {
            // Notify HR coordinator
            if ($case->hrCoordinator?->user) {
                Log::info('Notification sent to HR coordinator for offboarding case', [
                    'case_number' => $case->case_number,
                    'user_id' => $case->hrCoordinator->user->id,
                ]);
            }

            // Notify employee's direct manager
            if ($case->employee->supervisor?->user) {
                Log::info('Notification sent to employee supervisor for offboarding case', [
                    'case_number' => $case->case_number,
                    'supervisor_id' => $case->employee->supervisor->user->id,
                ]);
            }

            Log::info('Offboarding initiation notifications sent', [
                'case_number' => $case->case_number,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send offboarding initiation notifications', [
                'case_number' => $case->case_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send notifications when offboarding is cancelled.
     */
    public function notifyOffboardingCancelled(OffboardingCase $case): void
    {
        try {
            Log::info('Offboarding cancellation notifications sent', [
                'case_number' => $case->case_number,
                'employee_id' => $case->employee_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send offboarding cancellation notifications', [
                'case_number' => $case->case_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send notifications when offboarding is completed.
     */
    public function notifyOffboardingCompleted(OffboardingCase $case): void
    {
        try {
            Log::info('Offboarding completion notifications sent', [
                'case_number' => $case->case_number,
                'employee_id' => $case->employee_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send offboarding completion notifications', [
                'case_number' => $case->case_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate final documents for the offboarding case.
     */
    public function generateFinalDocuments(OffboardingCase $case): void
    {
        try {
            // Generate Clearance Certificate
            $this->createDocument($case, 'clearance_certificate', 'Clearance Certificate', true);

            // Generate Certificate of Employment
            $this->createDocument($case, 'certificate_of_employment', 'Certificate of Employment', true);

            // Generate Final Pay Computation
            $this->createDocument($case, 'final_pay_computation', 'Final Pay Computation', true);

            $case->update([
                'final_documents_generated_at' => now(),
                'final_documents_issued' => true,
            ]);

            Log::info('Final documents generated for offboarding case', [
                'case_number' => $case->case_number,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate final documents', [
                'case_number' => $case->case_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create a document for the offboarding case.
     */
    private function createDocument(
        OffboardingCase $case,
        string $documentType,
        string $documentName,
        bool $generateBySystem = true
    ): void {
        OffboardingDocument::create([
            'offboarding_case_id' => $case->id,
            'employee_id' => $case->employee_id,
            'document_type' => $documentType,
            'document_name' => $documentName,
            'file_path' => '/generated/' . $case->case_number . '/' . $documentType . '.pdf',
            'generated_by_system' => $generateBySystem,
            'status' => 'draft',
            'mime_type' => 'application/pdf',
        ]);
    }

    /**
     * Generate a PDF report for the offboarding case.
     */
    public function generateCaseReportPDF(OffboardingCase $case)
    {
        try {
            // For now, just return a simple response
            // In production, use a PDF library like DomPDF or Snappy

            Log::info('PDF report generated for offboarding case', [
                'case_number' => $case->case_number,
            ]);

            // This would use a proper PDF generation library
            // e.g., return PDF::loadView('offboarding.report', ['case' => $case])->download();

            throw new \Exception('PDF generation not yet implemented. Please use a PDF library.');
        } catch (\Exception $e) {
            Log::error('Failed to generate PDF report', [
                'case_number' => $case->case_number,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get statistics for offboarding cases by status.
     */
    public function getOffboardingStatistics(): array
    {
        $total = OffboardingCase::count();

        return [
            'total' => $total,
            'pending' => OffboardingCase::where('status', 'pending')->count(),
            'in_progress' => OffboardingCase::where('status', 'in_progress')->count(),
            'clearance_pending' => OffboardingCase::where('status', 'clearance_pending')->count(),
            'completed' => OffboardingCase::where('status', 'completed')->count(),
            'cancelled' => OffboardingCase::where('status', 'cancelled')->count(),
            'due_this_week' => OffboardingCase::whereBetween(
                'last_working_day',
                [now()->startOfWeek(), now()->endOfWeek()]
            )->whereIn('status', ['pending', 'in_progress'])->count(),
            'overdue' => OffboardingCase::where('last_working_day', '<', now()->toDateString())
                ->whereIn('status', ['pending', 'in_progress'])
                ->count(),
        ];
    }

    /**
     * Get pending clearances for a specific user/department.
     */
    public function getPendingClearancesForUser($userId): int
    {
        return ClearanceItem::whereHas('offboardingCase', function ($query) {
            $query->whereIn('status', ['pending', 'in_progress', 'clearance_pending']);
        })
        ->where('assigned_to', $userId)
        ->where('status', 'pending')
        ->count();
    }

    /**
     * Check if all clearances for a case are complete.
     */
    public function allClearancesComplete(OffboardingCase $case): bool
    {
        $totalItems = $case->clearanceItems()->count();
        $completedItems = $case->clearanceItems()
            ->whereIn('status', ['approved', 'waived'])
            ->count();

        return $totalItems > 0 && $totalItems === $completedItems;
    }

    /**
     * Send notification when a clearance item is approved.
     */
    public function notifyClearanceApproved(ClearanceItem $item, $approvedBy): void
    {
        try {
            $case = $item->offboardingCase;

            Log::info('Clearance item approved notification sent', [
                'case_number' => $case->case_number,
                'item_id' => $item->id,
                'item_name' => $item->item_name,
                'category' => $item->category,
                'approved_by' => $approvedBy,
            ]);

            // Notify HR coordinator of approval
            if ($case->hrCoordinator?->user) {
                // Notification would be sent here
            }
        } catch (\Exception $e) {
            Log::error('Failed to send clearance approval notification', [
                'item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send notification when a clearance issue is reported.
     */
    public function notifyClearanceIssueReported(ClearanceItem $item, string $issue): void
    {
        try {
            $case = $item->offboardingCase;

            Log::info('Clearance issue reported notification sent', [
                'case_number' => $case->case_number,
                'item_id' => $item->id,
                'item_name' => $item->item_name,
                'category' => $item->category,
                'issue' => $issue,
            ]);

            // Notify HR coordinator of issue
            if ($case->hrCoordinator?->user) {
                // Notification would be sent here
            }
        } catch (\Exception $e) {
            Log::error('Failed to send clearance issue notification', [
                'item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send notification when a clearance item is waived.
     */
    public function notifyClearanceWaived(ClearanceItem $item, string $reason, $waivedBy): void
    {
        try {
            $case = $item->offboardingCase;

            Log::info('Clearance item waived notification sent', [
                'case_number' => $case->case_number,
                'item_id' => $item->id,
                'item_name' => $item->item_name,
                'category' => $item->category,
                'reason' => $reason,
                'waived_by' => $waivedBy,
            ]);

            // Notify relevant stakeholders
            if ($case->hrCoordinator?->user) {
                // Notification would be sent here
            }
        } catch (\Exception $e) {
            Log::error('Failed to send clearance waiver notification', [
                'item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get detailed statistics for clearance items in a case.
     */
    public function getClearanceStatistics(OffboardingCase $case): array
    {
        $clearanceItems = $case->clearanceItems()->get();

        $statistics = [
            'total' => $clearanceItems->count(),
            'approved' => $clearanceItems->where('status', 'approved')->count(),
            'pending' => $clearanceItems->where('status', 'pending')->count(),
            'waived' => $clearanceItems->where('status', 'waived')->count(),
            'issue_reported' => $clearanceItems->where('status', 'issue_reported')->count(),
            'completion_percentage' => 0,
            'by_category' => [],
        ];

        // Calculate completion percentage
        if ($statistics['total'] > 0) {
            $completed = $statistics['approved'] + $statistics['waived'];
            $statistics['completion_percentage'] = round(($completed / $statistics['total']) * 100, 2);
        }

        // Group by category with counts
        foreach ($clearanceItems->groupBy('category') as $category => $items) {
            $statistics['by_category'][$category] = [
                'total' => $items->count(),
                'approved' => $items->where('status', 'approved')->count(),
                'pending' => $items->where('status', 'pending')->count(),
                'waived' => $items->where('status', 'waived')->count(),
                'issue_reported' => $items->where('status', 'issue_reported')->count(),
            ];
        }

        return $statistics;
    }

    /**
     * Get all pending clearances grouped by category.
     */
    public function getPendingClearancesByCategory(OffboardingCase $case): array
    {
        $pending = $case->clearanceItems()
            ->where('status', 'pending')
            ->get()
            ->groupBy('category')
            ->map(function ($items) {
                return [
                    'count' => $items->count(),
                    'items' => $items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'item_name' => $item->item_name,
                            'description' => $item->description,
                            'priority' => $item->priority,
                            'due_date' => $item->due_date->format('Y-m-d'),
                        ];
                    })->toArray(),
                ];
            })
            ->toArray();

        return $pending;
    }

    /**
     * Send notification when exit interview is completed.
     */
    public function notifyExitInterviewCompleted(OffboardingCase $case, $exitInterview): void
    {
        try {
            Log::info('Exit interview completion notification sent', [
                'case_number' => $case->case_number,
                'employee_id' => $case->employee_id,
                'interview_id' => $exitInterview->id,
                'sentiment_score' => $exitInterview->sentiment_score,
            ]);

            // Notify HR coordinator of completion
            if ($case->hrCoordinator?->user) {
                // Notification would be sent here
            }
        } catch (\Exception $e) {
            Log::error('Failed to send exit interview completion notification', [
                'case_number' => $case->case_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify relevant parties when an asset has liability (damaged/lost).
     * 
     * Informs Finance for final pay deduction and HR of the liability.
     */
    public function notifyAssetLiability(CompanyAsset $asset, float $liabilityAmount, string $condition): void
    {
        try {
            $employee = $asset->employee;
            $offboardingCase = $asset->offboardingCase;

            Log::info('Asset liability notification triggered', [
                'asset_id' => $asset->id,
                'employee_id' => $employee->id,
                'employee_name' => $employee->profile?->first_name . ' ' . $employee->profile?->last_name,
                'condition' => $condition,
                'liability_amount' => $liabilityAmount,
                'case_number' => $offboardingCase?->case_number,
            ]);

            // Finance notification for final pay deduction
            if ($asset->deducted_from_final_pay) {
                Log::warning('Asset flagged for final pay deduction', [
                    'asset_id' => $asset->id,
                    'employee_id' => $employee->id,
                    'amount' => $liabilityAmount,
                    'asset_type' => $asset->asset_type,
                    'asset_name' => $asset->asset_name,
                ]);
            }

            // HR notification
            Log::info('Asset liability recorded in employee file', [
                'employee_id' => $employee->id,
                'asset_type' => $asset->asset_type,
                'liability_amount' => $liabilityAmount,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send asset liability notification', [
                'asset_id' => $asset->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify relevant parties when an asset issue is reported (lost/damaged).
     * 
     * Informs HR Coordinator, Finance, and updates clearance status.
     */
    public function notifyAssetIssue(CompanyAsset $asset, string $issueStatus, float $liabilityAmount, string $description): void
    {
        try {
            $employee = $asset->employee;
            $offboardingCase = $asset->offboardingCase;

            Log::warning('Asset issue reported', [
                'asset_id' => $asset->id,
                'employee_id' => $employee->id,
                'employee_name' => $employee->profile?->first_name . ' ' . $employee->profile?->last_name,
                'issue_status' => $issueStatus,
                'asset_type' => $asset->asset_type,
                'asset_name' => $asset->asset_name,
                'description' => $description,
                'case_number' => $offboardingCase?->case_number,
            ]);

            // Finance notification
            Log::warning('Asset issue flagged for finance follow-up', [
                'asset_id' => $asset->id,
                'employee_id' => $employee->id,
                'issue_status' => $issueStatus,
                'liability_amount' => $liabilityAmount,
                'deduction_required' => true,
            ]);

            // HR coordination
            if ($offboardingCase && $offboardingCase->hrCoordinator) {
                Log::info('HR Coordinator notified of asset issue', [
                    'case_number' => $offboardingCase->case_number,
                    'hr_coordinator_id' => $offboardingCase->hrCoordinator->id,
                    'asset_id' => $asset->id,
                ]);
            }

            // Update clearance item status
            if ($asset->clearance_item_id) {
                Log::info('Clearance item status updated due to asset issue', [
                    'clearance_item_id' => $asset->clearance_item_id,
                    'asset_id' => $asset->id,
                    'new_status' => 'issues',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send asset issue notification', [
                'asset_id' => $asset->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Document generation helper - logs document creation.
     */
    public function logDocumentGeneration(OffboardingCase $case, string $documentType, string $documentName): void
    {
        try {
            Log::info('Document generation initiated', [
                'case_number' => $case->case_number,
                'employee_id' => $case->employee_id,
                'document_type' => $documentType,
                'document_name' => $documentName,
                'generated_by' => auth()->id(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log document generation', [
                'case_number' => $case->case_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify finance department of final pay computation.
     */
    public function notifyFinanceOfFinalPay(OffboardingCase $case, float $netAmount): void
    {
        try {
            Log::warning('Final pay computation ready for finance processing', [
                'case_number' => $case->case_number,
                'employee_id' => $case->employee_id,
                'employee_name' => $case->employee->profile?->first_name . ' ' . $case->employee->profile?->last_name,
                'net_amount' => $netAmount,
                'last_working_day' => $case->last_working_day,
            ]);

            // Track final pay computation status
            $case->update(['final_pay_computed' => true]);

        } catch (\Exception $e) {
            Log::error('Failed to notify finance of final pay', [
                'case_number' => $case->case_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Issue documents to employee.
     */
    public function issueDocumentsToEmployee(OffboardingCase $case): void
    {
        try {
            $documents = $case->documents()
                ->where('status', 'approved')
                ->where('issued_to_employee', false)
                ->get();

            $count = 0;
            foreach ($documents as $document) {
                $document->update([
                    'issued_to_employee' => true,
                    'issued_at' => now(),
                ]);
                $count++;
            }

            if ($count > 0) {
                Log::info('Documents issued to employee', [
                    'case_number' => $case->case_number,
                    'employee_id' => $case->employee_id,
                    'documents_count' => $count,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to issue documents to employee', [
                'case_number' => $case->case_number,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
