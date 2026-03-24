# SyncingSteel HRIS System — User Manual by Role

---

## **1. SUPERADMIN**

### Overview
Full system access. Manages users, roles, permissions, system configuration, and audit trails.

### Key Responsibilities
- **User Management** → Assign roles, manage access, audit user activities
- **System Configuration** → Set business rules, leave policies, payroll rules, company settings
- **Approval Workflows** → Configure multi-level approval chains for leaves, advances, expenses
- **Audit Logs** → Monitor all system activities for compliance
- **Security** → Two-factor authentication, password resets, session management

### Main Workflows
1. **Create New User**
   - Admin → System Administration → Users
   - Enter email, assign role (Superadmin, Office Admin, HR Manager, HR Staff, Payroll Officer, Employee)
   - System sends invitation link; user completes profile on first login

2. **Configure Leave Policy**
   - Admin → Admin Settings → Leave Policies
   - Set annual leave entitlement, vacation days, sick leave rules
   - Define accrual method (fixed or monthly) and carry-over limits
   - Link policies to departments/employee groups

3. **View Audit Trail**
   - Admin → Audit Logs
   - Filter by date, user, action type (create, update, delete)
   - Track changes to sensitive data (salary, documents, leave records)

4. **Set Business Rules**
   - Admin → Business Rules
   - Define attendance rules (check-in/out times, overtime thresholds)
   - Configure late arrival grace period, shift schedules
   - Set RFID reader parameters and sync intervals

---

## **2. OFFICE ADMIN**

### Overview
Manages single office/location. Controls local HR operations, staff, and compliance within assigned office.

### Key Responsibilities
- **Employee Management** → Onboard/offboard, manage documents, track performance
- **Leave Approvals** → Approve/reject leave requests (subject to company-wide policies)
- **Attendance Oversight** → Review RFID logs, resolve attendance disputes
- **Local Reports** → Generate office-level attendance, leave, and payroll reports

### Main Workflows
1. **Onboard New Employee**
   - HR → Employee → Onboard Employee
   - Fill personal info, position, department, salary grade
   - Assign employment contract, upload documents
   - System triggers equipment request & first-day checklists

2. **Approve Leave Requests**
   - HR → Approvals Dashboard
   - View pending leave requests with employee balance
   - Compare dates against blackout dates, other approvals
   - Click **Approve** or **Reject** with optional comment

3. **Review Attendance Anomalies**
   - Employee → Attendance
   - Filter by employee, date range, status
   - Click on red-flagged entries (late, missing checkout, duplicate taps)
   - Approve corrections or add manual attendance records

4. **Generate Office Report**
   - HR → Reports → Attendance Summary
   - Select office, date range
   - Export as PDF or Excel
   - Shows: present, absent, late, on-leave breakdown by department

---

## **3. HR MANAGER**

### Overview
Oversees all HR operations company-wide. Manages hiring, performance, leave policies, and workforce planning.

### Key Responsibilities
- **Recruitment** → Post jobs, screen candidates, conduct interviews, make offers
- **Performance Management** → Create appraisal cycles, track metrics, manage reviews
- **Leave & Attendance** → Approve high-level leave requests, policy enforcement
- **Offboarding** → Manage employee exits, clearances, exit interviews
- **Workforce Analytics** → Plan headcount, retirement, turnover analysis

### Main Workflows
1. **Create Job Posting**
   - HR → ATS → Job Postings
   - Enter position title, department, salary range, responsibilities
   - Publish to career page; system sends notifications to HR Staff
   - Track applications and pipeline stages

2. **Start Appraisal Cycle**
   - HR → Appraisals → Appraisal Cycles
   - Set review period (e.g., Jan–Dec), cycle dates
   - Select employees and raters (managers, peers, direct reports)
   - System sends notifications; tracking dashboard shows submission status

3. **Approve Multi-Level Leaves**
   - HR → Leave Requests (Advanced Filter)
   - View requests pending HR Manager approval (routed automatically)
   - Check employee balance, last approval, blackout dates
   - Approve (updates balance) or reject with reason

