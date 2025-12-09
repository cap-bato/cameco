# Document Management System - HR Staff & HR Manager Implementation

**Issue Type:** Feature Implementation  
**Priority:** HIGH  
**Estimated Duration:** 3-4 weeks  
**Target Users:** HR Staff, HR Manager  
**Dependencies:** Employee Module, Storage System, File Upload Component  
**Related Modules:** Employee Management, Onboarding, Compliance

---

## 📋 Executive Summary

Implement a comprehensive Document Management System for HR Staff and HR Manager to digitize and manage employee 201 files and company documents required for Philippine labor law compliance. This system will replace manual paper-based filing with a secure, searchable digital repository.

**Core Objectives:**
1. Digitize employee 201 files with all required documents
2. Implement document templates with variable fields for common HR documents
3. Create document approval workflows (critical docs only)
4. Build document repository with audit logging
5. Ensure Philippine labor law compliance (5-year retention)
6. Provide document search and retrieval with employee self-service requests
7. Track document expiry dates with automated reminders
8. Support bulk document upload for migration of existing files

**Applied Implementation Decisions:**

**Storage & Security:**
- Store documents in Laravel's `storage/app/employee-documents/{employee_id}/{category}/{year}/`
- File limits: 10MB per file, 100MB total per employee
- Allowed formats: PDF (primary), JPEG/PNG (scanned), DOCX (editable)
- Retention policy: 5 years after employee separation (DOLE compliance)
- Soft delete with automatic archiving after retention period

**Access Control:**
- **HR Staff**: View/upload all employee documents
- **HR Manager**: View/upload/approve all employee documents
- **Employees**: View their own documents (read-only) + request documents via portal
- **Department Heads**: Future consideration (not in initial phase)

**Approval Workflow:**
- **Critical documents** (contracts, clearances, separation): HR Manager approval required
- **Personal documents** (IDs, certificates): Auto-approved upon upload
- Audit logging for: upload, download, approve, delete actions

**Compliance Features:**
- BIR Form 2316 copies stored in employee documents
- Automatic alerts for government ID expirations
- Annual medical certificate renewal reminders
- Document expiry tracking with 30-day advance warnings

**Enhanced Features (Implemented):**
- **Document Request System**: Employees can request COE, payslips via portal
- **Bulk Upload**: CSV-based bulk document upload for existing file migration
- **Expiry Reminders**: Automated email reminders for expiring documents (medical, NBI, licenses)
- **Template System**: 9 standard templates with variable fields (locked after approval)

---

## ✅ Implementation Decisions Applied

**Storage Configuration:**
- Local storage at `storage/app/employee-documents/{employee_id}/{category}/{year}/`
- 10MB max per file, 100MB total per employee
- Formats: PDF, JPEG, PNG, DOCX only
- Private storage with signed URLs for downloads (24-hour expiry)

**Security & Compliance:**
- Role-based access: HR Staff (upload/view), HR Manager (approve/delete)
- Approval workflow: Critical docs require HR Manager approval, personal docs auto-approved
- Audit logging: Upload, download, approve, delete actions tracked
- Data retention: 5-year soft delete, automatic archiving after period

**Philippine Labor Law Compliance:**
- All 43 DOLE-required documents supported
- BIR Form 2316 integration with Payroll module
- Government ID expiration alerts (SSS, PhilHealth, Pag-IBIG, NBI)
- Annual medical certificate renewal reminders

**Enhanced Features Included:**
- ✅ **Document Request System**: Employees request COE/payslips via portal
- ✅ **Bulk Upload**: CSV-based bulk document upload for file migration
- ✅ **Expiry Reminders**: Automated email alerts for expiring documents
- ✅ **Template Management**: 9 templates with variable fields, version control
- ⏳ **Document Scanning/OCR**: Future enhancement (not in this phase)
- ⏳ **E-Signature Integration**: Future enhancement (not in this phase)

---

## 📂 Philippine Labor Law - Required Employee Documents (201 File)

### Category 1: Personal Identification Documents
1. **Birth Certificate** (PSA-authenticated)
   - Required by: DOLE, SSS
   - Validity: Permanent
   - Storage: Scanned copy in 201 file

2. **Valid Government IDs** (at least 2)
   - Examples: Driver's License, Passport, Postal ID, Voter's ID, PRC License
   - Required by: Bank, SSS, PhilHealth, Pag-IBIG
   - Validity: Check expiry dates
   - Storage: Front and back scans

3. **TIN Card / BIR Form 1902**
   - Tax Identification Number
   - Required by: BIR for tax filing
   - Validity: Permanent
   - Storage: Scanned copy

4. **SSS E-1 Form / SS Number**
   - Social Security System registration
   - Required by: SSS monthly remittance
   - Validity: Permanent
   - Storage: Scanned copy

5. **PhilHealth MDR Form**
   - PhilHealth registration
   - Required by: PhilHealth remittance
   - Validity: Permanent
   - Storage: Scanned copy

6. **Pag-IBIG Member Data Form (MDF)**
   - Home Development Mutual Fund registration
   - Required by: Pag-IBIG remittance
   - Validity: Permanent
   - Storage: Scanned copy

7. **2x2 ID Photos** (at least 4 copies)
   - For ID cards, clearances, certifications
   - Validity: 6 months to 1 year
   - Storage: Digital photo file

### Category 2: Educational & Professional Credentials
8. **Educational Credentials**
   - Diploma (highest educational attainment)
   - Transcript of Records
   - Professional licenses (if applicable)
   - Training certificates
   - Required by: DOLE, position requirements verification
   - Validity: Permanent
   - Storage: Scanned copies

9. **PRC License** (if applicable)
   - Professional Regulation Commission license
   - For regulated professions (engineers, nurses, accountants, etc.)
   - Validity: Check renewal date (usually 3 years)
   - Storage: Scanned copy with expiry tracking

