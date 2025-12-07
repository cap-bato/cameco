# Office Admin Workflow

## Role Overview
**Focus**: Company setup, business rules, and process configuration

### Core Responsibilities
- 🏢 Company onboarding and initial setup
- 📋 Business rules and process configuration
- 🏛️ Department and position management
- 📅 Leave policies and approval workflows
- 💰 Salary structures and payroll rules
- 🔔 System-wide configurations (payment methods, government rates)
- ✅ Approval authority for major employee requests (based on configuration)

---

## Dashboard Overview

```mermaid
graph TB
    AdminDash[Office Admin Dashboard]
    
    AdminDash --> CompanySetup[Company Onboarding<br/>& Setup]
    AdminDash --> BusinessRules[Business Rules<br/>Configuration]
    AdminDash --> DeptPositions[Departments &<br/>Positions]
    AdminDash --> LeavePolicies[Leave Policies<br/>Configuration]
    AdminDash --> PayrollRules[Payroll Rules<br/>Configuration]
    AdminDash --> SystemConfig[System-wide<br/>Configuration]
    AdminDash --> ApprovalWorkflows[Approval Workflow<br/>Setup]
    
    style AdminDash fill:#ff9800,color:#fff
    style CompanySetup fill:#4caf50,color:#fff
    style BusinessRules fill:#2196f3,color:#fff
    style DeptPositions fill:#9c27b0,color:#fff
    style LeavePolicies fill:#f44336,color:#fff
    style PayrollRules fill:#00bcd4,color:#fff
    style SystemConfig fill:#ffc107,color:#000
    style ApprovalWorkflows fill:#8bc34a,color:#fff
```

---

## 1. Company Onboarding & Setup

### Purpose
Configure company information, tax details, and government registration numbers.

### Workflow

```mermaid
graph TD
    Start([Access Company Setup]) --> CompanyInfo[Configure Company Information]
    
    CompanyInfo --> BasicDetails[Business Name<br/>Address<br/>Contact Info]
    CompanyInfo --> TaxDetails[Tax ID<br/>BIR Registration<br/>Business Permits]
    CompanyInfo --> GovtNumbers[SSS Number<br/>PhilHealth Number<br/>Pag-IBIG Number]
    
    BasicDetails --> UploadLogo[Upload Company Logo]
    TaxDetails --> UploadLogo
    GovtNumbers --> UploadLogo
    
    UploadLogo --> SaveConfig[Save Configuration]
    SaveConfig --> Complete([Company Info Configured])
```

### Configuration Fields

**Basic Information:**
- Company legal name
- Business address
- Contact phone/email
- Website (optional)
- Company logo

**Tax & Registration:**
- Tax Identification Number (TIN)
- BIR registration details
- Business permit numbers
- SEC registration (if applicable)

**Government Numbers:**
- SSS employer number
- PhilHealth employer number
- Pag-IBIG employer number

---

## 2. Business Rules Configuration

### Purpose
Define working hours, holidays, overtime rules, and attendance policies.

### Workflow

```mermaid
graph TD
    Start([Access Business Rules]) --> RuleType{Select Rule Type}
    
    RuleType --> WorkingHours[Working Hours]
    RuleType --> Holidays[Holiday Calendar]
    RuleType --> Overtime[Overtime Rules]
    RuleType --> Attendance[Attendance Rules]
    
    WorkingHours --> RegularSchedule[Regular: Mon-Fri 8am-5pm]
    WorkingHours --> ShiftSchedules[Shifts: Morning/Afternoon/Night]
    RegularSchedule --> SaveSchedule[Save Configuration]
    ShiftSchedules --> SaveSchedule
    
    Holidays --> NationalHolidays[Import National Holidays]
    Holidays --> CompanyHolidays[Add Company Holidays]
    NationalHolidays --> HolidayPay[Set Pay Multipliers]
    CompanyHolidays --> HolidayPay
    HolidayPay --> SaveHolidays[Save Calendar]
    
    Overtime --> OTThreshold[Set OT Threshold: 8 hours]
    OTThreshold --> OTRates[Regular: 1.25x<br/>Holiday: 2.0x<br/>Rest Day: 1.3x]
    OTRates --> OTApproval[OT Approval Required?]
    OTApproval --> SaveOT[Save OT Rules]
    
    Attendance --> LatePolicy[Late Policy<br/>Grace Period: 15 min]
    Attendance --> UndertimePolicy[Undertime Policy<br/>Deduction Rules]
    Attendance --> AbsencePolicy[Absence Policy<br/>With/Without Leave]
    LatePolicy --> SaveAttendance[Save Policies]
    UndertimePolicy --> SaveAttendance
    AbsencePolicy --> SaveAttendance
```

