# HR Manager Workflow

## Role Overview
**Focus**: Strategic HR oversight, high-level approvals, and workforce planning

### Core Responsibilities
- 👥 Employee lifecycle management oversight
- ✅ Leave approval (conditional approval authority)
- 🎯 Performance management and appraisal oversight
- 📊 Workforce analytics and reporting
- 🔍 Hiring approval and interview coordination
- 📈 HR strategy and policy implementation
- 🔔 Employee relations and conflict resolution

---

## Dashboard Overview

```mermaid
graph TB
    ManagerDash[HR Manager Dashboard]
    
    ManagerDash --> PendingApprovals[Pending Approvals<br/>Leave, Hiring, etc.]
    ManagerDash --> EmployeeDirectory[Employee Directory<br/>& Records]
    ManagerDash --> Analytics[Workforce Analytics<br/>& Reports]
    ManagerDash --> Performance[Performance Management<br/>& Appraisals]
    ManagerDash --> Recruitment[Recruitment<br/>Pipeline]
    ManagerDash --> Compliance[Compliance<br/>& Audits]
    
    style ManagerDash fill:#673ab7,color:#fff
    style PendingApprovals fill:#f44336,color:#fff
    style EmployeeDirectory fill:#2196f3,color:#fff
    style Analytics fill:#4caf50,color:#fff
    style Performance fill:#ff9800,color:#fff
    style Recruitment fill:#00bcd4,color:#fff
    style Compliance fill:#ffc107,color:#000
```

---

## 1. Leave Approval Workflow

### Purpose
Review and approve leave requests based on workforce availability and policies.

### Workflow

```mermaid
graph TD
    Start([Leave Request Received]) --> ReviewRequest[Review Leave Details]
    
    ReviewRequest --> CheckDuration{Leave Duration}
    CheckDuration -->|1-2 days| AutoApproved[Auto-Approved<br/>No Action Needed]
    CheckDuration -->|3-5 days| ReviewNeeded[HR Manager Review Required]
    CheckDuration -->|6+ days| ConditionalApprove[HR Manager<br/>Conditional Approval]
    
    ReviewNeeded --> CheckBalance[Check Leave Balance]
    CheckBalance --> Sufficient{Balance Sufficient?}
    Sufficient -->|No| RejectInsufficient[Reject: Insufficient Balance]
    Sufficient -->|Yes| CheckSchedule[Check Workforce Schedule]
    
    CheckSchedule --> Conflicts{Schedule Conflicts?}
    Conflicts -->|Yes| EvaluateImpact{Critical Impact?}
    Conflicts -->|No| ApproveFinal[Approve Leave]
    
    EvaluateImpact -->|High Impact| RejectConflict[Reject: Schedule Conflict<br/>Suggest Alternative Dates]
    EvaluateImpact -->|Manageable| ApproveFinal
    
    ConditionalApprove --> ForwardAdmin[Forward to Office Admin<br/>For Final Approval]
    ForwardAdmin --> AdminDecision{Office Admin Decision}
    AdminDecision -->|Approved| LeaveGranted[Leave Granted]
    AdminDecision -->|Rejected| LeaveRejected[Leave Rejected]
    
    ApproveFinal --> NotifyEmployee[Notify Employee]
    RejectInsufficient --> NotifyEmployee
    RejectConflict --> NotifyEmployee
    LeaveGranted --> NotifyEmployee
    LeaveRejected --> NotifyEmployee
    
    NotifyEmployee --> Complete([Process Complete])
```

### Approval Authority

**Auto-Approved (1-2 days):**
- System automatically approves if:
  - Sufficient leave balance
  - No schedule conflicts
  - Minimum 3 days advance notice
- HR Manager receives notification only

**HR Manager Approval Required (3-5 days):**
- Full approval authority
- Reviews:
  - Leave balance
  - Workforce schedule
  - Department coverage
  - Historical leave patterns
- Can approve or reject with reason

**Conditional Approval (6+ days):**
- HR Manager provides conditional approval
- Forwards to Office Admin for final decision
- Office Admin has final authority
- Both approvals required for leave to be granted

### Decision Factors

