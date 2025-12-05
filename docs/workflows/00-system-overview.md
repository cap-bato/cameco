# System Overview - SyncingSteel HRIS

## High-Level System Architecture

```mermaid
graph TB
    subgraph External["External Sources"]
        Supervisors[Supervisors<br/>Submit Paper Schedules]
        Applicants[Job Applicants<br/>Facebook/In-Person]
        RFID[RFID Card Taps<br/>Timekeeping]
    end
    
    subgraph EmployeeAccess["Employee Self-Service"]
        EmployeePortal[Employee Portal<br/>View & Submit Requests]
    end
    
    subgraph System["SyncingSteel HRIS - On-Premise System"]
        subgraph Admin["Administration Layer"]
            Superadmin[Superadmin<br/>System Monitoring]
            OfficeAdmin[Office Admin<br/>Company Setup]
        end
        
        subgraph HRLayer["HR Operations Layer"]
            HRManager[HR Manager<br/>Approvals]
            HRStaff[HR Staff<br/>Data Entry & Operations]
        end
        
        subgraph PayrollLayer["Payroll Layer"]
            PayrollOfficer[Payroll Officer<br/>Payroll Processing]
        end
        
        subgraph Modules["Core Modules"]
            EmployeeDB[(Employee<br/>Database)]
            Timekeeping[Timekeeping<br/>Module]
            PayrollModule[Payroll<br/>Module]
            WorkforceModule[Workforce<br/>Management]
            ATSModule[ATS<br/>Module]
            OnboardingModule[Onboarding<br/>Module]
            AppraisalModule[Appraisal<br/>Module]
        end
        
        subgraph Integration["Integration Layer"]
            EventBus[Event Bus]
            Notifications[Notification<br/>System]
        end
    end
    
    subgraph External2["External Systems (Future)"]
        Banks[Bank Transfer<br/>Integration]
        Ewallets[E-wallet<br/>Integration]
        JobBoard[Public Job<br/>Board]
    end
    
    %% External to System
    Supervisors -->|Paper Records| HRStaff
    Applicants -->|Applications| HRStaff
    RFID -->|Card Tap Events| EventBus
    
    %% Employee Portal to System
    EmployeePortal -->|Leave Requests| Modules
    EmployeePortal -->|View Data| Modules
    
    %% Admin Layer
    Superadmin -.->|Emergency Access| Modules
    Superadmin -->|Monitors| System
    OfficeAdmin -->|Configures| Modules
    
    %% HR Layer
    HRManager -->|Approves| HRStaff
    HRStaff -->|Inputs Data| Modules
    
    %% Payroll Layer
    PayrollOfficer -->|Processes| PayrollModule
    
    %% Module Connections
    EmployeeDB <-->|Master Data| Modules
    Timekeeping -->|Attendance Data| PayrollModule
    WorkforceModule -->|Schedule Data| Timekeeping
    ATSModule -->|Hired Candidates| OnboardingModule
    OnboardingModule -->|Active Employees| PayrollModule
    AppraisalModule -->|Performance Data| EmployeeDB
    
    %% Integration
    EventBus -->|Distributes Events| Timekeeping
    EventBus -->|Distributes Events| PayrollModule
    EventBus -->|Triggers| Notifications
    
    %% Future Integrations
    PayrollModule -.->|Future| Banks
    PayrollModule -.->|Future| Ewallets
    ATSModule -.->|Future| JobBoard
    
    style External fill:#f9f,stroke:#333,stroke-width:2px
    style External2 fill:#f9f,stroke:#333,stroke-width:2px,stroke-dasharray: 5 5
    style System fill:#e1f5ff,stroke:#01579b,stroke-width:3px
    style Admin fill:#fff3e0,stroke:#e65100,stroke-width:2px
    style HRLayer fill:#e8f5e9,stroke:#2e7d32,stroke-width:2px
    style PayrollLayer fill:#fce4ec,stroke:#c2185b,stroke-width:2px
    style Modules fill:#f3e5f5,stroke:#4a148c,stroke-width:2px
    style Integration fill:#e0f2f1,stroke:#00695c,stroke-width:2px
```

## User Roles & Responsibilities Summary

### 1. 👤 Superadmin
**Focus**: System health and infrastructure

- 🔧 System health monitoring (CPU, memory, database)
- 🖥️ Server management and security
- ⚙️ Application configuration
- 👥 User account management
- 🚨 Emergency module access

**[View Detailed Workflow →](./01-superadmin-workflow.md)**

---

### 2. 👤 Office Admin
**Focus**: Company setup and business rules

