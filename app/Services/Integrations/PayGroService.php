<?php

namespace App\Services\Integrations;

use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\CustomerPayment;
use App\Models\PaygroPaymentPlan;
use App\Models\PaygroSyncLog;
use App\Models\RepaymentSchedule;
use App\Models\Setting;
use App\Models\TokenTransaction;
use App\Traits\NormalizesPhoneNumbers;
use Carbon\Carbon;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

    public const SETTING_SESSION_ID = 'paygro_session_id';

    public const SETTING_ASPNET_COOKIE = 'paygro_aspnet_cookie';

    public const SETTING_SESSION_REFRESHED_AT = 'paygro_session_refreshed_at';

    public const SETTING_FIRST_SYNC_COMPLETED = 'paygro_first_sync_completed';

    public const SETTING_LAST_PAYMENT_SYNC_AT = 'paygro_last_payment_sync_at';

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
                    $this->upsertCustomer($mapped, $record);
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
     * Sync PayGro model-wise payment plan master data (UnlockPrice, daily payment, etc.).
     *
     * @return array{processed: int, failed: int, status: string}
     */
    public function syncPaymentPlans(string $syncSource = 'manual'): array
    {
        $startedAt = microtime(true);
        $sessionRefreshed = false;

        $log = PaygroSyncLog::create([
            'sync_type' => 'payment_plans',
            'status' => 'running',
            'source' => $syncSource,
            'started_at' => now(),
        ]);

        try {
            if (! Schema::hasTable('paygro_payment_plans')) {
                throw new \RuntimeException('Run database migrations before syncing payment plans.');
            }

            $sessionRefreshed = $this->ensureAuthenticated();

            $processed = 0;
            $failed = 0;
            $models = $this->resolveProductModelsToSync();

            foreach ($models as $model) {
                try {
                    $plans = $this->fetchProductPaymentPlanListForModel($model);

                    foreach ($plans as $row) {
                        $this->upsertPaygroPaymentPlanFromApiRow($row);
                        $processed++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('PayGro payment plan sync failed for model', [
                        'model' => $model,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $log->update([
                'status' => $failed > 0 && $processed === 0 ? 'failed' : 'completed',
                'session_refreshed' => $sessionRefreshed,
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'records_processed' => $processed,
                'records_failed' => $failed,
                'payload' => [
                    'models' => $models,
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
     * @return array<int, string>
     */
    public function configuredProductModels(): array
    {
        $models = config('paygro.product_models', []);

        return array_values(array_filter(array_map(
            fn ($model) => trim((string) $model),
            is_array($models) ? $models : [],
        )));
    }

    /**
     * Models to pull payment plans for: the configured list PLUS the models the
     * customers' units actually use (from the live product-sale report and any
     * already-synced assets). This keeps unlock prices working even when the
     * configured PAYGRO_PRODUCT_MODELS is empty, stale, or names the models
     * differently than PayGro does.
     *
     * @return array<int, string>
     */
    public function resolveProductModelsToSync(): array
    {
        $models = collect($this->configuredProductModels());

        try {
            foreach ($this->fetchAllProductSaleRecords() as $record) {
                $model = trim((string) $this->payGroNestedName($record['ProductModel'] ?? null));

                if ($model !== '') {
                    $models->push($model);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Could not derive product models from sale report', [
                'error' => $e->getMessage(),
            ]);
        }

        CustomerAsset::query()
            ->whereNotNull('model')
            ->where('model', '!=', '')
            ->distinct()
            ->pluck('model')
            ->each(fn ($model) => $models->push(trim((string) $model)));

        return $models
            ->map(fn ($model) => trim((string) $model))
            ->filter()
            ->unique(fn ($model) => strtolower($model))
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     outstanding_balance: float,
     *     total_paid: float,
     *     total_unlock_price: float,
     *     has_plan_balances: bool,
     *     units: array<int, array<string, mixed>>
     * }
     */
    public function buildCustomerFinancialOverview(Customer $customer): array
    {
        $customer->loadMissing('assets');

        $totalOutstanding = 0.0;
        $totalPaid = 0.0;
        $totalUnlock = 0.0;
        $hasPlanBalances = false;
        $units = [];

        foreach ($customer->assets as $asset) {
            $meta = is_array($asset->meta) ? $asset->meta : [];
            $salesId = trim((string) ($meta['paygro_sales_identifier'] ?? ''));
            $planName = trim((string) ($meta['paygro_payment_plan'] ?? ''));
            $unlockPrice = (float) ($meta['paygro_unlock_price'] ?? 0);
            $dailyPayment = (float) ($meta['paygro_daily_payment'] ?? 0);
            $creditDaysDownPayment = (int) ($meta['paygro_credit_days_down_payment'] ?? 0);
            $paid = $this->sumPayGroPaymentsForAsset($customer, $asset, $salesId);

            if ($unlockPrice <= 0 && $planName !== '') {
                $plan = $this->findPaygroPaymentPlan($asset->model, $planName);

                if ($plan) {
                    $unlockPrice = (float) $plan->unlock_price;
                    $dailyPayment = (float) $plan->credit_packet_price;
                    $creditDaysDownPayment = (int) $plan->credit_days_down_payment;
                    $planName = $plan->plan_name;
                }
            }

            if ($unlockPrice > 0) {
                $hasPlanBalances = true;
                $balance = max(0, $unlockPrice - $paid);
            } else {
                $balance = max(0, (float) ($meta['paygro_outstanding_balance'] ?? 0));
            }

            $totalOutstanding += $balance;
            $totalPaid += $paid;
            $totalUnlock += $unlockPrice;

            $units[] = [
                'serial' => $asset->unit_serial,
                'plan_name' => $planName !== '' ? $planName : null,
                'model' => $asset->model,
                'unlock_price' => $unlockPrice,
                'paid' => $paid,
                'balance' => $balance,
                'daily_payment' => $dailyPayment,
                'credit_days_down_payment' => $creditDaysDownPayment,
            ];
        }

        if (! $hasPlanBalances) {
            $totalOutstanding = (float) ($customer->outstanding_balance ?? 0);
            $totalPaid = (float) $customer->payments()->sum('amount');
        }

        return [
            'outstanding_balance' => $totalOutstanding,
            'total_paid' => $totalPaid,
            'total_unlock_price' => $totalUnlock,
            'has_plan_balances' => $hasPlanBalances,
            'units' => $units,
        ];
    }

    public function refreshCustomerFinancialsFromPlans(Customer $customer): void
    {
        $this->refreshCustomerStatusesFromPayGro($customer);
    }

    /**
     * Recompute financial balances and customer/unit status tags from synced
     * PayGro payment plans, payments, and credit-day balance.
     */
    public function refreshCustomerStatusesFromPayGro(Customer $customer): void
    {
        $customer->loadMissing('assets');

        foreach ($customer->assets as $asset) {
            $this->attachPaymentPlanMetadataToAsset($asset);
        }

        $customer->refresh();
        $customer->load('assets');

        $overview = $this->buildCustomerFinancialOverview($customer);

        foreach ($customer->assets as $asset) {
            $meta = is_array($asset->meta) ? $asset->meta : [];
            $unitBalance = (float) ($meta['paygro_outstanding_balance'] ?? 0);
            $creditDays = (int) ($meta['paygro_credit_days_down_payment'] ?? 0);

            $repaymentStatus = $this->resolveUnitRepaymentStatus(
                $customer,
                [
                    'DaysSinceLastPayment' => (int) ($meta['paygro_days_since_last_payment'] ?? 0),
                    'PaymentCreditType' => $meta['paygro_payment_credit_type'] ?? '',
                ],
                $unitBalance,
                $creditDays,
                $asset,
            );

            if (($meta['paygro_repayment_status'] ?? null) !== $repaymentStatus) {
                $asset->update([
                    'meta' => array_merge($meta, ['paygro_repayment_status' => $repaymentStatus]),
                ]);
            }
        }

        $customer->refresh();
        $customer->load('assets');

        $paymentStatus = $this->resolveCustomerPaymentStatus($customer, $overview);
        $hasHirePurchase = $this->customerHasHirePurchaseUnit($customer);
        $daysInArrears = $this->computeDaysInArrears($customer);
        $customerMeta = is_array($customer->meta) ? $customer->meta : [];
        $updates = [
            'payment_status' => $paymentStatus,
            'lifecycle_stage' => $this->resolveCustomerLifecycleStage($customer, $paymentStatus),
            'account_status' => $this->resolveCustomerAccountStatus($customer, $paymentStatus),
            'meta' => array_merge($customerMeta, [
                'paygro_has_hire_purchase' => $hasHirePurchase,
                'paygro_days_in_arrears' => $daysInArrears,
            ]),
        ];

        if ($overview['has_plan_balances']) {
            $updates['outstanding_balance'] = $overview['outstanding_balance'];
        }

        $customer->update($updates);
    }

    /**
     * @param  array{
     *     outstanding_balance: float,
     *     total_paid: float,
     *     total_unlock_price: float,
     *     has_plan_balances: bool,
     *     units: array<int, array<string, mixed>>
     * }  $overview
     */
    protected function resolveCustomerPaymentStatus(Customer $customer, array $overview): string
    {
        $tokenBalance = (int) ($customer->token_balance ?? 0);
        $balance = (float) $overview['outstanding_balance'];

        if ($overview['has_plan_balances'] && $balance <= 0.01) {
            return 'paid_off';
        }

        if (! $this->customerHasHirePurchaseUnit($customer)) {
            if ($tokenBalance > 3) {
                return 'current';
            }

            if ($tokenBalance > 0) {
                return 'due_soon';
            }

            return 'due_soon';
        }

        if ($tokenBalance > 3) {
            return 'current';
        }

        if ($tokenBalance > 0) {
            return $balance > 0.01 ? 'due_soon' : 'current';
        }

        if ($balance <= 0.01) {
            return 'current';
        }

        $maxDaysSincePayment = $this->maxDaysSinceLastPaymentForCustomer($customer);
        $graceDays = max(3, $this->maxPlanCreditDaysForCustomer($customer));

        if ($maxDaysSincePayment > $graceDays) {
            return 'overdue';
        }

        if ($customer->next_payment_date?->isPast()) {
            return 'overdue';
        }

        return 'due_soon';
    }

    protected function isHirePurchaseCreditType(?string $creditType): bool
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', (string) $creditType)));

        if ($normalized === '') {
            return false;
        }

        foreach (config('paygro.hire_purchase_credit_types', ['Hire Purchase']) as $configured) {
            $configured = strtolower(trim((string) $configured));

            if ($configured !== '' && str_contains($normalized, $configured)) {
                return true;
            }
        }

        return false;
    }

    protected function assetIsHirePurchase(CustomerAsset $asset): bool
    {
        $meta = is_array($asset->meta) ? $asset->meta : [];
        $creditType = (string) ($meta['paygro_payment_credit_type'] ?? '');

        if ($this->isHirePurchaseCreditType($creditType)) {
            return true;
        }

        $planCreditType = (string) ($meta['paygro_plan_credit_type'] ?? '');

        return $this->isHirePurchaseCreditType($planCreditType);
    }

    protected function customerHasHirePurchaseUnit(Customer $customer): bool
    {
        $customer->loadMissing('assets');

        foreach ($customer->assets as $asset) {
            if ($this->assetIsHirePurchase($asset)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Days in arrears when the customer has no token credit remaining.
     * Hire Purchase: days beyond the plan grace period or past next due date.
     * Daily PAYGO: days since the last recorded payment.
     */
    public function computeDaysInArrears(Customer $customer): int
    {
        $customer->loadMissing('assets');

        $tokenBalance = (int) ($customer->token_balance ?? 0);

        if ($tokenBalance > 0) {
            return 0;
        }

        $owingAssets = $customer->assets->reject(fn (CustomerAsset $asset) => $asset->isPaidOff());

        if ($owingAssets->isEmpty()) {
            return 0;
        }

        if ($owingAssets->contains(fn (CustomerAsset $asset) => $this->assetIsHirePurchase($asset))) {
            $fromDueDate = 0;

            if (
                (float) ($customer->outstanding_balance ?? 0) > 0.01
                && $customer->next_payment_date?->isPast()
            ) {
                $fromDueDate = (int) $customer->next_payment_date->startOfDay()->diffInDays(now()->startOfDay());
            }

            $fromLastPayment = 0;

            foreach ($owingAssets as $asset) {
                if (! $this->assetIsHirePurchase($asset)) {
                    continue;
                }

                $meta = is_array($asset->meta) ? $asset->meta : [];
                $daysSince = (int) ($meta['paygro_days_since_last_payment'] ?? 0);
                $grace = max(3, (int) ($meta['paygro_credit_days_down_payment'] ?? 0));
                $fromLastPayment = max($fromLastPayment, max(0, $daysSince - $grace));
            }

            return max($fromDueDate, $fromLastPayment);
        }

        return $this->maxDaysSinceLastPaymentForCustomer($customer);
    }

    protected function resolveCustomerLifecycleStage(Customer $customer, string $paymentStatus): string
    {
        return match ($paymentStatus) {
            'overdue' => 'at_risk',
            'paid_off' => $customer->lifecycle_stage === 'loyal' ? 'loyal' : 'active',
            default => in_array($customer->lifecycle_stage, ['loyal', 'inactive'], true)
                ? $customer->lifecycle_stage
                : 'active',
        };
    }

    protected function resolveCustomerAccountStatus(Customer $customer, ?string $paymentStatus = null): string
    {
        $customer->loadMissing('assets');

        $statuses = $customer->assets
            ->map(fn (CustomerAsset $asset) => (string) (is_array($asset->meta) ? ($asset->meta['paygro_repayment_status'] ?? '') : ''))
            ->filter()
            ->values();

        if ($statuses->isEmpty()) {
            $paymentStatus ??= $customer->payment_status;

            return match ($paymentStatus) {
                'paid_off' => 'paid_off',
                'overdue' => 'defaulting',
                default => 'active',
            };
        }

        return match (true) {
            $statuses->every(fn (string $status) => $status === 'paid_off') => 'paid_off',
            $statuses->contains('defaulting') => 'defaulting',
            default => 'active',
        };
    }

    protected function maxDaysSinceLastPaymentForCustomer(Customer $customer): int
    {
        $fromAssetMeta = 0;

        foreach ($customer->assets as $asset) {
            if ($asset->isPaidOff()) {
                continue;
            }

            $meta = is_array($asset->meta) ? $asset->meta : [];
            $fromAssetMeta = max($fromAssetMeta, (int) ($meta['paygro_days_since_last_payment'] ?? 0));

            $lastPaymentDate = $this->parsePayGroDate($meta['paygro_last_payment_date'] ?? null);

            if ($lastPaymentDate?->isPast()) {
                $fromAssetMeta = max(
                    $fromAssetMeta,
                    (int) $lastPaymentDate->startOfDay()->diffInDays(now()->startOfDay()),
                );
            }
        }

        if ($customer->payments()->where('source', 'paygro')->exists()) {
            return max($fromAssetMeta, $this->daysSinceLastPaymentFromRecords($customer));
        }

        return $fromAssetMeta;
    }

    protected function daysSinceLastPaymentFromRecords(Customer $customer): int
    {
        $lastPaidAt = $customer->payments()
            ->where('source', 'paygro')
            ->max('paid_at');

        if (! $lastPaidAt) {
            return 0;
        }

        return (int) \Illuminate\Support\Carbon::parse($lastPaidAt)->startOfDay()->diffInDays(now()->startOfDay());
    }

    protected function maxPlanCreditDaysForCustomer(Customer $customer): int
    {
        $max = 0;

        foreach ($customer->assets as $asset) {
            $meta = is_array($asset->meta) ? $asset->meta : [];
            $max = max($max, (int) ($meta['paygro_credit_days_down_payment'] ?? 0));
        }

        return $max;
    }

    /**
     * Sync PayGro unit serials onto customer assets using the product sale
     * register (ProductSerialNumber + CustomerName + payment metadata).
     *
     * @return array{processed: int, skipped: int, failed: int, status: string}
     */
    public function syncUnits(
        ?string $startDate = null,
        ?string $endDate = null,
        string $syncSource = 'manual',
    ): array {
        $startedAt = microtime(true);
        $sessionRefreshed = false;

        $log = PaygroSyncLog::create([
            'sync_type' => 'units',
            'status' => 'running',
            'source' => $syncSource,
            'started_at' => now(),
        ]);

        try {
            $sessionRefreshed = $this->ensureAuthenticated();

            $saleRecords = $this->fetchAllProductSaleRecords($startDate, $endDate);

            $processed = 0;
            $skipped = 0;
            $failed = 0;

            foreach ($saleRecords as $record) {
                try {
                    $normalized = $this->normalizeProductSaleRecord($record);

                    if (! $this->productSaleRecordIsCustomerAssigned($normalized)) {
                        continue;
                    }

                    $customer = $this->findCustomerForPayGroName((string) ($normalized['CustomerName'] ?? ''));

                    if (! $customer) {
                        $skipped++;

                        continue;
                    }

                    $this->syncCustomerAssetFromPayGro($customer, $normalized);
                    $processed++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('PayGro unit upsert failed', [
                        'record' => $record,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $reconciled = $this->reconcilePayGroAssetOwnership($saleRecords);

            $log->update([
                'status' => $failed > 0 && $processed === 0 ? 'failed' : 'completed',
                'session_refreshed' => $sessionRefreshed,
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'records_processed' => $processed,
                'records_failed' => $failed,
                'payload' => [
                    'source' => 'product_sale',
                    'sale_rows' => count($saleRecords),
                    'skipped_unmatched' => $skipped,
                    'assets_reconciled' => $reconciled['corrected'],
                    'assets_removed' => $reconciled['removed'],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'completed_at' => now(),
            ]);

            return [
                'processed' => $processed,
                'skipped' => $skipped,
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
     * Sync installment payments from PayGro's transaction report.
     *
     * @return array{processed: int, skipped: int, failed: int, status: string}
     */
    public function syncPayments(
        ?string $startDate = null,
        ?string $endDate = null,
        string $syncSource = 'manual',
    ): array {
        $startedAt = microtime(true);
        $sessionRefreshed = false;

        $log = PaygroSyncLog::create([
            'sync_type' => 'payments',
            'status' => 'running',
            'source' => $syncSource,
            'started_at' => now(),
        ]);

        try {
            $sessionRefreshed = $this->ensureAuthenticated();
            $this->ensureCustomerPaymentsPayGroReady();

            $baseUrl = (string) ($this->configValue('base_url') ?? config('paygro.base_url'));

            // Smart window: once payments exist, only re-fetch transactions newer
            // than the watermark (with a small overlap) instead of years of data.
            [$rangeStart, $rangeEnd, $syncMode] = $this->resolvePaymentSyncWindow($startDate, $endDate);
            $records = $this->fetchFromTransactionsReport($baseUrl, $rangeStart, $rangeEnd);

            // Resolve payment owners against an in-memory index instead of a DB
            // query per transaction row — the main cost on a full payment pull.
            $resolveCustomer = $this->customerNameResolver();

            $processed = 0;
            $skipped = 0;
            $failed = 0;
            $touchedCustomers = [];

            foreach ($records as $record) {
                try {
                    $reference = trim((string) ($record['PaymentReferenceNumber'] ?? ''));

                    if ($reference === '') {
                        $skipped++;

                        continue;
                    }

                    $customer = $resolveCustomer((string) ($record['CustomerName'] ?? ''));

                    if (! $customer) {
                        $skipped++;

                        continue;
                    }

                    $this->upsertCustomerPaymentFromPayGro($customer, $record);
                    $touchedCustomers[$customer->id] = $customer;
                    $processed++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('PayGro payment upsert failed', [
                        'record' => $record,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            foreach ($touchedCustomers as $customer) {
                try {
                    $this->refreshCustomerStatusesFromPayGro($customer);
                } catch (\Throwable $e) {
                    Log::warning('PayGro customer status refresh failed after payment sync', [
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $status = $failed > 0 && $processed === 0 ? 'failed' : 'completed';

            if ($status !== 'failed') {
                $this->advancePaymentSyncWatermark($startDate, $endDate);
            }

            $log->update([
                'status' => $status,
                'session_refreshed' => $sessionRefreshed,
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'records_processed' => $processed,
                'records_failed' => $failed,
                'payload' => [
                    'source' => 'transactions_report',
                    'sync_mode' => $syncMode,
                    'report_rows' => count($records),
                    'skipped_unmatched' => $skipped,
                    'customers_updated' => count($touchedCustomers),
                    'start_date' => $this->formatPayGroReportDate($rangeStart),
                    'end_date' => $this->formatPayGroReportDate($rangeEnd),
                ],
                'completed_at' => now(),
            ]);

            return [
                'processed' => $processed,
                'skipped' => $skipped,
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
     * Map PayGro unit allocations to customer assets and build repayment
     * schedule rows from synced payment history. Updates account_status so
     * active vs fully-paid customers are visible after sync.
     *
     * @return array{processed: int, skipped: int, failed: int, status: string}
     */
    public function syncRepaymentSchedules(
        ?string $startDate = null,
        ?string $endDate = null,
        string $syncSource = 'manual',
    ): array {
        $startedAt = microtime(true);
        $sessionRefreshed = false;

        $log = PaygroSyncLog::create([
            'sync_type' => 'repayment_schedules',
            'status' => 'running',
            'source' => $syncSource,
            'started_at' => now(),
        ]);

        try {
            $sessionRefreshed = $this->ensureAuthenticated();

            $saleRecords = $this->fetchAllProductSaleRecords($startDate, $endDate);
            $salesByIdentifier = $this->indexProductSaleRecordsBySalesIdentifier($saleRecords);
            $paymentPlanIndex = $this->loadPaymentPlanIndexFromDatabase();

            $processed = 0;
            $skipped = 0;
            $failed = 0;
            $touchedCustomers = [];

            foreach ($salesByIdentifier as $record) {
                try {
                    $customer = $this->findCustomerForPayGroName((string) ($record['CustomerName'] ?? ''));

                    if (! $customer) {
                        $skipped++;

                        continue;
                    }

                    $this->syncCustomerAssetFromPayGro($customer, $record);

                    $asset = $this->findCustomerAssetForPayGroRecord($customer, $record);

                    if (! $asset) {
                        $skipped++;

                        continue;
                    }

                    $this->syncRepaymentScheduleForAsset($customer, $asset, $record, $paymentPlanIndex);
                    $touchedCustomers[$customer->id] = $customer;
                    $processed++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::warning('PayGro repayment schedule sync failed', [
                        'record' => $record,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            foreach ($touchedCustomers as $customer) {
                $this->refreshCustomerStatusesFromPayGro($customer);
            }

            $reconciled = $this->reconcilePayGroAssetOwnership($saleRecords);

            [$rangeStart, $rangeEnd] = $this->unitReportDateRange($startDate, $endDate);

            $log->update([
                'status' => $failed > 0 && $processed === 0 ? 'failed' : 'completed',
                'session_refreshed' => $sessionRefreshed,
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'records_processed' => $processed,
                'records_failed' => $failed,
                'payload' => [
                    'source' => 'product_sale_by_sales_identifier',
                    'sale_rows' => count($saleRecords),
                    'unique_sales' => count($salesByIdentifier),
                    'payment_plan_rows' => Schema::hasTable('paygro_payment_plans')
                        ? PaygroPaymentPlan::query()->count()
                        : 0,
                    'skipped_unmatched' => $skipped,
                    'customers_updated' => count($touchedCustomers),
                    'assets_reconciled' => $reconciled['corrected'],
                    'assets_removed' => $reconciled['removed'],
                    'start_date' => $rangeStart,
                    'end_date' => $rangeEnd,
                ],
                'completed_at' => now(),
            ]);

            return [
                'processed' => $processed,
                'skipped' => $skipped,
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
     * Sync units for one customer using PayGro's customer-name search filter.
     *
     * @return array{processed: int}
     */
    public function syncUnitsForCustomer(Customer $customer): array
    {
        $this->ensureAuthenticated();

        $searchName = trim($customer->first_name.' '.($customer->last_name ?? ''));
        $records = $this->fetchProductSaleRecords(searchText: $searchName);
        $processed = 0;

        foreach ($records as $record) {
            $normalized = $this->normalizeProductSaleRecord($record);

            if (! $this->productSaleRecordIsCustomerAssigned($normalized)) {
                continue;
            }

            $matchedCustomer = $this->findCustomerForPayGroName((string) ($normalized['CustomerName'] ?? ''));

            if (! $matchedCustomer || $matchedCustomer->id !== $customer->id) {
                continue;
            }

            $this->syncCustomerAssetFromPayGro($customer, $normalized);
            $processed++;
        }

        return ['processed' => $processed];
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
     * Wipe all PayGro-synced data so the next sync rebuilds everything from
     * scratch. Customer accounts are preserved (they carry agent assignments,
     * notes, and SMS history, and are re-matched by name) — only the synced
     * assets, payments, repayment schedules, token transactions, and the
     * incremental watermark are cleared. Use to recover from a broken sync.
     *
     * @return array<string, int>
     */
    public function resetSyncedData(): array
    {
        $cleared = [];

        if ($this->repaymentSchedulesPayGroReady()) {
            $cleared['repayment_schedules'] = RepaymentSchedule::query()->delete();
        }

        if ($this->customerPaymentsPayGroReady()) {
            $cleared['payments'] = CustomerPayment::query()->where('source', 'paygro')->delete();
        }

        if ($this->tokenTransactionsPayGroReady()) {
            $cleared['token_transactions'] = TokenTransaction::query()
                ->where('source', 'like', 'paygro%')
                ->delete();
        }

        $cleared['assets'] = CustomerAsset::query()
            ->where(function ($query) {
                $query->whereNotNull('meta->paygro_sales_identifier')
                    ->orWhereNotNull('meta->paygro_sync_source');
            })
            ->delete();

        $cleared['customers_reset'] = $this->resetCustomerDerivedFinancials();

        Setting::forget(self::SETTING_LAST_PAYMENT_SYNC_AT);
        Setting::forget(self::SETTING_FIRST_SYNC_COMPLETED);

        Log::info('PayGro synced data reset', $cleared);

        return $cleared;
    }

    /**
     * Zero out the sync-derived financial fields that live on the customer row
     * (outstanding balance, token balance, statuses) and strip the computed
     * PayGro meta keys, so a customer reads as a clean slate after a reset
     * until the next sync recomputes them. Identity meta (paygro_srl_no, serial)
     * is kept so the customer can be re-matched.
     */
    protected function resetCustomerDerivedFinancials(): int
    {
        $derivedMetaKeys = [
            'paygro_has_hire_purchase',
            'paygro_days_in_arrears',
            'paygro_outstanding_balance',
            'paygro_credit_balance',
            'paygro_has_next_payment_due_passed',
        ];

        $count = 0;

        Customer::query()
            ->select('id', 'meta')
            ->chunkById(500, function ($customers) use ($derivedMetaKeys, &$count) {
                foreach ($customers as $customer) {
                    $meta = is_array($customer->meta) ? $customer->meta : [];

                    foreach ($derivedMetaKeys as $key) {
                        unset($meta[$key]);
                    }

                    Customer::query()->whereKey($customer->id)->update([
                        'outstanding_balance' => 0,
                        'token_balance' => 0,
                        'payment_status' => 'current',
                        'account_status' => 'active',
                        'next_payment_date' => null,
                        'meta' => $meta,
                    ]);

                    $count++;
                }
            });

        return $count;
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
     * Fetch the newest token for a customer — payment SMS codes and free-token
     * history are both considered; the most recent wins.
     *
     * @return array<string, mixed>|null
     */
    public function fetchLatestTokenForCustomer(
        Customer $customer,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): ?array {
        $freeToken = $this->fetchLatestFreeTokenForCustomer($customer, $fromDate, $toDate);
        $paymentToken = $this->fetchLatestPaymentTokenForCustomer($customer, $fromDate, $toDate);

        if ($freeToken && $paymentToken) {
            $freeAt = Carbon::parse((string) $freeToken['token_generation_date']);
            $paymentAt = Carbon::parse((string) $paymentToken['token_generation_date']);

            return $paymentAt->greaterThanOrEqualTo($freeAt) ? $paymentToken : $freeToken;
        }

        return $paymentToken ?? $freeToken;
    }

    /**
     * Fetch the newest payment SMS code (SmsCodeForPayment) for the customer's
     * unit serials from local payment records and PayGro's transaction report.
     *
     * @return array<string, mixed>|null
     */
    public function fetchLatestPaymentTokenForCustomer(
        Customer $customer,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): ?array {
        $serials = $this->customerPayGroSerials($customer);

        if ($serials->isEmpty()) {
            return null;
        }

        $latest = $this->pickNewestPaymentTokenCandidate(
            $this->findLatestPaymentTokenFromLocal($customer, $serials),
            $this->findLatestPaymentTokenFromPayGro($customer, $serials, $fromDate, $toDate),
        );

        if (! $latest && $fromDate === null && $toDate === null) {
            $fallbackDays = (int) config('paygro.token_history_fallback_start_days', 730);
            $defaultDays = (int) config('paygro.token_history_start_days', 365);

            if ($fallbackDays > $defaultDays) {
                $latest = $this->findLatestPaymentTokenFromPayGro(
                    $customer,
                    $serials,
                    now()->subDays($fallbackDays)->toDateString(),
                    now()->addDays((int) config('paygro.token_history_end_days', 0))->toDateString(),
                );
            }
        }

        if (! $latest) {
            return null;
        }

        /** @var Carbon $generatedAt */
        $generatedAt = $latest['generated_at'];

        return [
            'product_serial_number' => $latest['serial'],
            'generated_token_value' => $latest['sms_code'],
            'token_generation_date' => $generatedAt->toIso8601String(),
            'token_generation_date_display' => $generatedAt->format('M j, Y g:i A'),
            'token_type_name' => 'Payment',
            'token_source' => 'payment',
            'credit_quantity' => $latest['days_credited'] ?? null,
            'activation_duration' => $latest['days_credited'] ?? null,
            'token_tag' => null,
            'history_srl_no' => null,
            'payment_reference' => $latest['payment_reference'] ?? null,
            'customer_payment_id' => $latest['customer_payment_id'] ?? null,
            'matched_customer_account' => $customer->account_number,
            'matched_asset_serial' => $latest['serial'],
            'raw' => $latest['raw'] ?? null,
        ];
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
            throw new \RuntimeException(
                'No PayGro unit is linked to this customer. Open the Tokens tab again after PayGro sync, or run PayGro sync in Settings.'
            );
        }

        $latest = $this->findLatestPayGroTokenForSerials(
            $customer,
            $serials,
            $fromDate,
            $toDate,
        );

        if (! $latest && $fromDate === null && $toDate === null) {
            $fallbackDays = (int) config('paygro.token_history_fallback_start_days', 730);
            $defaultDays = (int) config('paygro.token_history_start_days', 365);

            if ($fallbackDays > $defaultDays) {
                $latest = $this->findLatestPayGroTokenForSerials(
                    $customer,
                    $serials,
                    now()->subDays($fallbackDays)->toDateString(),
                    now()->addDays((int) config('paygro.token_history_end_days', 0))->toDateString(),
                );
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
            'token_source' => 'free',
            'raw' => $row,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $serials
     * @return array{generated_at: Carbon, sms_code: string, serial: string, payment_reference: ?string, customer_payment_id: ?int, days_credited: ?int, raw: ?array}|null
     */
    protected function findLatestPaymentTokenFromLocal(Customer $customer, $serials): ?array
    {
        if (! $this->customerPaymentsPayGroReady()) {
            return null;
        }

        $serialKeys = $serials
            ->map(fn ($serial) => strtolower(trim((string) $serial)))
            ->filter()
            ->flip();

        $latest = null;

        $payments = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->where('source', 'paygro')
            ->whereNotNull('paid_at')
            ->orderByDesc('paid_at')
            ->get();

        foreach ($payments as $payment) {
            $meta = is_array($payment->meta) ? $payment->meta : [];
            $smsCode = trim((string) ($meta['sms_code_for_payment'] ?? ''));

            if ($smsCode === '') {
                continue;
            }

            $serial = trim((string) ($meta['product_serial_number'] ?? ''));

            if ($serial !== '' && ! $serialKeys->has(strtolower($serial))) {
                continue;
            }

            if ($serial === '') {
                $serial = (string) ($serials->first() ?? '');
            }

            /** @var Carbon $paidAt */
            $paidAt = $payment->paid_at;

            if (! $latest || $paidAt->greaterThan($latest['generated_at'])) {
                $latest = [
                    'generated_at' => $paidAt,
                    'sms_code' => $smsCode,
                    'serial' => $serial,
                    'payment_reference' => $payment->reference,
                    'customer_payment_id' => $payment->id,
                    'days_credited' => $payment->days_credited ?: null,
                    'raw' => $meta,
                ];
            }
        }

        return $latest;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $serials
     * @return array{generated_at: Carbon, sms_code: string, serial: string, payment_reference: ?string, customer_payment_id: ?int, days_credited: ?int, raw: ?array}|null
     */
    protected function findLatestPaymentTokenFromPayGro(
        Customer $customer,
        $serials,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): ?array {
        $this->ensureValidSessionForFetch();

        $baseUrl = rtrim((string) $this->configValue('base_url', 'https://app-main.pay-gro.com'), '/');
        [$from, $to] = $this->tokenHistoryDateRange($fromDate, $toDate);

        $records = $this->fetchFromTransactionsReport(
            $baseUrl,
            Carbon::createFromFormat('Y/m/d', $from)->toDateString(),
            Carbon::createFromFormat('Y/m/d', $to)->toDateString(),
        );

        $serialKeys = $serials
            ->map(fn ($serial) => strtolower(trim((string) $serial)))
            ->filter()
            ->flip();

        $latest = null;

        foreach ($records as $record) {
            $smsCode = trim((string) ($record['SmsCodeForPayment'] ?? ''));

            if ($smsCode === '') {
                continue;
            }

            $serial = trim((string) ($record['ProductSerialNumber'] ?? ''));

            if ($serial === '' || ! $serialKeys->has(strtolower($serial))) {
                continue;
            }

            $paidAt = $this->parsePayGroDateTime(
                (string) ($record['PaymentDate'] ?? $record['PaymentDateText'] ?? ''),
            );

            if (! $paidAt) {
                continue;
            }

            if (! $latest || $paidAt->greaterThan($latest['generated_at'])) {
                $latest = [
                    'generated_at' => $paidAt,
                    'sms_code' => $smsCode,
                    'serial' => $serial,
                    'payment_reference' => trim((string) ($record['PaymentReferenceNumber'] ?? '')) ?: null,
                    'customer_payment_id' => null,
                    'days_credited' => (int) ($record['PacketsPurchasedQuantity'] ?? 0) ?: null,
                    'raw' => $record,
                ];
            }
        }

        return $latest;
    }

    /**
     * @param  array{generated_at: Carbon, sms_code: string, serial: string, payment_reference: ?string, customer_payment_id: ?int, days_credited: ?int, raw: ?array}|null  $first
     * @param  array{generated_at: Carbon, sms_code: string, serial: string, payment_reference: ?string, customer_payment_id: ?int, days_credited: ?int, raw: ?array}|null  $second
     * @return array{generated_at: Carbon, sms_code: string, serial: string, payment_reference: ?string, customer_payment_id: ?int, days_credited: ?int, raw: ?array}|null
     */
    protected function pickNewestPaymentTokenCandidate(?array $first, ?array $second): ?array
    {
        if (! $first) {
            return $second;
        }

        if (! $second) {
            return $first;
        }

        return $second['generated_at']->greaterThan($first['generated_at']) ? $second : $first;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $serials
     * @return array{generated_at: Carbon, row: array<string, mixed>, matched_serial: string}|null
     */
    protected function findLatestPayGroTokenForSerials(
        Customer $customer,
        $serials,
        ?string $fromDate = null,
        ?string $toDate = null,
    ): ?array {
        [$from, $to] = $this->tokenHistoryDateRange($fromDate, $toDate);

        $baseUrl = rtrim((string) $this->configValue('base_url', 'https://app-main.pay-gro.com'), '/');
        $cookieHeader = $this->getSessionCookieHeader();
        $latest = null;

        foreach ($serials as $serial) {
            foreach ($this->fetchFreeTokenHistoryRows($baseUrl, $cookieHeader, $from, $to, $serial) as $row) {
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

        return $latest;
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
        $token = $this->fetchLatestTokenForCustomer($customer, $fromDate, $toDate);

        if (! $token) {
            return null;
        }

        if (! $this->tokenTransactionsPayGroReady()) {
            return array_merge($token, [
                'token_transaction_id' => null,
                'external_reference' => $this->payGroTokenExternalReference($token),
            ]);
        }

        $isPaymentToken = ($token['token_source'] ?? 'free') === 'payment';
        $generatedAt = Carbon::parse((string) $token['token_generation_date']);
        $externalReference = $this->payGroTokenExternalReference($token);
        $transaction = TokenTransaction::updateOrCreate(
            [
                'source' => $isPaymentToken ? 'paygro_payment_token' : 'paygro_free_token',
                'external_reference' => $externalReference,
            ],
            [
                'customer_id' => $customer->id,
                'customer_payment_id' => $token['customer_payment_id'] ?? null,
                'type' => 'credit',
                'tokens' => max(1, (int) ($token['credit_quantity'] ?? 1)),
                'days' => max(0, (int) ($token['activation_duration'] ?? 0)),
                'balance_after' => (int) ($customer->token_balance ?? 0),
                'token_value' => $token['generated_token_value'],
                'product_serial_number' => $token['product_serial_number'],
                'token_tag' => $token['token_tag'] ?? null,
                'description' => $isPaymentToken
                    ? 'Latest PayGro payment token for '.$token['product_serial_number']
                    : 'Latest PayGro free token for '.$token['product_serial_number'],
                'occurred_at' => $generatedAt,
                'meta' => [
                    'token_source' => $token['token_source'] ?? 'free',
                    'token_type_name' => $token['token_type_name'] ?? null,
                    'history_srl_no' => $token['history_srl_no'] ?? null,
                    'payment_reference' => $token['payment_reference'] ?? null,
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

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function customerPayGroSerials(Customer $customer)
    {
        $assetSerials = $customer->assets()
            ->whereNotNull('unit_serial')
            ->pluck('unit_serial');

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $metaSerials = collect([
            $meta['product_serial_number'] ?? null,
            $meta['ProductSerialNumber'] ?? null,
            $meta['ProductSerialNo'] ?? null,
            $meta['ProductSerial'] ?? null,
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
     * @param  array<string, mixed>  $record
     */
    protected function extractPayGroProductSerial(array $record): ?string
    {
        foreach ([
            'SerialNo',
            'ProductSerialNumber',
            'ProductSerialNo',
            'ProductSerial',
            'SerialNumber',
            'UnitSerial',
            'DeviceSerialNumber',
            'MeterSerialNumber',
            'MeterSerial',
            'product_serial_number',
            'unit_serial',
        ] as $key) {
            $value = trim((string) ($record[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function syncCustomerAssetFromPayGro(Customer $customer, array $record): void
    {
        $owner = $this->resolvePayGroAssetOwner($record);

        if (! $owner) {
            return;
        }

        if ($owner->id !== $customer->id) {
            $customer = $owner;
        }

        $serial = $this->extractPayGroProductSerial($record);

        if (! $serial) {
            $meta = is_array($record['meta'] ?? null) ? $record['meta'] : [];
            $serial = $this->extractPayGroProductSerial($meta);
        }

        if (! $serial) {
            return;
        }

        $productCategory = $record['ProductCategory'] ?? null;
        if (is_array($productCategory)) {
            $productCategory = $productCategory['Name'] ?? null;
        }

        $productModel = $record['ProductModel'] ?? $record['model'] ?? null;
        if (is_array($productModel)) {
            $productModel = $productModel['Name'] ?? null;
        }

        $salesIdentifier = trim((string) ($record['SalesIdentifier'] ?? ''));
        $allocationDate = $this->parsePayGroDate($record['AllocationDate'] ?? null);

        $metaFields = array_filter([
            'paygro_payment_plan' => $record['PaymentPlanName'] ?? null,
            'paygro_payment_credit_type' => $record['PaymentCreditType'] ?? null,
            'paygro_payment_source' => $record['PaymentSource'] ?? $record['PaymentSourceText'] ?? null,
            'paygro_sales_identifier' => $salesIdentifier ?: null,
            'paygro_allocation_date' => $record['AllocationDate'] ?? null,
            'paygro_last_payment_date' => $record['LastPaymentDate'] ?? null,
            'paygro_agent_name' => $record['AgentName'] ?? null,
            'paygro_product_device_token' => $record['ProductDeviceToken'] ?? null,
            'paygro_sale_srl_no' => $record['SrlNo'] ?? null,
            'paygro_inventory_location' => $record['InventoryLocation'] ?? null,
            'paygro_sync_source' => $record['_sync_source'] ?? null,
            'paygro_customer_name' => trim((string) ($record['CustomerName'] ?? '')) ?: null,
        ], fn ($value) => $value !== null && $value !== '');

        $attributes = [
            'customer_id' => $customer->id,
            'unit_serial' => $serial,
            'product_name' => $productCategory
                ?? $record['product_type']
                ?? $customer->product_type
                ?? 'PayGro Unit',
            'model' => $productModel,
            'installation_date' => $allocationDate,
            'status' => 'active',
        ];

        $asset = $this->findExistingPayGroAsset($serial, $salesIdentifier);

        if ($asset) {
            $previousCustomerId = $asset->customer_id;
            $previousSerial = $asset->unit_serial;

            if (strcasecmp($previousSerial, $serial) !== 0) {
                $metaFields['paygro_replaced_serial'] = $previousSerial;
                $metaFields['paygro_replaced_at'] = now()->toIso8601String();
            }

            $existingMeta = is_array($asset->meta) ? $asset->meta : [];

            $asset->update(array_merge($attributes, [
                'meta' => array_merge($existingMeta, $metaFields),
            ]));

            $this->removeDuplicateCustomerAssets($asset, $serial, $salesIdentifier);

            if ($previousCustomerId !== $customer->id) {
                $this->repointAssetCustomerRecords($asset, $previousCustomerId, $customer);
            }

            if ($salesIdentifier !== '') {
                $this->repointRepaymentSchedulesForSale($customer, $salesIdentifier, $asset);
            }

            $this->attachPaymentPlanMetadataToAsset($asset, $record);

            return;
        }

        $asset = CustomerAsset::create(array_merge($attributes, [
            'meta' => $metaFields,
        ]));

        $this->attachPaymentPlanMetadataToAsset($asset, $record);
    }

    protected function resolvePayGroAssetOwner(array $record): ?Customer
    {
        $payGroName = trim((string) ($record['CustomerName'] ?? ''));

        if ($payGroName !== '') {
            return $this->findCustomerForPayGroName($payGroName);
        }

        return null;
    }

    protected function customerOwnsPayGroAssetRecord(Customer $customer, array $record): bool
    {
        $owner = $this->resolvePayGroAssetOwner($record);

        return $owner !== null && $owner->id === $customer->id;
    }

    /**
     * Ensure each PayGro sale record owns exactly one asset on the correct customer.
     *
     * @param  array<int, array<string, mixed>>  $saleRecords
     * @return array{corrected: int, removed: int}
     */
    protected function reconcilePayGroAssetOwnership(array $saleRecords): array
    {
        $ownerBySerial = [];
        $ownerBySalesId = [];
        $ownedSerialsByCustomer = [];

        foreach ($saleRecords as $record) {
            $normalized = $this->normalizeProductSaleRecord($record);

            if (! $this->productSaleRecordIsCustomerAssigned($normalized)) {
                continue;
            }

            $customer = $this->resolvePayGroAssetOwner($normalized);
            $serial = $this->extractPayGroProductSerial($normalized);

            if (! $customer || ! $serial) {
                continue;
            }

            $serialKey = strtolower($serial);
            $salesId = strtolower(trim((string) ($normalized['SalesIdentifier'] ?? '')));

            $ownerBySerial[$serialKey] = $customer->id;

            if ($salesId !== '') {
                $ownerBySalesId[$salesId] = $customer->id;
            }

            $ownedSerialsByCustomer[$customer->id][$serialKey] = true;
        }

        $removed = $this->removeMisassignedPayGroAssets($ownerBySerial, $ownerBySalesId);
        $removed += $this->pruneUnownedPayGroAssets($ownedSerialsByCustomer);

        return [
            'corrected' => count($ownerBySerial),
            'removed' => $removed,
        ];
    }

    /**
     * @param  array<string, int>  $ownerBySerial
     * @param  array<string, int>  $ownerBySalesId
     */
    protected function removeMisassignedPayGroAssets(array $ownerBySerial, array $ownerBySalesId): int
    {
        if ($ownerBySerial === [] && $ownerBySalesId === []) {
            return 0;
        }

        $removed = 0;
        $canonical = [];

        $assets = CustomerAsset::query()
            ->where(function ($query) {
                $query->whereNotNull('meta->paygro_sales_identifier')
                    ->orWhereNotNull('meta->paygro_sync_source');
            })
            ->orderBy('id')
            ->get();

        foreach ($assets as $asset) {
            $meta = is_array($asset->meta) ? $asset->meta : [];
            $serialKey = strtolower(trim($asset->unit_serial));
            $salesId = strtolower(trim((string) ($meta['paygro_sales_identifier'] ?? '')));
            $ownerId = null;

            if ($salesId !== '' && isset($ownerBySalesId[$salesId])) {
                $ownerId = $ownerBySalesId[$salesId];
            } elseif (isset($ownerBySerial[$serialKey])) {
                $ownerId = $ownerBySerial[$serialKey];
            }

            if ($ownerId === null) {
                continue;
            }

            $canonicalKey = $salesId !== '' ? 'sale:'.$salesId : 'serial:'.$serialKey;

            if (isset($canonical[$canonicalKey])) {
                $this->deletePayGroAssetDuplicate($asset);
                $removed++;

                continue;
            }

            if ((int) $asset->customer_id !== (int) $ownerId) {
                $this->deletePayGroAssetDuplicate($asset);
                $removed++;

                continue;
            }

            $canonical[$canonicalKey] = $asset->id;
        }

        return $removed;
    }

    /**
     * @param  array<int, array<string, bool>>  $ownedSerialsByCustomer
     */
    protected function pruneUnownedPayGroAssets(array $ownedSerialsByCustomer): int
    {
        $removed = 0;

        foreach ($ownedSerialsByCustomer as $customerId => $ownedSerials) {
            $assets = CustomerAsset::query()
                ->where('customer_id', $customerId)
                ->where(function ($query) {
                    $query->whereNotNull('meta->paygro_sales_identifier')
                        ->orWhereNotNull('meta->paygro_sync_source');
                })
                ->get();

            foreach ($assets as $asset) {
                $serialKey = strtolower(trim($asset->unit_serial));

                if (! isset($ownedSerials[$serialKey])) {
                    $this->deletePayGroAssetDuplicate($asset);
                    $removed++;
                }
            }
        }

        return $removed;
    }

    protected function deletePayGroAssetDuplicate(CustomerAsset $asset): void
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                if ($this->repaymentSchedulesPayGroReady()) {
                    RepaymentSchedule::query()
                        ->where('customer_asset_id', $asset->id)
                        ->delete();
                }

                $asset->delete();

                return;
            } catch (QueryException $e) {
                if ($attempt >= 3 || ! str_contains($e->getMessage(), '1213')) {
                    throw $e;
                }

                usleep(150000 * $attempt);
                $asset->refresh();

                if (! $asset->exists) {
                    return;
                }
            }
        }
    }

    protected function findExistingPayGroAsset(string $serial, string $salesIdentifier): ?CustomerAsset
    {
        $bySerial = CustomerAsset::query()
            ->where('unit_serial', $serial)
            ->orderByDesc('updated_at')
            ->first();

        if ($bySerial) {
            return $bySerial;
        }

        if ($salesIdentifier === '') {
            return null;
        }

        return CustomerAsset::query()
            ->where('meta->paygro_sales_identifier', $salesIdentifier)
            ->orderByDesc('updated_at')
            ->first();
    }

    protected function removeDuplicateCustomerAssets(
        CustomerAsset $keep,
        string $serial,
        string $salesIdentifier,
    ): void {
        $duplicates = CustomerAsset::query()
            ->where('id', '!=', $keep->id)
            ->where(function ($query) use ($serial, $salesIdentifier) {
                $query->where('unit_serial', $serial);

                if ($salesIdentifier !== '') {
                    $query->orWhere('meta->paygro_sales_identifier', $salesIdentifier);
                }
            })
            ->get();

        foreach ($duplicates as $duplicate) {
            if ($this->repaymentSchedulesPayGroReady()) {
                RepaymentSchedule::query()
                    ->where('customer_asset_id', $duplicate->id)
                    ->update([
                        'customer_id' => $keep->customer_id,
                        'customer_asset_id' => $keep->id,
                    ]);
            }

            $duplicate->delete();
        }
    }

    protected function repointAssetCustomerRecords(
        CustomerAsset $asset,
        int $fromCustomerId,
        Customer $toCustomer,
    ): void {
        if ($fromCustomerId === $toCustomer->id) {
            return;
        }

        if ($this->repaymentSchedulesPayGroReady()) {
            RepaymentSchedule::query()
                ->where('customer_asset_id', $asset->id)
                ->update(['customer_id' => $toCustomer->id]);
        }

        if ($this->customerPaymentsPayGroReady()) {
            $salesIdentifier = is_array($asset->meta)
                ? trim((string) ($asset->meta['paygro_sales_identifier'] ?? ''))
                : '';

            CustomerPayment::query()
                ->where('customer_id', $fromCustomerId)
                ->where('source', 'paygro')
                ->where(function ($query) use ($asset, $salesIdentifier) {
                    $query->where('meta->product_serial_number', $asset->unit_serial);

                    if ($salesIdentifier !== '') {
                        $query->orWhere('meta->sales_identifier', $salesIdentifier);
                    }
                })
                ->update(['customer_id' => $toCustomer->id]);
        }

        Log::info('PayGro asset moved between customers', [
            'asset_id' => $asset->id,
            'unit_serial' => $asset->unit_serial,
            'from_customer_id' => $fromCustomerId,
            'to_customer_id' => $toCustomer->id,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $saleRecords
     * @return array<string, array<string, mixed>>
     */
    protected function indexProductSaleRecordsBySerial(array $saleRecords): array
    {
        $indexed = [];

        foreach ($saleRecords as $record) {
            $normalized = $this->normalizeProductSaleRecord($record);

            if (! $this->productSaleRecordIsCustomerAssigned($normalized)) {
                continue;
            }

            $serial = $this->extractPayGroProductSerial($normalized);

            if (! $serial) {
                continue;
            }

            $indexed[strtolower($serial)] = $normalized;
        }

        return $indexed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $saleRecords
     * @return array<string, array<string, mixed>>
     */
    protected function indexProductSaleRecordsBySalesIdentifier(array $saleRecords): array
    {
        $indexed = [];

        foreach ($saleRecords as $record) {
            $normalized = $this->normalizeProductSaleRecord($record);

            if (! $this->productSaleRecordIsCustomerAssigned($normalized)) {
                continue;
            }

            $salesIdentifier = trim((string) ($normalized['SalesIdentifier'] ?? ''));

            if ($salesIdentifier === '') {
                continue;
            }

            $key = strtolower($salesIdentifier);
            $indexed[$key] = $normalized;
        }

        return $indexed;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function findCustomerAssetForPayGroRecord(Customer $customer, array $record): ?CustomerAsset
    {
        $salesIdentifier = trim((string) ($record['SalesIdentifier'] ?? ''));

        if ($salesIdentifier !== '') {
            $bySale = CustomerAsset::query()
                ->where('customer_id', $customer->id)
                ->where('meta->paygro_sales_identifier', $salesIdentifier)
                ->first();

            if ($bySale) {
                return $bySale;
            }
        }

        $serial = $this->extractPayGroProductSerial($record);

        if (! $serial) {
            return null;
        }

        return CustomerAsset::query()
            ->where('customer_id', $customer->id)
            ->where('unit_serial', $serial)
            ->first();
    }

    protected function repointRepaymentSchedulesForSale(
        Customer $customer,
        string $salesIdentifier,
        CustomerAsset $asset,
    ): void {
        if (! $this->repaymentSchedulesPayGroReady()) {
            return;
        }

        RepaymentSchedule::query()
            ->where('sales_identifier', $salesIdentifier)
            ->update([
                'customer_id' => $customer->id,
                'customer_asset_id' => $asset->id,
            ]);
    }

    /**
     * @param  array<string, mixed>  $allocation
     * @param  array<string, array<string, mixed>>  $saleIndex
     * @return array<string, mixed>
     */
    protected function enrichAllocationWithSaleRecord(array $allocation, array $saleIndex): array
    {
        $serial = $this->extractPayGroProductSerial($allocation);
        $sale = $serial ? ($saleIndex[strtolower($serial)] ?? []) : [];

        return array_merge($sale, $allocation, [
            'ProductSerialNumber' => $serial ?? ($sale['ProductSerialNumber'] ?? null),
            'SerialNo' => $serial ?? ($allocation['SerialNo'] ?? null),
            '_sync_source' => $sale !== [] ? 'allocations+product_sale' : 'allocations',
        ]);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, array<int, array<string, mixed>>>  $paymentPlanIndex
     */
    protected function syncRepaymentScheduleForAsset(
        Customer $customer,
        CustomerAsset $asset,
        array $record,
        array $paymentPlanIndex = [],
    ): void {
        $salesIdentifier = trim((string) ($record['SalesIdentifier'] ?? ''));
        $paymentPlanName = trim((string) ($record['PaymentPlanName'] ?? ''));
        $creditType = trim((string) ($record['PaymentCreditType'] ?? ''));
        $lastPaymentDate = $this->parsePayGroDate($record['LastPaymentDate'] ?? null);
        $allocationDate = $this->parsePayGroDate($record['AllocationDate'] ?? null);
        $daysSinceLastPayment = (int) ($record['DaysSinceLastPayment'] ?? 0);
        $this->attachPaymentPlanMetadataToAsset($asset, $record);
        $asset->refresh();

        $assetMeta = is_array($asset->meta) ? $asset->meta : [];
        $unitBalance = (float) ($assetMeta['paygro_outstanding_balance'] ?? 0);
        $creditDays = (int) ($assetMeta['paygro_credit_days_down_payment'] ?? 0);
        $repaymentStatus = $this->resolveUnitRepaymentStatus(
            $customer,
            $record,
            $unitBalance,
            $creditDays,
            $asset,
        );

        $asset->update([
            'installation_date' => $allocationDate ?? $asset->installation_date,
            'meta' => array_merge($assetMeta, array_filter([
                'paygro_sales_identifier' => $salesIdentifier ?: null,
                'paygro_payment_plan' => $paymentPlanName ?: null,
                'paygro_payment_credit_type' => $creditType ?: null,
                'paygro_repayment_status' => $repaymentStatus,
                'paygro_last_payment_date' => $lastPaymentDate?->toDateString(),
                'paygro_days_since_last_payment' => $daysSinceLastPayment ?: null,
            ], fn ($value) => $value !== null && $value !== '')),
        ]);

        if (! $this->repaymentSchedulesPayGroReady()) {
            return;
        }

        if ($salesIdentifier !== '') {
            RepaymentSchedule::query()->updateOrCreate(
                [
                    'source' => 'paygro',
                    'external_reference' => 'plan:'.$salesIdentifier,
                ],
                [
                    'customer_id' => $customer->id,
                    'customer_asset_id' => $asset->id,
                    'entry_type' => RepaymentSchedule::ENTRY_PLAN,
                    'installment_number' => 0,
                    'due_date' => $allocationDate ?? now()->toDateString(),
                    'amount_due' => 0,
                    'amount_paid' => 0,
                    'status' => $repaymentStatus === 'paid_off' ? 'paid' : 'pending',
                    'paid_at' => $repaymentStatus === 'paid_off' ? ($lastPaymentDate ?? now()) : null,
                    'sales_identifier' => $salesIdentifier,
                    'payment_plan_name' => $paymentPlanName ?: null,
                    'meta' => [
                        'repayment_status' => $repaymentStatus,
                        'payment_credit_type' => $creditType ?: null,
                        'unit_serial' => $asset->unit_serial,
                        'last_payment_date' => $lastPaymentDate?->toIso8601String(),
                        'days_since_last_payment' => $daysSinceLastPayment,
                    ],
                ],
            );
        }

        $planRows = $this->findPaymentPlanRowsForName(
            $paymentPlanName,
            $paymentPlanIndex,
            $asset->model,
        );

        if ($planRows !== [] && $salesIdentifier !== '') {
            $this->syncPaymentPlanInstallmentsForSale(
                $customer,
                $asset,
                $salesIdentifier,
                $paymentPlanName,
                $planRows,
                $allocationDate,
            );
        }

        if (! $this->customerPaymentsPayGroReady()) {
            return;
        }

        $payments = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->where('source', 'paygro')
            ->orderBy('paid_at')
            ->get()
            ->filter(function (CustomerPayment $payment) use ($asset, $salesIdentifier) {
                $meta = is_array($payment->meta) ? $payment->meta : [];
                $paymentSalesId = strtolower(trim((string) ($meta['sales_identifier'] ?? '')));

                if ($salesIdentifier !== '' && $paymentSalesId !== '') {
                    return $paymentSalesId === strtolower($salesIdentifier);
                }

                $serial = strtolower(trim((string) ($meta['product_serial_number'] ?? '')));

                if ($serial === '') {
                    return $salesIdentifier === '';
                }

                return $serial === strtolower($asset->unit_serial);
            })
            ->values();

        $installmentNumber = 0;

        foreach ($payments as $payment) {
            $installmentNumber++;
            $reference = trim((string) ($payment->reference ?? ''));

            if ($reference === '') {
                continue;
            }

            $paidAt = $payment->paid_at ?? now();

            RepaymentSchedule::query()->updateOrCreate(
                [
                    'source' => 'paygro',
                    'external_reference' => 'payment:'.$reference,
                ],
                [
                    'customer_id' => $customer->id,
                    'customer_asset_id' => $asset->id,
                    'entry_type' => RepaymentSchedule::ENTRY_PAYMENT,
                    'installment_number' => $installmentNumber,
                    'due_date' => $paidAt,
                    'amount_due' => (float) $payment->amount,
                    'amount_paid' => (float) $payment->amount,
                    'status' => 'paid',
                    'paid_at' => $paidAt,
                    'sales_identifier' => $salesIdentifier ?: null,
                    'payment_plan_name' => $paymentPlanName ?: null,
                    'meta' => [
                        'payment_reference' => $reference,
                        'payment_type' => $payment->type,
                        'payment_method' => $payment->method,
                        'unit_serial' => $asset->unit_serial,
                        'sales_identifier' => $salesIdentifier ?: null,
                        'sms_code_for_payment' => $payment->meta['sms_code_for_payment'] ?? null,
                    ],
                ],
            );
        }

        if (
            $customer->next_payment_date
            && $repaymentStatus !== 'paid_off'
            && $customer->assets()->count() <= 1
            && $salesIdentifier !== ''
        ) {
            RepaymentSchedule::query()->updateOrCreate(
                [
                    'source' => 'paygro',
                    'external_reference' => 'next_due:'.$salesIdentifier,
                ],
                [
                    'customer_id' => $customer->id,
                    'customer_asset_id' => $asset->id,
                    'entry_type' => RepaymentSchedule::ENTRY_INSTALLMENT,
                    'installment_number' => $installmentNumber + 1,
                    'due_date' => $customer->next_payment_date,
                    'amount_due' => max(0, (float) ($customer->outstanding_balance ?? 0)),
                    'amount_paid' => 0,
                    'status' => $customer->payment_status === 'overdue' ? 'overdue' : 'pending',
                    'sales_identifier' => $salesIdentifier,
                    'payment_plan_name' => $paymentPlanName ?: null,
                    'meta' => [
                        'unit_serial' => $asset->unit_serial,
                        'sales_identifier' => $salesIdentifier,
                        'generated_from' => 'customer_next_payment_date',
                    ],
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function resolveUnitRepaymentStatus(
        Customer $customer,
        array $record,
        ?float $unitBalance = null,
        ?int $planCreditDays = null,
        ?CustomerAsset $asset = null,
    ): string {
        $balance = $unitBalance ?? (float) ($customer->outstanding_balance ?? 0);
        $tokenBalance = (int) ($customer->token_balance ?? 0);
        $daysSinceLastPayment = (int) ($record['DaysSinceLastPayment'] ?? 0);
        $creditType = (string) ($record['PaymentCreditType'] ?? '');

        $isHirePurchase = $asset
            ? $this->assetIsHirePurchase($asset)
            : $this->isHirePurchaseCreditType($creditType);

        if ($balance <= 0.01) {
            return 'paid_off';
        }

        if (! $isHirePurchase) {
            return 'active';
        }

        if ($tokenBalance > 0) {
            return 'active';
        }

        $graceDays = max(3, $planCreditDays ?? 0);

        if ($daysSinceLastPayment > $graceDays) {
            return 'defaulting';
        }

        return 'active';
    }

    protected function refreshCustomerAccountStatusFromAssets(Customer $customer): void
    {
        $customer->update([
            'account_status' => $this->resolveCustomerAccountStatus($customer),
        ]);
    }

    protected function repaymentSchedulesPayGroReady(): bool
    {
        return Schema::hasColumn('repayment_schedules', 'customer_asset_id')
            && Schema::hasColumn('repayment_schedules', 'external_reference');
    }

    /**
     * Build an in-memory name → customer resolver. Loading every customer once
     * and matching against a hash map turns the per-row DB lookup (a query for
     * each of ~34k transaction rows) into a single query plus array hits — the
     * difference between a ~1-hour and a ~1-minute payment sync. Names missing
     * from the exact-name index fall back to the full DB matcher (rare).
     *
     * @return callable(string): ?Customer
     */
    protected function customerNameResolver(): callable
    {
        $index = [];

        Customer::query()
            ->get(['id', 'first_name', 'last_name', 'phone'])
            ->each(function (Customer $customer) use (&$index) {
                $key = $this->normalizeNameKey($customer->first_name.' '.($customer->last_name ?? ''));

                if ($key !== '') {
                    $index[$key][] = $customer;
                }
            });

        $cache = [];

        return function (string $name) use ($index, &$cache): ?Customer {
            $key = $this->normalizeNameKey($name);

            if ($key === '') {
                return null;
            }

            if (array_key_exists($key, $cache)) {
                return $cache[$key];
            }

            $matches = collect($index[$key] ?? []);

            $resolved = match (true) {
                $matches->count() === 1 => $matches->first(),
                $matches->count() > 1 => $this->resolveDuplicateNameMatches($matches),
                // Not an exact-name hit — fall back to the DB matcher, which
                // also tries the first/last split. Bounded cost since misses
                // are uncommon and cached below.
                default => $this->findCustomerForPayGroName($name),
            };

            return $cache[$key] = $resolved;
        };
    }

    protected function normalizeNameKey(string $name): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $name)));
    }

    public function findCustomerForPayGroName(string $payGroName): ?Customer
    {
        $payGroName = trim(preg_replace('/\s+/', ' ', $payGroName));

        if ($payGroName === '') {
            return null;
        }

        $normalized = strtolower($payGroName);

        $matches = Customer::query()
            ->whereRaw(
                "LOWER(TRIM(CONCAT(first_name, ' ', COALESCE(last_name, '')))) = ?",
                [$normalized],
            )
            ->get();

        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->count() > 1) {
            if ($resolved = $this->resolveDuplicateNameMatches($matches)) {
                return $resolved;
            }

            Log::warning('PayGro customer name matched multiple accounts', [
                'paygro_name' => $payGroName,
                'matches' => $matches->pluck('account_number')->all(),
            ]);

            return null;
        }

        [$firstName, $lastName] = $this->splitCustomerName($payGroName);

        if ($lastName) {
            $matches = Customer::query()
                ->whereRaw('LOWER(first_name) = ?', [strtolower($firstName)])
                ->whereRaw('LOWER(last_name) = ?', [strtolower($lastName)])
                ->get();
        } else {
            $matches = Customer::query()
                ->whereRaw('LOWER(first_name) = ?', [strtolower($firstName)])
                ->where(function ($query) {
                    $query->whereNull('last_name')->orWhere('last_name', '');
                })
                ->get();
        }

        if ($matches->count() === 1) {
            return $matches->first();
        }

        if ($matches->count() > 1) {
            if ($resolved = $this->resolveDuplicateNameMatches($matches)) {
                return $resolved;
            }

            Log::warning('PayGro customer name matched multiple accounts', [
                'paygro_name' => $payGroName,
                'matches' => $matches->pluck('account_number')->all(),
            ]);
        }

        return null;
    }

    /**
     * When a PayGro name resolves to several customer rows that are really the
     * same person (identical phone), treat the oldest as canonical instead of
     * giving up. Genuinely distinct people who happen to share a name (different
     * phones) stay ambiguous and return null so we never misassign their units.
     *
     * @param  \Illuminate\Support\Collection<int, Customer>  $matches
     */
    protected function resolveDuplicateNameMatches($matches): ?Customer
    {
        $phones = $matches
            ->map(fn (Customer $c) => strtolower(trim((string) $c->phone)))
            ->filter()
            ->unique()
            ->values();

        if ($phones->count() <= 1) {
            return $matches->sortBy('id')->first();
        }

        return null;
    }

    protected function getPayGroSessionId(): string
    {
        $sessionId = Setting::get(self::SETTING_SESSION_ID);

        if ($sessionId) {
            return (string) $sessionId;
        }

        $this->loginAndStoreCookies();

        $sessionId = Setting::get(self::SETTING_SESSION_ID);

        if (! $sessionId) {
            throw new \RuntimeException('PayGro session id is missing. Reconnect to PayGro and try again.');
        }

        return (string) $sessionId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchAllProductSaleRecords(?string $startDate = null, ?string $endDate = null): array
    {
        return $this->fetchProductSaleRecords(
            searchText: null,
            startDate: $startDate,
            endDate: $endDate,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchProductSaleRecords(
        ?string $searchText = null,
        ?string $startDate = null,
        ?string $endDate = null,
    ): array {
        $baseUrl = rtrim((string) $this->configValue('base_url', 'https://app-main.pay-gro.com'), '/');
        $sessionId = $this->getPayGroSessionId();
        $customerSearch = trim((string) ($searchText ?? '')) !== '';
        $pageSize = (int) config('paygro.product_sale_page_size', 25);
        $maxPages = (int) config('paygro.product_sale_max_pages', 100);
        $sortBy = $customerSearch
            ? (int) config('paygro.product_sale_customer_search_by', 3)
            : (int) config('paygro.product_sale_sort_by', 4);
        $sortOrder = (int) config('paygro.product_sale_sort_order', 1);
        $timeout = (int) config('paygro.timeout_report', 120);
        $all = [];
        $pageCount = 1;

        for ($page = 1; $page <= min($pageCount, $maxPages); $page++) {
            $search = [
                'SessionId' => $sessionId,
                'PageNo' => $page,
                'PageSize' => $pageSize,
                'SortBy' => $sortBy,
                'SortOrder' => $sortOrder,
            ];

            if ($customerSearch) {
                $search['SearchText'] = trim((string) $searchText);
                $search['SearchOption'] = (int) config('paygro.product_sale_customer_search_option', 3);
            }

            if ($startDate && $endDate) {
                $search['FromDate'] = Carbon::parse($startDate)->format('Ymd');
                $search['ToDate'] = Carbon::parse($endDate)->format('Ymd');
            }

            $payload = [];
            foreach ($search as $key => $value) {
                $payload["productSaleSearchRequest[{$key}]"] = $value;
            }

            $response = $this->payGroRequest(
                fn ($http) => $http
                    ->withHeaders([
                        'Referer' => $baseUrl.'/Transactions/RegisterProductToCustomerByDistributorViewII',
                    ])
                    ->asForm()
                    ->post($baseUrl.'/Transactions/GetProductSaleRecord', $payload),
                $timeout,
            );

            if (! $response->successful()) {
                throw new \RuntimeException('PayGro product sale request failed: HTTP '.$response->status());
            }

            $data = $response->json();

            if (! is_array($data) || ! ($data['IsSuccess'] ?? false)) {
                $message = is_array($data) ? ($data['Message'] ?? 'Unknown PayGro error') : 'Invalid response';

                throw new \RuntimeException('PayGro product sale failed: '.$message);
            }

            $pageCount = max(1, (int) ($data['PageCount'] ?? 1));
            $sales = $data['ProductSales'] ?? [];

            if (! is_array($sales)) {
                break;
            }

            $all = array_merge($all, $sales);
        }

        return $all;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchProductPaymentPlanList(): array
    {
        $all = [];

        foreach ($this->configuredProductModels() as $model) {
            $all = array_merge($all, $this->fetchProductPaymentPlanListForModel($model));
        }

        return $all;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchProductPaymentPlanListForModel(string $productModel): array
    {
        $baseUrl = rtrim((string) $this->configValue('base_url', 'https://app-main.pay-gro.com'), '/');
        $sessionId = $this->getPayGroSessionId();
        $pageSize = (int) config('paygro.payment_plan_page_size', 25);
        $maxPages = (int) config('paygro.payment_plan_max_pages', 50);
        $sortBy = (int) config('paygro.payment_plan_sort_by', 1);
        $sortOrder = (int) config('paygro.payment_plan_sort_order', 1);
        $searchOption = (int) config('paygro.payment_plan_search_option', 1);
        $timeout = (int) config('paygro.timeout_report', 120);
        $all = [];
        $pageCount = 1;

        for ($page = 1; $page <= min($pageCount, $maxPages); $page++) {
            $search = [
                'SessionId' => $sessionId,
                'PageNo' => $page,
                'PageSize' => $pageSize,
                'SortBy' => $sortBy,
                'SortOrder' => $sortOrder,
                'SearchText' => trim($productModel),
                'SearchOption' => $searchOption,
            ];

            $payload = [];
            foreach ($search as $key => $value) {
                $payload["productPaymentPlanSearchRequest[{$key}]"] = $value;
            }

            $response = $this->payGroRequest(
                fn ($http) => $http
                    ->withHeaders([
                        'Referer' => $baseUrl.'/Masters/ModelWisePaymentPlanMasterIIIView',
                    ])
                    ->asForm()
                    ->post($baseUrl.'/Product/GetProductPaymentPlanList', $payload),
                $timeout,
            );

            if (! $response->successful()) {
                Log::warning('PayGro payment plan list request failed', [
                    'model' => $productModel,
                    'status' => $response->status(),
                ]);

                break;
            }

            $data = $response->json();

            if (! is_array($data) || ! ($data['IsSuccess'] ?? false)) {
                Log::warning('PayGro payment plan list returned no data', [
                    'model' => $productModel,
                    'trace' => is_array($data) ? ($data['TraceInfo'] ?? null) : null,
                ]);

                break;
            }

            $pageCount = max(1, (int) ($data['PageCount'] ?? 1));
            $plans = $data['ProductPaymentPlans'] ?? [];

            if (! is_array($plans) || $plans === []) {
                break;
            }

            $all = array_merge($all, $plans);
        }

        return $all;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function upsertPaygroPaymentPlanFromApiRow(array $row): PaygroPaymentPlan
    {
        $productModel = $this->payGroNestedName($row['ProductModel'] ?? null) ?? '';
        $planName = trim((string) ($row['PlanName'] ?? $row['PaymentPlanName'] ?? ''));

        return PaygroPaymentPlan::query()->updateOrCreate(
            ['paygro_srl_no' => (int) ($row['SrlNo'] ?? 0)],
            [
                'plan_name' => $planName,
                'product_model' => $productModel,
                'unlock_price' => (float) ($row['UnlockPrice'] ?? 0),
                'down_payment_price' => (float) ($row['DownPaymentPrice'] ?? 0),
                'credit_days_down_payment' => (int) ($row['CreditProvidedForDownPayment'] ?? 0),
                'credit_packet_price' => (float) ($row['CreditPacketPrice'] ?? 0),
                'credit_packet_size' => (int) ($row['CreditPacketSize'] ?? 0),
                'total_payments' => (int) ($row['TotalNoOfPayments'] ?? 0),
                'credit_type_name' => trim((string) ($row['CreditTypeName'] ?? '')) ?: null,
                'meta' => array_filter([
                    'credit_packet_name' => $row['CreditPacketName'] ?? null,
                    'credit_unit_name' => $row['CreditUnitName'] ?? null,
                    'min_purchase_quantity' => $row['MinPurchaseQuantity'] ?? null,
                    'max_purchase_quantity' => $row['MaxPurchaseQuantity'] ?? null,
                    'down_payment' => $row['DownPayment'] ?? null,
                ], fn ($value) => $value !== null && $value !== ''),
            ],
        );
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function loadPaymentPlanIndexFromDatabase(): array
    {
        if (! Schema::hasTable('paygro_payment_plans')) {
            return [];
        }

        $indexed = [];

        foreach (PaygroPaymentPlan::query()->get() as $plan) {
            $row = [
                'SrlNo' => $plan->paygro_srl_no,
                'PlanName' => $plan->plan_name,
                'PaymentPlanName' => $plan->plan_name,
                'ProductModel' => ['Name' => $plan->product_model],
                'UnlockPrice' => (float) $plan->unlock_price,
                'DownPaymentPrice' => (float) $plan->down_payment_price,
                'CreditProvidedForDownPayment' => $plan->credit_days_down_payment,
                'CreditPacketPrice' => (float) $plan->credit_packet_price,
                'TotalNoOfPayments' => $plan->total_payments,
            ];

            $nameKey = $this->normalizePaymentPlanName($plan->plan_name);
            $modelKey = strtolower($plan->product_model).'|'.$nameKey;

            $indexed[$nameKey][] = $row;
            $indexed[$modelKey][] = $row;
        }

        return $indexed;
    }

    protected function findPaygroPaymentPlan(?string $productModel, string $planName): ?PaygroPaymentPlan
    {
        if (! Schema::hasTable('paygro_payment_plans') || trim($planName) === '') {
            return null;
        }

        $productModel = trim((string) $productModel);
        $query = PaygroPaymentPlan::query();

        if ($productModel !== '') {
            $query->where('product_model', $productModel);
        }

        $candidates = $query->get();

        if ($candidates->isEmpty() && $productModel !== '') {
            $candidates = PaygroPaymentPlan::query()->get();
        }

        $normalized = $this->normalizePaymentPlanName($planName);
        $exact = $candidates->first(
            fn (PaygroPaymentPlan $plan) => $this->normalizePaymentPlanName($plan->plan_name) === $normalized,
        );

        if ($exact) {
            return $exact;
        }

        $core = $this->paymentPlanCoreName($planName);
        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            $score = max(
                $this->paymentPlanNameMatchScore($normalized, $this->normalizePaymentPlanName($candidate->plan_name)),
                $this->paymentPlanNameMatchScore($core, $this->paymentPlanCoreName($candidate->plan_name)),
            );

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $bestScore >= 0.82 ? $best : null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function attachPaymentPlanMetadataToAsset(CustomerAsset $asset, array $record = []): void
    {
        $asset->loadMissing('customer');

        if (! $asset->customer) {
            return;
        }

        $meta = is_array($asset->meta) ? $asset->meta : [];
        $planName = trim((string) ($record['PaymentPlanName'] ?? $meta['paygro_payment_plan'] ?? ''));
        $productModel = trim((string) ($asset->model ?? $this->payGroNestedName($record['ProductModel'] ?? null) ?? ''));

        if ($planName === '') {
            return;
        }

        $plan = $this->findPaygroPaymentPlan($productModel, $planName);

        if (! $plan) {
            return;
        }

        $salesId = trim((string) ($meta['paygro_sales_identifier'] ?? ''));
        $paid = $this->sumPayGroPaymentsForAsset($asset->customer, $asset, $salesId);
        $balance = max(0, (float) $plan->unlock_price - $paid);

        $asset->update([
            'meta' => array_merge($meta, array_filter([
                'paygro_payment_plan' => $plan->plan_name,
                'paygro_plan_srl_no' => $plan->paygro_srl_no,
                'paygro_plan_credit_type' => $plan->credit_type_name,
                'paygro_unlock_price' => (float) $plan->unlock_price,
                'paygro_down_payment_price' => (float) $plan->down_payment_price,
                'paygro_daily_payment' => (float) $plan->credit_packet_price,
                'paygro_credit_days_down_payment' => $plan->credit_days_down_payment,
                'paygro_total_payments' => $plan->total_payments,
                'paygro_amount_paid' => $paid,
                'paygro_outstanding_balance' => $balance,
            ], fn ($value) => $value !== null && $value !== '')),
        ]);
    }

    protected function sumPayGroPaymentsForAsset(Customer $customer, CustomerAsset $asset, string $salesIdentifier = ''): float
    {
        if (! $this->customerPaymentsPayGroReady()) {
            return 0.0;
        }

        $query = CustomerPayment::query()
            ->where('customer_id', $customer->id)
            ->where('source', 'paygro');

        if ($salesIdentifier !== '') {
            $query->where('meta->sales_identifier', $salesIdentifier);
        } else {
            $query->where('meta->product_serial_number', $asset->unit_serial);
        }

        return (float) $query->sum('amount');
    }

    /**
     * @param  array<int, array<string, mixed>>  $plans
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function indexProductPaymentPlansByName(array $plans): array
    {
        $indexed = [];

        foreach ($plans as $plan) {
            if (! is_array($plan)) {
                continue;
            }

            $planName = trim((string) (
                $plan['PaymentPlanName']
                ?? $plan['PlanName']
                ?? $plan['Name']
                ?? ''
            ));

            if ($planName === '') {
                continue;
            }

            $key = $this->normalizePaymentPlanName($planName);
            $indexed[$key][] = $plan;
        }

        foreach ($indexed as $key => $rows) {
            usort($rows, function (array $a, array $b): int {
                $aNo = (int) ($a['InstallmentNumber'] ?? $a['InstallmentNo'] ?? $a['RowNo'] ?? 0);
                $bNo = (int) ($b['InstallmentNumber'] ?? $b['InstallmentNo'] ?? $b['RowNo'] ?? 0);

                return $aNo <=> $bNo;
            });

            $indexed[$key] = $rows;
        }

        return $indexed;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $paymentPlanIndex
     * @return array<int, array<string, mixed>>
     */
    protected function findPaymentPlanRowsForName(
        string $planName,
        array $paymentPlanIndex,
        ?string $productModel = null,
    ): array {
        $planName = trim($planName);

        if ($planName === '' || $paymentPlanIndex === []) {
            return [];
        }

        $normalized = $this->normalizePaymentPlanName($planName);
        $model = trim((string) ($productModel ?? ''));

        if ($model !== '') {
            $modelKey = strtolower($model).'|'.$normalized;

            if (isset($paymentPlanIndex[$modelKey])) {
                return $paymentPlanIndex[$modelKey];
            }
        }

        if (isset($paymentPlanIndex[$normalized])) {
            return $paymentPlanIndex[$normalized];
        }

        $core = $this->paymentPlanCoreName($planName);

        if ($core !== '' && isset($paymentPlanIndex[$core])) {
            return $paymentPlanIndex[$core];
        }

        $bestKey = null;
        $bestScore = 0.0;

        foreach (array_keys($paymentPlanIndex) as $key) {
            $score = max(
                $this->paymentPlanNameMatchScore($normalized, $key),
                $this->paymentPlanNameMatchScore($core, $this->paymentPlanCoreName($key)),
            );

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestKey = $key;
            }
        }

        if ($bestKey !== null && $bestScore >= 0.82) {
            return $paymentPlanIndex[$bestKey];
        }

        return [];
    }

    protected function normalizePaymentPlanName(string $name): string
    {
        $name = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
        $name = str_replace(["\u{2019}", "'"], '', $name);

        return $name;
    }

    protected function paymentPlanCoreName(string $name): string
    {
        $normalized = $this->normalizePaymentPlanName($name);
        $core = trim((string) preg_replace('/\s*\([^)]*\)\s*/', ' ', $normalized));
        $core = preg_replace('/[,+]/', ' ', $core);
        $core = trim((string) preg_replace('/\s+/u', ' ', $core));

        return $core;
    }

    protected function paymentPlanNameMatchScore(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        if (str_contains($a, $b) || str_contains($b, $a)) {
            $shorter = strlen($a) <= strlen($b) ? $a : $b;
            $longer = strlen($a) > strlen($b) ? $a : $b;
            $ratio = strlen($longer) > 0 ? strlen($shorter) / strlen($longer) : 0.0;

            if ($shorter !== '' && preg_match('/(?:^|\s)'.preg_quote($shorter, '/').'$/', $longer)) {
                return max($ratio, 0.88);
            }

            return $ratio;
        }

        similar_text($a, $b, $percent);

        return $percent / 100;
    }

    /**
     * @param  array<int, array<string, mixed>>  $planRows
     */
    protected function syncPaymentPlanInstallmentsForSale(
        Customer $customer,
        CustomerAsset $asset,
        string $salesIdentifier,
        string $paymentPlanName,
        array $planRows,
        ?Carbon $allocationDate,
    ): void {
        $startDate = $allocationDate ?? now();

        foreach ($planRows as $index => $row) {
            $installmentNumber = (int) (
                $row['InstallmentNumber']
                ?? $row['InstallmentNo']
                ?? $row['RowNo']
                ?? ($index + 1)
            );

            if ($installmentNumber <= 0) {
                $installmentNumber = $index + 1;
            }

            $amountDue = (float) (
                $row['InstallmentAmount']
                ?? $row['Amount']
                ?? $row['PaymentAmount']
                ?? 0
            );

            $daysOffset = (int) (
                $row['DaysFromAllocation']
                ?? $row['DueDays']
                ?? $row['DaysOffset']
                ?? 0
            );

            $dueDate = $this->parsePayGroDate($row['DueDate'] ?? $row['InstallmentDueDate'] ?? null)
                ?? ($daysOffset > 0 ? $startDate->copy()->addDays($daysOffset) : $startDate->copy()->addMonths($installmentNumber - 1));

            RepaymentSchedule::query()->updateOrCreate(
                [
                    'source' => 'paygro',
                    'external_reference' => 'installment:'.$salesIdentifier.':'.$installmentNumber,
                ],
                [
                    'customer_id' => $customer->id,
                    'customer_asset_id' => $asset->id,
                    'entry_type' => RepaymentSchedule::ENTRY_INSTALLMENT,
                    'installment_number' => $installmentNumber,
                    'due_date' => $dueDate,
                    'amount_due' => $amountDue,
                    'amount_paid' => 0,
                    'status' => 'pending',
                    'sales_identifier' => $salesIdentifier,
                    'payment_plan_name' => $paymentPlanName,
                    'meta' => [
                        'unit_serial' => $asset->unit_serial,
                        'sales_identifier' => $salesIdentifier,
                        'generated_from' => 'payment_plan_master',
                        'paygro_plan_row' => array_filter([
                            'installment_number' => $installmentNumber,
                            'amount_due' => $amountDue,
                        ]),
                    ],
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function normalizeProductSaleRecord(array $record): array
    {
        return array_merge($record, [
            'ProductCategory' => $this->payGroNestedName($record['ProductCategory'] ?? null),
            'ProductModel' => $this->payGroNestedName($record['ProductModel'] ?? null),
            'ProductManufacturer' => $this->payGroNestedName($record['ProductManufacturer'] ?? null),
            '_sync_source' => 'product_sale',
        ]);
    }

    protected function payGroNestedName(mixed $value): ?string
    {
        if (is_array($value)) {
            $name = trim((string) ($value['Name'] ?? ''));

            return $name !== '' ? $name : null;
        }

        $name = trim((string) ($value ?? ''));

        return $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function productSaleRecordIsCustomerAssigned(array $record): bool
    {
        $location = strtoupper(trim((string) ($record['InventoryLocation'] ?? '')));

        return $location === '' || $location === 'CUSTOMER';
    }

    /**
     * @param  array<int, array<string, mixed>>  $allocationRecords
     * @param  array<int, array<string, mixed>>  $productRecords
     * @return array<int, array<string, mixed>>
     */
    protected function mergeUnitReportRecords(array $allocationRecords, array $productRecords): array
    {
        $merged = [];

        foreach ($allocationRecords as $row) {
            $serial = $this->extractPayGroProductSerial($row);

            if (! $serial) {
                continue;
            }

            $key = strtolower($serial);
            $merged[$key] = array_merge($row, ['_sync_source' => 'allocations']);
        }

        foreach ($productRecords as $row) {
            $location = strtoupper(trim((string) ($row['InventoryLocationName'] ?? '')));

            if ($location !== '' && $location !== 'CUSTOMER') {
                continue;
            }

            $serial = $this->extractPayGroProductSerial($row);

            if (! $serial) {
                continue;
            }

            $key = strtolower($serial);

            if (isset($merged[$key])) {
                $merged[$key] = array_merge($row, $merged[$key], [
                    '_sync_source' => 'allocations+product',
                ]);

                continue;
            }

            $merged[$key] = array_merge($row, ['_sync_source' => 'product']);
        }

        return array_values($merged);
    }

    /**
     * @param  array<string, mixed>  $token
     */
    protected function payGroTokenExternalReference(array $token): string
    {
        if (! empty($token['payment_reference'])) {
            return 'payment:'.$token['payment_reference'];
        }

        if (! empty($token['history_srl_no'])) {
            return 'history:'.$token['history_srl_no'];
        }

        return 'hash:'.sha1(implode('|', [
            $token['product_serial_number'] ?? '',
            $token['generated_token_value'] ?? '',
            $token['token_generation_date'] ?? '',
        ]));
    }

    protected function customerPaymentsPayGroReady(): bool
    {
        return Schema::hasColumn('customer_payments', 'source')
            && Schema::hasColumn('customer_payments', 'meta');
    }

    protected function tokenTransactionsPayGroReady(): bool
    {
        return Schema::hasColumn('token_transactions', 'external_reference')
            && Schema::hasColumn('token_transactions', 'token_value');
    }

    protected function ensureCustomerPaymentsPayGroReady(): void
    {
        if ($this->customerPaymentsPayGroReady()) {
            return;
        }

        throw new \RuntimeException(
            'PayGro payment sync needs a database update. Open Settings → System and run pending migrations, then try again.'
        );
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
        if ($fromDate && $toDate) {
            return [
                Carbon::parse($fromDate)->format('Y/m/d'),
                Carbon::parse($toDate)->format('Y/m/d'),
            ];
        }

        $startDays = (int) config('paygro.token_history_start_days', 365);
        $endDays = (int) config('paygro.token_history_end_days', 0);

        return [
            now()->subDays($startDays)->startOfDay()->format('Y/m/d'),
            now()->addDays($endDays)->endOfDay()->format('Y/m/d'),
        ];
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
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || str_starts_with($value, '0001-01-01')) {
            return null;
        }

        $parsed = $this->parsePayGroSlashDateTime($value);

        if ($parsed) {
            return $parsed->copy()->startOfDay();
        }

        try {
            return Carbon::parse($value)->startOfDay();
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

        if (! empty($data['SessionId'])) {
            Setting::set(self::SETTING_SESSION_ID, (string) $data['SessionId']);
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

    public function hasStoredCredentials(): bool
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
     * @return array<int, array<string, mixed>>
     */
    protected function fetchFromAllocationsReport(
        string $baseUrl,
        ?string $startOverride = null,
        ?string $endOverride = null,
    ): array {
        return $this->postHighLevelReport(
            $baseUrl,
            '/Masters/GetHighLevelReportForAllocations',
            $startOverride,
            $endOverride,
            fn (?string $start, ?string $end) => $this->unitReportDateRange($start, $end),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchFromProductReport(
        string $baseUrl,
        ?string $startOverride = null,
        ?string $endOverride = null,
    ): array {
        return $this->postHighLevelReport(
            $baseUrl,
            '/Masters/GetHighLevelReportForProduct',
            $startOverride,
            $endOverride,
            fn (?string $start, ?string $end) => $this->unitReportDateRange($start, $end),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function fetchFromTransactionsReport(
        string $baseUrl,
        ?string $startOverride = null,
        ?string $endOverride = null,
    ): array {
        return $this->postHighLevelReport(
            $baseUrl,
            '/Masters/GetHighLevelReportForTransactions',
            $startOverride,
            $endOverride,
            fn (?string $start, ?string $end) => $this->paymentReportDateRange($start, $end),
        );
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function upsertCustomerPaymentFromPayGro(Customer $customer, array $record): CustomerPayment
    {
        $reference = trim((string) ($record['PaymentReferenceNumber'] ?? ''));
        $paidAt = $this->parsePayGroDateTime(
            (string) ($record['PaymentDate'] ?? $record['PaymentDateText'] ?? ''),
        );

        return CustomerPayment::query()->updateOrCreate(
            [
                'source' => 'paygro',
                'reference' => $reference,
            ],
            [
                'customer_id' => $customer->id,
                'amount' => (float) ($record['PaymentAmount'] ?? 0),
                'type' => $this->mapPayGroPaymentType((string) ($record['PaymentTypeText'] ?? '')),
                'method' => $this->mapPayGroPaymentMethod($record),
                'tokens_credited' => 0,
                'days_credited' => (int) ($record['PacketsPurchasedQuantity'] ?? 0),
                'notes' => $this->buildPayGroPaymentNotes($record),
                'paid_at' => $paidAt ?? now(),
                'meta' => $this->buildPayGroPaymentMeta($record),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    protected function buildPayGroPaymentMeta(array $record): array
    {
        return array_filter([
            'product_serial_number' => $record['ProductSerialNumber'] ?? null,
            'sales_identifier' => $record['SalesIdentifier'] ?? null,
            'payment_plan_name' => $record['PaymentPlanName'] ?? null,
            'payment_type_text' => $record['PaymentTypeText'] ?? null,
            'payment_source_name' => $record['PaymentSourceName'] ?? null,
            'approval_status_text' => $record['ApprovalStatusText'] ?? null,
            'sms_code_for_payment' => $record['SmsCodeForPayment'] ?? null,
            'credit_packet_name' => $record['CreditPacketName'] ?? null,
            'credit_packet_size' => $record['CreditPacketSize'] ?? null,
            'agent_name' => $record['AgentName'] ?? null,
            'payment_comments' => $record['PaymentComments'] ?? null,
            'arrear_amount' => $record['ArrearAmount'] ?? null,
            'row_no' => $record['RowNo'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function buildPayGroPaymentNotes(array $record): ?string
    {
        $parts = array_filter([
            $record['PaymentPlanName'] ?? null,
            isset($record['ProductSerialNumber']) ? 'Unit '.$record['ProductSerialNumber'] : null,
            $record['PaymentComments'] ?? null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    protected function mapPayGroPaymentType(string $paymentTypeText): string
    {
        $normalized = strtoupper(trim($paymentTypeText));

        return match (true) {
            str_contains($normalized, 'DOWNPAYMENT') => 'deposit',
            str_contains($normalized, 'REFUND') => 'refund',
            str_contains($normalized, 'TOKEN') => 'token_purchase',
            default => 'payment',
        };
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function mapPayGroPaymentMethod(array $record): ?string
    {
        $comments = strtolower((string) ($record['PaymentComments'] ?? ''));
        $source = strtolower((string) ($record['PaymentSourceName'] ?? ''));

        if (str_contains($comments, 'mpesa') || str_contains($source, 'online')) {
            return 'mpesa';
        }

        if ($source !== '') {
            return $source;
        }

        return null;
    }

    protected function parsePayGroDateTime(string $value): ?Carbon
    {
        $value = trim($value);

        if ($value === '' || str_starts_with($value, '0001-01-01')) {
            return null;
        }

        $parsed = $this->parsePayGroSlashDateTime($value);

        if ($parsed) {
            return $parsed;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * PayGro reports use DD/MM/YYYY (e.g. 07/02/2025 = 7 Feb 2025), not US MM/DD/YYYY.
     */
    protected function parsePayGroSlashDateTime(string $value): ?Carbon
    {
        if (! preg_match(
            '/^(\d{1,2})\/(\d{1,2})\/(\d{4})(?:\s+(\d{1,2}:\d{2}(?::\d{2})?))?$/',
            $value,
            $matches,
        )) {
            return null;
        }

        $datePart = sprintf(
            '%02d/%02d/%04d',
            (int) $matches[1],
            (int) $matches[2],
            (int) $matches[3],
        );

        if (! empty($matches[4])) {
            $timePart = $matches[4];
            $format = strlen($timePart) === 5 ? 'd/m/Y H:i' : 'd/m/Y H:i:s';
            $candidate = $datePart.' '.$timePart;
        } else {
            $format = 'd/m/Y';
            $candidate = $datePart;
        }

        try {
            $parsed = Carbon::createFromFormat($format, $candidate);

            return $parsed instanceof Carbon ? $parsed : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function paymentReportDateRange(?string $startOverride = null, ?string $endOverride = null): array
    {
        if ($startOverride && $endOverride) {
            return [
                $this->formatPayGroReportDate($startOverride),
                $this->formatPayGroReportDate($endOverride),
            ];
        }

        $startDays = (int) config('paygro.payment_report_start_days', 90);
        $endDays = (int) config('paygro.payment_report_end_days', 30);

        return [
            now()->subDays($startDays)->format('Y/m/d'),
            now()->addDays($endDays)->format('Y/m/d'),
        ];
    }

    /**
     * Decide which transaction window to fetch.
     *
     * - Explicit dates (manual UI / backfill): honour them as-is.
     * - Payments already exist: incremental — only from the watermark (the last
     *   sync, or the newest stored payment) minus a small overlap buffer.
     * - First run / empty table: baseline — pull the full history once.
     *
     * @return array{0: string, 1: string, 2: string}  [start, end, mode]
     */
    protected function resolvePaymentSyncWindow(?string $startOverride, ?string $endOverride): array
    {
        if ($startOverride && $endOverride) {
            return [$startOverride, $endOverride, 'manual'];
        }

        $end = now()->addDays((int) config('paygro.payment_report_end_days', 30))->toDateString();

        // Incremental only makes sense when we actually hold payments. If the
        // table is empty (e.g. after a wipe) any stale watermark is ignored and
        // we fall through to a full baseline pull.
        $hasPayments = $this->customerPaymentsPayGroReady()
            && CustomerPayment::query()->where('source', 'paygro')->exists();

        $watermark = $hasPayments ? Setting::get(self::SETTING_LAST_PAYMENT_SYNC_AT) : null;

        if (! $watermark && $hasPayments) {
            // No stored watermark yet, but we already hold payments — resume from
            // the newest one rather than replaying the whole history.
            $watermark = CustomerPayment::query()
                ->where('source', 'paygro')
                ->max('paid_at');
        }

        if ($watermark) {
            $overlap = (int) config('paygro.payment_sync_overlap_days', 7);

            return [
                Carbon::parse((string) $watermark)->subDays($overlap)->toDateString(),
                $end,
                'incremental',
            ];
        }

        $firstStartDays = (int) config('paygro.payment_first_sync_start_days', 1825);

        return [
            now()->subDays($firstStartDays)->toDateString(),
            $end,
            'baseline',
        ];
    }

    /**
     * Move the payment watermark forward after a successful sync so the next run
     * only fetches newer transactions. A manual backfill of an old window (an
     * explicit end date in the past) must not advance — or rewind — the marker.
     */
    protected function advancePaymentSyncWatermark(?string $startOverride, ?string $endOverride): void
    {
        if ($endOverride && Carbon::parse($endOverride)->lt(now()->startOfDay())) {
            return;
        }

        $now = now();
        $existing = Setting::get(self::SETTING_LAST_PAYMENT_SYNC_AT);

        if ($existing && Carbon::parse((string) $existing)->greaterThanOrEqualTo($now)) {
            return;
        }

        Setting::set(self::SETTING_LAST_PAYMENT_SYNC_AT, $now->toIso8601String());
    }

    /**
     * @param  callable(?string, ?string): array{0: string, 1: string}  $dateRangeResolver
     * @return array<int, array<string, mixed>>
     */
    protected function postHighLevelReport(
        string $baseUrl,
        string $path,
        ?string $startOverride,
        ?string $endOverride,
        callable $dateRangeResolver,
    ): array {
        $distributorId = (int) $this->configValue('distributor_company_srl_no', 7);
        [$startDate, $endDate] = $dateRangeResolver($startOverride, $endOverride);
        $timeout = (int) config('paygro.timeout_report', 120);

        $response = $this->payGroRequest(
            fn ($http) => $http
                ->withHeaders([
                    'Referer' => rtrim($baseUrl, '/').'/Home/Dashboard',
                    'Origin' => rtrim($baseUrl, '/'),
                ])
                ->asForm()
                ->post(rtrim($baseUrl, '/').$path, [
                    'highLevelReportRequest' => [
                        'DistributorCompanySrlNo' => $distributorId,
                        'StartDate' => $startDate,
                        'EndDate' => $endDate,
                    ],
                ]),
            $timeout,
        );

        if (! $response->successful()) {
            throw new \RuntimeException('PayGro report request failed ('.$path.'): HTTP '.$response->status());
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new \RuntimeException('PayGro returned a non-JSON response ('.$path.').');
        }

        if (! ($data['IsSuccess'] ?? false)) {
            $message = $data['Message'] ?? 'Unknown PayGro error';

            throw new \RuntimeException('PayGro report failed ('.$path.'): '.$message);
        }

        $reportData = $data['ReportData'] ?? [];

        return is_array($reportData) ? $reportData : [];
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function unitReportDateRange(?string $startOverride = null, ?string $endOverride = null): array
    {
        if ($startOverride && $endOverride) {
            return [
                $this->formatPayGroReportDate($startOverride),
                $this->formatPayGroReportDate($endOverride),
            ];
        }

        $startDays = (int) config('paygro.unit_report_start_days', 730);
        $endDays = (int) config('paygro.unit_report_end_days', 30);

        return [
            now()->subDays($startDays)->format('Y/m/d'),
            now()->addDays($endDays)->format('Y/m/d'),
        ];
    }

    protected function formatPayGroReportDate(string $date): string
    {
        return Carbon::parse($date)->format('Y/m/d');
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
        $creditBalance = max(0, (int) ($record['CreditBalance'] ?? 0));

        $paymentStatus = 'current';
        $lifecycleStage = 'active';

        if ($creditBalance > 0 && $creditBalance <= 3) {
            $paymentStatus = 'due_soon';
        }

        $location = trim((string) ($record['ConsolidatedAddress'] ?? ''));
        if ($location === '') {
            $location = trim(implode(', ', array_filter([
                $record['StateName'] ?? null,
                $record['CountryName'] ?? null,
            ])));
        }

        $srlNo = $record['SrlNo'] ?? null;
        $productSerial = $this->extractPayGroProductSerial($record);

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
            'token_balance' => $creditBalance,
            'lifecycle_stage' => $lifecycleStage,
            'activated_at' => $record['CreatedOn'] ?? null,
            'meta' => [
                'paygro_srl_no' => $srlNo,
                'product_serial_number' => $productSerial,
                'paygro_credit_balance' => $creditBalance,
                'paygro_has_next_payment_due_passed' => $hasDuePassed,
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
     * @param  array<string, mixed>  $rawRecord
     */
    protected function upsertCustomer(array $record, array $rawRecord = []): Customer
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
            'lifecycle_stage' => $record['lifecycle_stage'] ?? 'new',
            'activated_at' => $record['activated_at'] ?? null,
            'meta' => $record['meta'] ?? null,
        ];

        if (array_key_exists('outstanding_balance', $record)) {
            $attributes['outstanding_balance'] = $record['outstanding_balance'];
        }

        if (array_key_exists('token_balance', $record)) {
            $attributes['token_balance'] = (int) $record['token_balance'];
        }

        $customer = null;

        if ($accountNumber) {
            $customer = Customer::where('account_number', $accountNumber)->first();
        }

        // PayGro re-registers the same person under new SrlNos, which would
        // otherwise create a fresh DB row per SrlNo and break name-based unit
        // matching. Collapse onto the existing person (same phone, and same name
        // when an account number is present) instead of duplicating them.
        if (! $customer && ($attributes['phone'] ?? '') !== '') {
            $query = Customer::where('phone', $attributes['phone']);

            if ($accountNumber) {
                $normalizedName = strtolower(trim(
                    $attributes['first_name'].' '.($attributes['last_name'] ?? '')
                ));

                $query->whereRaw(
                    "LOWER(TRIM(CONCAT(first_name, ' ', COALESCE(last_name, '')))) = ?",
                    [$normalizedName],
                );
            }

            $customer = $query->orderBy('id')->first();
        }

        if ($customer) {
            if ($accountNumber && $customer->account_number && $customer->account_number !== $accountNumber) {
                // Keep the canonical account number; remember the alternate SrlNo.
                $existingMeta = is_array($customer->meta) ? $customer->meta : [];
                $merged = (array) ($existingMeta['paygro_merged_account_numbers'] ?? []);

                if (! in_array($accountNumber, $merged, true)) {
                    $merged[] = $accountNumber;
                }

                $attributes['meta'] = array_merge(
                    is_array($attributes['meta']) ? $attributes['meta'] : [],
                    ['paygro_merged_account_numbers' => array_values($merged)],
                );

                unset($attributes['account_number']);
            } elseif ($accountNumber && ! $customer->account_number) {
                $attributes['account_number'] = $accountNumber;
            }

            $customer->update($attributes);

            return $customer;
        }

        $attributes['account_number'] = $accountNumber;

        return Customer::create($attributes);
    }
}