**Approve if:**
- ✅ Sufficient leave balance
- ✅ No critical schedule conflicts
- ✅ Adequate department coverage
- ✅ Proper advance notice
- ✅ Valid reason provided

**Reject if:**
- ❌ Insufficient leave balance
- ❌ Critical schedule conflict (busy period, insufficient coverage)
- ❌ Inadequate advance notice (< 3 days for planned leave)
- ❌ Concurrent leave requests from same department
- ❌ Previous unresolved leave issues

---

## 2. Hiring Approval & Interview Coordination

### Purpose
Review applicant shortlist, conduct interviews, and approve hiring recommendations.

### Workflow

```mermaid
graph TD
    Start([Applicant Shortlisted<br/>by HR Staff]) --> ReviewApplication[Review Application<br/>& Resume]
    
    ReviewApplication --> QualificationCheck{Meets Requirements?}
    QualificationCheck -->|No| RejectApplicant[Reject Application<br/>Provide Feedback]
    QualificationCheck -->|Yes| ScheduleInterview[Schedule Interview]
    
    ScheduleInterview --> NotifyApplicant[Notify Applicant<br/>Interview Details]
    NotifyApplicant --> ConductInterview[Conduct Interview]
    
    ConductInterview --> EvaluateCandidate[Evaluate Candidate]
    EvaluateCandidate --> AssessmentScore{Assessment Score}
    
    AssessmentScore -->|Below Threshold| RejectAfterInterview[Reject After Interview<br/>Provide Feedback]
    AssessmentScore -->|Meets Threshold| CreateRecommendation[Create Hiring Recommendation]
    
    CreateRecommendation --> ForwardAdmin[Forward to Office Admin<br/>For Final Approval]
    ForwardAdmin --> AdminDecision{Office Admin Decision}
    
    AdminDecision -->|Approved| HiringApproved[Hiring Approved]
    AdminDecision -->|Rejected| HiringRejected[Hiring Rejected<br/>Consider Other Candidates]
    
    HiringApproved --> NotifyStaff[Notify HR Staff<br/>to Process Onboarding]
    HiringRejected --> ReturnToPool[Return to Applicant Pool<br/>or Re-post Position]
    
    RejectApplicant --> LogDecision[Log Decision]
    RejectAfterInterview --> LogDecision
    NotifyStaff --> LogDecision
    ReturnToPool --> LogDecision
    
    LogDecision --> Complete([Process Complete])
```

### Interview Process

**Pre-Interview:**
1. Review applicant resume and portfolio
2. Check qualifications against job requirements
3. Review HR Staff screening notes
4. Prepare interview questions
5. Schedule interview (coordinate with applicant and panel)

**During Interview:**
1. Conduct competency-based interview
2. Assess technical skills
3. Evaluate cultural fit
4. Ask behavioral questions
5. Allow candidate questions
6. Provide role overview and expectations

**Post-Interview:**
1. Complete evaluation form
2. Score candidate against criteria
3. Document interview notes
4. Make hiring recommendation
5. Forward to Office Admin for final approval

### Hiring Decision Matrix

| Criteria | Weight | Scoring |
|----------|--------|---------|
| Technical Skills | 30% | 1-5 scale |
| Experience | 25% | Years + relevance |
| Cultural Fit | 20% | Interview assessment |
| Communication | 15% | Interview performance |
| Problem Solving | 10% | Scenario-based questions |

**Pass Threshold**: 70% overall score

**Decision:**
- **80%+ score**: Strong recommendation
- **70-79% score**: Conditional recommendation
- **Below 70%**: Reject

---

## 3. Performance Management & Appraisals

### Purpose
Oversee performance review cycles, appraisal approvals, and employee development.

### Workflow

