# Performance Appraisal Process

## Overview
End-to-end performance review workflow from cycle creation to final decision (regularization impact, promotion/PIP recommendations), aligned with Appraisal Module and role workflows.

**Participants**: HR Manager (owner), HR Staff (coordinator), Office Admin (final approvals for promotions/terminations), Department Supervisor (feedback provider, via HR proxy where no access)

**Cadence**: Semi-annual or annual (configurable)

---

## Complete Appraisal Flow

```mermaid
graph TD
    Start([Open Appraisal Cycle]) --> CreateCycle[HR Manager Creates<br/>Appraisal Cycle]
    CreateCycle --> DefineCriteria[Define Criteria & Weights<br/>Technical, Behavior, Attendance]
    DefineCriteria --> PublishCycle[Publish Cycle<br/>Open for Reviews]

    PublishCycle --> AssignEmployees[Assign Employees<br/>By Dept/Team/All]
    AssignEmployees --> NotifyStakeholders[Notify HR Staff & Supervisors]

    NotifyStakeholders --> CollectInputs[Collect Inputs<br/>Supervisor Feedback, HR Notes]
    CollectInputs --> TimekeepingImport[Import Attendance Data<br/>Lates/Absences/Violations]
    TimekeepingImport --> ScoreEntries[Enter Scores Per Criterion]
    ScoreEntries --> ComputeWeighted[Compute Weighted Score]

    ComputeWeighted --> PreliminaryReview[HR Staff Preliminary Review]
    PreliminaryReview --> HMReview[HR Manager Final Review]
    HMReview --> Decision{Overall Decision}

    Decision -->|Outstanding| RecommendPromotion[Recommend Promotion/Increase]
    Decision -->|Meets| ConfirmStatus[Confirm Current Status]
    Decision -->|Below| CreatePIP[Create Performance Improvement Plan]
    Decision -->|Unsatisfactory| RecommendSeparation[Recommend Termination]

    RecommendPromotion --> OfficeAdminApproval[Office Admin Approval]
    RecommendSeparation --> OfficeAdminApproval
    CreatePIP --> ScheduleReviews[Schedule PIP Reviews]

    OfficeAdminApproval --> UpdateHRIS[Update HRIS Employment/Salary]
    ConfirmStatus --> UpdateHRIS
    ScheduleReviews --> TrackPIP[Track PIP Progress]
    TrackPIP --> FinalizePIP{PIP Outcome}
    FinalizePIP -->|Improved| ConfirmStatus
    FinalizePIP -->|No Improvement| RecommendSeparation

    UpdateHRIS --> EmployeeAcknowledge[Employee Acknowledgment<br/>(via HR Proxy)]
    EmployeeAcknowledge --> Archive[Archive Appraisal<br/>Lock Results]
    Archive --> End([Process Complete])
```

---

## Criteria & Scoring

### Default Criteria (example weights)
- Technical Competence: 30%
- Quality & Productivity: 25%
- Attendance & Punctuality: 20% (auto-fed from Timekeeping)
- Teamwork & Behavior: 15%
- Initiative & Learning: 10%

### Scoring Scale
- 5 Exceptional; 4 Above Average; 3 Meets; 2 Needs Improvement; 1 Unsatisfactory
- Pass threshold: 3.0 average (60%)

### Weighting Formula
Weighted score per criterion: $w_i \times s_i$; Final score: $\sum_i w_i s_i$

---

## Phases & Checklists

### Phase 1: Cycle Setup (HR Manager)
- Define cycle name, start/end dates, eligibility population
- Configure criteria, weights, and guidance notes
- Publish cycle and notify HR Staff

### Phase 2: Data Collection (HR Staff)
- Gather supervisor feedback (paper/email → HR input)
- Import attendance/violation summary from Timekeeping
- Ensure role/department changes during cycle are noted

### Phase 3: Scoring & Review
- HR Staff enters per-criterion scores and notes
- System computes weighted score and flags outliers
- HR Manager reviews, edits, and finalizes ratings

### Phase 4: Decisions & Actions
- Outcomes: Promotion/Increase, Maintain Status, PIP, Termination
- Office Admin approves promotion/termination actions
- HR updates HRIS (employment status, compensation, notes)

### Phase 5: Acknowledgment & Archival
- HR discusses results with employee (in person)
- HR records employee acknowledgment (signature/scanned copy)
- Lock appraisal; generate reports; archive securely

---

## Integration Points
- Timekeeping: Attendance/violations feed into scoring automatically
- Workforce: Rotation/assignment history for context
- Payroll: Salary changes upon promotion (post-approval)
- HR Core: Employment status updates, regularization outcomes

---

## Roles & Responsibilities
- HR Manager: Owns cycles, final ratings and decisions
- HR Staff: Coordinates data, inputs scores, records acknowledgments
- Office Admin: Approves promotions and separations
- Supervisor: Provides qualitative feedback (via HR proxy)

---

## KPIs & Targets
- Cycle On-Time Completion: > 95%
- Acknowledgment Signed within 7 days: > 90%
- PIP Success Rate: > 70%
- Distribution Balance: < 10% extreme outliers without justification

---

## Common Issues & Resolutions
- Missing attendance data → Re-sync Timekeeping summaries
- Supervisor input delayed → Escalate to HR Manager; proceed with available data
- Disputed ratings → Record appeal note; set re-review window

## Immutable Ledger & Replay Monitoring

- Attendance KPIs feeding the appraisal criteria must originate from the PostgreSQL ledger (`rfid_ledger`) curated by the Replayable Event-Log Verification Layer; HR should reference ledger sequence reports when discussing punctuality.
- HR Manager and HR Staff should monitor replay-layer alerting/metrics (ledger commit latency, sequence gaps, hash mismatches, replay backlog) before finalizing ratings tied to attendance infractions.

---

## Related Documentation
- [Appraisal Module](../../APPRAISAL_MODULE.md)
- [HR Manager Workflow](../03-hr-manager-workflow.md)
- [HR Staff Workflow](../04-hr-staff-workflow.md)
- [Payroll Processing](./payroll-processing.md)

---

**Last Updated**: November 29, 2025  
**Process Owner**: HR Department  
**Cadence**: Semi-annual/Annual