### Key Configurations

**Working Hours:**
- Regular schedule (8am-5pm, Mon-Fri)
- Shift patterns (3 shifts: 6am-2pm, 2pm-10pm, 10pm-6am)
- Break times and durations
- Flexible work arrangements

**Holiday Calendar:**
- National holidays (auto-import from government list)
- Company-specific holidays
- Holiday pay multipliers (regular: 2.0x, special: 1.3x)
- Holiday work compensation rules

**Overtime Rules:**
- OT threshold (usually 8 hours/day)
- OT rates (regular: 1.25x, holiday: 2.0x, rest day: 1.3x)
- Maximum OT hours per day/week
- Approval requirements (auto-approve < 2 hours, requires approval ≥ 2 hours)

**Attendance Policies:**
- Grace period (15 minutes standard)
- Late deduction rules (per minute or per bracket)
- Undertime policy (proportional deduction)
- Absence handling (with/without approved leave)

---

## 3. Department & Position Management

### Purpose
Create and manage organizational structure, departments, and positions.

### Workflow

```mermaid
graph TD
    Start([Access Dept & Positions]) --> Action{Select Action}
    
    Action --> ManageDepts[Manage Departments]
    Action --> ManagePositions[Manage Positions]
    
    ManageDepts --> DeptAction{Department Action}
    DeptAction --> CreateDept[Create New Department]
    DeptAction --> EditDept[Edit Department]
    DeptAction --> ArchiveDept[Archive Department]
    
    CreateDept --> DeptForm[Fill Department Form]
    DeptForm --> DeptDetails[Name<br/>Code<br/>Manager<br/>Description]
    DeptDetails --> SaveDept[Save Department]
    
    ArchiveDept --> CheckActive{Has Active Employees?}
    CheckActive -->|Yes| CannotArchive[Cannot Archive<br/>Reassign Employees First]
    CheckActive -->|No| ConfirmArchive[Archive Department]
    
    ManagePositions --> PosAction{Position Action}
    PosAction --> CreatePos[Create New Position]
    PosAction --> EditPos[Edit Position]
    PosAction --> ArchivePos[Archive Position]
    
    CreatePos --> PosForm[Fill Position Form]
    PosForm --> PosDetails[Title<br/>Level<br/>Salary Range<br/>Requirements]
    PosDetails --> SavePos[Save Position]
```

### Department Management

**Creating a Department:**
1. Department name (e.g., "Human Resources")
2. Department code (e.g., "HR")
3. Assign department head/manager
4. Add description and responsibilities

**Department Hierarchy:**
- Top-level departments
- Sub-departments (optional)
- Cross-functional teams

### Position Management

**Creating a Position:**
1. Position title (e.g., "Senior Software Engineer")
2. Job level (Junior, Mid, Senior, Manager, etc.)
3. Salary range (min-max)
4. Required qualifications
5. Job description
6. Reporting structure

---

## 4. Leave Policies Configuration

### Purpose
Configure leave types, accrual methods, and approval workflows.

### Workflow

