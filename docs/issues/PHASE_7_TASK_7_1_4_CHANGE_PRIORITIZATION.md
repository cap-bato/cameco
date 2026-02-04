# Phase 7, Task 7.1.4 - Change Prioritization System

**Date Created:** February 4, 2026  
**Purpose:** Systematically prioritize changes based on user testing feedback  
**Status:** READY FOR USE

---

## Overview

This document provides a comprehensive framework for prioritizing improvements, bug fixes, and feature requests identified during HR Staff user testing. Use this system to make data-driven decisions about what to fix first.

**How to Use This System:**
1. Review all documented pain points from Task 7.1.3
2. Score each issue using the prioritization matrix
3. Rank issues by priority score
4. Create sprint/release plan based on rankings
5. Track progress using the action plan template

---

## Prioritization Framework

### Multi-Dimensional Scoring Matrix

**Each issue is scored across 5 dimensions:**

| Dimension | Weight | Range |
|-----------|--------|-------|
| **Severity** (How bad is it?) | 40% | 1-5 |
| **Frequency** (How often?) | 25% | 1-5 |
| **Impact** (How many users?) | 20% | 1-5 |
| **Effort** (How hard to fix?) | 10% | 1-5 (inverted) |
| **Business Value** (Strategic importance?) | 5% | 1-5 |

**Priority Score Formula:**
```
Priority Score = (Severity × 0.40) + (Frequency × 0.25) + (Impact × 0.20) + ((6 - Effort) × 0.10) + (Business Value × 0.05)
```

**Score Range:** 1.0 (lowest priority) to 5.0 (highest priority)

---

## Dimension Definitions

### 1. Severity (40% weight)

**How critical is this issue to system usability?**

| Score | Level | Definition | Examples |
|-------|-------|------------|----------|
| **5** | Critical | System unusable, data loss, security breach | App crashes, cannot save data, unauthorized access |
| **4** | High | Major feature broken, significant workaround needed | Core workflow broken, cannot complete task |
| **3** | Medium | Feature works but with difficulty, minor workaround | Confusing UI, requires trial-and-error |
| **2** | Low | Inconvenient but manageable | Slow performance, unclear label |
| **1** | Trivial | Cosmetic, no functional impact | Typo, spacing issue, color preference |

---

### 2. Frequency (25% weight)

**How often do users encounter this issue?**

| Score | Frequency | Definition | Examples |
|-------|-----------|------------|----------|
| **5** | Always | Every time feature is used (100% occurrence) | Broken button, missing required field |
| **4** | Often | Most times feature is used (75%+ occurrence) | Intermittent error, common edge case |
| **3** | Sometimes | Regularly but not majority (50% occurrence) | Specific workflow scenario |
| **2** | Rarely | Occasional occurrence (25% occurrence) | Uncommon edge case |
| **1** | Once | Single occurrence or very rare (<10% occurrence) | Unique circumstance |

---

### 3. Impact (20% weight)

**How many users are affected?**

| Score | Scope | Definition | Examples |
|-------|-------|------------|----------|
| **5** | All Users | 100% of users affected | Core navigation issue, main dashboard problem |
| **4** | Most Users | 75%+ of users affected | Common workflow all roles use |
| **3** | Many Users | 50% of users affected | Role-specific but multiple roles |
| **2** | Few Users | 25% of users affected | Single role, specific scenario |
| **1** | One User | <10% of users affected | Edge case, advanced feature |

---

### 4. Effort (10% weight) - INVERTED

**How much development effort is required to fix?**

| Score | Effort Level | Development Time | Examples |
|-------|--------------|------------------|----------|
| **5** | Minimal | < 2 hours | Text change, CSS tweak, config update |
| **4** | Low | 2-8 hours (1 day) | Simple UI fix, form validation, button logic |
| **3** | Medium | 1-3 days | Component redesign, new modal, API endpoint |
| **2** | High | 3-7 days (1 week) | Major feature addition, complex logic |
| **1** | Very High | > 1 week | Architecture change, module rebuild |

