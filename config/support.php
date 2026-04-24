<?php

return [
    'company_name' => env('SUPPORT_COMPANY_NAME', 'KIM RETAIL SOFTWARE SYSTEMS'),
    'contact_person' => env('SUPPORT_CONTACT_PERSON'),
    'phone_primary' => env('SUPPORT_PHONE_PRIMARY'),
    'phone_secondary' => env('SUPPORT_PHONE_SECONDARY'),
    'email' => env('SUPPORT_EMAIL'),
    'whatsapp' => env('SUPPORT_WHATSAPP'),
    'website' => env('SUPPORT_WEBSITE'),
    'hours' => env('SUPPORT_HOURS', 'Monday - Saturday, 8:00 AM - 6:00 PM'),
    'response_note' => env(
        'SUPPORT_RESPONSE_NOTE',
        'Share the screen, branch, error message, and time the issue happened so support can help faster.'
    ),
];