```mermaid
graph TD
    Start([Access Leave Policies]) --> PolicyType{Select Policy}
    
    PolicyType --> LeaveTypes[Leave Types]
    PolicyType --> LeaveAccrual[Leave Accrual]
    PolicyType --> LeaveApproval[Approval Rules]
    
    LeaveTypes --> CreateType[Create Leave Type]
    CreateType --> TypeDetails[Vacation: 15 days/year<br/>Sick: 15 days/year<br/>Emergency: 5 days/year<br/>Maternity: 105 days<br/>Paternity: 7 days]
    TypeDetails --> Carryover[Set Carryover Rules<br/>Max Days<br/>Expiry Date]
    Carryover --> SaveType[Save Leave Type]
    
    LeaveAccrual --> AccrualMethod{Accrual Method}
    AccrualMethod -->|Monthly| Monthly[1.25 days/month]
    AccrualMethod -->|Annual| Annual[15 days at start]
    AccrualMethod -->|Prorated| Prorated[Based on hire date]
    Monthly --> SaveAccrual[Save Accrual Rules]
    Annual --> SaveAccrual
    Prorated --> SaveAccrual
    
    LeaveApproval --> ApprovalRules[Define Approval Rules]
    ApprovalRules --> ShortLeave[1-2 days: Auto-approve]
    ApprovalRules --> MediumLeave[3-5 days: HR Manager]
    ApprovalRules --> LongLeave[6+ days: HR Manager + Office Admin]
    ShortLeave --> SaveApproval[Save Approval Rules]
    MediumLeave --> SaveApproval
    LongLeave --> SaveApproval
```

### Leave Type Configuration

**Standard Leave Types:**
- **Vacation Leave**: 15 days/year (convertible to cash)
- **Sick Leave**: 15 days/year (requires medical certificate for 3+ days)
- **Emergency Leave**: 5 days/year
- **Maternity Leave**: 105 days (60 days paid, 45 days unpaid)
- **Paternity Leave**: 7 days (paid)
- **Solo Parent Leave**: 7 days/year (with certificate)
- **Bereavement Leave**: 3-5 days (immediate family)

**Carryover Rules:**
- Maximum carryover days (e.g., 5 days)
- Expiry period (e.g., March 31 next year)
- Conversion to cash option

### Accrual Methods

**Monthly Accrual:**
- 1.25 days per month worked
- Prorated for partial months
- Available balance updates monthly

**Annual Accrual:**
- Full allocation on January 1 or hire date anniversary
- Prorated for new hires mid-year

**Prorated Accrual:**
- Based on actual hire date
- Calculated proportionally for first year

### Approval Workflows

**Configurable Approval Rules:**

Office Admin can configure leave approval workflows based on multiple criteria:

**Tier 1: HR Staff Approval (Default)**
- HR Staff has full authority to approve/reject
- Standard leave requests (within policy limits)
- Sufficient leave balance
- No critical workforce impact
- Advance notice met (minimum 3 days)

**Tier 2: HR Staff → HR Manager Approval (Configurable Triggers)**

Office Admin can set any combination of these triggers:
- **Duration-based**: Exceeds X days (e.g., > 5 days)
- **Balance threshold**: Requires approval if balance falls below X days after leave
- **Advance notice**: Less than X days advance notice
- **Workforce impact**: Coverage falls below X% threshold
- **Leave type**: Specific leave types always require manager approval (e.g., unpaid leave)
- **Blackout periods**: Requests during busy season/peak periods
- **Frequency**: Employee taking Y leave requests within Z timeframe

**Tier 3: HR Manager → Office Admin (Major Requests)**
- Exceeds maximum manager approval limit (configurable, e.g., > 15 days)
- Extended unpaid leave
- Leave of absence requests
- Special circumstances requiring executive approval

**Workforce Coverage Warning System:**
- System calculates department coverage percentage
- Warns approver if coverage falls below threshold
- Shows: "⚠️ Approving this leave will reduce [Department] coverage to 65% (below 75% minimum)"
- Approver can still approve but must acknowledge warning
- Critical warnings (< 50% coverage) may require manager override

---

## 5. Payroll Rules Configuration

### Purpose
Configure salary structure, deductions, government rates, and payment methods.

### Workflow