- 🏢 Company onboarding and setup
- 📋 Business rules configuration
- 🏛️ Department and position management
- 📅 Leave policies and approval workflows
- 💰 Payroll rules and salary structures
- 🔔 System-wide configurations

**[View Detailed Workflow →](./02-office-admin-workflow.md)**

---

### 3. 👤 HR Manager
**Focus**: Oversight and approvals

- ✅ Approve leave requests (3-5 days: manager, 6+ days: + office admin)
- ✅ Approve hiring decisions and job offers
- ✅ Approve performance appraisals
- ✅ Approve terminations and transfers
- 📊 Review workforce schedules
- 👀 Oversee HR staff operations

**[View Detailed Workflow →](./03-hr-manager-workflow.md)**

---

### 4. 👤 HR Staff
**Focus**: Day-to-day operations and data entry

- 📝 Input leave requests from employees (paper/email/phone)
- 📋 Input workforce schedules from supervisors (paper)
- 🎯 Manage ATS (job postings, applications, interviews)
- 🎓 Process employee onboarding
- 👥 Manage employee records and documents
- ⏰ Coordinate timekeeping and attendance
- 🌟 Create performance appraisals
- 📊 Generate HR reports

**[View Detailed Workflow →](./04-hr-staff-workflow.md)**

---

### 5. 👤 Payroll Officer
**Focus**: Payroll processing and compliance

- 💵 Process payroll periods and calculations
- 💰 Manage salary components and deductions
- 🏦 Handle employee advances and loans
- 🏛️ Process government remittances (SSS, PhilHealth, Pag-IBIG, BIR)
- 📄 Generate payslips and payment files
- 💵 Manage cash distribution (current method)
- 📊 Generate payroll reports and analytics

**[View Detailed Workflow →](./05-payroll-officer-workflow.md)**

---

### 6. 👤 Employee (Self-Service Portal)
**Focus**: Personal information access and leave management

- 📊 View personal information and employment details
- ⏰ View time logs and attendance records
- 💰 View and download payslips
- 📅 Check leave balances and history
- ✉️ Submit leave requests directly
- 📄 Track request status and approvals
- 🔔 Receive notifications and alerts

**[View Employee Portal Guide →](./06-employee-portal.md)**

---

## Key Processes

### 🔄 Leave Request Flow
```mermaid
graph LR
    Employee[Employee Submits<br/>via Portal] --> System[HRIS System<br/>Validates Request]
    System --> Duration{Duration?}
    Duration -->|1-2 days| AutoApprove[Auto-Approved<br/>by System]
    Duration -->|3-5 days| HRManager[HR Manager<br/>Approves]
    Duration -->|6+ days| Both[HR Manager +<br/>Office Admin]
    AutoApprove --> Notify[Employee<br/>Notified]
    HRManager --> Notify
    Both --> Notify
    
    style Employee fill:#ffecb3
    style HRStaff fill:#c8e6c9
    style AutoApprove fill:#b2dfdb
    style HRManager fill:#fff9c4
    style Both fill:#ffccbc
    style Notify fill:#d1c4e9
```

**[View Detailed Process →](./processes/leave-request-approval.md)**

---

### 💰 Payroll Processing Flow
```mermaid
graph LR
    Start[Start Payroll<br/>Period] --> Fetch[Fetch Attendance<br/>from Timekeeping]
    Fetch --> Calculate[Calculate Salaries<br/>Deductions & Taxes]
    Calculate --> Review[HR Manager<br/>Reviews]
    Review --> Approve[Office Admin<br/>Final Approval]
    Approve --> Distribute[Payroll Officer<br/>Distributes Payment]
    Distribute --> Complete[Payroll<br/>Complete]
    
    style Start fill:#e1f5fe
    style Fetch fill:#f3e5f5
    style Calculate fill:#fff9c4
    style Review fill:#ffecb3
    style Approve fill:#ffccbc
    style Distribute fill:#c8e6c9
    style Complete fill:#b2dfdb
```

**[View Detailed Process →](./processes/payroll-processing.md)**

---

### 📋 RFID Timekeeping Integration
```mermaid
graph LR
    Tap[Employee Taps<br/>RFID Card] --> Edge[RFID Edge<br/>Device]
    Edge --> Event[Generate Atomic<br/>Event]
    Event --> Bus[Internal<br/>Event Bus]
    Bus --> TK[Timekeeping<br/>Module]
    Bus --> PR[Payroll<br/>Module]
    Bus --> Notif[Notification<br/>System]
    
    TK --> Save[Save Time<br/>Record]
    PR --> Update[Update Payroll<br/>Data]
    Notif --> Send[Send<br/>Notification]
    
    style Tap fill:#ffecb3
    style Edge fill:#f3e5f5
    style Event fill:#e1f5fe
    style Bus fill:#fff9c4
    style TK fill:#c8e6c9
    style PR fill:#ffccbc
    style Notif fill:#d1c4e9
```

