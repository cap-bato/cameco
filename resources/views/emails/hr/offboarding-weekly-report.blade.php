@component('mail::message')
# 📊 Weekly Offboarding Report

Dear **{{ $hrHead->name }}**,

Here is your weekly offboarding summary report for the week of **{{ $weekStart }}** to **{{ $weekEnd }}**.

## 📈 Executive Summary

| Metric | Count |
|--------|-------|
| **New Cases This Week** | {{ $statistics['new_cases'] }} |
| **Completed This Week** | {{ $statistics['completed_this_week'] }} |
| **Currently In Progress** | {{ $statistics['in_progress'] }} |
| **Pending Clearances** | {{ $statistics['pending_clearances'] }} |
| **Pending Exit Interviews** | {{ $statistics['pending_interviews'] }} |
| **Overdue Asset Returns** | {{ $statistics['overdue_assets'] }} |

## 🎯 Key Alerts

@component('mail::panel')
@if($statistics['overdue_assets'] > 0)
⚠️ **{{ $statistics['overdue_assets'] }} overdue asset return(s)** - Immediate action may be required
@endif

@if($statistics['pending_clearances'] > 0)
⚠️ **{{ $statistics['pending_clearances'] }} pending clearance item(s)** - Follow up with approvers
@endif

@if($statistics['pending_interviews'] > 0)
⚠️ **{{ $statistics['pending_interviews'] }} pending exit interview(s)** - Remind employees
@endif

@if($statistics['new_cases'] > 0)
ℹ️ **{{ $statistics['new_cases'] }} new offboarding case(s)** initiated this week
@endif
@endcomponent

## 📋 Recommended Actions

### High Priority
@if($statistics['overdue_assets'] > 0)
- [ ] Review and follow up on overdue asset returns
@endif

@if($statistics['pending_clearances'] > 0)
- [ ] Contact approvers with outstanding clearances
@endif

### Standard Priority
@if($statistics['pending_interviews'] > 0)
- [ ] Remind employees to complete exit interviews
@endif

- [ ] Review any new offboarding cases
- [ ] Update status of in-progress cases

## 📞 Quick Reference

**Offboarding Dashboard:**
Click the button below to access the full offboarding dashboard and see detailed case information.

@component('mail::button', ['url' => config('app.url') . '/hr/offboarding/cases', 'color' => 'primary'])
View Offboarding Dashboard
@endcomponent

## 📧 Need Help?

For more information about specific cases or employees, please log in to the system or contact the HR team.

---

### Report Details
- **Generated:** {{ now()->format('F d, Y \a\t H:i A') }}
- **Period:** {{ $weekStart }} to {{ $weekEnd }}
- **System:** {{ config('app.name') }}

---

Best regards,  
{{ config('app.name') }} Automated Reporting System

---

*This is an automated notification. For questions about specific cases, contact your HR team directly. Do not reply to this email.*

@endcomponent