```mermaid
graph TD
    Start([Performance Review Cycle Start]) --> InitiateCycle[Initiate Review Cycle<br/>Semi-Annual or Annual]
    
    InitiateCycle --> NotifySupervisors[Notify Supervisors<br/>to Complete Reviews]
    NotifySupervisors --> MonitorProgress[Monitor Review Progress]
    
    MonitorProgress --> CheckComplete{All Reviews Submitted?}
    CheckComplete -->|No| SendReminders[Send Reminders<br/>to Supervisors]
    SendReminders --> MonitorProgress
    CheckComplete -->|Yes| ReviewAppraisals[Review All Appraisals]
    
    ReviewAppraisals --> IdentifyOutliers{Identify Outliers}
    IdentifyOutliers --> HighPerformers[High Performers<br/>Recognition & Rewards]
    IdentifyOutliers --> LowPerformers[Low Performers<br/>Improvement Plans]
    IdentifyOutliers --> AveragePerformers[Average Performers<br/>Development Plans]
    
    HighPerformers --> RecommendPromotion[Recommend Promotions<br/>or Salary Increases]
    LowPerformers --> CreatePIP[Create Performance<br/>Improvement Plan]
    AveragePerformers --> IdentifyTraining[Identify Training Needs]
    
    RecommendPromotion --> ForwardAdmin[Forward to Office Admin]
    CreatePIP --> MonitorPIP[Monitor PIP Progress<br/>30/60/90 Days]
    IdentifyTraining --> ScheduleTraining[Schedule Training Sessions]
    
    ForwardAdmin --> ApprovalDecision{Office Admin Approval}
    ApprovalDecision -->|Approved| ImplementChanges[Implement Salary/Role Changes]
    ApprovalDecision -->|Rejected| ReviewAgain[Review and Adjust Recommendation]
    
    MonitorPIP --> PIPOutcome{PIP Outcome}
    PIPOutcome -->|Improved| ContinueEmployment[Continue Employment<br/>Regular Monitoring]
    PIPOutcome -->|No Improvement| TerminationRecommendation[Recommend Termination]
    
    ImplementChanges --> NotifyEmployees[Notify Employees<br/>of Changes]
    ScheduleTraining --> NotifyEmployees
    ContinueEmployment --> NotifyEmployees
    TerminationRecommendation --> NotifyEmployees
    
    NotifyEmployees --> Complete([Cycle Complete])
```

### Performance Review Cycle

**Semi-Annual Cycle:**
- Mid-year review (June)
- Year-end review (December)

**Review Components:**
1. Self-assessment by employee
2. Supervisor rating and comments
3. HR Manager review and validation
4. Performance discussion meeting
5. Goal setting for next period

### Appraisal Rating Scale

| Rating | Description | % Expected |
|--------|-------------|------------|
| 5 - Outstanding | Consistently exceeds expectations | 5-10% |
| 4 - Exceeds Expectations | Frequently exceeds expectations | 15-20% |
| 3 - Meets Expectations | Solid, reliable performance | 60-70% |
| 2 - Needs Improvement | Below expectations, requires support | 5-10% |
| 1 - Unsatisfactory | Significantly below expectations | 0-5% |

### HR Manager Oversight

**Review Responsibilities:**
1. Validate rating distributions (avoid all 3s or all 5s)
2. Check for rating inflation or deflation
3. Ensure consistency across departments
4. Review comments for completeness
5. Identify high potential employees
6. Flag performance concerns
7. Recommend salary adjustments
8. Approve promotion recommendations

**Performance Improvement Plans (PIP):**
- Create PIP for ratings below 2.5
- Define specific improvement goals
- Set measurable milestones (30/60/90 days)
- Assign mentor or coach
- Monitor progress weekly
- Document all interactions
- Make final recommendation

---

## 4. Workforce Analytics & Reporting

### Purpose
Generate insights on workforce metrics, trends, and strategic planning.

### Workflow

```mermaid
graph TD
    Start([Generate Report]) --> ReportType{Select Report Type}
    
    ReportType --> HeadcountReport[Headcount Report]
    ReportType --> TurnoverReport[Turnover Analysis]
    ReportType --> AttendanceReport[Attendance Analytics]
    ReportType --> LeaveReport[Leave Utilization]
    ReportType --> PerformanceReport[Performance Distribution]
    
    HeadcountReport --> FilterData[Apply Filters<br/>Department, Position, Date]
    TurnoverReport --> FilterData
    AttendanceReport --> FilterData
    LeaveReport --> FilterData
    PerformanceReport --> FilterData
    
    FilterData --> GenerateCharts[Generate Charts & Visualizations]
    GenerateCharts --> AnalyzeTrends[Analyze Trends<br/>& Patterns]
    
    AnalyzeTrends --> IdentifyIssues{Issues Identified?}
    IdentifyIssues -->|Yes| CreateActionPlan[Create Action Plan]
    IdentifyIssues -->|No| DocumentFindings[Document Findings]
    
    CreateActionPlan --> ShareWithLeadership[Share with Office Admin<br/>& Leadership]
    DocumentFindings --> ShareWithLeadership
    
    ShareWithLeadership --> ImplementActions[Implement Recommended Actions]
    ImplementActions --> Complete([Reporting Complete])
```

