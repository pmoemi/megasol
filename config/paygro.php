<?php

return [
    // ── Endpoint ─────────────────────────────────────────────────────────
    'base_url' => env('PAYGRO_BASE_URL', 'https://app-main.pay-gro.com'),

    'distributor_company_srl_no' => env('PAYGRO_DISTRIBUTOR_COMPANY_SRL_NO', 7),

    // ── Default report date range ─────────────────────────────────────────
    'report_start_date' => env('PAYGRO_REPORT_START_DATE'),

    'report_end_date' => env('PAYGRO_REPORT_END_DATE'),

    // Rolling fallback: N days before/after today when fixed dates are unset.
    'report_start_days' => (int) env('PAYGRO_REPORT_START_DAYS', 60),

    'report_end_days' => (int) env('PAYGRO_REPORT_END_DAYS', 30),

    // ── Session management ────────────────────────────────────────────────
    // Cookies stored in env (optional override; DB settings take precedence).
    'paygro_cookie' => env('PAYGRO_COOKIE'),

    'aspnet_cookie' => env('PAYGRO_ASPNET_COOKIE'),

    // Proactively refresh the session when it is older than this many minutes.
    'session_max_age_minutes' => (int) env('PAYGRO_SESSION_MAX_AGE_MINUTES', 60),

    // ── HTTP timeouts (seconds) ───────────────────────────────────────────
    // Quick operations: session check, login GET.
    'timeout_short' => (int) env('PAYGRO_TIMEOUT_SHORT', 20),

    // Form POST login.
    'timeout_login' => (int) env('PAYGRO_TIMEOUT_LOGIN', 45),

    // Report / bulk data fetches.
    'timeout_report' => (int) env('PAYGRO_TIMEOUT_REPORT', 120),

    // Token history pages.
    'timeout_token' => (int) env('PAYGRO_TIMEOUT_TOKEN', 45),

    // ── Retry / backoff ───────────────────────────────────────────────────
    // Number of retry attempts for transient failures (5xx, timeout).
    'retry_times' => (int) env('PAYGRO_RETRY_TIMES', 3),

    // Base sleep between retries in milliseconds (doubles on each attempt).
    'retry_sleep_ms' => (int) env('PAYGRO_RETRY_SLEEP_MS', 1500),

    // ── Scheduled sync lock ───────────────────────────────────────────────
    // Maximum seconds the cache lock is held (prevents parallel scheduled runs).
    'sync_lock_seconds' => (int) env('PAYGRO_SYNC_LOCK_SECONDS', 600),
];
