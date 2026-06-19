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

    // Wider window for unit/allocation reports when no manual dates are set.
    'unit_report_start_days' => (int) env('PAYGRO_UNIT_REPORT_START_DAYS', 730),

    'unit_report_end_days' => (int) env('PAYGRO_UNIT_REPORT_END_DAYS', 30),

    // Payment transaction report (GetHighLevelReportForTransactions).
    'payment_report_start_days' => (int) env('PAYGRO_PAYMENT_REPORT_START_DAYS', 90),

    'payment_report_end_days' => (int) env('PAYGRO_PAYMENT_REPORT_END_DAYS', 30),

    // ── Incremental payment sync ──────────────────────────────────────────
    // Once payments exist, the sync only re-fetches transactions newer than the
    // stored watermark instead of re-pulling years of history every run.
    // Look-back from the last watermark to catch back-dated/edited transactions
    // (upserts are idempotent, so overlap never duplicates).
    'payment_sync_overlap_days' => (int) env('PAYGRO_PAYMENT_SYNC_OVERLAP_DAYS', 7),

    // First/baseline sync (no records yet) pulls this far back to seed history.
    'payment_first_sync_start_days' => (int) env('PAYGRO_PAYMENT_FIRST_SYNC_START_DAYS', 1825),

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

    // Default look-back when fetching latest token on a customer profile.
    'token_history_start_days' => (int) env('PAYGRO_TOKEN_HISTORY_START_DAYS', 365),

    'token_history_end_days' => (int) env('PAYGRO_TOKEN_HISTORY_END_DAYS', 0),

    // Second pass when the default window finds nothing (0 = disabled).
    'token_history_fallback_start_days' => (int) env('PAYGRO_TOKEN_HISTORY_FALLBACK_START_DAYS', 730),

    // Product sale register pagination (GetProductSaleRecord).
    'product_sale_page_size' => (int) env('PAYGRO_PRODUCT_SALE_PAGE_SIZE', 25),

    'product_sale_max_pages' => (int) env('PAYGRO_PRODUCT_SALE_MAX_PAGES', 100),

    // SortBy 4 = Sales Date; SortOrder 1 = ascending (matches PayGro UI defaults).
    'product_sale_sort_by' => (int) env('PAYGRO_PRODUCT_SALE_SORT_BY', 4),

    'product_sale_sort_order' => (int) env('PAYGRO_PRODUCT_SALE_SORT_ORDER', 1),

    // Customer-name search filter (SearchOption / SortBy = 3 in PayGro UI).
    'product_sale_customer_search_option' => (int) env('PAYGRO_PRODUCT_SALE_CUSTOMER_SEARCH_OPTION', 3),

    'product_sale_customer_search_by' => (int) env('PAYGRO_PRODUCT_SALE_CUSTOMER_SEARCH_BY', 3),

    // Payment plan master list (GetProductPaymentPlanList).
    'payment_plan_page_size' => (int) env('PAYGRO_PAYMENT_PLAN_PAGE_SIZE', 25),

    'payment_plan_max_pages' => (int) env('PAYGRO_PAYMENT_PLAN_MAX_PAGES', 50),

    'payment_plan_sort_by' => (int) env('PAYGRO_PAYMENT_PLAN_SORT_BY', 1),

    'payment_plan_sort_order' => (int) env('PAYGRO_PAYMENT_PLAN_SORT_ORDER', 1),

    'payment_plan_search_option' => (int) env('PAYGRO_PAYMENT_PLAN_SEARCH_OPTION', 1),

    // Product models to pull from ModelWisePaymentPlanMaster (SearchOption 1).
    'product_models' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('PAYGRO_PRODUCT_MODELS', 'TWP-K088,TWP-SR24,TWP-SR31C')),
    ))),

    // Credit types that use installment/arrears tracking (Hire Purchase).
    // Daily PAYGO customers are never marked overdue/at-risk from sync.
    'hire_purchase_credit_types' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('PAYGRO_HIRE_PURCHASE_CREDIT_TYPES', 'Hire Purchase,Higher Purchase')),
    ))),

    // ── Retry / backoff ───────────────────────────────────────────────────
    // Number of retry attempts for transient failures (5xx, timeout).
    'retry_times' => (int) env('PAYGRO_RETRY_TIMES', 3),

    // Base sleep between retries in milliseconds (doubles on each attempt).
    'retry_sleep_ms' => (int) env('PAYGRO_RETRY_SLEEP_MS', 1500),

    // ── Scheduled sync lock ───────────────────────────────────────────────
    // Maximum seconds the cache lock is held (prevents parallel scheduled runs).
    'sync_lock_seconds' => (int) env('PAYGRO_SYNC_LOCK_SECONDS', 600),
];