**Note:** This dimension is INVERTED in the formula `(6 - Effort)` so lower effort = higher priority.

---

### 5. Business Value (5% weight)

**Strategic importance to business goals?**

| Score | Value | Definition | Examples |
|-------|-------|------------|----------|
| **5** | Critical | Must-have for launch, regulatory requirement | DOLE compliance, payroll accuracy |
| **4** | High | Strong business case, competitive advantage | Efficiency gain, cost reduction |
| **3** | Medium | Nice to have, improves operations | Better reporting, UX polish |
| **2** | Low | Minor benefit, convenience | Feature request, preference |
| **1** | Minimal | No clear business impact | "Nice-to-have", aesthetic |

---

## Priority Tiers

**Based on calculated priority scores:**

### Tier P0: Critical Priority (Score: 4.5 - 5.0)

**Characteristics:**
- **Severity:** Critical or High
- **Frequency:** Always or Often
- **Impact:** All or Most Users
- **Timeline:** Fix immediately (1-3 days)

**Action:** STOP development, fix immediately, hotfix if necessary

**Examples:**
- Data loss bugs
- Security vulnerabilities
- System crashes
- Core workflow completely broken

---

### Tier P1: High Priority (Score: 4.0 - 4.4)

**Characteristics:**
- **Severity:** High or Medium
- **Frequency:** Often or Sometimes
- **Impact:** Most or Many Users
- **Timeline:** Fix before next release (3-7 days)

**Action:** Include in current sprint, block release if not fixed

**Examples:**
- Major usability issues affecting daily work
- Important features not working correctly
- Significant performance problems
- Confusing workflows causing errors

---

### Tier P2: Medium Priority (Score: 3.0 - 3.9)

**Characteristics:**
- **Severity:** Medium
- **Frequency:** Sometimes or Rarely
- **Impact:** Many or Few Users
- **Timeline:** Fix in next sprint (1-2 weeks)

**Action:** Plan into sprint backlog, prioritize after P0/P1

**Examples:**
- Moderate usability improvements
- Secondary features with issues
- Performance optimization
- UI polish for better UX

---

### Tier P3: Low Priority (Score: 2.0 - 2.9)

**Characteristics:**
- **Severity:** Low or Trivial
- **Frequency:** Rarely or Once
- **Impact:** Few or One User
- **Timeline:** Backlog for future release (3-4 weeks+)

**Action:** Add to backlog, address when capacity allows

**Examples:**
- Minor UI tweaks
- Nice-to-have features
- Edge case fixes
- Cosmetic improvements

---

### Tier P4: Deferred (Score: 1.0 - 1.9)

**Characteristics:**
- **Severity:** Trivial
- **Frequency:** Once or Never
- **Impact:** One User or None
- **Timeline:** Indefinite (next major version or never)

**Action:** Document only, revisit if more users report

**Examples:**
- Personal preferences
- Aesthetic opinions
- Rarely used features
- "Wouldn't it be cool if..." ideas

---

## Prioritization Worksheet

### Issue Scoring Template

**Issue ID:** [e.g., NAV-001]  
**Issue Title:** [Brief description]

| Dimension | Raw Score | Weight | Weighted Score | Rationale |
|-----------|-----------|--------|----------------|-----------|
| **Severity** | ___/5 | 40% | ___ | [Why this score?] |
| **Frequency** | ___/5 | 25% | ___ | [Why this score?] |
| **Impact** | ___/5 | 20% | ___ | [Why this score?] |
| **Effort** | ___/5 | 10% | ___ | [Why this score?] (inverted) |
| **Business Value** | ___/5 | 5% | ___ | [Why this score?] |
| **TOTAL PRIORITY SCORE** | | **100%** | **___/5** | |

**Priority Tier:** P___ ([Tier name])  
**Recommended Timeline:** [Date or timeframe]  
**Assigned To:** [Person/Team]

---

### Batch Scoring Table