**[View Detailed Integration →](./integrations/rfid-integration.md)**

---

## Module Overview

### 📊 Core Modules

| Module | Primary Users | Purpose |
|--------|--------------|---------|
| **Employee Management** | HR Staff, HR Manager | Employee master data and lifecycle |
| **Timekeeping** | HR Staff | Attendance tracking and RFID integration |
| **Payroll** | Payroll Officer | Salary calculation and payment distribution |
| **Workforce Management** | HR Staff, HR Manager | Shift scheduling and rotations |
| **ATS (Recruitment)** | HR Staff, HR Manager | Job postings and applicant tracking |
| **Onboarding** | HR Staff | New hire document collection and setup |
| **Appraisal** | HR Staff, HR Manager | Performance reviews and ratings |
| **Leave Management** | HR Staff, HR Manager, Office Admin | Leave requests and approvals |

### 🔗 Module Dependencies

```mermaid
graph TD
    EmployeeDB[(Employee<br/>Database)]
    
    EmployeeDB -->|Master Data| Timekeeping
    EmployeeDB -->|Master Data| Payroll
    EmployeeDB -->|Master Data| Workforce
    EmployeeDB -->|Master Data| Leave
    EmployeeDB -->|Master Data| Appraisal
    
    Timekeeping -->|Attendance Data| Payroll
    Workforce -->|Schedule Data| Timekeeping
    Leave -->|Leave Data| Payroll
    Leave -->|Leave Data| Workforce
    
    ATS -->|Hired Candidates| Onboarding
    Onboarding -->|Active Employees| EmployeeDB
    Onboarding -->|Active Employees| Payroll
    
    Appraisal -->|Performance Data| EmployeeDB
    
    style EmployeeDB fill:#4a148c,color:#fff
    style Timekeeping fill:#00695c,color:#fff
    style Payroll fill:#c2185b,color:#fff
    style Workforce fill:#2e7d32,color:#fff
    style ATS fill:#e65100,color:#fff
    style Onboarding fill:#01579b,color:#fff
    style Appraisal fill:#6a1b9a,color:#fff
    style Leave fill:#f57c00,color:#fff
```

---

## Data Flow Architecture

### 📥 Input Sources

```mermaid
graph TB
    subgraph External["External Sources"]
        Super[Supervisors<br/>Paper Schedules]
        Emp[Employees<br/>Leave Requests]
        Apps[Applicants<br/>Job Applications]
        RFID[RFID Cards<br/>Attendance]
    end
    
    subgraph Processing["Data Processing"]
        HREntry[HR Staff<br/>Manual Entry]
        AutoProcess[Automated<br/>Processing]
    end
    
    subgraph Storage["System Storage"]
        Database[(Central<br/>Database)]
    end
    
    Super -->|Paper| HREntry
    Emp -->|Paper/Email/Phone| HREntry
    Apps -->|Facebook/In-Person| HREntry
    RFID -->|Card Tap| AutoProcess
    
    HREntry --> Database
    AutoProcess --> Database
    
    style External fill:#ffecb3,stroke:#f57c00,stroke-width:2px
    style Processing fill:#c8e6c9,stroke:#2e7d32,stroke-width:2px
    style Storage fill:#b2dfdb,stroke:#00695c,stroke-width:2px
```

---

## Approval Workflows

### ✅ Multi-Level Approvals

```mermaid
graph TD
    subgraph Leaves["Leave Requests"]
        L1[1-2 days: Auto-Approved]
        L2[3-5 days: HR Manager]
        L3[6+ days: HR Manager + Office Admin]
    end
    
    subgraph Hiring["Hiring Decisions"]
        H1[HR Staff: Screen]
        H2[HR Manager: Interview & Approve]
        H3[Office Admin: Final Approval]
    end
    
    subgraph Payroll["Payroll Processing"]
        P1[Payroll Officer: Calculate]
        P2[HR Manager: Review]
        P3[Office Admin: Final Approval]
    end
    
    subgraph Termination["Terminations"]
        T1[HR Staff: Initiate]
        T2[HR Manager: Approve]
        T3[Payroll Officer: Final Pay]
    end
    
    style L1 fill:#b2dfdb
    style L2 fill:#fff9c4
    style L3 fill:#ffccbc
    style H1 fill:#c8e6c9
    style H2 fill:#fff9c4
    style H3 fill:#ffccbc
    style P1 fill:#e1bee7
    style P2 fill:#fff9c4
    style P3 fill:#ffccbc
    style T1 fill:#c8e6c9
    style T2 fill:#fff9c4
    style T3 fill:#e1bee7
```