### Key Reports

**1. Headcount Report**
- Total employee count
- Breakdown by department, position, employment type
- New hires vs. separations
- Trend analysis (month-over-month, year-over-year)

**2. Turnover Analysis**
- Voluntary vs. involuntary turnover
- Turnover rate by department
- Exit reasons analysis
- Retention strategies effectiveness

**3. Attendance Analytics**
- Overall attendance rate
- Late arrivals and early departures
- Absence patterns (by day, employee, department)
- RFID timekeeping accuracy

**4. Leave Utilization**
- Leave balances by employee
- Leave taken vs. available
- Leave patterns (seasonal, departmental)
- Carryover and forfeited leaves

**5. Performance Distribution**
- Rating distribution across company
- Department comparison
- Correlation with tenure, position, department
- Training effectiveness

### Strategic Insights

**Workforce Planning:**
- Identify hiring needs based on turnover
- Forecast headcount growth
- Budget planning for salaries and benefits
- Succession planning for key positions

**Risk Identification:**
- High turnover departments (investigate causes)
- Attendance issues (potential engagement problems)
- Performance clusters (training needs)
- Leave abuse patterns

---

## 5. Employee Directory & Records Management

### Purpose
Maintain accurate employee records and ensure data compliance.

### Workflow

```mermaid
graph TD
    Start([Access Employee Directory]) --> Action{Select Action}
    
    Action --> ViewRecords[View Employee Records]
    Action --> UpdateRecords[Update Employee Information]
    Action --> AuditRecords[Audit Records Compliance]
    
    ViewRecords --> SearchEmployee[Search Employee<br/>by Name, ID, Department]
    SearchEmployee --> ViewDetails[View Full Profile<br/>Employment History<br/>Performance Records]
    ViewDetails --> ExportData{Export Data?}
    ExportData -->|Yes| GenerateExport[Generate PDF/Excel]
    ExportData -->|No| CloseView[Close View]
    
    UpdateRecords --> SelectEmployee[Select Employee]
    SelectEmployee --> UpdateFields[Update Fields<br/>Position, Salary, Status]
    UpdateFields --> ValidateChanges[Validate Changes]
    ValidateChanges --> RequireApproval{Requires Office Admin Approval?}
    RequireApproval -->|Yes| ForwardApproval[Forward to Office Admin]
    RequireApproval -->|No| SaveChanges[Save Changes]
    ForwardApproval --> SaveChanges
    SaveChanges --> LogChange[Log Change History]
    
    AuditRecords --> CheckCompleteness[Check Record Completeness]
    CheckCompleteness --> IdentifyGaps{Gaps Identified?}
    IdentifyGaps -->|Yes| CreateTasks[Create Tasks for HR Staff<br/>to Complete Records]
    IdentifyGaps -->|No| GenerateAuditReport[Generate Audit Report]
    CreateTasks --> GenerateAuditReport
    
    GenerateExport --> Complete([Task Complete])
    CloseView --> Complete
    LogChange --> Complete
    GenerateAuditReport --> Complete
```

### Record Management Responsibilities

**Employee Records Access:**
- View all employee information
- Access employment history
- Review performance records
- View compensation details
- Check leave balances and history

**Update Authority:**
- Update position changes (with Office Admin approval)
- Update employment status (active, on leave, resigned)
- Update department assignments
- Add notes and comments
- Update contact information

**Data Compliance:**
- Ensure all employee records are complete
- Verify document authenticity
- Maintain data privacy and confidentiality
- Conduct quarterly compliance audits
- Archive records per retention policy

---

## 6. Compliance & Audits

### Purpose
Ensure HR processes comply with labor laws and company policies.

### Workflow

