# Overtime Approval Process

## Overview
Workflow for requesting, validating, approving, and recording overtime (OT) before it flows to Timekeeping and Payroll. Applies to office-managed manufacturing workforce; supervisors submit paper forms, HR Staff encodes.

**Participants**: Employee/Supervisor (paper request) → HR Staff (encoder) → HR Manager (approver) → Office Admin (escalated approval per rule) → Payroll Officer (consumer)

**Configurable Rules** (Office Admin): thresholds, rates, approval levels, maximum hours.

---

## Complete Overtime Flow

```mermaid
graph TD
    Start([Supervisor Submits OT Form]) --> HRStaffIntake[HR Staff Encodes<br/>Overtime Request]
    HRStaffIntake --> ValidateInputs[Validate Details<br/>Date, Time, Reason]
    ValidateInputs --> CheckSchedule[Check Assigned Schedule<br/>& Conflicts]
    CheckSchedule --> Conflict{Conflict or Rule Violation?}
    Conflict -->|Yes| ResolveConflict[Adjust Request<br/>or Reject]
    ResolveConflict --> ValidateInputs
    Conflict -->|No| DetermineApproval[Determine Approval Path<br/>Based on Duration/Config]
    
    DetermineApproval --> ShortOT{Planned Hours ≤ Threshold?}
    ShortOT -->|Yes| HRManagerQueue[Queue for HR Manager Approval]
    ShortOT -->|No| EscalateOfficeAdmin[Send to Office Admin<br/>after HR Manager]
    
    HRManagerQueue --> HRManagerReview[HR Manager Reviews<br/>Reason & Coverage]
    HRManagerReview --> ManagerDecision{HR Manager Decision}
    ManagerDecision -->|Rejected| NotifyReject[Notify Supervisor<br/>& Log]
    ManagerDecision -->|Approved| AwaitNextStep
    
    AwaitNextStep --> EscalationNeeded{Escalation Required?}
    EscalationNeeded -->|Yes| OfficeAdminReview[Office Admin Reviews<br/>High OT or Special Cases]
    EscalationNeeded -->|No| ApprovedOT
    OfficeAdminReview --> OAdecision{Office Admin Decision}
    OAdecision -->|Rejected| NotifyReject
    OAdecision -->|Approved| ApprovedOT[Mark Request Approved]
    
    ApprovedOT --> NotifyTimekeeping[Notify Timekeeping<br/>& Update Schedule]
    NotifyTimekeeping --> LockPlanned[Lock Planned OT<br/>(# hours, cost center)]
    LockPlanned --> ActualWork[Capture Actual Time<br/>via RFID + Manual]
    ActualWork --> CompareActual[Compare Actual vs Planned]
    CompareActual --> Variance{Variance Detected?}
    Variance -->|Yes| AdjustRecord[Adjust OT Record<br/>Log Reason]
    Variance -->|No| FinalizeRecord
    AdjustRecord --> FinalizeRecord[Finalize OT Record]
    
    FinalizeRecord --> NotifyPayroll[Notify Payroll<br/>Include OT Hours]
    NotifyPayroll --> ArchiveDocs[Archive OT Form<br/>and Approval Trail]
    ArchiveDocs --> End([Process Complete])
```

---

## Phase Breakdown

### Phase 1: Intake & Validation
- HR Staff encodes supervisor-submitted OT form (paper/email)
- Required fields: employee, date, start/end time, hours, location, reason, cost center
- Validation checks:
  - Employee active and scheduled that day
  - No overlapping shifts or existing OT
  - Adheres to max hours/day and per week (configurable)

### Phase 2: Approval Routing
- Configured thresholds (example):
  - ≤ 2 hours: HR Manager approval only
  - > 2 hours or rest day OT: HR Manager + Office Admin
  - Holiday OT: automatic Office Admin visibility
- HR Manager verifies justification, workforce coverage, budget
- Office Admin confirms compliance with labor rules and cost impact

### Phase 3: Execution & Tracking
- Approved OT auto-pushes to shift assignments/timekeeping
- RFID tap events determine actual start/end; discrepancies flagged
- HR Staff records manual notes if employee forgets to tap (with supervisor countersign)

### Phase 4: Payroll Integration
- Confirmed OT hours appear in payroll period adjustments
- Differentiated rates (regular OT, rest day, holiday) derived from Office Admin rules
- Payroll Officer reviews OT summary before calculations

---

## Integration Points
- **Workforce Scheduling**: ensures assignments reflect approved OT slots
- **Timekeeping Module**: stores planned/actual OT, references `overtime_requests`
- **Payroll Processing**: pulls approved OT hours per period; applied multipliers
- **Notifications**: alerts HR Manager/Office Admin for pending approvals; reminders for actual logging

---

## Roles & Responsibilities
- **HR Staff**: encode requests, validate, manage status changes, upload supporting docs
- **HR Manager**: approve/reject OT, ensure manpower coverage, enforce policies
- **Office Admin**: approve high-risk/high-cost OT, adjust rules
- **Payroll Officer**: consume OT data, flag anomalies, ensure payout accuracy

---

## KPIs & Targets
- Approval Turnaround: < 8 business hours
- Planned vs Actual Variance: < 10%
- Unscheduled OT occurrences: < 5 per month
- Audit Compliance (complete trail): 100%

---

## Common Issues & Resolutions
- **Missing RFID taps** → Use manual actual log signed by supervisor; attach to request
- **Exceeded max hours** → System auto-reject; require Office Admin override
- **Duplicate requests** → Conflict detection prevents; HR Staff merges or cancels
- **Payroll mismatch** → Re-run OT summary, ensure finalized status before payroll cut-off

## Immutable Ledger & Replay Monitoring

- Planned vs actual OT comparisons rely on RFID events captured in the PostgreSQL ledger (`rfid_ledger`) by the Replayable Event-Log Verification Layer; approvals should reference ledger sequence IDs.
- HR Staff, HR Managers, and Office Admins monitor the replay layer's alerting/metrics (ledger commit latency, sequence gaps, hash mismatches, replay backlog) to stop payouts when OT data integrity is in question.

---

## Related Documentation
- [Workforce Scheduling Process](./workforce-scheduling.md)
- [Timekeeping Module Architecture](../../TIMEKEEPING_MODULE_ARCHITECTURE.md)
- [Payroll Processing Workflow](./payroll-processing.md)
- [HR Staff Workflow](../04-hr-staff-workflow.md#timekeeping)
- [Office Admin Workflow](../02-office-admin-workflow.md#business-rules-configuration)

---

**Last Updated**: November 29, 2025  
**Process Owner**: HR Department  
**Thresholds & Rates**: Configurable per company policy