---

## System Configuration

### 🎛️ Configurable Features (Office Admin)

- **Working Hours**: Regular schedule, shift patterns
- **Holiday Calendar**: National holidays, company holidays, pay multipliers
- **Overtime Rules**: Threshold, rates, approval requirements
- **Attendance Rules**: Late policy, undertime policy, grace periods
- **Leave Policies**: Types, accrual methods, approval rules
- **Salary Structure**: Basic salary, allowances, bonuses
- **Deduction Rules**: Mandatory (SSS, PhilHealth, Pag-IBIG), optional (loans, advances)
- **Government Rates**: SSS, PhilHealth, Pag-IBIG, BIR tax tables
- **Payment Methods**: Cash (current), bank transfer (future), e-wallet (future)
- **Approval Workflows**: Leave, hiring, payroll, expenses

---

## Security & Access Control

### 🔐 Role-Based Access Control (RBAC)

```mermaid
graph TB
    subgraph Roles["User Roles"]
        SA[Superadmin<br/>Emergency Access]
        OA[Office Admin<br/>Configuration]
        HRM[HR Manager<br/>Approvals]
        HRS[HR Staff<br/>Operations]
        PO[Payroll Officer<br/>Payroll]
    end
    
    subgraph Permissions["Permission Levels"]
        Full[Full Access<br/>Create, Read, Update, Delete]
        Approve[Approval Rights<br/>Review & Approve]
        View[View Only<br/>Read-only Access]
        None[No Access<br/>Restricted]
    end
    
    SA -.->|Emergency Only| Full
    OA --> Full
    HRM --> Approve
    HRS --> Full
    PO --> Full
    
    style SA fill:#f44336,color:#fff
    style OA fill:#ff9800,color:#fff
    style HRM fill:#4caf50,color:#fff
    style HRS fill:#2196f3,color:#fff
    style PO fill:#9c27b0,color:#fff
    
    style Full fill:#4caf50,color:#fff
    style Approve fill:#ff9800,color:#fff
    style View fill:#2196f3,color:#fff
    style None fill:#757575,color:#fff
```

---

## Technology Stack

### 💻 System Architecture

- **Backend**: Laravel 11 + Jetstream (MVCSR Pattern)
- **Frontend**: React + Inertia.js (No API Mode)
- **Database**: PostgreSQL/SQLite
- **Authentication**: Role-based access control with approval workflow
- **Deployment**: On-premise company server
- **Integration**: Event-driven architecture (RFID → Event Bus)

### 🔮 Future Enhancements

- 🌐 Public job board website
- 📱 Mobile app for employees
- 🏦 Bank transfer integration
- 💳 E-wallet payment support
- 👤 Biometric attendance (facial recognition/fingerprint)
- 🖥️ Supervisor portal for direct schedule submission

---

## Quick Links

### 📖 Documentation
- [Superadmin Workflow](./01-superadmin-workflow.md)
- [Office Admin Workflow](./02-office-admin-workflow.md)
- [HR Manager Workflow](./03-hr-manager-workflow.md)
- [HR Staff Workflow](./04-hr-staff-workflow.md)
- [Payroll Officer Workflow](./05-payroll-officer-workflow.md)

### 🔄 Processes
- [Leave Request Approval](./processes/leave-request-approval.md)
- [Payroll Processing](./processes/payroll-processing.md)
- [Employee Onboarding](./processes/employee-onboarding.md)
- [Performance Appraisal](./processes/performance-appraisal.md)

### 🔗 Integrations
- [RFID Integration](./integrations/rfid-integration.md)
- [ATS Integration](./integrations/ats-integration.md)

## Immutable Ledger & Replay Monitoring

- All RFID/timekeeping events feeding these workflows persist first in the PostgreSQL ledger (`rfid_ledger`) governed by the Replayable Event-Log Verification Layer.
- That layer owns dedicated alerting and metrics (ledger commit latency, sequence gaps, hash verification failures, replay backlog) that platform owners must monitor before approving downstream actions.

---

**Document Version**: 2.0  
**Last Updated**: November 29, 2025  
**System Type**: On-Premise HRIS for Office Staff Use