4. **Offboard Employee**
   - HR → Offboarding → Offboarding Cases
   - Create exit case; system generates clearance checklist
   - Assign tasks (IT return, document collection, final settlement)
   - Link exit interview, capture feedback, finalize records

5. **View Workforce Dashboard**
   - HR → Workforce Analytics
   - Headcount by department, contract type
   - Turnover rate, retirement eligibility
   - Voluntary vs. involuntary separations by reason

---

## **4. HR STAFF**

### Overview
Frontline HR operations. Processes applications, maintains employee records, supports leave and document administration.

### Key Responsibilities
- **Recruitment Support** → Screen resumes, schedule interviews, send offer letters
- **Employee Records** → Maintain personnel files, document uploads, profile updates
- **Leave Administration** → Process leave requests, track balances, issue certifications
- **Document Management** → Issue ID cards, certificates, employee requests

### Main Workflows
1. **Screen Job Application**
   - HR → ATS → Candidates
   - Review resume, cover letter, submitted form
   - Add notes, move candidate to next stage (Phone Screen → Interview → Offer)
   - Schedule interviews; system sends calendar invite to candidate and interviewers

2. **Upload Employee Document**
   - HR → Documents → Employee Documents
   - Select employee, document type (contract, SSS, health record, etc.)
   - Upload file, add effective date
   - System notifies employee; document becomes accessible in Employee Portal

3. **Process Leave Request**
   - HR → Leave Requests
   - Filter pending requests awaiting HR approval
   - Verify employee balance, check for conflicts
   - Click **Approve** (system deducts balance) or **Request More Info**
   - Notify employee via email and in-app notification

4. **Issue Employee Certificate**
   - HR → Documents → Certificates
   - Select employee, certificate type (Service, Salary, Clearance)
   - Auto-populate data (dates, position, salary info)
   - Generate PDF, email to employee or external party

---

## **5. PAYROLL OFFICER**

### Overview
Manages payroll processing, salary calculations, government contributions, and payment execution.

### Key Responsibilities
- **Payroll Processing** → Run payroll, calculate components, apply deductions
- **Government Compliance** → Calculate SSS, PhilHealth, Pag-IBIG, BIR taxes (Philippines)
- **Employee Payments** → Disburse via bank transfer or check; manage payment methods
- **Payroll Reports** → Generate payslips, tax reports, audit trails

### Main Workflows
1. **Process Monthly Payroll**
   - Payroll → Payroll Processing → Run Payroll
   - Select month, payroll group (if using multiple runs)
   - System auto-calculates: gross, SSS, PhilHealth, Pag-IBIG, BIR, net
   - Review salary components (basic, allowances, overtime, deductions)
   - Click **Finalize** to lock and generate payslips

2. **Review & Approve Payroll**
   - Payroll → Approvals
   - View pending payroll runs awaiting sign-off
   - Spot-check calculations (totals, per-employee records)
   - Click **Approve** to finalize; system sends payslips to employees

3. **Generate Payment File**
   - Payroll → Payments → Payment Processing
   - Select approved payroll run
   - Choose payment method (Bank Transfer Export, Check, Cash)
   - System generates .csv for bank upload or check print queue

4. **Issue Payslips**
   - Payroll → Reports → Payslips
   - Employees can download from Employee Portal
   - Print & distribute physical copies if required
   - Payslip shows: gross, deductions, net, YTD totals

5. **File Government Reports**
   - Payroll → Reports → Government Filings
   - Generate SSS, PhilHealth, Pag-IBIG, BIR monthly/quarterly summaries
   - Download in required format (BIR Form 2316, SSS Excel template, etc.)
   - Submit to relevant agencies by deadline

---

## **6. EMPLOYEE (Self-Service Portal)**

### Overview
Employees manage personal information, submit leave requests, view payslips, and track attendance.

### Key Responsibilities
- **Self-Service** → Update profile, submit leave requests, view payslips
- **Attendance Tracking** → Check in/out via RFID, view attendance history
- **Documents** → Download certificates, personal records
- **Notifications** → Receive updates on leave approvals, payroll, schedule changes

