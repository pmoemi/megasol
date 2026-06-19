<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\PaygroPaymentPlan;
use App\Services\Integrations\PayGroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class PayGroCustomerStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_with_credit_days_is_not_marked_overdue(): void
    {
        $customer = Customer::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => '+254712345678',
            'payment_status' => 'overdue',
            'lifecycle_stage' => 'at_risk',
            'token_balance' => 12,
        ]);

        CustomerAsset::create([
            'customer_id' => $customer->id,
            'unit_serial' => 'SN-001',
            'product_name' => 'Solar Unit',
            'model' => 'TWP-K088',
            'status' => 'active',
            'meta' => [
                'paygro_payment_plan' => 'KOSAP MEGA 20',
                'paygro_sales_identifier' => 'Z123',
                'paygro_days_since_last_payment' => 20,
            ],
        ]);

        PaygroPaymentPlan::create([
            'paygro_srl_no' => 101,
            'plan_name' => 'KOSAP MEGA 20',
            'product_model' => 'TWP-K088',
            'unlock_price' => 50000,
            'credit_days_down_payment' => 7,
            'credit_packet_price' => 50,
            'total_payments' => 100,
        ]);

        app(PayGroService::class)->refreshCustomerStatusesFromPayGro($customer->fresh());

        $customer->refresh();

        $this->assertSame('current', $customer->payment_status);
        $this->assertSame('active', $customer->lifecycle_stage);
        $this->assertSame('active', $customer->account_status);
    }

    public function test_customer_without_credit_and_high_balance_is_overdue_for_hire_purchase(): void
    {
        $customer = Customer::create([
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone' => '+254712345679',
            'payment_status' => 'current',
            'lifecycle_stage' => 'active',
            'token_balance' => 0,
        ]);

        CustomerAsset::create([
            'customer_id' => $customer->id,
            'unit_serial' => 'SN-002',
            'product_name' => 'Solar Unit',
            'model' => 'TWP-K088',
            'status' => 'active',
            'meta' => [
                'paygro_payment_plan' => 'KOSAP MEGA 20',
                'paygro_payment_credit_type' => 'Hire Purchase',
                'paygro_sales_identifier' => 'Z456',
                'paygro_unlock_price' => 50000,
                'paygro_outstanding_balance' => 40000,
                'paygro_days_since_last_payment' => 15,
                'paygro_credit_days_down_payment' => 7,
            ],
        ]);

        app(PayGroService::class)->refreshCustomerStatusesFromPayGro($customer->fresh());

        $customer->refresh();

        $this->assertSame('overdue', $customer->payment_status);
        $this->assertSame('at_risk', $customer->lifecycle_stage);
        $this->assertSame('defaulting', $customer->account_status);
        $this->assertTrue($customer->isHirePurchaseAccount());
    }

    public function test_daily_paygo_customer_is_never_marked_overdue(): void
    {
        $customer = Customer::create([
            'first_name' => 'Mary',
            'last_name' => 'Paygo',
            'phone' => '+254712345680',
            'payment_status' => 'overdue',
            'lifecycle_stage' => 'at_risk',
            'token_balance' => 0,
            'next_payment_date' => now()->subDays(20),
        ]);

        CustomerAsset::create([
            'customer_id' => $customer->id,
            'unit_serial' => 'SN-003',
            'product_name' => 'Solar Unit',
            'model' => 'TWP-SR24',
            'status' => 'active',
            'meta' => [
                'paygro_payment_plan' => 'KOSAP MEGA 20',
                'paygro_payment_credit_type' => 'Daily Payment',
                'paygro_sales_identifier' => 'Z789',
                'paygro_unlock_price' => 50000,
                'paygro_outstanding_balance' => 40000,
                'paygro_days_since_last_payment' => 20,
            ],
        ]);

        app(PayGroService::class)->refreshCustomerStatusesFromPayGro($customer->fresh());

        $customer->refresh();

        $this->assertSame('due_soon', $customer->payment_status);
        $this->assertSame('active', $customer->lifecycle_stage);
        $this->assertSame('active', $customer->account_status);
        $this->assertSame(20, $customer->days_in_arrears);
        $this->assertSame(20, $customer->meta['paygro_days_in_arrears']);
        $this->assertFalse($customer->isHirePurchaseAccount());
    }

    public function test_hire_purchase_days_in_arrears_computed_from_sync(): void
    {
        $customer = Customer::create([
            'first_name' => 'HP',
            'last_name' => 'Customer',
            'phone' => '+254712345681',
            'token_balance' => 0,
            'next_payment_date' => now()->subDays(12),
        ]);

        CustomerAsset::create([
            'customer_id' => $customer->id,
            'unit_serial' => 'SN-HP-001',
            'product_name' => 'Solar Unit',
            'model' => 'TWP-K088',
            'status' => 'active',
            'meta' => [
                'paygro_payment_credit_type' => 'Hire Purchase',
                'paygro_days_since_last_payment' => 20,
                'paygro_credit_days_down_payment' => 7,
            ],
        ]);

        $service = app(PayGroService::class);
        $service->refreshCustomerStatusesFromPayGro($customer->fresh());

        $customer->refresh();

        $this->assertTrue($customer->isHirePurchaseAccount());
        $this->assertSame(13, $customer->days_in_arrears);
        $this->assertSame(13, $customer->meta['paygro_days_in_arrears']);
    }

    public function test_hire_purchase_with_credit_days_has_zero_arrears(): void
    {
        $customer = Customer::create([
            'first_name' => 'HP',
            'last_name' => 'Current',
            'phone' => '+254712345682',
            'token_balance' => 5,
            'next_payment_date' => now()->subDays(30),
        ]);

        CustomerAsset::create([
            'customer_id' => $customer->id,
            'unit_serial' => 'SN-HP-002',
            'product_name' => 'Solar Unit',
            'status' => 'active',
            'meta' => ['paygro_payment_credit_type' => 'Hire Purchase'],
        ]);

        app(PayGroService::class)->refreshCustomerStatusesFromPayGro($customer->fresh());

        $customer->refresh();

        $this->assertSame(0, $customer->days_in_arrears);
    }

    public function test_map_report_record_treats_credit_balance_as_token_days(): void
    {
        $service = app(PayGroService::class);
        $method = new ReflectionMethod(PayGroService::class, 'mapReportRecord');

        $mapped = $method->invoke($service, [
            'SrlNo' => 99,
            'CustomerName' => 'Test Customer',
            'PrimaryMobileNumber' => '0712345678',
            'HasNextPaymentDueDatePassed' => 1,
            'CreditBalance' => 18,
        ]);

        $this->assertSame(18, $mapped['token_balance']);
        $this->assertSame('current', $mapped['payment_status']);
        $this->assertArrayNotHasKey('outstanding_balance', $mapped);
    }
}