**Use this table to score multiple issues quickly:**

| Issue ID | Severity | Freq | Impact | Effort | Bus Val | **Priority Score** | **Tier** |
|----------|----------|------|--------|--------|---------|-------------------|----------|
| NAV-001 | 5 | 4 | 5 | 4 | 3 | **4.45** | P1 |
| VIS-001 | 3 | 3 | 4 | 5 | 2 | **3.35** | P2 |
| PERF-001 | 4 | 5 | 5 | 2 | 4 | **4.20** | P1 |
| FORM-001 | 2 | 2 | 3 | 4 | 1 | **2.35** | P3 |
| ERR-001 | 5 | 5 | 5 | 3 | 5 | **4.75** | P0 |
| ... | ... | ... | ... | ... | ... | **...** | ... |

**How to calculate (example for ERR-001):**
```
Priority Score = (5 × 0.40) + (5 × 0.25) + (5 × 0.20) + ((6-3) × 0.10) + (5 × 0.05)
               = 2.00 + 1.25 + 1.00 + 0.30 + 0.25
               = 4.80 → Tier P0 (Critical Priority)
```

---

## Decision Framework

### When to Override Calculated Score

**Sometimes human judgment should override the formula:**

**Override to HIGHER Priority if:**
- ✅ CEO/stakeholder mandate
- ✅ Regulatory/legal requirement
- ✅ Security vulnerability discovered
- ✅ Blocking other critical work
- ✅ PR/reputation risk

**Override to LOWER Priority if:**
- ⬇️ Technical debt requires foundational work first
- ⬇️ Resource constraints (no one available with skills)
- ⬇️ Waiting for external dependency
- ⬇️ User confusion stems from lack of training
- ⬇️ More data needed to validate issue

**Document all overrides with clear rationale.**

---

### Special Considerations

#### Grouping Related Issues

**When multiple issues share a root cause:**
- Group them under a single "epic" or theme
- Score the epic based on combined impact
- Fix all related issues together
- Example: 5 separate form validation issues → 1 "Form Validation Epic"

#### Quick Wins Matrix

**Identify low-effort, high-impact issues for quick morale boost:**

| | High Impact | Low Impact |
|---|-------------|------------|
| **Low Effort** | 🎯 **QUICK WINS** - Do these first! | ⚡ Nice polish, if time permits |
| **High Effort** | 🎯 Strategic investment | ❌ Avoid unless critical |

---

## Prioritization Workshop Agenda

**Use this agenda when team reviews feedback:**

### 1. Preparation (30 minutes before meeting)

- [ ] Compile all issues from Pain Points Documentation
- [ ] Pre-score obvious issues (P0 critical bugs)
- [ ] Prepare scoring sheets for each issue
- [ ] Invite stakeholders: Dev Lead, Product Owner, QA Lead, HR Manager

### 2. Meeting Agenda (2 hours)

**Part 1: Review Issues (45 minutes)**
- Present all documented issues
- Clarify any ambiguous issues
- Group related issues into epics

**Part 2: Scoring Session (60 minutes)**
- Score each issue using the matrix
- Discuss disagreements
- Document rationale for scores
- Override scores where justified

**Part 3: Sprint Planning (15 minutes)**
- Assign P0 issues to immediate hotfix
- Plan P1 issues into next sprint
- Review P2 issues for sprint after
- Move P3/P4 to backlog

### 3. Post-Meeting Actions

- [ ] Document all decisions
- [ ] Create JIRA/GitHub issues for P0/P1
- [ ] Assign developers to issues
- [ ] Schedule fix review meeting
- [ ] Communicate plan to stakeholders

---

## Sprint Planning Template

### Sprint N: Immediate Fixes

**Sprint Goal:** Fix all P0 and critical P1 issues  
**Duration:** [Start date] to [End date] (X days)  
**Team Capacity:** [X developer-days]

#### P0 Issues (Must Fix)