```mermaid
graph TD
    Start([Access Payroll Rules]) --> RuleType{Select Configuration}
    
    RuleType --> SalaryStructure[Salary Structure]
    RuleType --> Deductions[Deduction Rules]
    RuleType --> GovtRates[Government Rates]
    RuleType --> PaymentMethods[Payment Methods]
    
    SalaryStructure --> BasicSalary[Basic Salary<br/>Salary Grades]
    SalaryStructure --> Allowances[Allowances<br/>Housing, Transportation, Meal]
    SalaryStructure --> Bonuses[Bonuses<br/>13th Month, Performance]
    BasicSalary --> SaveSalary[Save Configuration]
    Allowances --> SaveSalary
    Bonuses --> SaveSalary
    
    Deductions --> Mandatory[Mandatory Deductions<br/>SSS, PhilHealth, Pag-IBIG]
    Deductions --> Optional[Optional Deductions<br/>Loans, Advances, Insurance]
    Mandatory --> SaveDeductions[Save Deduction Rules]
    Optional --> SaveDeductions
    
    GovtRates --> SSS[SSS Contribution Table]
    GovtRates --> PhilHealth[PhilHealth Rates]
    GovtRates --> PagIbig[Pag-IBIG Rates]
    GovtRates --> Tax[BIR Tax Table]
    SSS --> EffectiveDate[Set Effective Date]
    PhilHealth --> EffectiveDate
    PagIbig --> EffectiveDate
    Tax --> EffectiveDate
    EffectiveDate --> SaveRates[Save Government Rates]
    
    PaymentMethods --> CurrentMethod[Current: Cash Only]
    PaymentMethods --> FutureMethods[Future Options]
    CurrentMethod --> EnableCash[Enable Cash Distribution]
    FutureMethods --> EnableBank{Enable Bank Transfer?}
    FutureMethods --> EnableEwallet{Enable E-wallet?}
    EnableBank -->|Yes| BankSetup[Configure Bank Integration]
    EnableEwallet -->|Yes| EwalletSetup[Configure E-wallet]
    EnableCash --> SavePayment[Save Payment Config]
    BankSetup --> SavePayment
    EwalletSetup --> SavePayment
```

### Salary Structure

**Basic Salary:**
- Salary grades (1-15 or custom)
- Minimum-maximum per grade
- Step increments within grade
- Annual salary review dates

**Allowances (De Minimis/Taxable):**
- Housing allowance
- Transportation allowance
- Meal allowance
- Communication allowance
- Clothing allowance
- Medical/dental allowance

**Bonuses:**
- 13th month pay (mandatory)
- Performance bonus
- Signing bonus
- Retention bonus
- Project completion bonus

### Deduction Rules

**Mandatory Deductions:**
- **SSS**: Based on contribution table
- **PhilHealth**: Based on monthly salary
- **Pag-IBIG**: Fixed rate (1-2% of salary)
- **Withholding Tax**: Based on BIR tax table

**Optional Deductions:**
- Company loans
- SSS/Pag-IBIG loans
- Cash advances
- Insurance premiums
- Uniform deductions
- Other authorized deductions

### Government Rates

**SSS Contribution Table** (Updated annually):
- Salary brackets
- Employee and employer shares
- Maximum contribution cap

**PhilHealth Rates** (Updated annually):
- Premium rate (current: 5% of monthly salary)
- Employee: 2.5%, Employer: 2.5%
- Minimum and maximum contributions

**Pag-IBIG Rates**:
- Employee: 1-2% (member choice)
- Employer: 2%
- Maximum salary base: ₱5,000

**BIR Tax Table**:
- Progressive tax rates
- Tax exemptions
- Deductions (SSS, PhilHealth, Pag-IBIG)
- TRAIN law compliance

### Payment Methods

**Current: Cash Distribution**
- Salary envelopes
- Employee signature required
- Accountability report
- Security protocols

**Future: Bank Transfer** (Configurable)
- Bank file generation
- Auto-transfer scheduling
- Transfer confirmation
- Bank reconciliation

