<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OffboardingCase extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'initiated_by',
        'case_number',
        'separation_type',
        'separation_reason',
        'last_working_day',
        'notice_period_days',
        'status',
        'resignation_submitted_at',
        'clearance_started_at',
        'exit_interview_completed_at',
        'all_clearances_approved_at',
        'final_documents_generated_at',
        'account_deactivated_at',
        'completed_at',
        'hr_coordinator_id',
        'rehire_eligible',
        'rehire_eligibility_reason',
        'final_pay_computed',
        'final_documents_issued',
        'internal_notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_working_day' => 'date:Y-m-d',
        'resignation_submitted_at' => 'datetime',
        'clearance_started_at' => 'datetime',
        'exit_interview_completed_at' => 'datetime',
        'all_clearances_approved_at' => 'datetime',
        'final_documents_generated_at' => 'datetime',
        'account_deactivated_at' => 'datetime',
        'completed_at' => 'datetime',
        'rehire_eligible' => 'boolean',
        'final_pay_computed' => 'boolean',
        'final_documents_issued' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the employee being offboarded.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who initiated the offboarding.
     */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /**
     * Get the HR coordinator assigned to this case.
     */
    public function hrCoordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_coordinator_id');
    }

    /**
     * Get all clearance items for this offboarding case.
     */
    public function clearanceItems(): HasMany
    {
        return $this->hasMany(ClearanceItem::class);
    }

    /**
     * Get the exit interview for this case.
     */
    public function exitInterview(): HasOne
    {
        return $this->hasOne(ExitInterview::class);
    }

    /**
     * Get all company assets being returned.
     */
    public function companyAssets(): HasMany
    {
        return $this->hasMany(CompanyAsset::class);
    }

    /**
     * Get all knowledge transfer items for this case.
     */
    public function knowledgeTransferItems(): HasMany
    {
        return $this->hasMany(KnowledgeTransferItem::class);
    }

    /**
     * Get all access revocations for this case.
     */
    public function accessRevocations(): HasMany
    {
        return $this->hasMany(AccessRevocation::class);
    }

    /**
     * Get all documents for this offboarding case.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(OffboardingDocument::class);
    }

    /**
     * Scope: Get pending cases.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope: Get in-progress cases.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope: Get completed cases.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Get cases due this week.
     */
    public function scopeDueThisWeek($query)
    {
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        return $query->whereBetween('last_working_day', [$startOfWeek, $endOfWeek])
            ->whereIn('status', ['pending', 'in_progress']);
    }

    /**
     * Scope: Get cases with pending clearances.
     */
    public function scopeWithPendingClearances($query)
    {
        return $query->whereHas('clearanceItems', function ($q) {
            $q->where('status', 'pending');
        });
    }

    /**
     * Scope: Get cases overdue for completion.
     */
    public function scopeOverdue($query)
    {
        return $query->where('last_working_day', '<', now())
            ->whereIn('status', ['pending', 'in_progress']);
    }

    /**
     * Start the clearance process for this case.
     */
    public function startClearanceProcess(): void
    {
        $this->update([
            'status' => 'in_progress',
            'clearance_started_at' => now(),
        ]);
    }

    /**
     * Mark exit interview as completed.
     */
    public function completeExitInterview(): void
    {
        $this->update(['exit_interview_completed_at' => now()]);
    }

    /**
     * Calculate completion percentage based on clearance items.
     */
    public function calculateCompletionPercentage(): int
    {
        $totalItems = $this->clearanceItems()->count();

        if ($totalItems === 0) {
            return 0;
        }

        $completedItems = $this->clearanceItems()
            ->whereIn('status', ['approved', 'waived'])
            ->count();

        return (int) (($completedItems / $totalItems) * 100);
    }

    /**
     * Check if this case can be completed.
     * All clearances must be approved/waived and exit interview completed.
     */
    public function canBeCompleted(): bool
    {
        // Check if all clearances are approved or waived
        $pendingClearances = $this->clearanceItems()
            ->whereNotIn('status', ['approved', 'waived'])
            ->count();

        if ($pendingClearances > 0) {
            return false;
        }

        // Check if at least one clearance item exists
        if ($this->clearanceItems()->count() === 0) {
            return false;
        }

        return true;
    }

    /**
     * Mark the case as completed.
     */
    public function markAsCompleted(): void
    {
        if (!$this->canBeCompleted()) {
            throw new \Exception('Case cannot be completed. Ensure all clearances are approved.');
        }

        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'all_clearances_approved_at' => now(),
        ]);
    }

    /**
     * Get next actions required for this case.
     */
    public function getNextActions(): array
    {
        $actions = [];

        if ($this->status === 'pending') {
            $actions[] = 'Start clearance process';
        }

        if ($this->clearanceItems()->where('status', 'pending')->exists()) {
            $actions[] = 'Await clearance approvals';
        }

        if (!$this->exitInterview()->exists()) {
            $actions[] = 'Complete exit interview';
        }

        if ($this->companyAssets()->where('status', 'issued')->exists()) {
            $actions[] = 'Collect company assets';
        }

        if ($this->canBeCompleted()) {
            $actions[] = 'Complete offboarding case';
        }

        return $actions;
    }

    /**
     * Get case progress summary.
     */
    public function getProgressSummary(): array
    {
        // Calculate clearance percentage
        $totalClearances = $this->clearanceItems()->count();
        $approvedClearances = $this->clearanceItems()
            ->whereIn('status', ['approved', 'waived'])
            ->count();
        $clearancePercentage = $totalClearances > 0 ? round(($approvedClearances / $totalClearances) * 100) : 0;
        
        // Calculate assets return percentage
        $totalAssets = $this->companyAssets()
            ->where('status', 'issued')
            ->count();
        $returnedAssets = $this->companyAssets()
            ->where('status', 'returned')
            ->count();
        $assetsPercentage = $totalAssets > 0 ? round(($returnedAssets / $totalAssets) * 100) : 0;

        // Get exit interview status
        $exitInterviewCompleted = $this->exitInterview()->where('status', 'completed')->exists();
        $exitInterviewPercentage = $exitInterviewCompleted ? 100 : 0;

        // Calculate access revocation percentage
        $totalAccessRevocations = $this->accessRevocations()->count();
        $revokedAccess = $this->accessRevocations()
            ->whereIn('status', ['revoked', 'completed'])
            ->count();
        $accessRevocationPercentage = $totalAccessRevocations > 0 ? round(($revokedAccess / $totalAccessRevocations) * 100) : 0;

        // Calculate documents percentage
        $totalDocuments = $this->documents()->count();
        $issuedDocuments = $this->documents()
            ->where('issued_to_employee', true)
            ->count();
        $documentsPercentage = $totalDocuments > 0 ? round(($issuedDocuments / $totalDocuments) * 100) : 0;

        // Calculate overall percentage (average of all progress percentages)
        $overallPercentage = round(
            ($clearancePercentage + $assetsPercentage + $exitInterviewPercentage + $accessRevocationPercentage + $documentsPercentage) / 5
        );

        return [
            'overall_percentage' => $overallPercentage,
            'clearance_percentage' => $clearancePercentage,
            'exit_interview_completed' => $exitInterviewCompleted,
            'assets_percentage' => $assetsPercentage,
            'access_revocation_percentage' => $accessRevocationPercentage,
            'documents_percentage' => $documentsPercentage,
        ];
    }
}
