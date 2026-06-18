<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Africa's Talking credentials
    |--------------------------------------------------------------------------
    |
    | Use username "sandbox" with a sandbox API key for testing.
    | Production credentials come from https://account.africastalking.com
    |
    */

    'username' => env('AFRICASTALKING_USERNAME', 'sandbox'),

    'api_key' => env('AFRICASTALKING_API_KEY'),

    /*
    | Registered shortcode or alphanumeric sender ID.
    | Leave empty to use Africa's Talking default for your account.
    */

    'sender_id' => env('AFRICASTALKING_SENDER_ID'),

    /*
    | Default country calling code for local numbers (e.g. 254 for Kenya).
    | Numbers starting with 0 are converted: 0712... → +254712...
    */

    'default_country_code' => env('AFRICASTALKING_DEFAULT_COUNTRY_CODE', '254'),

    /*
    | Secret token appended to delivery-report webhook URL for verification.
    */

    'dlr_secret' => env('AFRICASTALKING_DLR_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Inbound (two-way) SMS
    |--------------------------------------------------------------------------
    |
    | Inbound replies can run on a separate, dedicated shortcode — and even a
    | separate Africa's Talking account. Outbound campaigns and single sends
    | always use the credentials above; only inbound auto-replies use these.
    | Username / API key fall back to the outbound credentials when blank.
    |
    */

    'inbound' => [
        'username' => env('AFRICASTALKING_INBOUND_USERNAME'),
        'api_key' => env('AFRICASTALKING_INBOUND_API_KEY'),
        // Dedicated inbound shortcode / sender ID used when auto-replying.
        'sender_id' => env('AFRICASTALKING_INBOUND_SENDER_ID'),
        // Secret token appended to the inbound webhook URL for verification.
        'secret' => env('AFRICASTALKING_INBOUND_SECRET'),
    ],

    /*
    | Queue name for outbound SMS jobs.
    */

    'queue' => env('AFRICASTALKING_SMS_QUEUE', 'sms'),

    /*
    | When true, bulk sends use AT enqueue mode (fire-and-forget to telcos).
    */

    'enqueue_bulk' => env('AFRICASTALKING_ENQUEUE_BULK', true),

];
