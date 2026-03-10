
# Applicant Tracking System (ATS) Module - Architecture & Implementation Plan

## Module Overview
The ATS module manages the full recruitment lifecycle: job posting, candidate application, interview scheduling, evaluation, and handoff to HR Core and Onboarding. It ensures a structured, auditable, and efficient hiring process for Cathay Metal Corporation.


## Module Goals
1. **Job Posting Management:** Create and manage job openings with requirements and descriptions. 
	- **Current Approach:** The HRIS is now accessible externally via Cloudflare Tunnel. Job postings can be accessed from the root index page (`/`) where candidates can browse open positions and apply directly. HR staff manage job postings internally at `/hr/ats/job-postings`.
	- **Public Access:** Candidates can navigate to `/job-postings` from the main landing page to view all open positions and submit applications online.
	- **Social Media Integration (Optional):** Can integrate with Facebook using Meta Graph API to cross-post job openings to the company's Facebook Page with links back to the public job postings page.
2. **Candidate Application Tracking:** Accept, store, and manage candidate applications and resumes.
	- **Automated Option:** Use a Facebook Messenger bot or a custom web form (linked from the Facebook post) to collect candidate details. The bot or form can send application data directly to the ATS via a webhook or API, creating candidate/application records automatically.
3. **Interview Scheduling:** Schedule interviews, assign interviewers, and collect feedback.
4. **Candidate Evaluation:** Score and rank candidates based on interviews and assessments.
5. **Handoff to HR Core/Onboarding:** Seamlessly transition successful candidates to employee onboarding.

---

## Database Schema (ATS Module)

### candidates
```sql
- id (primary key)
- profile_id (foreign key to profiles, nullable)
- source (enum: referral, job_board, walk_in, agency, internal, other)
- status (enum: new, in_process, interviewed, offered, hired, rejected, withdrawn)
- applied_at (timestamp)
- notes (text, nullable)
- created_at, updated_at
```

### job_postings
```sql
- id (primary key)
- title (string, required)
- department_id (foreign key to departments)
- description (text)
- requirements (text)
- status (enum: open, closed, draft)
- posted_at (timestamp)
- closed_at (timestamp, nullable)
- created_by (foreign key to users)
- created_at, updated_at
```

### applications
```sql
- id (primary key)
- candidate_id (foreign key to candidates)
- job_id (foreign key to job_postings)
- status (enum: submitted, shortlisted, interviewed, offered, hired, rejected, withdrawn)
- score (decimal(5,2), nullable)
- resume_path (string, nullable)
- cover_letter (text, nullable)
- applied_at (timestamp)
- created_at, updated_at
```

### interviews
```sql
- id (primary key)
- application_id (foreign key to applications)
- scheduled_at (timestamp)
- interviewer_id (foreign key to users)
- feedback (text, nullable)
- score (decimal(5,2), nullable)
- status (enum: scheduled, completed, cancelled, no_show)
- created_at, updated_at
```

### candidate_notes
```sql
- id (primary key)
- candidate_id (foreign key to candidates)
- note (text)
- created_by (foreign key to users)
- created_at, updated_at
```

---

## Key Workflows



### 1. Job Posting & Application (with Optional Facebook Integration)
- HR Manager/Staff creates job postings at `/hr/ats/job-postings`.
- Job postings marked as "open" automatically appear on the public job postings page (`/job-postings`).
- Candidates can browse available positions from the main landing page and apply directly online.
- Applications are automatically created in the ATS, reducing manual data entry.
- **Optional Facebook Integration:** With Facebook integration enabled, job details can be cross-posted to the company's Facebook Page via Meta Graph API with links back to the public job postings page.
- Applications from referrals or walk-ins can still be entered manually by HR.
---

## Facebook Integration & Automation Strategy

### Automated Job Posting
- Use the Meta Graph API to allow the HRIS to publish job posts directly to the company's Facebook Page.
- Job posts can include job title, description, requirements, and a call-to-action (e.g., "Apply via Messenger").
- HR staff can trigger posting from the HR dashboard; posts are tracked in the ATS.

