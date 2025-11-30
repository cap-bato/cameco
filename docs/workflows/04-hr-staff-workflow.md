# HR Staff Workflow

## Role Overview
**Focus**: Day-to-day HR operations, employee services, and administrative tasks

### Core Responsibilities
- 📝 Employee onboarding and offboarding
- 🔍 Applicant screening and ATS management
- 📄 Document processing and 201 file management
- 📅 Leave request processing (submission on behalf of employees)
- 📊 Timekeeping monitoring and exception handling
- 👥 Employee inquiries and support
- 📋 HR administrative tasks and data entry

---

## Dashboard Overview

```mermaid
graph TB
    StaffDash[HR Staff Dashboard]
    
    StaffDash --> PendingTasks[Pending Tasks<br/>& Reminders]
    StaffDash --> EmployeeServices[Employee Services<br/>& Support]
    StaffDash --> Recruitment[Recruitment<br/>& ATS]
    StaffDash --> Onboarding[Onboarding<br/>New Hires]
    StaffDash --> Documents[Document<br/>Management]
    StaffDash --> Timekeeping[Timekeeping<br/>Monitoring]
    
    style StaffDash fill:#009688,color:#fff
    style PendingTasks fill:#f44336,color:#fff
    style EmployeeServices fill:#2196f3,color:#fff
    style Recruitment fill:#4caf50,color:#fff
    style Onboarding fill:#ff9800,color:#fff
    style Documents fill:#9c27b0,color:#fff
    style Timekeeping fill:#00bcd4,color:#fff
```

---

## 1. Employee Onboarding

### Purpose
Process new hire paperwork, setup accounts, and facilitate smooth onboarding experience.

### Workflow

```mermaid
graph TD
    Start([New Hire Approved<br/>by Office Admin]) --> ReceiveDocuments[Receive Pre-Employment<br/>Documents from Applicant]
    
    ReceiveDocuments --> ValidateDocuments[Validate Documents<br/>Completeness & Authenticity]
    ValidateDocuments --> Complete{All Documents Complete?}
    
    Complete -->|No| RequestMissing[Request Missing Documents<br/>from New Hire]
    RequestMissing --> ReceiveDocuments
    Complete -->|Yes| Create201File[Create 201 File]
    
    Create201File --> EnterHRIS[Enter Employee Data<br/>into HRIS]
    EnterHRIS --> AssignEmployeeID[Assign Employee ID]
    AssignEmployeeID --> CreateUserAccount[Create System User Account]
    CreateUserAccount --> AssignRole[Assign User Role<br/>Based on Position]
    
    AssignRole --> IssueRFID[Issue RFID Card<br/>Register in System]
    IssueRFID --> SetupPayroll[Setup in Payroll Module<br/>Salary, Deductions, Bank]
    SetupPayroll --> CreateWelcomePack[Prepare Welcome Pack<br/>ID, Handbook, Policies]
    
    CreateWelcomePack --> ScheduleOrientation[Schedule Orientation<br/>Day 1 Activities]
    ScheduleOrientation --> NotifyNewHire[Notify New Hire<br/>Start Date & Requirements]
    NotifyNewHire --> NotifyDept[Notify Department<br/>of New Hire Start Date]
    
    NotifyDept --> OnStartDate[On Start Date:<br/>Conduct Orientation]
    OnStartDate --> FacilityTour[Facility Tour<br/>Introductions]
    FacilityTour --> SystemTraining[System Training<br/>RFID, Leave, Policies]
    SystemTraining --> DeptHandover[Handover to Department<br/>Supervisor]
    
    DeptHandover --> FollowUp[Follow-up after 1 week<br/>Address Issues]
    FollowUp --> Complete201[Complete Onboarding Checklist]
    Complete201 --> LogOnboarding[Log in Onboarding Module]
    LogOnboarding --> Completed([Onboarding Complete])
```

### Pre-Employment Document Checklist

**Required Documents:**
- ✅ Resume / Curriculum Vitae
- ✅ Birth certificate (PSA copy)
- ✅ Valid government IDs (2 IDs)
- ✅ TIN (Tax Identification Number)
- ✅ SSS number
- ✅ PhilHealth number
- ✅ Pag-IBIG number
- ✅ Educational credentials (diploma, transcript)
- ✅ NBI clearance (National Bureau of Investigation)
- ✅ Medical certificate (fit to work)
- ✅ Previous employment certificate (if applicable)
- ✅ 2x2 ID photos (4 copies)
- ✅ Bank account details (for future payroll)