**Future: E-wallet** (Configurable)
- GCash, PayMaya, etc.
- Instant transfer
- Transaction notifications
- E-wallet reconciliation

---

## 6. System-wide Configuration

### Purpose
Configure notifications, reports, and system integrations.

### Workflow

```mermaid
graph TD
    Start([Access System Config]) --> ConfigType{Select Configuration}
    
    ConfigType --> Notifications[Notification Settings]
    ConfigType --> Reports[Report Settings]
    ConfigType --> Integrations[Integration Settings]
    
    Notifications --> EmailNotif[Email Notifications<br/>SMTP Configuration]
    Notifications --> SMSNotif[SMS Notifications<br/>Future Feature]
    EmailNotif --> Templates[Email Templates<br/>Leave Approval<br/>Payslips<br/>Interviews]
    SMSNotif --> Templates
    Templates --> SaveNotif[Save Notification Config]
    
    Reports --> ReportFormats[Report Formats<br/>PDF Settings<br/>Excel Settings]
    Reports --> AutoReports[Scheduled Reports<br/>Monthly Payroll<br/>Attendance Summary]
    ReportFormats --> SaveReports[Save Report Config]
    AutoReports --> SaveReports
    
    Integrations --> RFID[RFID Integration]
    Integrations --> JobBoard[Job Board Future]
    RFID --> RFIDDevice[Configure Edge Device<br/>IP Address<br/>Port<br/>Protocol]
    RFIDDevice --> EventBus[Setup Event Bus<br/>Timekeeping<br/>Payroll<br/>Notifications]
    EventBus --> TestRFID[Test RFID Integration]
    TestRFID --> SaveIntegration[Save Integration Config]
    JobBoard --> SaveIntegration
```

### Notification Configuration

**Email Settings:**
- SMTP server configuration
- Sender email and name
- Email templates for:
  - Leave approval/rejection
  - Payslip distribution
  - Interview scheduling
  - Performance review reminders
  - System alerts

**SMS Settings (Future):**
- SMS gateway integration
- SMS templates
- Priority notifications

### Report Configuration

**PDF Settings:**
- Company logo on reports
- Header/footer templates
- Page size and margins
- Font and styling

**Excel Settings:**
- Column formatting
- Auto-width columns
- Freeze panes
- Summary sheets

**Scheduled Reports:**
- Monthly payroll register
- Attendance summary
- Leave utilization
- Government remittance reports
- Auto-email to recipients

### Integration Settings

**RFID Timekeeping:**
1. Configure edge device (IP, port)
2. Setup event bus routing
3. Map events to modules:
   - Timekeeping: Record attendance
   - Payroll: Update work hours
   - Notifications: Send confirmation
4. Test card tap and event flow

**Job Board (Future):**
- Public website integration
- Application form mapping
- Auto-import to ATS
- Applicant notifications

---

## 7. Approval Workflow Setup

### Purpose
Configure multi-level approval workflows for various processes.

### Workflow

```mermaid
graph TD
    Start([Access Workflow Setup]) --> WorkflowType{Select Workflow}
    
    WorkflowType --> LeaveFlow[Leave Request Workflow]
    WorkflowType --> HiringFlow[Hiring Workflow]
    WorkflowType --> PayrollFlow[Payroll Workflow]
    WorkflowType --> ExpenseFlow[Expense Workflow]
    
    LeaveFlow --> DefineLeave[Define Leave Workflow]
    DefineLeave --> Step1L[Step 1: HR Staff Submits]
    Step1L --> Step2L[Step 2: HR Manager Approves Conditional]
    Step2L --> Step3L[Step 3: Office Admin Final Approval 6+ days]
    Step3L --> SaveLeave[Save Leave Workflow]
    
    HiringFlow --> DefineHiring[Define Hiring Workflow]
    DefineHiring --> Step1H[Step 1: HR Staff Screens]
    Step1H --> Step2H[Step 2: HR Manager Interviews & Approves]
    Step2H --> Step3H[Step 3: Office Admin Final Approval]
    Step3H --> SaveHiring[Save Hiring Workflow]
    
    PayrollFlow --> DefinePayroll[Define Payroll Workflow]
    DefinePayroll --> Step1P[Step 1: Payroll Officer Calculates]
    Step1P --> Step2P[Step 2: HR Manager Reviews]
    Step2P --> Step3P[Step 3: Office Admin Final Approval]
    Step3P --> SavePayroll[Save Payroll Workflow]
    
    ExpenseFlow --> DefineExpense[Define Expense Workflow]
    DefineExpense --> SaveExpense[Save Expense Workflow]
```