### Main Workflows
1. **Submit Leave Request**
   - Employee → Leave → Request Leave
   - Select leave type (vacation, sick, special), dates
   - System shows balance, checks for blackout dates
   - Submit with optional reason
   - Status updates in real-time: Pending → Approved/Rejected

2. **Check Attendance Record**
   - Employee → Attendance
   - View check-in/out times for current & past months
   - Flag errors (missing checkout, incorrect time)
   - Submit correction request with reason; HR reviews and approves

3. **View Payslip**
   - Employee → Payslips
   - Select month/year
   - Download PDF showing: gross, allowances, deductions, net, taxes
   - View payment method and deposit date

4. **Update Profile**
   - Settings → Profile
   - Update emergency contact, address, phone number
   - Upload profile photo
   - System updates Employee Directory

5. **RFID Check-In/Out (Timekeeping Kiosk)**
   - Employee taps RFID card at office reader
   - Display shows: Welcome [Name], current time, check-in/out status
   - "Check In" / "Check Out" confirmation displayed
   - System logs tap in real-time; syncs to main server every 2 seconds
   - Attendance auto-updates in Employee dashboard

6. **Download Documents**
   - Employee → Documents
   - View certificates, contracts, ID card request status
   - Download available documents (certificates, payslips, work permits)

---

## **7. RFID TIMEKEEPING KIOSK OPERATOR** *(Administrative Role)*

### Overview
Manages RFID reader hardware. Ensures timekeeping device runs smoothly and syncs with main system.

### Key Responsibilities
- **Device Monitoring** → Check reader status, heartbeat, sync logs
- **Error Resolution** → Restart device, clear buffer, troubleshoot connectivity
- **Card Management** → Register/deactivate employee cards, handle lost cards

### Main Workflows
1. **Check Device Status**
   - System Admin → Timekeeping → RFID Readers
   - View connected readers, last heartbeat timestamp, sync status
   - **Green** = Online, **Red** = Offline, **Yellow** = Slow/Stalled

2. **Restart RFID Reader**
   - Approach physical reader at site
   - Press restart button (or run: `nssm stop CamecoRfidServer; nssm start CamecoRfidServer`)
   - Wait 30 seconds; reader reconnects with "Device Online" beacon
   - Verify in System Admin dashboard

3. **Troubleshoot Sync Issues**
   - If reader shows "Offline" for >2 minutes:
     - Check network connectivity
     - Verify firewall allows API calls to `/api/rfid/`
     - Review reader logs at `rfid-server/logs/`
   - Manual sync: Run `python sync.py` from reader machine

4. **Manage Employee RFID Cards**
   - HR → Timekeeping → Card Management
   - Register new card: Enter card UID, select employee, click Register
   - Deactivate lost card: Select card, click Deactivate (prevents unauthorized taps)
   - Reactivate card: Restore from history if found

---

## **Quick Start Summary by Role**

| Role | First Login → | Main Dashboard | Quick Action |
|------|---|---|---|
| **Superadmin** | System Config | Admin Home | Add User / Set Policy |
| **Office Admin** | HR Home | Approvals | Approve Leave |
| **HR Manager** | HR Home | Workforce Analytics | Post Job / Start Appraisal |
| **HR Staff** | HR Home | My Tasks | Screen Candidate / Process Request |
| **Payroll Officer** | Payroll Home | Payroll Queue | Run Monthly Payroll |
| **Employee** | Employee Home | My Leave Balance | Submit Leave / Check Attendance |
| **RFID Operator** | System Admin | Device Status | Restart Reader |

---

## **Common Features Across All Roles**

✅ **Notifications** — Real-time alerts for approvals, requests, updates  
✅ **Activity Logs** — Personal audit trail of actions taken  
✅ **Search & Filter** — Find employees, records, reports by keyword/date  
✅ **Export** — Download records as PDF or Excel  
✅ **Mobile-Responsive** — Accessible on phones, tablets, desktops  
✅ **Two-Factor Auth** — Optional security for sensitive roles