**Optional Documents:**
- Professional license (if applicable)
- Character references
- Training certificates
- Portfolio or work samples

### Account Setup Tasks

**HRIS Account:**
1. Enter personal information
2. Enter employment details (position, department, start date)
3. Enter compensation details
4. Upload scanned documents
5. Assign employee ID (auto-generated or manual)
6. Create user account credentials
7. Assign user role based on position

**RFID Card:**
1. Generate new RFID card
2. Encode employee ID
3. Register card in timekeeping system
4. Test card tap on edge device
5. Provide card to employee on Day 1

**Payroll Setup:**
1. Enter basic salary
2. Setup allowances (if any)
3. Setup deductions (SSS, PhilHealth, Pag-IBIG, tax)
4. Add bank account (for future bank transfer)
5. Set payroll schedule (15th and 30th)

### Orientation Checklist (Day 1)

**Morning (HR Orientation):**
- ✅ Welcome and introductions
- ✅ Company overview and values
- ✅ Organizational structure
- ✅ HR policies and procedures
- ✅ Employee handbook review
- ✅ Benefits and compensation overview
- ✅ Leave policies
- ✅ Timekeeping and RFID card usage
- ✅ System login and demo

**Afternoon (Department Orientation):**
- ✅ Facility tour
- ✅ Meet team members
- ✅ Workstation setup
- ✅ Introduction to supervisor
- ✅ Department processes and workflows
- ✅ Role-specific training
- ✅ First week schedule

---

## 2. Applicant Screening & ATS Management

### Purpose
Screen applications, shortlist candidates, and manage recruitment pipeline.

### Workflow

```mermaid
graph TD
    Start([Job Posting Active]) --> ReceiveApplications[Receive Applications<br/>Email, Walk-in, Job Board]
    
    ReceiveApplications --> EnterATS[Enter Application<br/>into ATS Module]
    EnterATS --> InitialScreen[Initial Screening<br/>Resume Review]
    
    InitialScreen --> MeetsMin{Meets Minimum<br/>Requirements?}
    MeetsMin -->|No| RejectEarly[Reject Application<br/>Email Notification]
    MeetsMin -->|Yes| PhoneScreen{Conduct Phone<br/>Screening?}
    
    PhoneScreen -->|Yes| CallApplicant[Call Applicant<br/>Brief Interview]
    CallApplicant --> PhonePass{Passed Phone Screen?}
    PhonePass -->|No| RejectPhone[Reject After Phone Screen]
    PhonePass -->|Yes| Shortlist[Add to Shortlist]
    PhoneScreen -->|No| Shortlist
    
    Shortlist --> NotifyManager[Notify HR Manager<br/>Shortlisted Candidates]
    NotifyManager --> ManagerReview[HR Manager Reviews<br/>& Schedules Interview]
    ManagerReview --> UpdateATS[Update ATS Status<br/>to "Interview Scheduled"]
    
    UpdateATS --> InterviewConducted[HR Manager Conducts Interview]
    InterviewConducted --> InterviewResult{Interview Result}
    InterviewResult -->|Rejected| RejectInterview[Update ATS: Rejected<br/>Email Notification]
    InterviewResult -->|Approved| HiringRecommendation[HR Manager Recommends<br/>Office Admin Approves]
    
    HiringRecommendation --> FinalDecision{Office Admin<br/>Final Decision}
    FinalDecision -->|Rejected| RejectFinal[Update ATS: Not Hired<br/>Email Notification]
    FinalDecision -->|Approved| SendJobOffer[Send Job Offer<br/>via Email]
    
    SendJobOffer --> ApplicantResponse{Applicant Response}
    ApplicantResponse -->|Accepted| ProcessOnboarding[Proceed to Onboarding]
    ApplicantResponse -->|Declined| OfferDeclined[Update ATS: Offer Declined<br/>Consider Next Candidate]
    ApplicantResponse -->|No Response| FollowUpOffer[Follow-up After 3 Days]
    FollowUpOffer --> ApplicantResponse
    
    RejectEarly --> LogATS[Log in ATS]
    RejectPhone --> LogATS
    RejectInterview --> LogATS
    RejectFinal --> LogATS
    OfferDeclined --> LogATS
    ProcessOnboarding --> LogATS
    
    LogATS --> Complete([Process Complete])
```