### Approval Workflow Types

**Leave Request Workflow:**
- **1-2 days**: Auto-approved (if balance sufficient)
- **3-5 days**: HR Manager approval required
- **6+ days**: HR Manager + Office Admin approval required

**Hiring Approval Workflow:**
1. HR Staff screens applications
2. HR Manager conducts interview and recommends
3. Office Admin provides final hiring approval
4. HR Staff processes onboarding

**Payroll Approval Workflow:**
1. Payroll Officer calculates payroll
2. HR Manager reviews calculations and exceptions
3. Office Admin provides final approval before payment
4. Payroll Officer distributes payment

**Expense Approval Workflow:**
1. Employee submits expense (via HR Staff)
2. Department head approves
3. Accounting reviews
4. Office Admin approves (if above threshold)

### Configuring Leave Approval Rules

**Access**: Settings > Leave Management > Approval Rules

**Rule Configuration Options:**

1. **Duration-Based Rules**
   - Days requiring HR Manager approval: `[  5  ] days` (default: 5)
   - Days requiring Office Admin approval: `[ 15 ] days` (default: 15)
   - Auto-approval maximum: `[  2  ] days` (if balance sufficient)

2. **Workforce Coverage Rules**
   - Minimum department coverage: `[ 75 ]%`
   - Warning threshold: `[ 80 ]%` (show warning to approver)
   - Critical threshold: `[ 50 ]%` (require manager approval)
   - ☑ Block leave if coverage falls below critical threshold

3. **Advance Notice Rules**
   - Standard advance notice: `[ 3 ] days`
   - Short notice requires manager approval: `< [ 2 ] days`
   - Emergency leave exemption: ☑ Allow emergency leave without advance notice

4. **Leave Type Specific Rules**
   - Vacation Leave: HR Staff approval (≤5 days), Manager (>5 days)
   - Sick Leave: HR Staff approval (≤5 days), Manager (>5 days)
   - Emergency Leave: Always HR Staff approval (urgent)
   - Unpaid Leave: Always requires HR Manager approval
   - Maternity/Paternity: Always requires HR Manager approval
   - Leave of Absence: Requires HR Manager + Office Admin

5. **Balance Threshold Rules**
   - Require manager approval if remaining balance < `[ 3 ] days`
   - ☑ Warn employee when balance < 5 days
   - ☑ Block leave if insufficient balance

6. **Blackout Period Rules**
   - Define blackout periods (e.g., December 15-31, Inventory Week)
   - Action during blackout: ○ Require Manager Approval ● Block All Leave ○ Warning Only

7. **Frequency Rules**
   - Require manager approval if employee has `[ 3 ]` or more leave requests in `[ 30 ] days`
   - ☑ Flag frequent short-term absences for review

**Example Configuration:**

*Small Company (Relaxed Policy):*
- HR Staff approves up to 10 days
- 60% minimum coverage
- 2 days advance notice

*Large Enterprise (Strict Policy):*
- HR Staff approves up to 3 days only
- 85% minimum coverage
- 5 days advance notice
- Manager approval for any leave during Q4

**Testing Approval Rules:**
1. Configure rules in settings
2. Use "Test Leave Scenario" tool
3. Input: Employee, leave type, duration, dates
4. System shows: Approval path, warnings, blockers
5. Adjust rules as needed
6. Save and activate

---

## Common Tasks

### Initial System Setup Checklist

**Day 1: Company Information**
- ✅ Configure company basic details
- ✅ Upload company logo
- ✅ Input tax and government numbers
- ✅ Save company configuration