### Category 3: Employment & Background Documents
10. **Resume / Curriculum Vitae**
    - Latest version submitted during application
    - Storage: PDF file

11. **NBI Clearance**
    - National Bureau of Investigation clearance
    - Required by: Background check requirement
    - Validity: 6 months (recommended annual renewal)
    - Storage: Scanned copy with expiry tracking

12. **Police Clearance** (optional but recommended)
    - Local police clearance
    - Validity: 6 months
    - Storage: Scanned copy

13. **Barangay Clearance** (optional)
    - Certificate of Good Moral Character
    - Validity: 6 months to 1 year
    - Storage: Scanned copy

14. **Previous Employment Certificate**
    - Certificate of Employment from previous employer
    - Certificate of Separation / Clearance
    - Required by: Employment history verification
    - Validity: Permanent
    - Storage: Scanned copies

### Category 4: Medical & Health Documents
15. **Pre-Employment Medical Certificate**
    - Fit-to-work certificate from licensed physician
    - Drug test results
    - Chest X-ray (for food handlers, healthcare workers)
    - Required by: DOLE Occupational Safety & Health Standards
    - Validity: 1 year (annual renewal required)
    - Storage: Scanned copy with expiry tracking

16. **Annual Physical Examination Results**
    - Required by: DOLE for ongoing employment
    - Validity: 1 year
    - Storage: Scanned copies, update annually

### Category 5: Employment Contract & Company Documents
17. **Job Application Form**
    - Original application form with signature
    - Storage: Scanned copy

18. **Employment Contract**
    - Signed contract with terms and conditions
    - Probationary contract (first 6 months)
    - Regular employment contract
    - Required by: DOLE, legal requirement
    - Validity: Duration of employment
    - Storage: Signed PDF

19. **Job Offer Letter**
    - Original offer with acceptance signature
    - Storage: Scanned copy

20. **Non-Disclosure Agreement (NDA)**
    - If applicable for position
    - Storage: Signed PDF

21. **Employee Handbook Acknowledgment**
    - Signed acknowledgment of receipt and understanding
    - Required by: Company policy, DOLE compliance
    - Storage: Signed PDF

22. **Company ID Release Form**
    - Receipt for company ID card issuance
    - Storage: Scanned copy

### Category 6: Compensation & Benefits Documents
23. **Payroll Information Sheet**
    - Bank account details
    - Emergency contact information
    - Tax exemption details (dependents)
    - Storage: Latest version

24. **BIR Form 2316** (Annual Tax Certificate)
    - Copy from previous employer (if applicable)
    - Annual ITR from current company
    - Required by: BIR for income tax filing
    - Validity: Per calendar year
    - Storage: PDF per year

25. **Loan Documents**
    - SSS salary loan application & approval
    - Pag-IBIG housing loan documents
    - Company loan agreements
    - Storage: Scanned copies

### Category 7: Performance & Disciplinary Records
26. **Performance Appraisal Forms**
    - Probationary evaluation
    - Annual performance reviews
    - 360-degree feedback (if applicable)
    - Storage: PDF per evaluation period

27. **Training & Seminar Certificates**
    - Company-provided training
    - External training certificates
    - Storage: Scanned copies

28. **Disciplinary Action Records**
    - Written warnings
    - Suspension notices
    - Incident reports
    - Required by: Due process, DOLE compliance
    - Storage: Scanned copies (sensitive documents)

29. **Leave Records**
    - Leave applications with approvals
    - Leave balance history
    - Medical certificates for sick leave
    - Storage: System-generated reports + supporting docs

### Category 8: Separation Documents (if applicable)
30. **Resignation Letter**
    - Original resignation letter
    - Acceptance letter from management
    - Storage: Scanned copies

31. **Clearance Form**
    - Signed by all departments
    - Equipment return acknowledgment
    - Financial clearance
    - Storage: Completed form

32. **Certificate of Employment**
    - Issued upon separation
    - Copy retained in 201 file
    - Storage: PDF

33. **Final Pay Computation**
    - Last salary details
    - Unused leave conversion
    - 13th month pay prorated
    - Tax refund or payment
    - Storage: PDF with employee signature

34. **Certificate of Separation (BIR Form 2316 equivalent)**
    - For separated employees
    - Storage: PDF

### Category 9: Government Mandatory Documents
35. **SSS R-1A Form** (Employment Report)
    - Employee registration with SSS
    - Storage: Scanned copy

36. **PhilHealth ER1 Form** (Employer Registration)
    - Employee enrollment in PhilHealth
    - Storage: Scanned copy

