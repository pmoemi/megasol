<?php

namespace App\Services\Integrations;

use App\Models\Customer;
use App\Models\PaygroSyncLog;
use App\Models\Setting;
use App\Models\TokenTransaction;
use App\Traits\NormalizesPhoneNumbers;
use Carbon\Carbon;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayGroService
{
    use NormalizesPhoneNumbers;

    public const SETTING_BASE_URL = 'paygro_base_url';

    public const SETTING_DISTRIBUTOR_ID = 'paygro_distributor_company_srl_no';

    public const SETTING_START_DATE = 'paygro_report_start_date';

    public const SETTING_END_DATE = 'paygro_report_end_date';

    public const SETTING_USERNAME = 'paygro_username';

    public const SETTING_PASSWORD = 'paygro_password_encrypted';

    public const SETTING_PAYGRO_COOKIE = 'paygro_paygro_cookie';

    public const SETTING_ASPNET_COOKIE = 'paygro_aspnet_cookie';

    public const SETTING_SESSION_REFRESHED_AT = 'paygro_session_refreshed_at';

    public const SETTING_FIRST_SYNC_COMPLETED = 'paygro_first_sync_completed';

    public const SETTING_ACCOUNT_NAME = 'paygro_account_name';

    public const SETTING_ACCOUNT_EMAIL = 'paygro_account_email';

    public const SETTING_ACCOUNT_MOBILE = 'paygro_account_mobile';

    public const SETTING_ACCOUNT_TYPE_NAME = 'paygro_account_type_name';

    public const SETTING_DISTRIBUTOR_ACCOUNT_SRL_NO = 'paygro_distributor_account_srl_no';

    /** @deprecated Legacy REST API — use session login instead */
    public const SETTING_API_URL = 'paygro_api_url';

    /** @deprecated Legacy REST API — use session login instead */
    public const SETTING_API_KEY = 'paygro_api_key';

    /**
     * @return array{processed: int, failed: int, status: string}
     */
    public function syncCustomers(
        ?string $startDate = null,
        ?string $endDate = null,
        bool $markFirstSync = false,
        string $syncSource = 'manual',
    ): array {
        $startedAt = microtime(true);
        $sessionRefreshed = false;

        $log = PaygroSyncLog::create([
            'sync_type' => 'customers',
            'status' => 'running',
            'source' => $syncSource,
            'started_at' => now(),
        ]);

        try {
            $sessionRefreshed = $this->ensureAuthenticated();

            $fetchResult = $this->fetchCustomers($startDate, $endDate);
            $records = $fetchResult['records'];
            $source = $fetchResult['source'];
            $processed = 0;
            $failed = 0;

            foreach ($records as $record) {
                try {
                    $mapped = $this->isPayGroReportRecord($record)
                        ? $this->mapReportRecord($record)
                        : $record;
                    $this->upsertCustomer($mapped);
                    $processed++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('PayGro customer upsert failed', [
                        'record' => $record,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($markFirstSync && $processed > 0) {
                Setting::set(self::SETTING_FIRST_SYNC_COMPLETED, '1');
            }

            $log->update([
                'status' => $failed > 0 && $processed === 0 ? 'failed' : 'completed',
                'session_refreshed' => $sessionRefreshed,
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'records_processed' => $processed,
                'records_failed' => $failed,
                'payload' => [
                    'fetch_source' => $source,
                    'count' => count($records),
                    'start_date' => $startDate ?? $this->resolveReportStartDate(),
                    'end_date' => $endDate ?? $this->resolveReportEndDate(),
                ],
                'completed_at' => now(),
            ]);

            return [
                'processed' => $processed,
                'failed' => $failed,
                'status' => $log->status,
            ];
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'session_refreshed' => $sessionRefreshed,
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }

  /**
     * @return array{
     *     configured: bool,
     *     connected: bool,
     *     has_credentials: bool,
     *     has_password: bool,
     *     username: ?string,
     *     last_refresh: ?string,
     *     first_sync_completed: bool,
     *     account_name: ?string,
     *     account_email: ?string,
     *     account_mobile: ?string,
     *     account_type_name: ?string,
     *     distributor_account_srl_no: ?string
     * }
     */
    public function getConnectionStatus(): array
    {
        $hasCredentials = $this->hasStoredCredentials();
        $lastRefresh = Setting::get(self::SETTING_SESSION_REFRESHED_AT);
        $isStale = $this->sessionIsStale();

        return [
            'configured' => $hasCredentials || $this->getSessionCookieHeader() !== null,
            'connected' => $hasCredentials ? $this->verifySession(false) : $this->getSessionCookieHeader() !== null,
            'has_credentials' => $hasCredentials,
            'has_password' => $this->hasStoredPassword(),
            'username' => $this->getStoredUsername(),
            'last_refresh' => $lastRefresh,
            'session_stale' => $isStale,
            'session_max_age_minutes' => (int) config('paygro.session_max_age_minutes', 60),
            'first_sync_completed' => (bool) Setting::get(self::SETTING_FIRST_SYNC_COMPLETED),
            'account_name' => Setting::get(self::SETTING_ACCOUNT_NAME),
            'account_email' => Setting::get(self::SETTING_ACCOUNT_EMAIL),
            'account_mobile' => Setting::get(self::SETTING_ACCOUNT_MOBILE),
            'account_type_name' => Setting::get(self::SETTING_ACCOUNT_TYPE_NAME),
            'distributor_account_srl_no' => Setting::get(self::SETTING_DISTRIBUTOR_ACCOUNT_SRL_NO),
        ];
    }

    public function saveCredentials(string $username, ?string $password = null): void
    {
        Setting::set(self::SETTING_USERNAME, trim($username));

        if ($password !== null && $password !== '') {
            Setting::set(self::SETTING_PASSWORD, Crypt::encryptString($password));
        }
    }

    /**
     * @return array{success: bool, message: string, refreshed_at: ?string}
     */
    public function login(): array
    {
        try {
            $this->loginAndStoreCookies();

            return [
                'success' => true,
                'message' => 'Connected to PayGro successfully.',
                'refreshed_at' => Setting::get(self::SETTING_SESSION_REFRESHED_AT),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'refreshed_at' => null,
            ];
        }
    }

    /**
     * Ensure a valid PayGro session is active. Returns true when a new login
     * was performed (session was refreshed), false when an existing valid
     * session was reused.
     *
     * Call this before every PayGro operation so the service self-heals
     * without operator involvement.
     */
    public function ensureAuthenticated(bool $force = false): bool
    {
        if (! $this->hasStoredCredentials()) {
            // No stored credentials — rely on whatever cookies are present.
            if (! $this->getSessionCookieHeader()) {
                throw new \RuntimeException('PayGro credentials are not configured. Save your username and password first.');
            }

            return false;
        }

        // Proactively refresh when the session is stale even if it still works.
        if ($force || ! $this->getSessionCookieHeader() || $this->sessionIsStale()) {
            $this->loginAndStoreCookies();

            return true;
        }

        return false;
    }

    /**
     * @deprecated Use ensureAuthenticated() instead.
     */
    public function authenticate(bool $forceRefresh = false): void
    {
        $this->ensureAuthenticated($forceRefresh);
    }

    /**
     * Returns true when the stored session is older than the configured
     * session_max_age_minutes, meaning we should proactively refresh before
     * the next API call rather than waiting for a 401.
     */
    protected function sessionIsStale(): bool
    {
        $refreshedAt = Setting::get(self::SETTING_SESSION_REFRESHED_AT);

        if (! $refreshedAt) {
            return true;
        }

        $maxAge = (int) config('paygro.session_max_age_minutes', 60);

        try {
            return Carbon::parse((string) $refreshedAt)->addMinutes($maxAge)->isPast();
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Fetch the newest free-token history row that belongs to one of the
     * customer's registered PayGro unit serials.
     *
     * @return array<string, mixed>|null
     */
    public function fetchLatestFreeTokenForCustomer(
        Customer $customer,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): ?array {
        $this->ensureValidSessionForFetch();

        $cookieHeader = $this->getSessionCookieHeader();
        if (! $cookieHeader) {
            throw new \RuntimeException('PayGro session is not active. Connect to PayGro before fetching tokens.');
        }

        $serials = $this->customerPayGroSerials($customer);

        if ($serials->isEmpty()) {
            throw new \RuntimeException('This customer has no unit serial or PayGro serial metadata to match token history.');
        }

        [$fromDate, $toDate] = $this->tokenHistoryDateRange($fromDate, $toDate);
        $baseUrl = rtrim((string) $this->configValue('base_url', 'https://app-main.pay-gro.com'), '/');
        $latest = null;

        foreach ($serials as $serial) {
            foreach ($this->fetchFreeTokenHistoryRows($baseUrl, $cookieHeader, $fromDate, $toDate, $serial) as $row) {
                if (! $this->tokenRowMatchesSerial($row, $serial)) {
                    continue;
                }

                $generatedAt = $this->parsePayGroDate($row['TokenGenerationDate'] ?? null);
                if (! $generatedAt) {
                    continue;
                }

                if (trim((string) ($row['GeneratedTokenValue'] ?? '')) === '') {
                    continue;
                }

                if (! $latest || $generatedAt->greaterThan($latest['generated_at'])) {
                    $latest = [
                        'generated_at' => $generatedAt,
                        'row' => $row,
                        'matched_serial' => $serial,
                    ];
                }
            }
        }

        if (! $latest) {
            return null;
        }

        $row = $latest['row'];
        /** @var Carbon $generatedAt */
        $generatedAt = $latest['generated_at'];

        return [
            'product_serial_number' => (string) ($row['ProductSerialNumber'] ?? $latest['matched_serial']),
            'generated_token_value' => (string) ($row['GeneratedTokenValue'] ?? ''),
            'token_generation_date' => $generatedAt->toIso8601String(),
            'token_generation_date_display' => $generatedAt->format('M j, Y g:i A'),
            'token_type_name' => $row['TokenTypeName'] ?? null,
            'credit_quantity' => $row['CreditQuantity'] ?? null,
            'activation_duration' => $row['ActivationDuration'] ?? null,
            'token_tag' => $row['TokenTag'] ?? null,
            'history_srl_no' => $row['HistorySrlNo'] ?? null,
            'matched_customer_account' => $customer->account_number,
            'matched_asset_serial' => $latest['matched_serial'],
            'raw' => $row,
        ];
    }

    /**
     * Fetch, match, and persist only the newest PayGro free-token row for the
     * customer. Repeated test syncs update the same ledger row instead of
     * creating duplicates.
     *
     * @return array<string, mixed>|null
     */
    public function syncLatestFreeTokenForCustomer(
        Customer $customer,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): ?array {
        $token = $this->fetchLatestFreeTokenForCustomer($customer, $fromDate, $toDate);

        if (! $token) {
            return null;
        }

        $generatedAt = Carbon::parse((string) $token['token_generation_date']);
        $externalReference = $this->payGroTokenExternalReference($token);
        $transaction = TokenTransaction::updateOrCreate(
            [
                'source' => 'paygro_free_token',
                'external_reference' => $externalReference,
            ],
            [
                'customer_id' => $customer->id,
                'customer_payment_id' => null,
                'type' => 'credit',
                'tokens' => max(1, (int) ($token['credit_quantity'] ?? 1)),
                'days' => max(0, (int) ($token['activation_duration'] ?? 0)),
                'balance_after' => (int) ($customer->token_balance ?? 0),
                'token_value' => $token['generated_token_value'],
                'product_serial_number' => $token['product_serial_number'],
                'token_tag' => $token['token_tag'] ?? null,
                'description' => 'Latest PayGro free token for '.$token['product_serial_number'],
                'occurred_at' => $generatedAt,
                'meta' => [
                    'token_type_name' => $token['token_type_name'] ?? null,
                    'history_srl_no' => $token['history_srl_no'] ?? null,
                    'matched_asset_serial' => $token['matched_asset_serial'] ?? null,
                    'matched_customer_account' => $customer->account_number,
                    'raw' => $token['raw'] ?? null,
                ],
            ],
        );

        return array_merge($token, [
            'token_transaction_id' => $transaction->id,
            'external_reference' => $externalReference,
        ]);
    }

    protected function customerPayGroSerials(Customer $customer)
    {
        $assetSerials = $customer->assets()
            ->whereNotNull('unit_serial')
            ->pluck('unit_serial');

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $metaSerials = collect([
            $meta['product_serial_number'] ?? null,
            $meta['ProductSerialNumber'] ?? null,
            $meta['serial_number'] ?? null,
            $meta['SerialNumber'] ?? null,
            $meta['unit_serial'] ?? null,
            $meta['UnitSerial'] ?? null,
        ]);

        return $assetSerials
            ->merge($metaSerials)
            ->map(fn ($serial) => trim((string) $serial))
            ->filter()
            ->unique(fn ($serial) => strtolower($serial))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $token
     */
    protected function payGroTokenExternalReference(array $token): string
    {
        if (! empty($token['history_srl_no'])) {
            return 'history:'.$token['history_srl_no'];
        }

        return 'hash:'.sha1(implode('|', [
            $token['product_serial_number'] ?? '',
            $token['generated_token_value'] ?? '',
            $token['token_generation_date'] ?? '',
        ]));
    }

    protected function ensureValidSessionForFetch(): void
    {
        $this->ensureAuthenticated();
    }

    /**
     * Central HTTP wrapper for all PayGro authenticated API calls.
     *
     * - Attaches the current session cookies automatically.
     * - Applies a configurable timeout.
     * - Retries on transient failures (5xx, DNS/connection errors) with
     *   exponential back-off.
     * - On a 401 or a response body that looks like the login page, performs
     *   one forced re-login and retries the original request.
     *
     * @param  callable(\Illuminate\Http\Client\PendingRequest): \Illuminate\Http\Client\Response  $requestFn
     */
    protected function payGroRequest(
        callable $requestFn,
        int $timeout,
        bool $hasRetriedAfterLogin = false,
    ): \Illuminate\Http\Client\Response {
        $cookieHeader = $this->getSessionCookieHeader();

        if (! $cookieHeader) {
            throw new \RuntimeException('PayGro session is not active. Ensure credentials are saved and Connect to PayGro.');
        }

        $retryTimes = (int) config('paygro.retry_times', 3);
        $retrySleepMs = (int) config('paygro.retry_sleep_ms', 1500);

        $pending = Http::withHeaders([
            'Cookie' => $cookieHeader,
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => '*/*',
        ])
            ->timeout($timeout)
            ->retry(
                $retryTimes,
                fn (int $attempt) => (int) ($retrySleepMs * (2 ** ($attempt - 1))),
                fn (\Throwable $e, $response) => $this->isTransientFailure($e, $response),
                throw: false,
            );

        $response = $requestFn($pending);

        // Detect an expired session: explicit 401 or the login page in the body.
        if (! $hasRetriedAfterLogin && $this->isExpiredSessionResponse($response)) {
            if ($this->hasStoredCredentials()) {
                Log::info('PayGro: session expired, refreshing and retrying request.');
                $this->loginAndStoreCookies();

                return $this->payGroRequest($requestFn, $timeout, true);
            }
        }

        return $response;
    }

    /**
     * @param  \Illuminate\Http\Client\Response|\Throwable|null  $response
     */
    protected function isTransientFailure(\Throwable $e, mixed $response): bool
    {
        if ($e instanceof \Illuminate\Http\Client\ConnectionException) {
            return true;
        }

        if ($response instanceof \Illuminate\Http\Client\Response) {
            return $response->serverError();
        }

        return false;
    }

    protected function isExpiredSessionResponse(\Illuminate\Http\Client\Response $response): bool
    {
        if ($response->status() === 401) {
            return true;
        }

        if ($response->status() === 200 && str_contains(strtolower((string) $response->body()), 'sign in')) {
            return true;
        }

        return false;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function tokenHistoryDateRange(?string $fromDate = null, ?string $toDate = null): array
    {
        $from = $fromDate ? Carbon::parse($fromDate) : now()->subMonth()->startOfDay();
        $to = $toDate ? Carbon::parse($toDate) : now()->endOfDay();

        return [$from->format('Y/m/d'), $to->format('Y/m/d')];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchFreeTokenHistoryRows(
        string $baseUrl,
        string $cookieHeader,
        string $fromDate,
        string $toDate,
        string $serialNumber,
        int $pageNumber = 1,
    ): array {
        $timeout = (int) config('paygro.timeout_token', 45);

        $response = $this->payGroRequest(
            fn ($http) => $http
                ->withHeaders([
                    'Referer' => $baseUrl.'/Transactions/FreeTokenGenerationHistory',
                ])
                ->get($baseUrl.'/Transactions/GetFreeTokenGenerationHistory', [
                    'fromDate' => $fromDate,
                    'toDate' => $toDate,
                    'serialNumber' => $serialNumber,
                    'pageNumber' => $pageNumber,
                    'pageSize' => 100,
                ]),
            $timeout,
        );

        if (! $response->successful()) {
            throw new \RuntimeException('PayGro token history request failed: HTTP '.$response->status());
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new \RuntimeException('PayGro returned a non-JSON token history response.');
        }

        if (! ($data['IsSuccess'] ?? false)) {
            $message = $data['Message'] ?? $data['StatusMessage'] ?? 'Unknown PayGro error';

            throw new \RuntimeException('PayGro token history failed: '.$message);
        }

        $rows = is_array($data['Rows'] ?? null) ? $data['Rows'] : [];
        $pages = max(1, (int) ($data['NoOfPages'] ?? 1));

        if ($pageNumber < $pages && $pageNumber < 20) {
            $cookieHeader = $this->getSessionCookieHeader() ?? $cookieHeader;

            return array_merge(
                $rows,
                $this->fetchFreeTokenHistoryRows($baseUrl, $cookieHeader, $fromDate, $toDate, $serialNumber, $pageNumber + 1),
            );
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function tokenRowMatchesSerial(array $row, string $serial): bool
    {
        return strtolower(trim((string) ($row['ProductSerialNumber'] ?? ''))) === strtolower(trim($serial));
    }

    protected function parsePayGroDate(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function loginAndStoreCookies(): void
    {
        $baseUrl = rtrim((string) $this->configValue('base_url', 'https://app-main.pay-gro.com'), '/');
        $username = $this->getStoredUsername();
        $password = $this->getStoredPassword();

        if (! $username || ! $password) {
            throw new \RuntimeException('PayGro username and password are required.');
        }

        $jar = new CookieJar();
        $loginTimeout = (int) config('paygro.timeout_login', 45);
        $shortTimeout = (int) config('paygro.timeout_short', 20);

        $http = Http::withOptions([
            'cookies' => $jar,
            'allow_redirects' => true,
        ])->timeout($loginTimeout);

        Http::withOptions(['cookies' => $jar, 'allow_redirects' => true])
            ->timeout($shortTimeout)
            ->get($baseUrl.'/Home/Login');

        $response = $http
            ->asForm()
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json, text/javascript, */*; q=0.01',
                'Referer' => $baseUrl.'/Home/Login',
                'Origin' => $baseUrl,
            ])
            ->post($baseUrl.'/Security/ValidateLogin', [
                'userId' => $username,
                'password' => $password,
            ]);

        $data = $response->json();

        if (! is_array($data) || (int) ($data['SessionStatus'] ?? 0) !== 1) {
            $message = trim((string) ($data['StatusMessage'] ?? 'Invalid PayGro credentials.'));

            throw new \RuntimeException($message ?: 'PayGro login failed.');
        }

        $http->get($baseUrl.'/Home/Dashboard');

        $cookies = $this->extractCookiesFromJar($jar);

        if (! $cookies['paygro'] && ! empty($data['SessionId'])) {
            $cookies['paygro'] = (string) $data['SessionId'];
        }

        if (! $cookies['paygro'] || ! $cookies['aspnet']) {
            throw new \RuntimeException('PayGro login succeeded but session cookies were not received.');
        }

        Setting::set(self::SETTING_PAYGRO_COOKIE, $cookies['paygro']);
        Setting::set(self::SETTING_ASPNET_COOKIE, $cookies['aspnet']);
        Setting::set(self::SETTING_SESSION_REFRESHED_AT, now()->toIso8601String());

        $this->storeAccountDetails($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function storeAccountDetails(array $data): void
    {
        if (! empty($data['UserName'])) {
            Setting::set(self::SETTING_ACCOUNT_NAME, (string) $data['UserName']);
        }

        if (! empty($data['Email'])) {
            Setting::set(self::SETTING_ACCOUNT_EMAIL, (string) $data['Email']);
        }

        if (! empty($data['Mobile'])) {
            Setting::set(self::SETTING_ACCOUNT_MOBILE, (string) $data['Mobile']);
        }

        if (! empty($data['UserTypeName'])) {
            Setting::set(self::SETTING_ACCOUNT_TYPE_NAME, (string) $data['UserTypeName']);
        }

        if (! empty($data['DistributorAccountSrlNo'])) {
            Setting::set(self::SETTING_DISTRIBUTOR_ACCOUNT_SRL_NO, (string) $data['DistributorAccountSrlNo']);
        }

        if (! empty($data['DistributorCompanySrlNo'])) {
            Setting::set(self::SETTING_DISTRIBUTOR_ID, (string) $data['DistributorCompanySrlNo']);
        }
    }

    /**
     * @return array{paygro: ?string, aspnet: ?string}
     */
    protected function extractCookiesFromJar(CookieJar $jar): array
    {
        $paygro = null;
        $aspnet = null;

        foreach ($jar->toArray() as $cookie) {
            if ($cookie['Name'] === 'Paygro') {
                $paygro = $cookie['Value'];
            }
            if ($cookie['Name'] === '.AspNetCore.Cookies') {
                $aspnet = $cookie['Value'];
            }
        }

        return ['paygro' => $paygro, 'aspnet' => $aspnet];
    }

    protected function verifySession(bool $retryLogin = true): bool
    {
        $cookieHeader = $this->getSessionCookieHeader();

        if (! $cookieHeader) {
            return false;
        }

        $baseUrl = rtrim((string) $this->configValue('base_url', 'https://app-main.pay-gro.com'), '/');
        $timeout = (int) config('paygro.timeout_short', 20);

        try {
            $response = Http::withHeaders([
                'Cookie' => $cookieHeader,
                'X-Requested-With' => 'XMLHttpRequest',
            ])
                ->timeout($timeout)
                ->get($baseUrl.'/Home/Dashboard');

            if ($response->successful() && ! str_contains(strtolower($response->body()), 'sign in')) {
                return true;
            }
        } catch (\Throwable) {
            // fall through to re-login
        }

        if ($retryLogin && $this->hasStoredCredentials()) {
            try {
                $this->loginAndStoreCookies();

                return true;
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    protected function hasStoredCredentials(): bool
    {
        return $this->getStoredUsername() && $this->hasStoredPassword();
    }

    public function hasStoredPassword(): bool
    {
        $encrypted = Setting::get(self::SETTING_PASSWORD);

        return $encrypted !== null && $encrypted !== '';
    }

    protected function getStoredUsername(): ?string
    {
        $username = Setting::get(self::SETTING_USERNAME);

        return $username ? (string) $username : null;
    }

    protected function getStoredPassword(): ?string
    {
        $encrypted = Setting::get(self::SETTING_PASSWORD);

        if (! $encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{records: array<int, array<string, mixed>>, source: string}
     */
    protected function fetchCustomers(?string $startOverride = null, ?string $endOverride = null): array
    {
        $cookies = $this->getSessionCookieHeader();

        if ($cookies) {
            $baseUrl = (string) $this->configValue('base_url', 'https://app-main.pay-gro.com');

            return [
                'records' => $this->fetchFromHighLevelReport($baseUrl, $cookies, $startOverride, $endOverride),
                'source' => 'high_level_report',
            ];
        }

        $apiUrl = $this->configValue('api_url') ?? Setting::get(self::SETTING_API_URL);
        $apiKey = $this->configValue('api_key') ?? Setting::get(self::SETTING_API_KEY);

        if ($apiUrl && $apiKey) {
            return [
                'records' => $this->fetchFromLegacyApi($apiUrl, $apiKey),
                'source' => 'legacy_api',
            ];
        }

        if ($this->hasStoredCredentials()) {
            throw new \RuntimeException('PayGro session is not active. Click Connect to PayGro to refresh your session.');
        }

        return [
            'records' => $this->mockCustomers(),
            'source' => 'mock',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchFromHighLevelReport(
        string $baseUrl,
        string $cookieHeader,
        ?string $startOverride = null,
        ?string $endOverride = null,
    ): array {
        $url = rtrim($baseUrl, '/').'/Masters/GetHighLevelReportForCustomer';
        $distributorId = (int) $this->configValue('distributor_company_srl_no', 7);
        [$startDate, $endDate] = $this->reportDateRange($startOverride, $endOverride);
        $timeout = (int) config('paygro.timeout_report', 120);

        $response = $this->payGroRequest(
            fn ($http) => $http
                ->withHeaders([
                    'Referer' => rtrim($baseUrl, '/').'/Home/Dashboard',
                    'Origin' => rtrim($baseUrl, '/'),
                ])
                ->asForm()
                ->post($url, [
                    'highLevelReportRequest' => [
                        'DistributorCompanySrlNo' => $distributorId,
                        'StartDate' => $startDate,
                        'EndDate' => $endDate,
                    ],
                ]),
            $timeout,
        );

        if (! $response->successful()) {
            throw new \RuntimeException('PayGro report request failed: HTTP '.$response->status());
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new \RuntimeException('PayGro returned a non-JSON response.');
        }

        if (! ($data['IsSuccess'] ?? false)) {
            $message = $data['Message'] ?? 'Unknown PayGro error';

            throw new \RuntimeException('PayGro report failed: '.$message);
        }

        $reportData = $data['ReportData'] ?? [];

        return is_array($reportData) ? $reportData : [];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function reportDateRange(?string $startOverride = null, ?string $endOverride = null): array
    {
        if ($startOverride && $endOverride) {
            return [$startOverride, $endOverride];
        }

        $start = $this->configValue('report_start_date');
        $end = $this->configValue('report_end_date');

        if ($start && $end) {
            return [(string) $start, (string) $end];
        }

        return [$this->resolveReportStartDate(), $this->resolveReportEndDate()];
    }

    protected function resolveReportStartDate(): string
    {
        $startDays = (int) config('paygro.report_start_days', 60);

        return now()->subDays($startDays)->toDateString();
    }

    protected function resolveReportEndDate(): string
    {
        $endDays = (int) config('paygro.report_end_days', 30);

        return now()->addDays($endDays)->toDateString();
    }

    protected function getSessionCookieHeader(): ?string
    {
        $paygro = $this->configValue('paygro_cookie');
        $aspnet = $this->configValue('aspnet_cookie');

        if (! $paygro || ! $aspnet) {
            return null;
        }

        return 'Paygro='.$paygro.'; .AspNetCore.Cookies='.$aspnet;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchFromLegacyApi(string $apiUrl, string $apiKey): array
    {
        $response = Http::withToken($apiKey)
            ->timeout(30)
            ->get(rtrim($apiUrl, '/').'/customers');

        if (! $response->successful()) {
            throw new \RuntimeException('PayGro API request failed: '.$response->status());
        }

        $data = $response->json();

        return is_array($data['data'] ?? null) ? $data['data'] : (is_array($data) ? $data : []);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function isPayGroReportRecord(array $record): bool
    {
        return isset($record['SrlNo']) || isset($record['CustomerName']);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function mapReportRecord(array $record): array
    {
        [$firstName, $lastName] = $this->splitCustomerName((string) ($record['CustomerName'] ?? ''));
        $phone = $this->normalizePayGroPhone(
            $record['PrimaryMobileNumber'] ?? null,
            $record['MobileNumberWithCountryCode'] ?? null,
        );

        $hasDuePassed = (int) ($record['HasNextPaymentDueDatePassed'] ?? 0) === 1;
        $nextDue = $record['NextPaymentDueDate'] ?? null;

        $paymentStatus = $hasDuePassed ? 'overdue' : 'current';
        $lifecycleStage = $hasDuePassed ? 'at_risk' : 'active';

        $location = trim((string) ($record['ConsolidatedAddress'] ?? ''));
        if ($location === '') {
            $location = trim(implode(', ', array_filter([
                $record['StateName'] ?? null,
                $record['CountryName'] ?? null,
            ])));
        }

        $srlNo = $record['SrlNo'] ?? null;

        return [
            'account_number' => $srlNo ? 'PG-'.$srlNo : null,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'email' => $record['PrimaryEmailAddress'] ?? null,
            'product_type' => $record['DistributorCompanyName'] ?? null,
            'location' => $location ?: null,
            'payment_status' => $paymentStatus,
            'next_payment_date' => $nextDue,
            'outstanding_balance' => $record['CreditBalance'] ?? null,
            'lifecycle_stage' => $lifecycleStage,
            'activated_at' => $record['CreatedOn'] ?? null,
            'meta' => [
                'paygro_srl_no' => $srlNo,
                'gender' => $record['Gender'] ?? null,
                'approval_status' => $record['ApprovalStatus'] ?? null,
                'record_status' => $record['RecordStatus'] ?? null,
                'is_kyc_verified' => $record['IsKYCVerified'] ?? null,
                'agent_srl_no' => $record['AgentSrlNo'] ?? null,
                'agent_name' => $record['AgentName'] ?? null,
                'national_id' => $record['NationalID'] ?? null,
                'distributor_company_srl_no' => $record['DistributorCompanySrlNo'] ?? null,
                'last_credit_balance_update_date' => $record['LastCreditBalanceUpdateDate'] ?? null,
                'modified_on' => $record['ModifiedOn'] ?? null,
                'pin_code' => $record['PinCode'] ?? null,
                'district_name' => $record['DistrictName'] ?? null,
                'city_name' => $record['CityName'] ?? null,
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    protected function splitCustomerName(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));

        if ($name === '') {
            return ['Unknown', null];
        }

        $parts = explode(' ', $name, 2);

        return [$parts[0], $parts[1] ?? null];
    }

    protected function normalizePayGroPhone(?string $primary, ?string $withCountry): string
    {
        $phone = trim((string) ($withCountry ?: $primary ?: ''));

        if ($phone === '') {
            return '';
        }

        $phone = $this->normalizePhone($phone);

        if (preg_match('/^\+2540(\d+)$/', $phone, $matches)) {
            return '+254'.$matches[1];
        }

        return $phone;
    }

    protected function configValue(string $key, mixed $default = null): mixed
    {
        $settingMap = [
            'base_url' => self::SETTING_BASE_URL,
            'distributor_company_srl_no' => self::SETTING_DISTRIBUTOR_ID,
            'report_start_date' => self::SETTING_START_DATE,
            'report_end_date' => self::SETTING_END_DATE,
            'paygro_cookie' => self::SETTING_PAYGRO_COOKIE,
            'aspnet_cookie' => self::SETTING_ASPNET_COOKIE,
            'api_url' => self::SETTING_API_URL,
            'api_key' => self::SETTING_API_KEY,
        ];

        if (isset($settingMap[$key])) {
            $fromSetting = Setting::get($settingMap[$key]);

            if ($fromSetting !== null && $fromSetting !== '') {
                return $fromSetting;
            }
        }

        return config('paygro.'.$key, $default);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function mockCustomers(): array
    {
        return [
            [
                'account_number' => 'PG-10001',
                'first_name' => 'Jane',
                'last_name' => 'Mwangi',
                'phone' => '0712345678',
                'email' => 'jane@example.com',
                'product_type' => 'Solar Home',
                'location' => 'Nairobi',
                'payment_status' => 'current',
                'next_payment_date' => now()->addDays(7)->toDateString(),
                'outstanding_balance' => 15000.00,
                'lifecycle_stage' => 'active',
                'activated_at' => now()->subMonths(3)->toIso8601String(),
            ],
            [
                'account_number' => 'PG-10002',
                'first_name' => 'Peter',
                'last_name' => 'Ochieng',
                'phone' => '0723456789',
                'email' => 'peter@example.com',
                'product_type' => 'Solar Home',
                'location' => 'Kisumu',
                'payment_status' => 'overdue',
                'next_payment_date' => now()->subDays(5)->toDateString(),
                'outstanding_balance' => 8500.00,
                'lifecycle_stage' => 'at_risk',
                'activated_at' => now()->subMonths(6)->toIso8601String(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function upsertCustomer(array $record): Customer
    {
        $accountNumber = $record['account_number'] ?? null;

        $attributes = [
            'first_name' => $record['first_name'] ?? 'Unknown',
            'last_name' => $record['last_name'] ?? null,
            'phone' => $record['phone'] ?? '',
            'email' => $record['email'] ?? null,
            'product_type' => $record['product_type'] ?? null,
            'location' => $record['location'] ?? null,
            'payment_status' => $record['payment_status'] ?? 'current',
            'next_payment_date' => $record['next_payment_date'] ?? null,
            'outstanding_balance' => $record['outstanding_balance'] ?? null,
            'lifecycle_stage' => $record['lifecycle_stage'] ?? 'new',
            'activated_at' => $record['activated_at'] ?? null,
            'meta' => $record['meta'] ?? null,
        ];

        if ($accountNumber) {
            return Customer::updateOrCreate(
                ['account_number' => $accountNumber],
                $attributes,
            );
        }

        return Customer::updateOrCreate(
            ['phone' => $attributes['phone']],
            $attributes,
        );
    }
}
