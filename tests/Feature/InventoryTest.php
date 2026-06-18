<?php

namespace Tests\Feature;

use App\Livewire\Inventory\InventoryIndex;
use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private function customer(array $attrs = []): Customer
    {
        return Customer::create(array_merge([
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane'.Str::random(6).'@test.com',
            'phone' => '+25470'.random_int(1000000, 9999999), 'account_number' => 'A'.Str::random(5),
            'payment_status' => 'current', 'lifecycle_stage' => 'active',
        ], $attrs));
    }

    private function user(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin'.Str::random(4).'@test.com', 'password' => bcrypt('x'), 'is_active' => true]);
    }

    public function test_can_register_a_unit_in_stock(): void
    {
        $this->actingAs($this->user());

        Livewire::test(InventoryIndex::class)
            ->call('newUnit')
            ->set('unitSerial', 'SN-STOCK-001')
            ->set('productName', 'Solar Home Plus')
            ->call('saveUnit')
            ->assertHasNoErrors()
            ->assertSee('SN-STOCK-001');

        $this->assertDatabaseHas('customer_assets', [
            'unit_serial' => 'SN-STOCK-001',
            'customer_id' => null,
        ]);
    }

    public function test_unit_serial_required(): void
    {
        $this->actingAs($this->user());

        Livewire::test(InventoryIndex::class)
            ->call('newUnit')
            ->set('unitSerial', '')
            ->call('saveUnit')
            ->assertHasErrors(['unitSerial' => 'required']);
    }

    public function test_can_assign_in_stock_unit_to_customer(): void
    {
        $this->actingAs($this->user());
        $customer = $this->customer();
        $asset = CustomerAsset::create(['unit_serial' => 'SN-FREE-1', 'status' => 'active']);

        Livewire::test(InventoryIndex::class)
            ->call('openAssign', $asset->id)
            ->set('assignCustomerId', $customer->id)
            ->call('assignToCustomer');

        $this->assertSame($customer->id, $asset->fresh()->customer_id);
    }

    public function test_can_return_unit_to_stock(): void
    {
        $this->actingAs($this->user());
        $customer = $this->customer();
        $asset = CustomerAsset::create(['customer_id' => $customer->id, 'unit_serial' => 'SN-ASSIGNED-1', 'status' => 'active']);

        Livewire::test(InventoryIndex::class)
            ->call('unassignUnit', $asset->id);

        $this->assertNull($asset->fresh()->customer_id);
    }

    public function test_inventory_counts_and_filters(): void
    {
        $this->actingAs($this->user());
        $customer = $this->customer();
        CustomerAsset::create(['unit_serial' => 'SN-IN-STOCK', 'status' => 'active']);
        CustomerAsset::create(['customer_id' => $customer->id, 'unit_serial' => 'SN-DEPLOYED', 'status' => 'active']);

        $component = Livewire::test(InventoryIndex::class)
            ->assertSee('SN-IN-STOCK')
            ->assertSee('SN-DEPLOYED');

        $component->set('assignment', 'in_stock')
            ->assertSee('SN-IN-STOCK')
            ->assertDontSee('SN-DEPLOYED');
    }

    public function test_can_delete_unit_from_inventory(): void
    {
        $this->actingAs($this->user());
        $asset = CustomerAsset::create(['unit_serial' => 'SN-DELETE-ME', 'status' => 'active']);

        Livewire::test(InventoryIndex::class)
            ->call('deleteUnit', $asset->id);

        $this->assertNull(CustomerAsset::find($asset->id));
    }
}