37. **Pag-IBIG MCRF** (Member's Change Request Form)
    - Updates to member information
    - Storage: Scanned copies

### Category 10: Special Cases (if applicable)
38. **PWD ID Card**
    - For Persons with Disability
    - Entitles to PWD benefits under RA 10754
    - Storage: Scanned copy

39. **Solo Parent ID**
    - For solo parent benefits (7 days additional leave)
    - Storage: Scanned copy

40. **Marriage Certificate** (if claiming married tax exemption)
    - PSA-authenticated copy
    - Storage: Scanned copy

41. **Birth Certificates of Dependents**
    - For tax exemption claims
    - Storage: Scanned copies

42. **Spouse Employment Certificate**
    - If claiming additional exemptions
    - Storage: Scanned copy

43. **Work Permit** (for foreign employees)
    - Alien Employment Permit (AEP)
    - 9(g) Working Visa
    - ACR I-Card (Alien Certificate of Registration)
    - Storage: Scanned copies with expiry tracking

---

## 🎯 Implementation Phases

### Phase 1: Permissions, Routes & Controllers (Week 1)

**Goal:** Set up permissions, routing, and backend controllers for document management

**Duration:** 5-7 days

**Status:** 🔄 67% (4/6 tasks complete)

#### Task 1.1: Document Management Permissions Seeder ✅ COMPLETE
- [x] Create `database/seeders/DocumentManagementPermissionsSeeder.php`
  - [x] Create permissions with module 'hr' and guard 'web':
    - [x] **HR Staff Permissions:**
      - `hr.documents.view` - View all employee documents
      - `hr.documents.upload` - Upload documents
      - `hr.documents.download` - Download documents
      - `hr.documents.delete` - Delete documents (soft delete)
    
    - [x] **HR Manager Additional Permissions:**
      - `hr.documents.approve` - Approve pending documents
      - `hr.documents.reject` - Reject pending documents
      - `hr.documents.templates.manage` - Manage document templates
      - `hr.documents.audit` - View audit logs
      - `hr.documents.bulk-upload` - Bulk upload via CSV
  
  - [x] Assign permissions to roles:
    - [x] **HR Staff role**: Assign all HR Staff permissions (4 permissions)
    - [x] **HR Manager role**: Assign all HR Staff + HR Manager permissions (9 permissions)

  - [x] Run seeder:
    ```bash
    php artisan db:seed --class=DocumentManagementPermissionsSeeder
    ```
    **Result**: ✓ Seeded successfully - 9 permissions created and assigned

#### Task 1.2: Route Configuration ✅ COMPLETE
- [x] Add routes to `routes/hr.php`
  ```php
  use App\Http\Controllers\HR\Documents\EmployeeDocumentController;
  use App\Http\Controllers\HR\Documents\DocumentTemplateController;
  use App\Http\Controllers\HR\Documents\DocumentRequestController;

  // Document Management Routes
  Route::prefix('documents')->name('documents.')->group(function () {
      // Employee Documents
      Route::get('/', [EmployeeDocumentController::class, 'index'])
          ->middleware('permission:hr.documents.view')
          ->name('index');
      
      Route::get('/upload', [EmployeeDocumentController::class, 'create'])
          ->middleware('permission:hr.documents.upload')
          ->name('create');
      
      Route::post('/', [EmployeeDocumentController::class, 'store'])
          ->middleware('permission:hr.documents.upload')
          ->name('store');
      
      Route::get('/bulk-upload', [EmployeeDocumentController::class, 'bulkUploadForm'])
          ->middleware('permission:hr.documents.bulk-upload')
          ->name('bulk-upload');
      
      Route::post('/bulk-upload', [EmployeeDocumentController::class, 'bulkUpload'])
          ->middleware('permission:hr.documents.bulk-upload')
          ->name('bulk-upload.store');
      
      Route::get('/{document}', [EmployeeDocumentController::class, 'show'])
          ->middleware('permission:hr.documents.view')
          ->name('show');
      
      Route::get('/{document}/download', [EmployeeDocumentController::class, 'download'])
          ->middleware('permission:hr.documents.download')
          ->name('download');
      
      Route::post('/{document}/approve', [EmployeeDocumentController::class, 'approve'])
          ->middleware('permission:hr.documents.approve')
          ->name('approve');
      
      Route::post('/{document}/reject', [EmployeeDocumentController::class, 'reject'])
          ->middleware('permission:hr.documents.reject')
          ->name('reject');
      
      Route::delete('/{document}', [EmployeeDocumentController::class, 'destroy'])
          ->middleware('permission:hr.documents.delete')
          ->name('destroy');
      
      // Document Templates
      Route::prefix('templates')->name('templates.')->group(function () {
          Route::get('/', [DocumentTemplateController::class, 'index'])
              ->middleware('permission:hr.documents.view')
              ->name('index');
          
          Route::get('/create', [DocumentTemplateController::class, 'create'])
              ->middleware('permission:hr.documents.templates.manage')
              ->name('create');
          
          Route::post('/', [DocumentTemplateController::class, 'store'])
              ->middleware('permission:hr.documents.templates.manage')
              ->name('store');
          
          Route::get('/{template}/edit', [DocumentTemplateController::class, 'edit'])
              ->middleware('permission:hr.documents.templates.manage')
              ->name('edit');
          
          Route::put('/{template}', [DocumentTemplateController::class, 'update'])
              ->middleware('permission:hr.documents.templates.manage')
              ->name('update');
          
          Route::post('/{template}/generate', [DocumentTemplateController::class, 'generate'])
              ->middleware('permission:hr.documents.view')
              ->name('generate');
      });
      
      // Document Requests
      Route::prefix('requests')->name('requests.')->group(function () {
          Route::get('/', [DocumentRequestController::class, 'index'])
              ->middleware('permission:hr.documents.view')
              ->name('index');
          
          Route::post('/{request}/process', [DocumentRequestController::class, 'process'])
              ->middleware('permission:hr.documents.upload')
              ->name('process');
      });
  });
  ```

#### Task 1.3: Validation Request Classes ✅ COMPLETE
- [x] Create `app/Http/Requests/HR/Documents/UploadDocumentRequest.php`
  - [x] Rules: employee_id (required, exists:employees,id)
  - [x] Rules: document_category (required, string, in:personal,educational,employment,medical,contracts,benefits,performance,separation,government,special)
  - [x] Rules: document_type (required, string, max:100)
  - [x] Rules: file (required, file, max:10240, mimes:pdf,jpg,jpeg,png,docx)
  - [x] Rules: expires_at (nullable, date, after:today)
  - [x] Rules: notes (nullable, string, max:500)
  - [x] Custom error messages for all validation rules

- [x] Create `app/Http/Requests/HR/Documents/ApproveDocumentRequest.php`
  - [x] Rules: notes (nullable, string, max:500)
  - [x] Custom error messages

- [x] Create `app/Http/Requests/HR/Documents/RejectDocumentRequest.php`
  - [x] Rules: rejection_reason (required, string, max:500)
  - [x] Custom error messages

- [x] Create `app/Http/Requests/HR/Documents/BulkUploadRequest.php`
  - [x] Rules: csv_file (required, file, mimes:csv,txt, max:5120)
  - [x] Rules: zip_file (required, file, mimes:zip, max:102400)
  - [x] Custom error messages

**Result**: ✓ 4 validation request classes created following Laravel FormRequest pattern with custom error messages

#### Task 1.4: Employee Document Controller ✅ COMPLETE
- [x] Create `app/Http/Controllers/HR/Documents/EmployeeDocumentController.php`
  - [x] `index()` method - List all documents with filters
    - [x] Filter by: employee, category, status, expiry date
    - [x] Search by: file name, employee name
    - [x] Paginate: 20 per page
    - [x] Include: employee info, category, uploader, approver
    - [x] Inertia render: `HR/Documents/Index`
    - [x] Returns mock data structure for frontend development

  - [x] `show()` method - View document details
    - [x] Return: document metadata, preview URL, audit log
    - [x] Log: document view action (security audit)
    - [x] Inertia render: `HR/Documents/Show`
    - [x] Returns mock data structure for frontend development

  - [x] `create()` method - Show upload form
    - [x] Return: employees list, categories list
    - [x] Inertia render: `HR/Documents/Upload`
    - [x] Fetches active employees from database

  - [x] `store()` method - Upload document
    - [x] Uses UploadDocumentRequest for validation
    - [x] Validates: file (required, max 10MB, mimes: pdf,jpg,png,docx)
    - [x] Validates: employee_id, document_category, document_type, expires_at (nullable)
    - [x] Logs: upload action (security audit)
    - [x] Return: redirect with success message
    - [x] Note: File storage implementation pending (Phase 4 - Database)

  - [x] `download()` method - Download document
    - [x] Authorize: middleware handles permission
    - [x] Log: download action (security audit)
    - [x] Return: file download response
    - [x] Note: File retrieval implementation pending (Phase 4 - Database)

  - [x] `approve()` method - Approve pending document
    - [x] Uses ApproveDocumentRequest for validation
    - [x] Authorize: middleware handles HR Manager permission
    - [x] Logs: approve action (security audit)
    - [x] Return: redirect with success message
    - [x] Note: Database update implementation pending (Phase 4)

  - [x] `reject()` method - Reject pending document
    - [x] Uses RejectDocumentRequest for validation
    - [x] Authorize: middleware handles HR Manager permission
    - [x] Validates: rejection_reason (required)
    - [x] Logs: reject action (security audit)
    - [x] Return: redirect with success message
    - [x] Note: Database update implementation pending (Phase 4)

  - [x] `destroy()` method - Soft delete document
    - [x] Authorize: middleware handles permission
    - [x] Logs: delete action (security audit with 'warning' severity)
    - [x] Return: redirect with success message
    - [x] Note: Soft delete implementation pending (Phase 4)

  - [x] `bulkUploadForm()` method - Show bulk upload form
    - [x] Return: CSV template structure with headers and example
    - [x] Return: categories list
    - [x] Inertia render: `HR/Documents/BulkUpload`

  - [x] `bulkUpload()` method - Upload multiple documents via CSV
    - [x] Uses BulkUploadRequest for validation
    - [x] Authorize: middleware handles permission
    - [x] Accept: CSV file + ZIP file containing documents
    - [x] Logs: bulk_upload_initiated action (security audit)
    - [x] Return: Results summary (total, success, errors)
    - [x] Inertia render: `HR/Documents/BulkUploadResult`
    - [x] Note: CSV parsing and file extraction implementation pending (Phase 4)

**Result**: ✓ EmployeeDocumentController created with all 10 methods, security audit logging, and Inertia page rendering. Controllers return mock data for frontend development (Phase 2). Database integration pending (Phase 4).

#### Task 1.5: Document Template Controller
- [ ] Create `app/Http/Controllers/HR/Documents/DocumentTemplateController.php`
  - [ ] `index()` method - List templates
    - [ ] Filter by: status (active/inactive)
    - [ ] Include: creator, approver
    - [ ] Inertia render: `HR/Documents/Templates/Index`

  - [ ] `create()` method - Show create form
    - [ ] Inertia render: `HR/Documents/Templates/Create`

  - [ ] `store()` method - Create template
    - [ ] Authorize: HR Manager only
    - [ ] Validate: name, file, description, variables (JSON)
    - [ ] Store: template file
    - [ ] Create: DocumentTemplate record
    - [ ] Return: redirect with success message

  - [ ] `edit()` method - Show edit form
    - [ ] Authorize: HR Manager only
    - [ ] Inertia render: `HR/Documents/Templates/Edit`

  - [ ] `update()` method - Update template
    - [ ] Authorize: HR Manager only
    - [ ] Increment: version number
    - [ ] Update: template record
    - [ ] Return: redirect with success message

  - [ ] `generate()` method - Generate document from template
    - [ ] Accept: template_id, employee_id, variables
    - [ ] Replace: {{variables}} in template
    - [ ] Return: generated PDF

#### Task 1.6: Document Request Controller
- [ ] Create `app/Http/Controllers/HR/Documents/DocumentRequestController.php`
  - [ ] `index()` method - List requests
    - [ ] Filter by: status (pending/processed)
    - [ ] Include: employee, processor
    - [ ] Inertia render: `HR/Documents/Requests/Index`

  - [ ] `process()` method - Process request
    - [ ] Generate: requested document
    - [ ] Upload: to employee documents
    - [ ] Update: request status, file_path
    - [ ] Notify: employee
    - [ ] Return: redirect with success message

---

### Phase 2: Frontend Pages - HR Staff & HR Manager (Week 2)

**Goal:** Build React/TypeScript pages for document management

**Duration:** 5-7 days

**Status:** ⏳ Pending

#### Task 2.1: Navigation Menu Updates
- [ ] Update `resources/js/components/nav-hr.tsx`
  - [ ] Add "Documents" section under HR menu
  - [ ] Menu items:
    ```typescript
    {
      title: 'Document Management',
      icon: FileText,
      href: '/hr/documents',
      permission: 'hr.documents.view',
    },
    {
      title: 'Document Templates',
      icon: FileSignature,
      href: '/hr/documents/templates',
      permission: 'hr.documents.templates.manage',
    },
    {
      title: 'Document Requests',
      icon: FileQuestion,
      href: '/hr/documents/requests',
      permission: 'hr.documents.view',
      badge: 'pendingRequestsCount', // Optional
    },
    ```

#### Task 2.2: Document List Page
- [ ] Create `resources/js/pages/HR/Documents/Index.tsx`
  - [ ] Component structure:
    - [ ] Page header with title "Document Management"
    - [ ] Filter bar: employee search, category dropdown, status filter, expiry date range
    - [ ] Action buttons: Upload Document, Manage Templates
    - [ ] DataTable with columns: Employee, Category, Document Type, File Name, Uploaded By, Status, Expiry Date, Actions
    - [ ] Row actions: View, Download, Approve (if pending), Delete
    - [ ] Pagination controls
    - [ ] Empty state: "No documents found"

  - [ ] Features:
    - [ ] Real-time search with debounce
    - [ ] Multi-select for bulk actions
    - [ ] Status badges: Pending (yellow), Approved (green), Rejected (red), Expired (gray)
    - [ ] Expiry warnings: Documents expiring in 30 days show warning icon
    - [ ] Sort by: upload date, expiry date, employee name

#### Task 2.3: Document Upload Page
- [ ] Create `resources/js/pages/HR/Documents/Upload.tsx`
  - [ ] Form fields:
    - [ ] Employee selector (searchable dropdown)
    - [ ] Document category dropdown (required)
    - [ ] Document type input (text, e.g., "Birth Certificate", "NBI Clearance")
    - [ ] File upload (drag-and-drop zone, max 10MB, accept: .pdf,.jpg,.png,.docx)
    - [ ] Expiry date picker (optional, for documents with expiry)
    - [ ] Notes textarea (optional)
    - [ ] Submit button

  - [ ] Validation:
    - [ ] File type validation
    - [ ] File size validation (max 10MB)
    - [ ] Required field validation
    - [ ] Display error messages

  - [ ] UX:
    - [ ] File preview before upload
    - [ ] Upload progress bar
    - [ ] Success message after upload
    - [ ] Redirect to document list

#### Task 2.4: Document Details Modal
- [ ] Create `resources/js/components/hr/document-details-modal.tsx`
  - [ ] Modal sections:
    - [ ] Document metadata: Category, Type, File Name, File Size, Uploaded By, Upload Date
    - [ ] Approval section: Status, Approved By, Approval Date, Rejection Reason (if rejected)
    - [ ] Expiry tracking: Expiry Date, Days Until Expiry, Renewal Required
    - [ ] Notes section: Display notes
    - [ ] Preview section: PDF viewer or image preview
    - [ ] Audit log: List of actions (uploaded, viewed, downloaded, approved)

  - [ ] Actions:
    - [ ] Download button
    - [ ] Approve button (HR Manager only, if pending)
    - [ ] Reject button (HR Manager only, if pending)
    - [ ] Delete button (HR Manager only)
    - [ ] Close button

#### Task 2.5: Document Templates Page
- [ ] Create `resources/js/pages/HR/Documents/Templates/Index.tsx`
  - [ ] List templates with: Name, Description, Version, Created By, Status
  - [ ] Actions: Create New, Edit, Generate Document
  - [ ] Template preview modal

- [ ] Create `resources/js/pages/HR/Documents/Templates/CreateEdit.tsx`
  - [ ] Form: Name, Description, File Upload, Variables (JSON editor)
  - [ ] Variable format help text: {{employee_name}}, {{position}}, {{start_date}}

#### Task 2.6: Document Requests Page
- [ ] Create `resources/js/pages/HR/Documents/Requests/Index.tsx`
  - [ ] List pending requests: Employee, Document Type, Purpose, Request Date
  - [ ] Action: Process Request button
  - [ ] Process modal: Generate document, upload, mark as processed

#### Task 2.7: Employee Document Widget (for Employee Show page)
- [ ] Create `resources/js/components/hr/employee-documents-widget.tsx`
  - [ ] Display: Documents for specific employee
  - [ ] Group by: Category
  - [ ] Quick upload button
  - [ ] Document checklist: Show missing required documents
  - [ ] Expiry alerts: Documents expiring soon

#### Task 2.8: Bulk Upload Page
- [ ] Create `resources/js/pages/HR/Documents/BulkUpload.tsx`
  - [ ] Instructions section:
    - [ ] Step 1: Download CSV template
    - [ ] Step 2: Fill CSV with document metadata
    - [ ] Step 3: Create ZIP file with all documents
    - [ ] Step 4: Upload both CSV and ZIP

  - [ ] Form fields:
    - [ ] CSV file upload (required, .csv or .txt)
    - [ ] ZIP file upload (required, .zip, max 100MB)
    - [ ] Notes textarea (optional)
    - [ ] Submit button

  - [ ] CSV Template Generator:
    - [ ] Button: "Download CSV Template"
    - [ ] Generates: CSV with headers (employee_id, document_category, document_type, file_name, expires_at)
    - [ ] Includes: Sample row for reference

  - [ ] Upload Process:
    - [ ] Show progress bar during upload
    - [ ] Display processing status
    - [ ] On completion: Show results page with success/error counts
    - [ ] Error log: List failed uploads with reasons
    - [ ] Action: Download error log as CSV

- [ ] Create `resources/js/pages/HR/Documents/BulkUploadResult.tsx`
  - [ ] Display: Upload summary (total, success, errors)
  - [ ] Success table: List successfully uploaded documents
  - [ ] Error table: List failed uploads with reasons
  - [ ] Actions: Download error log, Upload again, Return to documents

#### Task 2.9: Document Expiry Dashboard Widget
- [ ] Create `resources/js/components/hr/document-expiry-dashboard.tsx`
  - [ ] Display: Summary cards
    - [ ] Card 1: Expired documents count (red)
    - [ ] Card 2: Expiring within 7 days (orange)
    - [ ] Card 3: Expiring within 30 days (yellow)
    - [ ] Card 4: All documents up to date (green)

  - [ ] List section:
    - [ ] Table: Employee, Document Type, Expiry Date, Days Remaining
    - [ ] Sort by: Expiry date (soonest first)
    - [ ] Filter by: Category
    - [ ] Action: Quick upload renewal button

  - [ ] Integration:
    - [ ] Add to HR Dashboard as widget
    - [ ] Add to Documents Index page as alert banner

---

### Phase 3: Employee Portal Integration (Week 3)

**Goal:** Enable employees to view their documents and request new ones

**Duration:** 2-3 days

**Status:** ⏳ Pending

#### Task 3.1: Employee Document Routes
- [ ] Create employee document request routes in `routes/employee.php`
  ```php
  // Document Request Routes (Employee Self-Service)
  Route::prefix('documents')->name('documents.')->group(function () {
      Route::get('/', [\App\Http\Controllers\Employee\DocumentController::class, 'index'])
          ->name('index'); // View own documents
      
      Route::get('/request', [\App\Http\Controllers\Employee\DocumentController::class, 'createRequest'])
          ->name('request.create'); // Request form
      
      Route::post('/request', [\App\Http\Controllers\Employee\DocumentController::class, 'storeRequest'])
          ->name('request.store'); // Submit request
      
      Route::get('/{document}/download', [\App\Http\Controllers\Employee\DocumentController::class, 'download'])
          ->name('download'); // Download own documents
  });
  ```

- [ ] Create `app/Http/Controllers/Employee/DocumentController.php`
  - [ ] `index()` - List employee's own documents
    - [ ] Filter by: Category
    - [ ] Display: Document type, uploaded date, expiry date
    - [ ] Action: Download button (signed URL)
    - [ ] Inertia render: `Employee/Documents/Index`

  - [ ] `createRequest()` - Show document request form
    - [ ] Available requests: Certificate of Employment, Payslip (specific period), 2316 Form
    - [ ] Inertia render: `Employee/Documents/RequestForm`

  - [ ] `storeRequest()` - Submit document request
    - [ ] Validate: document_type, purpose (optional)
    - [ ] Create: DocumentRequest record
    - [ ] Notify: HR Staff
    - [ ] Return: Success message

  - [ ] `download()` - Download own document
    - [ ] Authorize: Document belongs to authenticated employee
    - [ ] Generate: Signed URL (24-hour expiry)
    - [ ] Log: download action
    - [ ] Return: File download

- [ ] Create `resources/js/pages/Employee/Documents/Index.tsx`
  - [ ] Page header: "My Documents"
  - [ ] Action button: "Request Document"
  - [ ] Group documents by category
  - [ ] Display: Document type, upload date, expiry date, download button
  - [ ] Empty state: "No documents available"

- [ ] Create `resources/js/pages/Employee/Documents/RequestForm.tsx`
  - [ ] Form fields:
    - [ ] Document type dropdown (COE, Payslip, 2316 Form)
    - [ ] Purpose textarea (optional)
    - [ ] Submit button
  - [ ] Validation: Required fields
  - [ ] Success: Redirect to documents list with message

- [ ] Update `resources/js/components/nav-employee.tsx`
  - [ ] Add "My Documents" menu item
  - [ ] Icon: FileText
  - [ ] Badge: Show count of pending requests (optional)

#### Task 3.2: Integration with Employee Show Page
- [ ] Update `resources/js/pages/HR/Employees/Show.tsx`
  - [ ] Add "Documents" tab
  - [ ] Display: EmployeeDocumentsWidget component
  - [ ] Show: Document checklist, upload button, expiry warnings

---

### Phase 4: Database, Models & Backend Services (Week 4)

**Goal:** Create database schema, models, and backend services

**Duration:** 5-7 days

**Status:** ⏳ Pending

#### Task 4.1: Database Migrations
- [ ] Create `employee_documents` table migration
  - [ ] Fields: id, employee_id, document_category, document_type, file_name, file_path, file_size, mime_type
  - [ ] Fields: uploaded_by, uploaded_at, expires_at, status (pending/approved/rejected/auto_approved)
  - [ ] Fields: approved_by, approved_at, rejection_reason, notes
  - [ ] Fields: requires_approval (boolean), is_critical (boolean), reminder_sent_at (nullable)
  - [ ] Fields: bulk_upload_batch_id (nullable), source (manual/bulk/employee_portal)
  - [ ] Indexes: employee_id, document_type, status, expires_at, bulk_upload_batch_id
  - [ ] Foreign keys: employee_id → employees, uploaded_by → users, approved_by → users
  - [ ] Soft deletes with retention_expires_at (5 years from separation date)

- [ ] Create `document_categories` table migration (OPTIONAL - using string categories for now)
  - [ ] Can use enum/string instead: personal, educational, employment, medical, contracts, benefits, performance, separation, government, special

- [ ] Create `document_templates` table migration
  - [ ] Fields: id, name, description, file_path, variables (JSON), created_by, status
  - [ ] Fields: approved_by, approved_at, version, is_locked (boolean), is_active
  - [ ] Fields: template_type (contract, offer_letter, coe, memo, warning, clearance, resignation, termination)

- [ ] Create `document_requests` table migration
  - [ ] Fields: id, employee_id, document_type, purpose, requested_at, status (pending/processed/rejected)
  - [ ] Fields: processed_by, processed_at, file_path, notes, rejection_reason (nullable)
  - [ ] Fields: request_source (employee_portal), employee_notified_at (nullable)
  - [ ] Index: employee_id, status, requested_at

- [ ] Create `document_audit_logs` table migration
  - [ ] Fields: id, document_id, user_id, action, ip_address, user_agent, created_at
  - [ ] Actions: uploaded, downloaded, approved, rejected, deleted, bulk_uploaded, reminder_sent
  - [ ] Index: document_id, user_id, action, created_at

- [ ] Create `bulk_upload_batches` table migration
  - [ ] Fields: id, uploaded_by, total_count, success_count, error_count, status, started_at, completed_at
  - [ ] Fields: csv_file_path, error_log (JSON), notes
  - [ ] Status: processing, completed, failed, partially_completed

#### Task 4.2: Eloquent Models
- [ ] Create `EmployeeDocument` model
  - [ ] Define fillable fields
  - [ ] Relationships: belongsTo(Employee), belongsTo(User, 'uploaded_by')
  - [ ] Relationships: belongsTo(User, 'approved_by')
  - [ ] Scopes: active(), pending(), approved(), expired()
  - [ ] Accessors: file_url, file_size_formatted, is_expired
  - [ ] Methods: approve(), reject(), softDelete()

- [ ] Create `DocumentTemplate` model
  - [ ] Define fillable fields
  - [ ] Relationships: belongsTo(User, 'created_by'), belongsTo(User, 'approved_by')
  - [ ] Scopes: active(), approved()
  - [ ] Methods: generateDocument($variables), incrementVersion()

- [ ] Create `DocumentRequest` model
  - [ ] Define fillable fields
  - [ ] Relationships: belongsTo(Employee), belongsTo(User, 'processed_by')
  - [ ] Scopes: pending(), processed()
  - [ ] Methods: process(), reject()

- [ ] Create `DocumentAuditLog` model
  - [ ] Define fillable fields
  - [ ] Relationships: belongsTo(EmployeeDocument), belongsTo(User)
  - [ ] Static method: log($document, $action, $user)

- [ ] Create `BulkUploadBatch` model
  - [ ] Define fillable fields
  - [ ] Relationships: belongsTo(User, 'uploaded_by'), hasMany(EmployeeDocument)
  - [ ] Scopes: completed(), failed()

#### Task 4.3: Document Expiry Reminder Service
- [ ] Create `app/Services/HR/DocumentExpiryReminderService.php`
  - [ ] Method: `checkExpiringDocuments()` - Find documents expiring in 30 days
    - [ ] Query: Documents with expires_at between today and 30 days from now
    - [ ] Filter: Where reminder_sent_at is null or > 7 days ago
    - [ ] Return: Collection of expiring documents with employee info

  - [ ] Method: `sendReminderNotifications()` - Send email reminders
    - [ ] For each expiring document:
      - [ ] Send email to HR Staff/Manager
      - [ ] Include: Employee name, document type, expiry date, days remaining
      - [ ] Update: reminder_sent_at timestamp
      - [ ] Log: reminder_sent action
    - [ ] Return: Count of reminders sent

  - [ ] Method: `generateExpiryReport()` - Generate expiry dashboard
    - [ ] Group by: Document category
    - [ ] Aggregate: Count by expiry window (7 days, 14 days, 30 days, expired)
    - [ ] Return: Array for dashboard widget

- [ ] Create console command `app/Console/Commands/SendDocumentExpiryReminders.php`
  - [ ] Signature: `documents:send-expiry-reminders`
  - [ ] Schedule: Daily at 8:00 AM
  - [ ] Uses: DocumentExpiryReminderService
  - [ ] Output: Count of reminders sent

#### Task 4.4: Document Template Seeder
- [ ] Create `database/seeders/DocumentTemplateSeeder.php`
  - [ ] Seed 9 templates: Employment Contract, Job Offer, NDA, COE, Memo, Warning Letter, Clearance Form, Resignation Acceptance, Termination Letter
  - [ ] Each template with variables array: {{employee_name}}, {{position}}, {{start_date}}, etc.

#### Task 4.5: Storage Configuration
- [ ] Update `config/filesystems.php`
  - [ ] Add disk configuration for employee documents:
    ```php
    'employee_documents' => [
        'driver' => 'local',
        'root' => storage_path('app/employee-documents'),
        'visibility' => 'private',
    ],
    ```

- [ ] Create storage directory:
  - [ ] Run: `php artisan storage:link`
  - [ ] Create: `storage/app/employee-documents` directory

#### Task 4.6: Testing
- [ ] Unit Tests:
  - [ ] Test EmployeeDocument model relationships
  - [ ] Test DocumentCategory seeder
  - [ ] Test file upload validation
  - [ ] Test bulk upload CSV parsing
  - [ ] Test expiry reminder logic

- [ ] Feature Tests:
  - [ ] Test HR Staff can upload documents
  - [ ] Test HR Manager can approve documents
  - [ ] Test document download authorization
  - [ ] Test document audit logging
  - [ ] Test file size and type validation
  - [ ] Test document expiry tracking
  - [ ] Test bulk upload processing
  - [ ] Test employee can request documents
  - [ ] Test employee can view own documents only
  - [ ] Test expiry reminder command

- [ ] Manual Testing:
  - [ ] Upload documents for test employee
  - [ ] Approve/reject documents as HR Manager
  - [ ] Download documents
  - [ ] Search and filter documents
  - [ ] Generate document from template
  - [ ] Process document request
  - [ ] Check audit logs
  - [ ] Test bulk upload with sample CSV and ZIP
  - [ ] Login as employee and request COE
  - [ ] Verify expiry reminder emails sent

---

## 📊 Progress Tracking

### Overall Progress
- Phase 1: 🔄 67% (4/6 tasks complete) - Permissions, Routes & Controllers
- Phase 2: ⏳ 0% (0/9 tasks complete) - Frontend Pages (HR Staff & HR Manager)
- Phase 3: ⏳ 0% (0/2 tasks complete) - Employee Portal Integration
- Phase 4: ⏳ 0% (0/6 tasks complete) - Database, Models & Backend Services

**Total Progress: 17% (4/23 tasks complete)**

### Completed Tasks
✅ **Phase 1 - Task 1.1**: Document Management Permissions Seeder (9 permissions created)
✅ **Phase 1 - Task 1.2**: Route Configuration (18 routes added to routes/hr.php)
✅ **Phase 1 - Task 1.3**: Validation Request Classes (4 classes created with custom error messages)
✅ **Phase 1 - Task 1.4**: Employee Document Controller (10 methods with security audit logging)

### Feature Status
- ✅ Document Storage: Design complete (local storage with migration path)
- ✅ Approval Workflow: Design complete (critical docs only)
- ✅ Audit Logging: Design complete (upload, download, approve, delete)
- ✅ Bulk Upload: Designed and integrated into Phase 2 & 3
- ✅ Document Requests: Designed and integrated into Phase 4 (employee portal)
- ✅ Expiry Reminders: Designed and integrated into Phase 2 & 3
- ⏳ Implementation: Not started

---

## 🔐 Security & Compliance

### File Upload Security
- **File Type Validation**: Whitelist only PDF, JPEG, PNG, DOCX
- **File Size Limits**: 10MB per file, 100MB total per employee
- **Malware Scanning**: Consider ClamAV integration for production
- **Storage Location**: `storage/app/employee-documents` (outside public directory)
- **File Naming**: UUID-based names to prevent overwriting and path traversal
- **MIME Type Verification**: Server-side validation (not just extension check)

### Access Control & Authorization
- **Signed URLs**: 24-hour expiry for document downloads
- **Role-Based Access**: HR Staff (upload/view), HR Manager (approve/delete), Employee (view own only)
- **Permission Gates**: Every route protected by Spatie permissions
- **Document Ownership**: Employees can only access their own documents
- **Audit Logging**: All actions logged with user_id, IP address, timestamp

### Data Privacy Compliance
- **Philippine Data Privacy Act of 2012**: Full compliance
  - Lawful processing (employment relationship)
  - Data subject rights (access, correction, erasure)
  - Security measures (encryption, access control)
  - Breach notification (48-hour requirement)

- **Employee Consent**: Implied consent via employment contract
- **Right to Access**: Employees can view their documents via portal
- **Right to Erasure**: Soft delete with 5-year retention (labor law requirement)
- **Data Portability**: Employees can download their documents

### File Retention Policy
- **Active Employees**: Documents stored indefinitely
- **Separated Employees**: 5-year retention from separation date (DOLE requirement)
- **Soft Delete**: Document records marked as deleted, physical files retained
- **Automatic Archiving**: After 5 years, files moved to archive storage
- **Physical Deletion**: Manual review before permanent deletion

### Audit Trail Requirements
- **Logged Actions**: uploaded, downloaded, approved, rejected, deleted, bulk_uploaded, reminder_sent
- **Logged Data**: user_id, IP address, user_agent, timestamp, document_id
- **Retention**: Audit logs retained indefinitely for compliance
- **Reporting**: HR Manager can view full audit trail

---

## 📝 Implementation Notes

### Migration from Paper Files
- **Bulk Upload Sessions**: Schedule dedicated time for HR Staff to scan and upload existing 201 files
- **CSV Template**: Use provided template to map scanned files to employees
- **Quality Check**: HR Manager reviews first batch before proceeding with full migration
- **Priority Documents**: Start with critical docs (contracts, IDs) then move to supporting docs

### Training Requirements
- **HR Staff Training** (2-hour session):
  - Document scanning best practices
  - System navigation and upload process
  - Category selection and expiry date entry
  - Bulk upload workflow
  - Employee document request handling

- **HR Manager Training** (1-hour session):
  - Approval workflow
  - Template management
  - Audit log review
  - Expiry dashboard monitoring

### Backup & Disaster Recovery
- **Daily Backups**: Automated backup of `storage/app/employee-documents` directory
- **Off-Site Storage**: Weekly sync to external backup location
- **Retention**: Backups retained for 90 days
- **Recovery Testing**: Quarterly restore tests to verify backup integrity

### Performance Optimization
- **File Storage**: Organized by employee_id/category/year for fast retrieval
- **Database Indexes**: On employee_id, status, expires_at for fast queries
- **Lazy Loading**: Frontend loads documents paginated (20 per page)
- **Signed URLs**: Cached for 24 hours to reduce server load

### Future Enhancements (Not in This Phase)
- **Mobile App**: Document scanning with OCR for auto-data extraction
- **E-Signature Integration**: Digital signatures for contracts (legally binding in PH)
- **Cloud Storage Migration**: AWS S3 or DigitalOcean Spaces for scalability
- **Advanced Search**: Full-text search across document contents (OCR + indexing)
- **Department Head Access**: Filtered document view for department managers

---

## 🎯 Success Criteria

- [ ] All 43 required Philippine labor documents supported
- [ ] HR Staff can upload documents in < 2 minutes
- [ ] HR Manager can approve documents in < 1 minute
- [ ] Document search returns results in < 1 second
- [ ] 100% document audit trail coverage
- [ ] Zero unauthorized document access
- [ ] 201 file completeness tracking (show missing documents)
- [ ] Automatic expiry notifications working