```mermaid
graph TD
    Start([Initiate Compliance Check]) --> AuditType{Select Audit Type}
    
    AuditType --> LaborLaw[Labor Law Compliance]
    AuditType --> PayrollCompliance[Payroll Compliance]
    AuditType --> LeaveCompliance[Leave Policy Compliance]
    AuditType --> RecordCompliance[Record Keeping Compliance]
    
    LaborLaw --> CheckMinWage[Check Minimum Wage Compliance]
    LaborLaw --> CheckOT[Check Overtime Compliance]
    LaborLaw --> Check13thMonth[Check 13th Month Pay]
    CheckMinWage --> LaborIssues{Issues Found?}
    CheckOT --> LaborIssues
    Check13thMonth --> LaborIssues
    
    PayrollCompliance --> CheckGovtRemit[Check Government Remittances<br/>SSS, PhilHealth, Pag-IBIG]
    CheckGovtRemit --> CheckTax[Check Tax Withholding]
    CheckTax --> PayrollIssues{Issues Found?}
    
    LeaveCompliance --> CheckLeaveAccrual[Check Leave Accrual Accuracy]
    CheckLeaveAccrual --> CheckLeaveUsage[Check Leave Usage Patterns]
    CheckLeaveUsage --> LeaveIssues{Issues Found?}
    
    RecordCompliance --> Check201Files[Check 201 File Completeness]
    Check201Files --> CheckRetention[Check Retention Compliance]
    CheckRetention --> RecordIssues{Issues Found?}
    
    LaborIssues -->|Yes| DocumentFindings[Document Findings]
    PayrollIssues -->|Yes| DocumentFindings
    LeaveIssues -->|Yes| DocumentFindings
    RecordIssues -->|Yes| DocumentFindings
    
    LaborIssues -->|No| PassAudit[Audit Passed]
    PayrollIssues -->|No| PassAudit
    LeaveIssues -->|No| PassAudit
    RecordIssues -->|No| PassAudit
    
    DocumentFindings --> CreateCAPA[Create Corrective Action Plan]
    CreateCAPA --> AssignResponsible[Assign Responsible Person<br/>HR Staff or Payroll Officer]
    AssignResponsible --> SetDeadline[Set Deadline]
    SetDeadline --> MonitorProgress[Monitor Progress]
    MonitorProgress --> VerifyFixed{Issue Resolved?}
    VerifyFixed -->|No| Escalate[Escalate to Office Admin]
    VerifyFixed -->|Yes| CloseAudit[Close Audit Finding]
    
    PassAudit --> GenerateReport[Generate Audit Report]
    CloseAudit --> GenerateReport
    Escalate --> GenerateReport
    
    GenerateReport --> Complete([Audit Complete])
```

### Compliance Checklist

**Labor Law Compliance:**
- ✅ Minimum wage compliance (per region)
- ✅ Overtime rate compliance (1.25x regular, 2.0x holiday)
- ✅ Rest day compliance (1 day per week minimum)
- ✅ 13th month pay computation and timing
- ✅ Maternity/paternity leave compliance
- ✅ Safe working conditions

**Payroll Compliance:**
- ✅ SSS remittance (monthly, on time)
- ✅ PhilHealth remittance (monthly, on time)
- ✅ Pag-IBIG remittance (monthly, on time)
- ✅ BIR tax withholding and remittance
- ✅ Payslip distribution (every payday)
- ✅ Government rate updates (annually)

**Leave Compliance:**
- ✅ Service Incentive Leave (5 days minimum)
- ✅ Leave accrual accuracy
- ✅ Leave balance tracking
- ✅ Leave conversion (if applicable)
- ✅ Maternity/paternity leave entitlement

**Record Compliance:**
- ✅ 201 files (complete employee records)
- ✅ Daily Time Records (DTR)
- ✅ Payroll registers
- ✅ Leave applications
- ✅ Performance appraisals
- ✅ Retention period compliance (varies by document)

---

## Common Tasks

### Daily Tasks
- ✅ Review and approve pending leave requests (3-5 days duration)
- ✅ Monitor recruitment pipeline
- ✅ Respond to employee inquiries
- ✅ Review timekeeping exceptions
- ✅ Check dashboard metrics

