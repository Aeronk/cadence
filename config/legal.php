<?php

/**
 * Details that appear in the public privacy policy and terms of service.
 *
 * These pages are what Google and Microsoft read during OAuth app verification,
 * so the values here have to be real: a verification reviewer checks that the
 * entity named on the policy matches the one that owns the OAuth client and the
 * verified domain. Placeholders will fail review.
 */
return [
    // The legal entity that operates the service, as registered. Not the
    // product name — Google compares this against your OAuth client owner.
    'entity' => env('LEGAL_ENTITY_NAME', 'Cadence'),

    // The product name shown to users. Must match the app name on the Google
    // OAuth consent screen exactly, or verification is rejected.
    'product' => env('LEGAL_PRODUCT_NAME', 'Cadence'),

    // Registered business address. Google requires a contactable postal address
    // for apps requesting sensitive or restricted scopes.
    'address' => env('LEGAL_ADDRESS'),

    // Where privacy questions and deletion requests go. Must be monitored —
    // reviewers do send test mail here.
    'privacy_email' => env('LEGAL_PRIVACY_EMAIL', 'privacy@bint.co.zw'),

    'support_email' => env('LEGAL_SUPPORT_EMAIL', 'support@bint.co.zw'),

    // Governing law for the terms.
    'jurisdiction' => env('LEGAL_JURISDICTION', 'Zimbabwe'),

    // Shown at the top of both documents. Bump it whenever the terms change
    // materially; users are entitled to know when that happened.
    'effective_date' => env('LEGAL_EFFECTIVE_DATE', '2026-09-22'),
];
