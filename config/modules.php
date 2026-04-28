<?php

return [
    'employee'    => env('MODULE_EMPLOYEE_ENABLED', true),
    'leave'       => env('MODULE_LEAVE_ENABLED', false),
    'documents'   => env('MODULE_DOCUMENTS_ENABLED', false),
    'ats'         => env('MODULE_ATS_ENABLED', false),
    'workforce'   => env('MODULE_WORKFORCE_ENABLED', false),
    'timekeeping' => env('MODULE_TIMEKEEPING_ENABLED', false),
    'appraisals'  => env('MODULE_APPRAISALS_ENABLED', false),
    'offboarding' => env('MODULE_OFFBOARDING_ENABLED', false),
    'payroll'     => env('MODULE_PAYROLL_ENABLED', false),
    'admin'       => env('MODULE_ADMIN_ENABLED', false),
    'system'      => env('MODULE_SYSTEM_ENABLED', false),
];