### Initial Screening Criteria

**Minimum Requirements Check:**
- ✅ Educational attainment (matches job requirement)
- ✅ Years of experience (minimum required)
- ✅ Required skills (technical or functional)
- ✅ Location availability (willing to work on-site)
- ✅ Salary expectation (within budget range)

**Resume Quality:**
- Clear and well-organized
- Relevant work experience highlighted
- Skills match job description
- Contact information complete
- Professional formatting

### Phone Screening (Optional)

**When to Use:**
- High volume of applications
- Remote/OFW applicants
- Clarify resume details
- Assess communication skills
- Verify availability and salary expectations

**Key Questions:**
1. Current employment status
2. Notice period (if employed)
3. Salary expectations
4. Availability for in-person interview
5. Clarify work experience details
6. Assess communication skills and enthusiasm

### ATS Status Flow

| Status | Description | Actions |
|--------|-------------|---------|
| **New** | Application received | Enter into ATS |
| **Screening** | Under initial review | Resume review, phone screen |
| **Shortlisted** | Passed screening | Notify HR Manager |
| **Interview Scheduled** | Interview date set | Confirm with applicant |
| **Interviewed** | Interview completed | Await HR Manager decision |
| **Recommended** | HR Manager approved | Forward to Office Admin |
| **Offer Sent** | Job offer sent | Await applicant response |
| **Hired** | Offer accepted | Proceed to onboarding |
| **Rejected** | Not selected | Archive application |
| **Offer Declined** | Applicant declined | Consider next candidate |

---

## 3. Leave Request Processing