| Issue ID | Title | Assigned To | Estimate | Status |
|----------|-------|-------------|----------|--------|
| ERR-001 | [Title] | [Developer] | 4 hours | ☐ To Do |
| NAV-003 | [Title] | [Developer] | 1 day | ☐ To Do |
| [ID] | [Title] | [Developer] | [Time] | ☐ To Do |

**Subtotal:** ___ developer-days

#### P1 Issues (Should Fix)

| Issue ID | Title | Assigned To | Estimate | Status |
|----------|-------|-------------|----------|--------|
| NAV-001 | [Title] | [Developer] | 2 days | ☐ To Do |
| PERF-001 | [Title] | [Developer] | 3 days | ☐ To Do |
| [ID] | [Title] | [Developer] | [Time] | ☐ To Do |

**Subtotal:** ___ developer-days

**Total Planned:** ___ developer-days  
**Buffer (20%):** ___ developer-days  
**Sprint Capacity:** ___ developer-days

**Overflow Strategy:** If P1 issues don't fit, move lowest-scoring P1 to next sprint.

---

### Sprint N+1: Medium Priority Fixes

**Sprint Goal:** Improve usability and polish UX  
**Duration:** [Start date] to [End date] (X days)

#### P2 Issues (Nice to Fix)

| Issue ID | Title | Assigned To | Estimate | Status |
|----------|-------|-------------|----------|--------|
| VIS-001 | [Title] | [Developer] | 1 day | ☐ To Do |
| FORM-002 | [Title] | [Developer] | 2 days | ☐ To Do |
| [ID] | [Title] | [Developer] | [Time] | ☐ To Do |

**Total Planned:** ___ developer-days

---

### Backlog: Future Enhancements

**Target:** Next major release (v2.0)

#### P3/P4 Issues (Backlog)

| Issue ID | Title | Priority Tier | Tentative Date |
|----------|-------|---------------|----------------|
| MOB-002 | [Title] | P3 | Q2 2026 |
| MISS-003 | [Title] | P3 | Q2 2026 |
| [ID] | [Title] | P4 | TBD |

---

## Re-Prioritization Triggers

**When to re-evaluate priorities:**

### Triggers for Re-Prioritization

- 🔔 **New critical bug discovered** → Immediate re-prioritization meeting
- 🔔 **User complaints spike** → Re-assess impact/frequency scores
- 🔔 **Business priority changes** → Update business value scores
- 🔔 **Technical discovery** → Update effort estimates
- 🔔 **Every 2 weeks** → Regular sprint planning review

---

## Communication Plan

### Stakeholder Updates

**Weekly Status Report Template:**

```
Subject: Timekeeping Module - User Testing Fixes - Week [N]

Summary:
- P0 Issues Fixed: [X/Y]
- P1 Issues Fixed: [X/Y]
- P2 Issues In Progress: [X/Y]
- New Issues Discovered: [X]

Highlights:
- [Major fix completed this week]
- [User impact of fix]

Blockers:
- [Any blockers preventing progress]

Next Week Plan:
- [Top 3 priorities for next week]

Full Details: [Link to project board]
```

---

## Success Metrics

**Track these metrics to measure improvement:**

### Issue Resolution Metrics

| Metric | Target | Current |
|--------|--------|---------|
| **P0 Issues Resolved** | 100% within 3 days | ___% |
| **P1 Issues Resolved** | 100% within 1 week | ___% |
| **P2 Issues Resolved** | 80% within 2 weeks | ___% |
| **Avg Resolution Time (P0)** | < 1 day | ___ days |
| **Avg Resolution Time (P1)** | < 5 days | ___ days |
| **Avg Resolution Time (P2)** | < 10 days | ___ days |

### User Satisfaction Improvement

| Metric | Before Fixes | After Fixes | Improvement |
|--------|--------------|-------------|-------------|
| **Overall Usability Rating** | [X.X]/5 | [Y.Y]/5 | +___% |
| **Task Completion Rate** | ___% | ___% | +___% |
| **User Satisfaction** | ___% | ___% | +___% |
| **Critical Issues Count** | ___ | ___ | -___ |

