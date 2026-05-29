<?php

return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/integrations/gmail/callback'),
        'scopes' => [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/gmail.readonly',
            'https://www.googleapis.com/auth/gmail.send',
            'https://www.googleapis.com/auth/calendar',
        ],
        'pubsub_topic' => env('GOOGLE_PUBSUB_TOPIC'),
    ],

    'microsoft' => [
        'client_id' => env('MS_CLIENT_ID'),
        'client_secret' => env('MS_CLIENT_SECRET'),
        'tenant_id' => env('MS_TENANT_ID', 'common'),
        'redirect_uri' => env('MS_REDIRECT_URI', env('APP_URL').'/integrations/microsoft/callback'),
        'scopes' => [
            'openid',
            'email',
            'profile',
            'offline_access',
            'https://graph.microsoft.com/Mail.Read',
            'https://graph.microsoft.com/Mail.Send',
            'https://graph.microsoft.com/Calendars.ReadWrite',
        ],
        'webhook_url' => env('MS_WEBHOOK_URL', env('APP_URL').'/integrations/microsoft/webhook'),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from_number' => env('TWILIO_FROM_NUMBER'),
        'webhook_url' => env('TWILIO_WEBHOOK_URL', env('APP_URL').'/integrations/twilio/webhook'),
    ],

    'whatsapp' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'webhook_url' => env('WHATSAPP_WEBHOOK_URL', env('APP_URL').'/integrations/whatsapp/webhook'),
    ],
];
