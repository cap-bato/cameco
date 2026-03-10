@component('mail::message')
@if($isUrgent)
# ⚠️ URGENT: RFID Badge Expiration Notice
@else
# 🔔 RFID Badge Expiration Notice
@endif

Dear **{{ $employeeName }}**,

This is a reminder that your RFID access badge will expire in **{{ $daysRemaining }} day(s)**.

## Badge Details

| Detail | Value |
|--------|-------|
| **Card UID** | `{{ $cardUid }}` |
| **Expiration Date** | **{{ $expirationDate }}** |

@if($isUrgent)
@component('mail::panel')
⚠️ **ACTION REQUIRED:** Please visit the HR Office **immediately** to renew your badge before it expires. An expired badge will prevent access to all facilities and gates.
@endcomponent
@else
@component('mail::panel')
Please visit the HR Office during business hours to renew or update your badge.
@endcomponent
@endif

## Next Steps

1. **Visit HR Office:** Bring your expired badge (if available) and a valid government ID
2. **Complete Renewal Form:** HR will verify your information and issue a new badge
3. **New Badge Activation:** The new badge will be activated immediately upon issuance
4. **Return Old Badge:** Return your old badge to HR for compliance documentation

## HR Office Information

📍 **Location:** [Your Location Here]

🕒 **Business Hours:**
- Monday - Friday: 8:00 AM - 5:00 PM
- Saturday - Sunday: CLOSED

📞 **Contact HR:**
- Email: hr@{{ parse_url(config('app.url'), PHP_URL_HOST) }}
- Phone: [HR Phone Number]

## Important Notes

- **Access Denied:** After the expiration date, your badge will no longer grant access to doors and gates
- **Grace Period:** There is **no grace period** - please renew before the expiration date
- **Replacement Fee:** If lost or damaged, there may be a badge replacement fee
- **Questions?** Contact the HR Department for any inquiries

---

@component('mail::button', ['url' => config('app.url') . '/employee/profile', 'color' => 'primary'])
View My Profile
@endcomponent

Thank you for your prompt attention to this matter.

Best regards,  
{{ config('app.name') }} HR Team

---

*This is an automated notification. Please do not reply to this email. For questions, contact HR directly.*

@endcomponent