---

## Example: Completed Prioritization

### Sample Issue: Navigation Menu Confusion

**Issue ID:** NAV-001  
**Issue Title:** Users cannot find Ledger page in navigation menu

**Scoring:**

| Dimension | Score | Weight | Calculation | Rationale |
|-----------|-------|--------|-------------|-----------|
| Severity | 4 | 40% | 4 × 0.40 = 1.60 | Major usability issue, core feature |
| Frequency | 5 | 25% | 5 × 0.25 = 1.25 | All 3 testers mentioned this |
| Impact | 5 | 20% | 5 × 0.20 = 1.00 | Affects all users, critical page |
| Effort | 4 | 10% | (6-4) × 0.10 = 0.20 | Simple menu restructure, 4 hours |
| Business Value | 3 | 5% | 3 × 0.05 = 0.15 | Important but not critical to business |
| **TOTAL** | | **100%** | **4.20** | |

**Priority Tier:** P1 (High Priority)  
**Timeline:** Fix within 1 week  
**Assigned To:** Frontend Developer  
**Estimated Effort:** 4 hours

**Action Plan:**
1. Redesign navigation menu structure
2. Add visual separator between Overview and Ledger
3. Rename menu items for clarity
4. Add tooltips for menu items
5. User test new navigation with 1 tester
6. Deploy and monitor

**Success Criteria:**
- ✅ 100% of users find Ledger page within 10 seconds
- ✅ No "Where is...?" questions about navigation
- ✅ Usability rating for navigation improves to ≥ 4.5/5

---

## Tools & Templates

### Priority Score Calculator (Spreadsheet Formula)

**Excel/Google Sheets Formula:**
```excel
=((B2*0.4)+(C2*0.25)+(D2*0.2)+((6-E2)*0.1)+(F2*0.05))
```

**Where:**
- B2 = Severity (1-5)
- C2 = Frequency (1-5)
- D2 = Impact (1-5)
- E2 = Effort (1-5)
- F2 = Business Value (1-5)

### Priority Tier Lookup (Spreadsheet Formula)

```excel
=IF(G2>=4.5,"P0",IF(G2>=4,"P1",IF(G2>=3,"P2",IF(G2>=2,"P3","P4"))))
```

Where G2 = Priority Score

---

## Appendices

### Appendix A: Prioritization Decision Log

**Record all major prioritization decisions:**

| Date | Issue ID | Decision | Rationale | Decided By |
|------|----------|----------|-----------|------------|
| 2026-02-04 | NAV-001 | Override to P1 | CEO request | Product Owner |
| 2026-02-05 | PERF-001 | Defer to P2 | Technical debt | Dev Lead |
| [Date] | [ID] | [Decision] | [Rationale] | [Person] |

---

### Appendix B: Sprint Retrospective Template

**After each sprint, review prioritization effectiveness:**

**Sprint N Retrospective:**

**What went well:**
- [What worked in prioritization]

**What didn't go well:**
- [What didn't work]

**Lessons learned:**
- [Key learnings]

**Prioritization adjustments:**
- [How to improve next time]

---

## Document Metadata

**Document Version:** 1.0  
**Last Updated:** February 4, 2026  
**Prepared By:** Development Team  
**Reviewed By:** Product Owner  
**Approved By:** Project Manager

**Related Documents:**
- [PHASE_7_TASK_7_1_3_PAIN_POINTS_DOCUMENTATION.md](PHASE_7_TASK_7_1_3_PAIN_POINTS_DOCUMENTATION.md)
- [PHASE_7_TASK_7_1_TESTING_EVALUATION_CHECKLIST.md](PHASE_7_TASK_7_1_TESTING_EVALUATION_CHECKLIST.md)
- [TIMEKEEPING_RFID_INTEGRATION_IMPLEMENTATION.md](TIMEKEEPING_RFID_INTEGRATION_IMPLEMENTATION.md)

**Contact:** dev@cameco.local