**Day 2: Business Rules**
- ✅ Setup working hours and shifts
- ✅ Import holiday calendar
- ✅ Configure overtime rules
- ✅ Setup attendance policies

**Day 3: Organizational Structure**
- ✅ Create all departments
- ✅ Define positions and job levels
- ✅ Assign department heads
- ✅ Setup reporting structure

**Day 4: Leave Policies**
- ✅ Create all leave types
- ✅ Configure accrual methods
- ✅ Setup approval workflows
- ✅ Define carryover rules

**Day 5: Payroll Configuration**
- ✅ Setup salary structure
- ✅ Configure allowances and bonuses
- ✅ Input government rates
- ✅ Enable payment methods

**Day 6: System Configuration**
- ✅ Configure email notifications
- ✅ Setup report templates
- ✅ Configure RFID integration
- ✅ Test all integrations

**Day 7: Approval Workflows**
- ✅ Define leave approval workflow
- ✅ Define hiring workflow
- ✅ Define payroll workflow
- ✅ Train users on workflows

### Updating Government Rates (Annual Task)

**When to Update:**
- SSS: January (when announced)
- PhilHealth: January (when announced)
- Pag-IBIG: As announced (rare)
- BIR Tax: January (TRAIN law updates)

**Update Process:**
1. Review official government announcement
2. Navigate to Payroll Rules > Government Rates
3. Select rate type (SSS/PhilHealth/Pag-IBIG/Tax)
4. Input new rates and brackets
5. Set effective date (usually January 1)
6. Save and notify Payroll Officer
7. Generate test payroll to verify

### Managing Holiday Calendar

**Annual Update (December):**
1. Import next year's national holidays
2. Add company-specific holidays
3. Set holiday pay multipliers
4. Review special working day classifications
5. Save and publish calendar
6. Notify all users of holiday schedule

---

## Best Practices

### Configuration Management
- ✅ Document all configuration changes
- ✅ Test changes in staging first (if available)
- ✅ Notify affected users before major changes
- ✅ Keep backup of previous configurations
- ✅ Review configurations quarterly

### Data Accuracy
- ✅ Verify government rates from official sources
- ✅ Cross-check salary structures with HR policy
- ✅ Validate leave policies against labor law
- ✅ Test approval workflows before rollout
- ✅ Audit system configurations annually

### Security & Compliance
- ✅ Restrict configuration access to Office Admin only
- ✅ Log all configuration changes
- ✅ Ensure compliance with labor laws
- ✅ Keep government rates updated
- ✅ Regular security audits

---

## Troubleshooting

### Common Issues

**Issue: Government rates not applying to payroll**
- Check effective date of rate configuration
- Verify payroll period date range
- Re-calculate payroll after rate update
- Contact Payroll Officer to verify

**Issue: Leave auto-approval not working**
- Check leave type configuration
- Verify approval workflow rules
- Check employee leave balance
- Review advance notice requirements

**Issue: RFID events not captured**
- Check edge device connectivity
- Verify event bus configuration
- Test RFID card registration
- Review integration logs

## Immutable Ledger & Replay Monitoring

- Attendance/timekeeping data that drive these configurations must originate from the PostgreSQL ledger (`rfid_ledger`) controlled by the Replayable Event-Log Verification Layer.
- Office Admin should subscribe to the replay layer's alerting/metrics (ledger commit latency, sequence gaps, hash mismatches, replay backlog) to pause rule changes whenever integrity warnings exist.

---

## Related Documentation
- [System Overview](./00-system-overview.md)
- [Superadmin Workflow](./01-superadmin-workflow.md)
- [HR Manager Workflow](./03-hr-manager-workflow.md)
- [Payroll Officer Workflow](./05-payroll-officer-workflow.md)
- [RBAC Matrix](../RBAC_MATRIX.md)

---

**Last Updated**: November 29, 2025  
**Role**: Office Admin  
**Access Level**: Full Configuration Access (No Emergency Module Access)
