# Cash Salary Distribution Process

## Overview
Detailed steps for preparing, verifying, and releasing salary envelopes for cash-only payroll distribution (current deployment). Ensures physical cash handling remains auditable until future bank/e-wallet methods are enabled.

**Participants**: Payroll Officer (owner), HR Manager (witness/reviewer), Office Admin (cash custodian approval), Accounting (optional observer), Employees (sign receipts)

**Frequency**: Semi-monthly (15th & 30th) or monthly depending on configuration.

---

## Complete Cash Distribution Flow

```mermaid
graph TD
    Start([Payroll Approved]) --> WithdrawCash[Withdraw Total Cash
From Company Vault/Bank]
    WithdrawCash --> CountVault[Initial Count & Verification
(Payroll Officer + Office Admin)]
    CountVault --> PrepareDenoms[Prepare Denomination Breakdown]
    PrepareDenoms --> EnvelopePrep[Prepare Labeled Envelopes]
    EnvelopePrep --> StuffEnvelopes[Insert Cash + Payslip]
    StuffEnvelopes --> DoubleCheck[Second Person Verification
(HR Manager)]
    DoubleCheck --> SecureStorage[Seal Envelopes + Store in Safe]

    SecureStorage --> DistributionDay{Distribution Day}
    DistributionDay --> SetupDesk[Setup Disbursement Desk
Log Sheet, Queue]
    SetupDesk --> IdentityCheck[Verify Employee Identity
ID + Signature]
    IdentityCheck --> ReleaseCash[Hand Over Envelope]
    ReleaseCash --> EmployeeSign[Employee Signs Payroll Log
(plus payslip acknowledgment)]
    EmployeeSign --> UpdateTracker[Update Cash Tracker]

    DistributionDay --> |Employee Absent|HoldEnvelope[Hold Envelope in Safe]
    HoldEnvelope --> SchedulePickup[Schedule Catch-up Release]
    SchedulePickup --> IdentityCheck

    UpdateTracker --> EndShiftRecount[End-of-Day Recount
+ Remaining Cash]
    EndShiftRecount --> Reconcile[Reconcile vs Payroll Register]
    Reconcile --> DepositsExcess{Excess or Variance?}
    DepositsExcess -->|Yes| ReturnExcess[Return/Deposit Excess]
    DepositsExcess -->|No| Archive
    ReturnExcess --> Archive[Archive Docs
(Log, Receipts, CCTV references)]
    Archive --> End([Process Complete])
```

---

## Phase Breakdown

### Phase 1: Cash Preparation
- Withdraw required cash (authorized by Office Admin)
- Use payroll register to compute totals per department/team
- Document withdrawal reference numbers
- Prepare denomination breakdown to minimize counting errors

### Phase 2: Envelope Assembly
- Print payslips and attach to envelope interior
- Label envelopes with employee name, ID, amount (hidden flap)
- Insert net pay + allowances/adjustments
- Second verifier re-counts each envelope and signs checklist
- Seal envelopes; place tamper-evident tape if available

### Phase 3: Secure Storage
- Store sealed envelopes in locked safe until distribution
- Maintain chain-of-custody log (time, person, purpose)
- CCTV coverage recommended in storage area

### Phase 4: Distribution Day
- Setup queue per department to avoid crowding
- Required items: payslip logbook, ID scanner or manual verification, pen, receipt stamps
- Steps per employee:
  1. Verify identity and amount due
  2. Obtain signature/thumbmark on payroll log
  3. Provide payslip copy (employee retains)
  4. Mark system as “paid”

### Phase 5: Post-Distribution Reconciliation
- Recount remaining envelopes/cash with witness
- Log absent employees for follow-up release schedule
- Reconcile totals vs payroll register (should be zero variance)
- Deposit excess/returned cash back to vault
- Archive signed logs, acknowledgement forms, and CCTV references

---

## Controls & Safeguards
- Dual control during cash withdrawal, counting, and storage
- CCTV in cash handling areas
- Payroll logbook with signatures + government ID reference number
- Random spot checks by Office Admin
- Separate list for employees with advances/loans to ensure correct deductions reflected

---

## Exceptions Handling
- **Employee absent**: envelope stored in safe; release only with ID + countersign within 5 days
- **Employee representative**: allowed only with notarized authorization + ID copies; requires Office Admin approval
- **Discrepancy claim**: escalate immediately; recount with witness; file incident report
- **Lost envelope**: treat as major incident; notify Office Admin and Superadmin for investigation

---

## KPIs & Targets
- Reconciliation variance: ₱0 target
- Distribution time per employee: < 2 minutes
- Outstanding envelopes after payday: < 2%
- Incident reports (loss/dispute): 0 per year target

---

## Integration Points
- **Payroll Processing Workflow**: triggers cash preparation after Office Admin final approval
- **Government Remittances**: uses signed logs as proof of payout
- **Accounting**: records cash movement and reconciles with vault balance
- **Future Payment Methods**: Office Admin may switch to bank/e-wallet, but cash process remains fallback

## Immutable Ledger & Replay Monitoring

- Cash release values come from payroll runs that rely on the PostgreSQL ledger (`rfid_ledger`) managed by the Replayable Event-Log Verification Layer; no envelope should be prepared until ledger-aligned attendance is confirmed.
- Payroll and HR must monitor replay-layer alerting/metrics (ledger commit latency, sequence gaps, hash mismatches, replay backlog) so payout preparation pauses whenever integrity issues exist.

---

## Related Documentation
- [Payroll Processing Workflow](./payroll-processing.md)
- [Payroll Officer Workflow](../05-payroll-officer-workflow.md)
- [System Workflow Flowchart](../../SYSTEM_WORKFLOW_FLOWCHART.md#payment-methods)
- [HR & Payroll Config](../../HR_PAYROLL_CONFIG.md)

---

**Last Updated**: November 29, 2025  
**Process Owner**: Payroll Department  
**Payment Method**: Cash only (bank/e-wallet configurable for future)