### Weekly Tasks
- ✅ Review workforce analytics reports
- ✅ Conduct candidate interviews
- ✅ Hold HR staff meetings
- ✅ Review and approve employee record updates
- ✅ Monitor performance improvement plans

### Monthly Tasks
- ✅ Generate monthly HR reports (headcount, turnover, attendance)
- ✅ Review payroll calculations (before Office Admin approval)
- ✅ Conduct compliance checks
- ✅ Update workforce forecasts
- ✅ Review training needs

### Quarterly Tasks
- ✅ Conduct HR compliance audit
- ✅ Review compensation structures
- ✅ Analyze turnover trends
- ✅ Update succession plans
- ✅ Strategic workforce planning meeting

### Annual Tasks
- ✅ Initiate performance review cycles (2x per year)
- ✅ Update HR policies
- ✅ Annual salary review and recommendations
- ✅ Government compliance reporting (BIR, DOLE)
- ✅ HR budget planning

---

## Key Performance Indicators (KPIs)

| KPI | Target | Measurement |
|-----|--------|-------------|
| Leave Approval Time | < 24 hours | Time from submission to approval |
| Employee Turnover Rate | < 15% annually | (Separations / Avg Headcount) x 100 |
| Time to Fill (Hiring) | < 30 days | Days from req opening to hire |
| Attendance Rate | > 95% | (Days Present / Total Days) x 100 |
| Performance Review Completion | 100% | % of employees reviewed on time |
| Compliance Audit Pass Rate | 100% | % of audits with no findings |

---

## Best Practices

### Leave Approval
- ✅ Review within 24 hours
- ✅ Consider workforce impact, not just balance
- ✅ Communicate rejection reasons clearly
- ✅ Suggest alternative dates when rejecting
- ✅ Check historical leave patterns

### Hiring Decisions
- ✅ Use structured interview questions
- ✅ Score candidates objectively
- ✅ Document interview notes thoroughly
- ✅ Check references before final recommendation
- ✅ Consider cultural fit and team dynamics

### Performance Management
- ✅ Ensure rating consistency across departments
- ✅ Provide specific feedback, not generic
- ✅ Address performance issues early
- ✅ Recognize and reward high performers
- ✅ Create actionable development plans

### Compliance
- ✅ Stay updated on labor law changes
- ✅ Conduct regular compliance audits
- ✅ Document all HR decisions
- ✅ Maintain confidentiality
- ✅ Escalate issues promptly

---

## Troubleshooting

### Common Issues

**Issue: Leave request stuck in pending**
- Check if request requires Office Admin approval (6+ days)
- Verify no system errors in approval workflow
- Check if employee has sufficient balance
- Contact Office Admin if escalated

**Issue: Interview no-show**
- Review notification sent to applicant
- Check if interview details were clear
- Reschedule if legitimate reason
- Mark as "Failed to Appear" in ATS

**Issue: Performance rating distribution unrealistic (all 3s or all 5s)**
- Meet with supervisor to discuss ratings
- Explain forced distribution concept
- Request justification for outlier ratings
- Provide coaching on effective performance reviews

**Issue: Compliance audit finding**
- Document the issue thoroughly
- Create corrective action plan immediately
- Assign responsible person with deadline
- Escalate to Office Admin if critical
- Follow up until resolved

## Immutable Ledger & Replay Monitoring

- Attendance, overtime, and MDTR comparisons used in HR approvals must align with the PostgreSQL ledger (`rfid_ledger`) captured by the Replayable Event-Log Verification Layer.
- HR Managers should review the replay layer's alerting/metrics (ledger commit latency, sequence gaps, hash mismatches, replay backlog) before granting approvals tied to attendance or payroll-impacting actions.

---

## Related Documentation
- [System Overview](./00-system-overview.md)
- [Office Admin Workflow](./02-office-admin-workflow.md)
- [HR Staff Workflow](./04-hr-staff-workflow.md)
- [Payroll Officer Workflow](./05-payroll-officer-workflow.md)
- [RBAC Matrix](../RBAC_MATRIX.md)
- [Performance Appraisal Module](../APPRAISAL_MODULE.md)

---

**Last Updated**: November 29, 2025  
**Role**: HR Manager  
**Access Level**: Conditional Approval Authority (Requires Office Admin for final approval on major decisions)