### Purpose
Submit leave requests on behalf of employees (who don't have system access).

### Workflow

```mermaid
graph TD
    Start([Employee Submits Leave Form<br/>Paper Form]) --> ReceiveForm[Receive Leave Form<br/>from Employee]
    
    ReceiveForm --> ValidateForm[Validate Form<br/>Complete & Signed]
    ValidateForm --> FormValid{Form Valid?}
    
    FormValid -->|No| ReturnForm[Return to Employee<br/>for Correction]
    FormValid -->|Yes| CheckAttachment{Requires Supporting<br/>Documents?}
    
    CheckAttachment -->|Yes| CheckDocuments[Check Medical Cert<br/>or Other Proof]
    CheckDocuments --> DocsComplete{Documents Complete?}
    DocsComplete -->|No| ReturnForm
    DocsComplete -->|Yes| EnterSystem[Enter Leave Request<br/>into HRIS]
    CheckAttachment -->|No| EnterSystem
    
    EnterSystem --> SelectEmployee[Select Employee<br/>from System]
    SelectEmployee --> EnterDetails[Enter Leave Details<br/>Type, Dates, Reason]
    EnterDetails --> UploadAttachment[Upload Supporting<br/>Documents if any]
    UploadAttachment --> CheckBalance[System Checks<br/>Leave Balance]
    
    CheckBalance --> BalanceSufficient{Balance Sufficient?}
    BalanceSufficient -->|No| InformEmployee[Inform Employee<br/>Insufficient Balance]
    InformEmployee --> CancelRequest[Cancel Request<br/>or File Leave Without Pay]
    
    BalanceSufficient -->|Yes| SubmitRequest[Submit Request<br/>to Approval Workflow]
    SubmitRequest --> DurationCheck{Leave Duration}
    
    DurationCheck -->|1-2 days| AutoApproved[Auto-Approved<br/>by System]
    DurationCheck -->|3-5 days| PendingManager[Pending HR Manager<br/>Approval]
    DurationCheck -->|6+ days| PendingBoth[Pending HR Manager<br/>+ Office Admin]
    
    AutoApproved --> NotifyEmployee[Notify Employee<br/>Approved]
    PendingManager --> ManagerDecision{HR Manager Decision}
    ManagerDecision -->|Approved| NotifyEmployee
    ManagerDecision -->|Rejected| NotifyRejection[Notify Employee<br/>Rejection Reason]
    
    PendingBoth --> ManagerReview[HR Manager<br/>Conditional Approval]
    ManagerReview --> AdminReview[Office Admin<br/>Final Decision]
    AdminReview --> AdminDecision{Office Admin Decision}
    AdminDecision -->|Approved| NotifyEmployee
    AdminDecision -->|Rejected| NotifyRejection
    
    NotifyEmployee --> FileForm[File Paper Form<br/>in 201 File]
    NotifyRejection --> FileForm
    CancelRequest --> FileForm
    FileForm --> Complete([Process Complete])
```

### Leave Form Validation

**Required Fields:**
- ✅ Employee name and ID
- ✅ Department
- ✅ Leave type (Vacation, Sick, Emergency, etc.)
- ✅ Leave dates (from - to)
- ✅ Number of days
- ✅ Reason for leave
- ✅ Employee signature
- ✅ Date submitted

**Supporting Documents (if required):**
- **Sick Leave (3+ days)**: Medical certificate
- **Emergency Leave**: Death certificate (bereavement), hospital records, police report, etc.
- **Maternity Leave**: Medical certificate from OB-GYN
- **Solo Parent Leave**: Solo parent certificate

### Data Entry into HRIS

**Steps:**
1. Login to HRIS
2. Navigate to Leave Management module
3. Click "Submit Leave Request" (as HR Staff on behalf of employee)
4. Search and select employee
5. Choose leave type from dropdown
6. Enter leave start date
7. Enter leave end date (system auto-calculates days)
8. Enter reason/remarks
9. Upload supporting documents (if any)
10. Click "Submit" button
11. System routes to approval workflow automatically

**System Validations:**
- Checks if employee has sufficient balance
- Checks for overlapping leave requests
- Checks minimum advance notice (if applicable)
- Routes to appropriate approver based on duration

### Common Leave Scenarios

**Scenario 1: Sick Leave with Medical Certificate**
- Employee files 5-day sick leave
- Requires medical certificate
- HR Staff validates certificate authenticity
- Enters into system
- Routes to HR Manager for approval

**Scenario 2: Vacation Leave (1 day)**
- Employee files 1-day vacation leave
- No documents required
- HR Staff enters into system
- Auto-approved if balance sufficient
- Employee notified via email

**Scenario 3: Emergency Leave**
- Employee has family emergency
- Verbal notice to HR Staff
- HR Staff enters as emergency leave
- Supporting documents can be submitted later
- Routes to HR Manager for approval

---

## 4. Timekeeping Monitoring & Exception Handling

### Purpose
Monitor daily attendance, identify exceptions, and resolve timekeeping issues.

### Workflow

```mermaid
graph TD
    Start([Daily Attendance<br/>Monitoring]) --> PullReport[Pull Daily Attendance Report<br/>from Timekeeping Module]
    
    PullReport --> IdentifyExceptions[Identify Exceptions<br/>Missing, Late, Undertime]
    IdentifyExceptions --> ExceptionType{Exception Type}
    
    ExceptionType --> MissingPunch[Missing Time Punch]
    ExceptionType --> LateArrival[Late Arrival]
    ExceptionType --> EarlyDeparture[Early Departure / Undertime]
    ExceptionType --> NoShow[No Show / Absent]
    
    MissingPunch --> ContactEmployee[Contact Employee<br/>via Phone/Email]
    ContactEmployee --> EmployeeResponse{Employee Response}
    EmployeeResponse -->|Forgot to Punch| ManualEntry[Create Manual Time Entry<br/>with Justification]
    EmployeeResponse -->|RFID Card Issue| TestCard[Test RFID Card<br/>Re-register if Needed]
    EmployeeResponse -->|On Leave| CheckLeave[Check if Leave Approved]
    CheckLeave -->|Approved| MarkAsOnLeave[Mark as On Leave]
    CheckLeave -->|Not Approved| FollowUpLeave[Follow-up Leave Application]
    
    TestCard --> CardFixed{Card Fixed?}
    CardFixed -->|Yes| IssueNewCard[Issue New RFID Card<br/>if Defective]
    CardFixed -->|No| ManualEntry
    
    LateArrival --> CheckGrace[Check Grace Period<br/>15 minutes]
    CheckGrace --> WithinGrace{Within Grace?}
    WithinGrace -->|Yes| NoAction[No Action Required]
    WithinGrace -->|No| LogLate[Log Late Arrival<br/>System Auto-calculates<br/>Deduction]
    
    EarlyDeparture --> CheckApproval{Approved Undertime?}
    CheckApproval -->|Yes| LogApprovedUndertime[Log as Approved<br/>Undertime]
    CheckApproval -->|No| LogUnapprovedUndertime[Log as Unapproved<br/>System Calculates Deduction]
    
    NoShow --> CheckLeave2[Check Leave Status]
    CheckLeave2 -->|On Leave| MarkAsOnLeave
    CheckLeave2 -->|No Leave| MarkAbsent[Mark as Absent<br/>Without Leave]
    MarkAbsent --> NotifySupervisor[Notify Department Supervisor<br/>& HR Manager]
    
    ManualEntry --> LogException[Log Exception<br/>in Timekeeping Module]
    IssueNewCard --> LogException
    MarkAsOnLeave --> LogException
    FollowUpLeave --> LogException
    NoAction --> LogException
    LogLate --> LogException
    LogApprovedUndertime --> LogException
    LogUnapprovedUndertime --> LogException
    NotifySupervisor --> LogException
    
    LogException --> UpdatePayroll[Update Payroll Module<br/>with Adjustments]
    UpdatePayroll --> Complete([Exception Handled])
```

### Daily Monitoring Tasks

**Morning Routine (8:30 AM):**
1. Pull timekeeping report for today
2. Check who has not punched in yet
3. Cross-check with approved leaves
4. Identify potential no-shows or late arrivals

**End of Day Routine (5:30 PM):**
1. Pull end-of-day timekeeping report
2. Check missing out-punches
3. Identify early departures (undertime)
4. Log all exceptions before day ends

### Exception Types & Handling

**Missing Time Punch:**
- **Cause**: Forgot to tap RFID, card malfunction, edge device offline
- **Action**: Contact employee, verify actual attendance, create manual entry with supervisor approval
- **Documentation**: Manual time entry form, supervisor signature

**Late Arrival:**
- **Grace Period**: 15 minutes (configurable by Office Admin)
- **Within Grace**: No deduction
- **Beyond Grace**: System auto-calculates late deduction
- **Action**: Log in system, no manual intervention unless contested

**Undertime / Early Departure:**
- **Approved**: With prior approval (medical appointment, personal emergency)
- **Unapproved**: Left early without approval
- **Action**: Log as approved or unapproved, system calculates proportional deduction

**Absent:**
- **With Approved Leave**: Mark as on leave, no deduction
- **Without Leave**: Mark as absent, full day deduction
- **Action**: Notify supervisor and HR Manager, follow-up with employee

### RFID Card Issues

**Common Problems:**
- Card not reading (defective card, reader issue)
- Card not registered in system
- Card demagnetized
- Employee lost card

**Resolution:**
1. Test card on edge device
2. Check if card registered in system
3. Re-register card if needed
4. Issue replacement card if defective
5. Log replacement in system
6. Charge employee for lost/damaged card (per policy)

---

## 5. Document Processing & 201 File Management

### Purpose
Maintain accurate and complete employee records in physical and digital formats.

### Workflow

```mermaid
graph TD
    Start([Document Request]) --> RequestType{Document Type}
    
    RequestType --> NewEmployee[New Employee Documents]
    RequestType --> UpdateExisting[Update Existing Records]
    RequestType --> GenerateCert[Generate Certificate<br/>COE, Certificate of Deductions]
    
    NewEmployee --> ReceiveDocs[Receive Pre-Employment<br/>Documents]
    ReceiveDocs --> ValidateDocs[Validate Documents<br/>Authenticity & Completeness]
    ValidateDocs --> ScanDocs[Scan All Documents<br/>Digital Copies]
    ScanDocs --> Upload[Upload to HRIS<br/>Document Module]
    Upload --> CreatePhysical[Create Physical 201 File<br/>Organize by Category]
    CreatePhysical --> LabelFile[Label File<br/>Employee Name & ID]
    LabelFile --> StoreFile[Store in Filing Cabinet<br/>Alphabetical Order]
    
    UpdateExisting --> ReceiveUpdate[Receive Updated Document<br/>New Cert, Training, etc.]
    ReceiveUpdate --> ValidateUpdate[Validate Document]
    ValidateUpdate --> ScanUpdate[Scan Document]
    ScanUpdate --> UploadUpdate[Upload to HRIS<br/>Tag to Employee]
    UploadUpdate --> Add201[Add to Physical 201 File]
    Add201 --> LogUpdate[Log Update in System]
    
    GenerateCert --> SelectEmployee[Select Employee]
    SelectEmployee --> CertType{Certificate Type}
    CertType --> COE[Certificate of Employment]
    CertType --> TaxCert[Certificate of Deductions<br/>BIR 2316]
    CertType --> CompensationCert[Certificate of Compensation]
    
    COE --> FillCOE[Fill COE Template<br/>Employee Details<br/>Employment Dates<br/>Position]
    FillCOE --> PrintCOE[Print on Company Letterhead]
    PrintCOE --> SignCOE[For HR Manager Signature]
    SignCOE --> ProvideCOE[Provide to Employee]
    
    TaxCert --> Generate2316[Generate BIR 2316<br/>from Payroll Data]
    Generate2316 --> Verify2316[Verify Accuracy<br/>with Payroll Officer]
    Verify2316 --> Print2316[Print BIR Form]
    Print2316 --> Sign2316[For Authorized Signatory]
    Sign2316 --> Provide2316[Provide to Employee]
    
    CompensationCert --> FillCompCert[Fill Template<br/>Salary, Allowances]
    FillCompCert --> PrintCompCert[Print & Sign]
    PrintCompCert --> ProvideCompCert[Provide to Employee]
    
    StoreFile --> Complete([Task Complete])
    LogUpdate --> Complete
    ProvideCOE --> Complete
    Provide2316 --> Complete
    ProvideCompCert --> Complete
```

### 201 File Organization

**File Sections (Physical Folder):**
1. **Personal Information**
   - Bio-data form
   - Birth certificate
   - Valid IDs
   - 2x2 photos
   - SSS, PhilHealth, Pag-IBIG, TIN

2. **Employment Documents**
   - Application form
   - Resume / CV
   - Interview notes
   - Job offer letter
   - Employment contract
   - Job description

3. **Educational & Professional**
   - Diploma / transcript of records
   - Professional licenses
   - Training certificates
   - Seminars attended

4. **Previous Employment**
   - Certificate of employment
   - Clearance from previous employer
   - Service record

5. **Medical & Background**
   - Medical certificate (pre-employment)
   - NBI clearance
   - Drug test result
   - Psychological exam (if applicable)

6. **Performance & Disciplinary**
   - Performance appraisals
   - Awards and recognition
   - Disciplinary actions (if any)
   - Warning letters

7. **Leave & Attendance**
   - Leave applications (approved/rejected)
   - Daily time records (monthly summary)
   - Attendance issues

8. **Compensation & Benefits**
   - Payslips (annual summary)
   - Salary adjustment memos
   - Loan applications
   - Benefits enrollment forms

9. **Separation (if applicable)**
   - Resignation letter
   - Clearance form
   - Certificate of employment
   - Quitclaim (if any)

### Digital File Management (HRIS)

**Document Upload Process:**
1. Scan document (PDF format, max 5MB per file)
2. Name file clearly (e.g., "Juan_Dela_Cruz_NBI_Clearance_2025.pdf")
3. Upload to HRIS > Employee Profile > Documents
4. Tag document category
5. Set expiry date (if applicable, e.g., NBI valid for 1 year)
6. Save and verify upload

**Document Security:**
- Access restricted to HR Staff, HR Manager, Office Admin
- Encrypted storage
- Audit log of who accessed documents
- No download/print for sensitive documents (by policy)

### Certificate Generation

**Certificate of Employment (COE):**
- **Purpose**: For visa, loans, new employment
- **Contains**: Name, position, employment dates, status (active/resigned)
- **Process**: Generate from template, fill details, print on letterhead, HR Manager signs
- **Turnaround**: 1-2 business days

**BIR 2316 (Certificate of Compensation & Tax Withheld):**
- **Purpose**: Annual tax filing
- **Contains**: Total compensation, benefits, tax withheld for the year
- **Process**: Generate from payroll system, verify with Payroll Officer, print BIR form, authorized signatory signs
- **Availability**: January-February of following year

---

## 6. Employee Inquiries & Support

### Purpose
Provide front-line support for employee HR questions and concerns.

### Workflow

```mermaid
graph TD
    Start([Employee Inquiry]) --> InquiryChannel{Inquiry Channel}
    
    InquiryChannel --> InPerson[In-Person<br/>Walk-in to HR Office]
    InquiryChannel --> Phone[Phone Call]
    InquiryChannel --> Email[Email]
    
    InPerson --> ListenConcern[Listen to Concern<br/>& Take Notes]
    Phone --> ListenConcern
    Email --> ListenConcern
    
    ListenConcern --> InquiryType{Inquiry Type}
    
    InquiryType --> LeaveInquiry[Leave Balance<br/>or Leave Application]
    InquiryType --> PayrollInquiry[Payroll, Salary,<br/>Deductions]
    InquiryType --> BenefitsInquiry[Benefits, Loans,<br/>Government Numbers]
    InquiryType --> AttendanceInquiry[Timekeeping,<br/>Attendance Issues]
    InquiryType --> DocumentRequest[Document Request<br/>COE, Certificates]
    InquiryType --> ComplaintConcern[Complaint or Concern]
    
    LeaveInquiry --> CheckLeaveBalance[Check Leave Balance<br/>in HRIS]
    CheckLeaveBalance --> ProvideInfo[Provide Information<br/>to Employee]
    
    PayrollInquiry --> CheckPayroll[Check Payroll Details<br/>or Forward to Payroll Officer]
    CheckPayroll --> ProvideInfo
    
    BenefitsInquiry --> CheckBenefits[Check Benefits Enrollment<br/>or Government Numbers]
    CheckBenefits --> ProvideInfo
    
    AttendanceInquiry --> CheckTimekeeping[Check Timekeeping Records]
    CheckTimekeeping --> ResolveAttendance[Resolve Issue or<br/>Create Manual Entry]
    ResolveAttendance --> ProvideInfo
    
    DocumentRequest --> ProcessDocument[Process Document Request<br/>Generate Certificate]
    ProcessDocument --> ProvideInfo
    
    ComplaintConcern --> Severity{Severity Level}
    Severity -->|Minor| ResolveDirectly[Resolve Directly<br/>Policy Explanation]
    Severity -->|Moderate| EscalateManager[Escalate to HR Manager]
    Severity -->|Serious| EscalateAdmin[Escalate to Office Admin<br/>Document Formally]
    
    ResolveDirectly --> ProvideInfo
    EscalateManager --> ProvideInfo
    EscalateAdmin --> ProvideInfo
    
    ProvideInfo --> FollowUp{Requires Follow-up?}
    FollowUp -->|Yes| SetReminder[Set Follow-up Reminder]
    FollowUp -->|No| LogInquiry[Log Inquiry in System]
    SetReminder --> LogInquiry
    
    LogInquiry --> Complete([Inquiry Resolved])
```

### Common Employee Inquiries

**Leave-Related:**
- "What's my current leave balance?"
- "How do I apply for leave?"
- "Why was my leave rejected?"
- "Can I convert my leave to cash?"
- "I'm sick, how do I file leave?"

**Payroll-Related:**
- "When is the next payday?"
- "Why is my salary short?"
- "What are these deductions on my payslip?"
- "How do I get my 13th month pay?"
- "Can I get a salary loan?"

**Benefits-Related:**
- "What's my SSS number?"
- "How do I enroll in PhilHealth?"
- "Do we have HMO benefits?"
- "When can I apply for a Pag-IBIG loan?"

**Attendance-Related:**
- "I forgot to punch in/out yesterday, what do I do?"
- "My RFID card is not working."
- "I was late due to traffic, will I get deducted?"
- "Can I undertime for a doctor's appointment?"

**Document Requests:**
- "I need a COE for my visa application."
- "Can I get my BIR 2316 for tax filing?"
- "I need proof of employment for a loan."

**Complaints/Concerns:**
- Workplace conflict with colleague
- Supervisor harassment or discrimination
- Unsafe working conditions
- Policy violations by others

### Response Guidelines

**Immediate Response (Same Day):**
- Leave balance inquiries
- Simple document requests (if documents ready)
- Policy clarifications
- Timekeeping corrections

**1-2 Business Days:**
- Certificate of Employment generation
- Leave application processing
- Benefits enrollment
- Payroll inquiries (requires coordination with Payroll Officer)

**3-5 Business Days:**
- Complex document requests (BIR 2316, service records)
- Complaint investigations (escalated to HR Manager)
- Benefits claims processing

**Escalation to HR Manager:**
- Employee complaints (harassment, discrimination)
- Policy interpretation disputes
- Salary and compensation concerns
- Disciplinary issues

**Escalation to Office Admin:**
- Serious violations (fraud, theft)
- Major policy changes
- High-value financial requests
- Legal concerns

---

## Common Tasks

### Daily Tasks
- ✅ Monitor timekeeping exceptions (missing punches, late arrivals)
- ✅ Process leave applications submitted by employees
- ✅ Respond to employee inquiries (email, phone, walk-in)
- ✅ Update employee records in HRIS
- ✅ Issue RFID cards to new hires or replacements

### Weekly Tasks
- ✅ Screen new job applications in ATS
- ✅ Update recruitment pipeline status
- ✅ Organize and file employee documents
- ✅ Generate and distribute certificates (COE, etc.)
- ✅ Follow-up on pending onboarding items

### Monthly Tasks
- ✅ Generate monthly attendance summary report
- ✅ Reconcile timekeeping data with payroll
- ✅ Conduct 201 file audit (completeness check)
- ✅ Update ATS with monthly recruitment metrics
- ✅ Archive resolved employee inquiries

### Quarterly Tasks
- ✅ Audit 201 file completeness
- ✅ Update employee emergency contact information
- ✅ Review and archive old leave applications
- ✅ Check document expiry dates (NBI clearance, medical certs)

### Annual Tasks
- ✅ Generate BIR 2316 for all employees (January-February)
- ✅ Update employee photos in 201 files
- ✅ Archive previous year's payslips and DTRs
- ✅ Conduct annual 201 file purging (per retention policy)

---

## Best Practices

### Customer Service
- ✅ Be courteous and professional at all times
- ✅ Listen actively to employee concerns
- ✅ Provide accurate information or escalate if unsure
- ✅ Follow-up on commitments made
- ✅ Maintain confidentiality of employee information

### Data Accuracy
- ✅ Double-check all data entries before saving
- ✅ Verify employee identity before providing information
- ✅ Cross-check leave requests with system balance
- ✅ Validate documents for authenticity
- ✅ Keep HRIS and physical files synchronized

### Efficiency
- ✅ Use templates for common documents (COE, certificates)
- ✅ Batch similar tasks together (e.g., screen applications once per day)
- ✅ Set reminders for follow-ups
- ✅ Maintain organized physical and digital filing systems
- ✅ Prepare ahead for known busy periods (payday, government reporting)

### Compliance
- ✅ Follow data privacy protocols
- ✅ Ensure all employee documents are complete
- ✅ Maintain document retention policy
- ✅ Escalate concerns appropriately
- ✅ Document all employee interactions

---

## Troubleshooting

### Common Issues

**Issue: Employee cannot find leave application status**
- Check if request was submitted (search in system)
- Verify if request is pending approval or already decided
- Check approval workflow (who needs to approve)
- Notify employee of current status

**Issue: Timekeeping exception not updating in payroll**
- Verify exception was logged in timekeeping module
- Check if payroll sync is enabled
- Notify Payroll Officer of manual adjustment needed
- Escalate to IT if system issue

**Issue: Document upload fails in HRIS**
- Check file size (max 5MB)
- Check file format (PDF preferred)
- Try compressing file if too large
- Contact IT if persistent issue

**Issue: Employee forgot RFID card**
- Allow manual time entry for the day
- Require supervisor approval
- Remind employee to bring card tomorrow
- Issue temporary card if repeated offense

## Immutable Ledger & Replay Monitoring

- HR Staff must reconcile manual entries against the PostgreSQL ledger (`rfid_ledger`) maintained by the Replayable Event-Log Verification Layer to ensure attendance integrity.
- Subscribe to the replay layer's alerting/metrics (ledger commit latency, sequence gaps, hash mismatches, replay backlog) so adjustments halt until underlying anomalies are cleared.

---

## Related Documentation
- [System Overview](./00-system-overview.md)
- [HR Manager Workflow](./03-hr-manager-workflow.md)
- [Payroll Officer Workflow](./05-payroll-officer-workflow.md)
- [Onboarding Module](../ONBOARDING_MODULE.md)
- [ATS Module](../ATS_MODULE.md)
- [RBAC Matrix](../RBAC_MATRIX.md)

---

**Last Updated**: November 29, 2025  
**Role**: HR Staff  
**Access Level**: Operational Access (View, Create, Edit employee records - No final approval authority)
