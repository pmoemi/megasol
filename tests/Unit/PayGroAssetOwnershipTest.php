<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Services\Integrations\PayGroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class PayGroAssetOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_removes_assets_not_owned_by_customer_in_paygro(): void
    {
        $vivian = Customer::create([
            'first_name' => 'Vivian',
            'last_name' => 'Senkemoi Ntirkama',
            'phone' => '+254711111111',
            'account_number' => 'PG-173',
        ]);

        $magdaline = Customer::create([
            'first_name' => 'Magdaline',
            'last_name' => 'Kipuri',
            'phone' => '+254722222222',
            'account_number' => 'PG-310',
        ]);

        CustomerAsset::create([
            'customer_id' => $vivian->id,
            'unit_serial' => 'TWP-SR31C-962-2405080106',
            'product_name' => 'Solar Unit',
            'status' => 'active',
            'meta' => [
                'paygro_sales_identifier' => 'Z502280525085511',
                'paygro_sync_source' => 'product_sale',
            ],
        ]);

        CustomerAsset::create([
            'customer_id' => $vivian->id,
            'unit_serial' => 'TWP-SR31C-256-2405080258',
            'product_name' => 'Solar Unit',
            'status' => 'active',
            'meta' => [
                'paygro_sales_identifier' => 'Z676050925153619',
                'paygro_sync_source' => 'product_sale',
            ],
        ]);

        CustomerAsset::create([
            'customer_id' => $magdaline->id,
            'unit_serial' => 'TWP-SR31C-256-2405080258',
            'product_name' => 'Solar Unit',
            'status' => 'active',
            'meta' => [
                'paygro_sales_identifier' => 'Z676050925153619',
                'paygro_sync_source' => 'product_sale',
            ],
        ]);

        $service = app(PayGroService::class);
        $method = new ReflectionMethod(PayGroService::class, 'reconcilePayGroAssetOwnership');

        $result = $method->invoke($service, [
            [
                'ProductSerialNumber' => 'TWP-SR31C-962-2405080106',
                'SalesIdentifier' => 'Z502280525085511',
                'CustomerName' => 'Vivian Senkemoi Ntirkama',
                'InventoryLocation' => 'CUSTOMER',
                'PaymentPlanName' => 'KOSAP MEGA 50 (P.P.U)',
            ],
            [
                'ProductSerialNumber' => 'TWP-SR31C-256-2405080258',
                'SalesIdentifier' => 'Z676050925153619',
                'CustomerName' => 'Magdaline Kipuri',
                'InventoryLocation' => 'CUSTOMER',
                'PaymentPlanName' => 'KOSAP MEGA 50 (P.P.U)',
            ],
        ]);

        $vivian->refresh();

        $this->assertSame(1, $vivian->assets()->count());
        $this->assertSame('TWP-SR31C-962-2405080106', $vivian->assets()->first()->unit_serial);
        $this->assertGreaterThan(0, $result['removed']);
    }

    public function test_sync_asset_assigns_to_paygro_customer_name_owner(): void
    {
        $vivian = Customer::create([
            'first_name' => 'Vivian',
            'last_name' => 'Senkemoi Ntirkama',
            'phone' => '+254733333333',
            'account_number' => 'PG-173',
        ]);

        $other = Customer::create([
            'first_name' => 'Other',
            'last_name' => 'Customer',
            'phone' => '+254744444444',
            'account_number' => 'PG-999',
        ]);

        $service = app(PayGroService::class);
        $method = new ReflectionMethod(PayGroService::class, 'syncCustomerAssetFromPayGro');

        $method->invoke($service, $other, [
            'ProductSerialNumber' => 'TWP-SR31C-962-2405080106',
            'SalesIdentifier' => 'Z502280525085511',
            'CustomerName' => 'Vivian Senkemoi Ntirkama',
            'InventoryLocation' => 'CUSTOMER',
            'PaymentPlanName' => 'KOSAP MEGA 50 (P.P.U)',
            'ProductModel' => ['Name' => 'TWP-SR31C'],
        ]);

        $this->assertSame(0, $other->assets()->count());
        $this->assertSame(1, $vivian->assets()->count());
        $this->assertSame('TWP-SR31C-962-2405080106', $vivian->assets()->first()->unit_serial);
    }
}