### Automated Candidate Intake
- Deploy a Facebook Messenger bot (using Meta's Messenger Platform) or a custom web form linked from the Facebook post.
- The bot/form collects candidate details (name, contact, resume, etc.).
- When a candidate submits, the bot/form sends the data to the ATS via a secure webhook or REST API endpoint.
- The ATS creates candidate and application records automatically, reducing manual data entry.

### Technical Considerations
- Requires a Facebook Page and developer access to Meta Graph API and Messenger Platform.
- Messenger bots can be built using Node.js, Python, or third-party platforms (e.g., ManyChat, Chatfuel) with webhook support.
- ATS must expose a secure API endpoint to receive candidate data.
- All automated actions should be logged for audit and error handling.

### Benefits
- Reduces manual HR work and speeds up candidate intake.
- Ensures all applications are tracked in the ATS, regardless of source.
- Provides a foundation for future integration with other platforms (e.g., /job-posting page from / page, job boards).

---
---

## Integration Strategy: Public Access & Social Media

### Current State: Public Job Postings via Cloudflare Tunnel
- The HRIS is now accessible externally through Cloudflare Tunnel.
- Job postings are created at `/hr/ats/job-postings` and automatically published to the public job postings page.
- The main landing page (`/`) includes a "Careers" or "Job Openings" button that navigates to `/job-postings`.
- Candidates can browse all open positions and submit applications directly online.
- Applications are automatically created in the ATS, eliminating manual data entry.
- All candidate tracking, interview scheduling, and evaluation remain secured in the internal HR section.

### Optional: Social Media Cross-Posting
- Can integrate with Facebook using Meta Graph API to automatically cross-post job openings.
- Facebook posts include links back to the public job postings page (`https://your-domain.com/job-postings`).
- All applications flow through the same online form, maintaining a single source of truth in the ATS.

### Future Enhancements
- Develop a full marketing/sales website with company information.
- Expand to other job boards and platforms (Indeed, LinkedIn, etc.).
- Implement advanced filtering and search on the public job postings page.

---

### 2. Screening & Shortlisting
- HR reviews applications, shortlists candidates based on requirements.
- Shortlisted candidates are scheduled for interviews.

### 3. Interview Scheduling & Feedback
- HR schedules interviews, assigns interviewers.
- Interviewers provide feedback and scores after each interview.
- Multiple interview rounds supported.

### 4. Candidate Evaluation & Offer
- HR reviews interview feedback and scores.
- Candidates are ranked and offers are extended to top choices.
- Offer status is tracked; rejected/withdrawn candidates are archived.

### 5. Handoff to HR Core & Onboarding
- On hire, create `employees` record and trigger onboarding tasks.
- Candidate profile is linked to `profiles` and `government_ids` as needed.

---

## Integration Points
- **HR Core:**
	- On successful hire, create employee record and link candidate profile.
- **Onboarding:**
	- Trigger onboarding checklist and account provisioning.
- **Reporting:**
	- Provide analytics on hiring pipeline, time-to-hire, and source effectiveness.

---

## Roles & Permissions
- **HR Manager:** Full access to create, edit, and close job postings, manage candidates, and approve hires.
- **HR Staff:** Can manage applications, schedule interviews, and record feedback.

---

## Implementation Phases

### Phase 1: Foundation Models & Migrations
- [ ] Create Eloquent models for candidates, job_postings, applications, interviews
- [ ] Create migrations for all tables
- [ ] Seeders for sample job postings and candidates

### Phase 2: Repository & Service Layer
- [ ] Define repository interfaces for candidate, job, and interview management
- [ ] Implement Eloquent repositories
- [ ] Create services for application workflow, interview scheduling, and evaluation

### Phase 3: UI & Workflow Integration
- [ ] Build React/Inertia.js pages for job posting, application review, and interview management
- [ ] Integrate with HR Core and Onboarding modules
- [ ] Add reporting and analytics dashboards

---

## Future Refactor: Domain Layer
When the system is refactored to MVCSR + Domain, move the following to the Domain layer:
- Candidate eligibility and scoring rules
- Interview scheduling constraints
- Offer/acceptance invariants

---

**Dependencies:** HR Core (profiles), Onboarding Module, Departments, Users
