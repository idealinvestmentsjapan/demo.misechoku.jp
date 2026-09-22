<?php

/*
 * ### demo function and data for test ###
 *
 * Demo mode configuration.
 * Enable via .env: DEMO_MODE=true
 *
 * When enabled the app exposes helper routes that let external testers
 * complete email/LINE verification and push notification checks without
 * real infrastructure (no SMTP, no LINE OAuth callback).
 *
 * ***MUST be false in production.***
 */

return [
    // Master switch for the demo-only helpers below.
    'enabled' => filter_var(env('DEMO_MODE', false), FILTER_VALIDATE_BOOLEAN),

    // Sales fixtures are opt-in and also require a non-production environment/allowed host.
    'sales_enabled' => filter_var(env('DEMO_SALES_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'sales_hosts' => ['demo.misechoku.jp', 'localhost', '127.0.0.1'],

    // Skip Mail::raw and mark casts/shop_managers.email_verified_at immediately
    // when the user requests a verification mail.
    'auto_verify_email' => filter_var(env('DEMO_AUTO_VERIFY_EMAIL', true), FILTER_VALIDATE_BOOLEAN),

    // Bypass LINE OAuth. Mock login/link routes work with an arbitrary user id.
    'mock_line' => filter_var(env('DEMO_MOCK_LINE', true), FILTER_VALIDATE_BOOLEAN),

    // Expose POST /api/push/test to fire a canned Web Push at the current user
    // (does not skip the real service worker path — testers still need to grant
    // browser permission and complete `MisechokuPush.enable()`).
    'test_push' => filter_var(env('DEMO_TEST_PUSH', true), FILTER_VALIDATE_BOOLEAN),
];
