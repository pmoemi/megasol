<?php

namespace Tests\Feature;

use App\Livewire\Collections\CollectionsBoard;
use App\Livewire\Customers\CustomerProfile;
use App\Models\AgentAssignment;
use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\CustomerPayment;
use App\Models\RepaymentSchedule;
use App\Models\SmsMessage;
use App\Models\TokenTransaction;
use App\Models\User;
use App\Services\Sms\InboundSmsHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class Customer360Test extends TestCase
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

    public function test_days_in_arrears_accessor(): void
    {
        $overdue = $this->customer(['next_payment_date' => now()->subDays(30)]);
        $current = $this->customer(['next_payment_date' => now()->addDays(5)]);

        $this->assertSame(30, $overdue->days_in_arrears);
        $this->assertSame(0, $current->days_in_arrears);
    }

    public function test_total_paid_sums_payments(): void
    {
        $customer = $this->customer();
        CustomerPayment::create(['customer_id' => $customer->id, 'amount' => 1000, 'type' => 'payment', 'paid_at' => now()]);
        CustomerPayment::create(['customer_id' => $customer->id, 'amount' => 500, 'type' => 'token_purchase', 'paid_at' => now()]);

        $this->assertSame(1500.0, $customer->total_paid);
    }

    public function test_profile_overview_renders_with_key_metrics(): void
    {
        $this->actingAs($this->user());
        $customer = $this->customer([
            'product_type' => 'Solar Home', 'outstanding_balance' => 12500,
            'token_balance' => 24, 'next_payment_date' => now()->subDays(10),
        ]);

        Livewire::test(CustomerProfile::class, ['customer' => $customer])
            ->assertSee('Jane Doe')
            ->assertSee('Token Balance')
            ->assertSee('Days in Arrears')
            ->assertSee('Solar Home');
    }

    public function test_profile_tabs_load_related_data(): void
    {
        $this->actingAs($this->user());
        $customer = $this->customer(['token_balance' => 30]);

        CustomerPayment::create(['customer_id' => $customer->id, 'amount' => 3500, 'type' => 'token_purchase', 'method' => 'mpesa', 'reference' => 'MPX123', 'paid_at' => now(), 'days_credited' => 30]);
        TokenTransaction::create(['customer_id' => $customer->id, 'type' => 'purchase', 'tokens' => 30, 'days' => 30, 'balance_after' => 30, 'occurred_at' => now(), 'description' => '30-day token']);
        CustomerAsset::create(['customer_id' => $customer->id, 'unit_serial' => 'SN-ABC', 'product_name' => 'Solar Unit', 'installation_date' => now()->subMonth(), 'warranty_expiry' => now()->addYear(), 'status' => 'active']);
        RepaymentSchedule::create(['customer_id' => $customer->id, 'installment_number' => 1, 'due_date' => now()->addMonth(), 'amount_due' => 3500, 'status' => 'pending']);

        $component = Livewire::test(CustomerProfile::class, ['customer' => $customer]);

        $component->call('setTab', 'payments')->assertSee('MPX123');
        $component->call('setTab', 'tokens')->assertSee('30-day token');
        $component->call('setTab', 'assets')->assertSee('SN-ABC')->assertSee('Solar Unit');
        $component->call('setTab', 'schedule')->assertSee('Repayment Schedule');
    }

    public function test_can_assign_a_unit_to_a_customer(): void
    {
        $this->actingAs($this->user());
        $customer = $this->customer();

        Livewire::test(CustomerProfile::class, ['customer' => $customer])
            ->call('setTab', 'assets')
            ->call('newAsset')
            ->set('assetUnitSerial', 'SN-UNIT-001')
            ->set('assetProductName', 'Solar Home Plus')
            ->set('assetModel', 'MS-300W')
            ->set('assetInstallationDate', now()->subMonth()->format('Y-m-d'))
            ->set('assetWarrantyExpiry', now()->addYear()->format('Y-m-d'))
            ->set('assetStatus', 'active')
            ->call('saveAsset')
            ->assertHasNoErrors()
            ->assertSee('SN-UNIT-001')
            ->assertSee('Solar Home Plus');

        $this->assertDatabaseHas('customer_assets', [
            'customer_id' => $customer->id,
            'unit_serial' => 'SN-UNIT-001',
            'model' => 'MS-300W',
            'status' => 'active',
        ]);
    }

    public function test_unit_serial_is_required_when_assigning(): void
    {
        $this->actingAs($this->user());
        $customer = $this->customer();

        Livewire::test(CustomerProfile::class, ['customer' => $customer])
            ->call('newAsset')
            ->set('assetUnitSerial', '')
            ->call('saveAsset')
            ->assertHasErrors(['assetUnitSerial' => 'required']);
    }

    public function test_can_edit_and_remove_a_unit(): void
    {
        $this->actingAs($this->user());
        $customer = $this->customer();
        $asset = CustomerAsset::create([
            'customer_id' => $customer->id, 'unit_serial' => 'SN-OLD', 'product_name' => 'Old Unit', 'status' => 'active',
        ]);

        $component = Livewire::test(CustomerProfile::class, ['customer' => $customer])
            ->call('editAsset', $asset->id)
            ->assertSet('assetUnitSerial', 'SN-OLD')
            ->set('assetStatus', 'faulty')
            ->call('saveAsset');

        $this->assertSame('faulty', $asset->fresh()->status);

        $component->call('deleteAsset', $asset->id);
        $this->assertNull(CustomerAsset::find($asset->id));
    }

    public function test_can_send_sms_from_profile(): void
    {
        Queue::fake();
        $this->actingAs($this->user());
        $customer = $this->customer(['phone' => '+254712345699']);

        Livewire::test(CustomerProfile::class, ['customer' => $customer])
            ->call('setTab', 'messages')
            ->set('smsBody', 'Hello, your account is up to date.')
            ->call('sendSms')
            ->assertHasNoErrors()
            ->assertSet('smsBody', '');

        // The outbound message is logged against the customer so it appears in
        // their timeline (single send, not via a campaign).
        $this->assertDatabaseHas('sms_messages', [
            'customer_id' => $customer->id,
            'direction' => 'outbound',
            'body' => 'Hello, your account is up to date.',
            'campaign_id' => null,
        ]);

        Queue::assertPushed(\App\Jobs\SendSmsJob::class, function ($job) use ($customer) {
            return $job->to === $customer->phone
                && $job->message === 'Hello, your account is up to date.'
                && $job->smsMessageId !== null;
        });
    }

    public function test_cannot_send_sms_to_opted_out_customer(): void
    {
        Queue::fake();
        $this->actingAs($this->user());
        $customer = $this->customer(['phone' => '+254712345698', 'sms_opted_out' => true]);

        Livewire::test(CustomerProfile::class, ['customer' => $customer])
            ->call('setTab', 'messages')
            ->set('smsBody', 'Hello')
            ->call('sendSms')
            ->assertHasErrors('smsBody');

        Queue::assertNotPushed(\App\Jobs\SendSmsJob::class);
    }

    public function test_messages_tab_shows_inbound_and_outbound(): void
    {
        $this->actingAs($this->user());
        $customer = $this->customer();

        SmsMessage::create(['customer_id' => $customer->id, 'to' => 'MEGASOL', 'from' => $customer->phone, 'body' => 'BALANCE please', 'direction' => 'inbound', 'status' => 'delivered']);
        SmsMessage::create(['customer_id' => $customer->id, 'to' => $customer->phone, 'body' => 'Your balance is 100', 'direction' => 'outbound', 'status' => 'sent']);

        Livewire::test(CustomerProfile::class, ['customer' => $customer])
            ->call('setTab', 'messages')
            ->assertSee('SMS received')
            ->assertSee('SMS sent');
    }

    public function test_assign_customer_to_field_agent_from_profile(): void
    {
        $this->actingAs($this->user());
        $customer = $this->customer(['payment_status' => 'overdue', 'outstanding_balance' => 5000]);
        $agent = $this->user();

        Livewire::test(CustomerProfile::class, ['customer' => $customer])
            ->call('setTab', 'collections')
            ->set('assignAgentId', $agent->id)
            ->set('assignReason', '30 days overdue')
            ->call('assignToAgent');

        $assignment = AgentAssignment::where('customer_id', $customer->id)->first();
        $this->assertNotNull($assignment);
        $this->assertSame($agent->id, $assignment->agent_id);
        $this->assertSame('assigned', $assignment->status);
        $this->assertEquals('5000.00', $assignment->amount_at_assignment);
        $this->assertSame($agent->id, $customer->fresh()->assigned_agent_id);
    }

    public function test_resolving_assignment_clears_agent(): void
    {
        $this->actingAs($this->user());
        $agent = $this->user();
        $customer = $this->customer(['assigned_agent_id' => $agent->id]);
        $assignment = AgentAssignment::create([
            'customer_id' => $customer->id, 'agent_id' => $agent->id, 'status' => 'assigned', 'assigned_at' => now(),
        ]);

        Livewire::test(CustomerProfile::class, ['customer' => $customer])
            ->call('updateAssignmentStatus', $assignment->id, 'resolved');

        $this->assertSame('resolved', $assignment->fresh()->status);
        $this->assertNotNull($assignment->fresh()->resolved_at);
        $this->assertNull($customer->fresh()->assigned_agent_id);
    }

    public function test_writing_off_assignment_sets_account_status(): void
    {
        $this->actingAs($this->user());
        $agent = $this->user();
        $customer = $this->customer(['assigned_agent_id' => $agent->id]);
        $assignment = AgentAssignment::create([
            'customer_id' => $customer->id, 'agent_id' => $agent->id, 'status' => 'in_progress', 'assigned_at' => now(),
        ]);

        Livewire::test(CustomerProfile::class, ['customer' => $customer])
            ->call('updateAssignmentStatus', $assignment->id, 'written_off');

        $this->assertSame('written_off', $customer->fresh()->account_status);
    }

    public function test_collections_board_lists_unassigned_defaulters(): void
    {
        $this->actingAs($this->user());
        $this->customer(['first_name' => 'Unassigned', 'last_name' => 'Defaulter', 'payment_status' => 'overdue', 'outstanding_balance' => 9000]);
        $assigned = $this->customer(['first_name' => 'Already', 'last_name' => 'Assigned', 'payment_status' => 'overdue', 'assigned_agent_id' => $this->user()->id]);

        Livewire::test(CollectionsBoard::class)
            ->assertSet('tab', 'unassigned')
            ->assertSee('9,000.00')
            ->assertSee('Unassigned Defaulter')
            ->assertDontSee($assigned->full_name);
    }

    public function test_collections_board_assigns_customer(): void
    {
        $this->actingAs($this->user());
        $agent = $this->user();
        $customer = $this->customer(['payment_status' => 'overdue', 'outstanding_balance' => 7000]);

        Livewire::test(CollectionsBoard::class)
            ->call('openAssign', $customer->id)
            ->set('assignAgentId', $agent->id)
            ->set('assignReason', 'No response')
            ->call('assign');

        $this->assertSame($agent->id, $customer->fresh()->assigned_agent_id);
        $this->assertDatabaseHas('agent_assignments', ['customer_id' => $customer->id, 'agent_id' => $agent->id]);
    }

    // ── Inbound SMS handling ─────────────────────────────────────────────

    public function test_inbound_stop_opts_customer_out(): void
    {
        Queue::fake();
        $customer = $this->customer(['phone' => '+254712345678', 'sms_opted_out' => false]);

        $result = app(InboundSmsHandler::class)->handle('+254712345678', 'STOP');

        $this->assertSame('opt_out', $result['intent']);
        $this->assertTrue($customer->fresh()->sms_opted_out);
        $this->assertDatabaseHas('sms_messages', ['customer_id' => $customer->id, 'direction' => 'inbound', 'body' => 'STOP']);
    }

    public function test_inbound_balance_request_records_and_replies(): void
    {
        Queue::fake();
        $customer = $this->customer(['phone' => '+254712345670', 'outstanding_balance' => 4200, 'token_balance' => 12]);

        $result = app(InboundSmsHandler::class)->handle('+254712345670', 'BALANCE');

        $this->assertSame('balance', $result['intent']);
        $this->assertStringContainsString('4,200', $result['reply']);
        $this->assertStringContainsString('12 day', $result['reply']);
        Queue::assertPushed(\App\Jobs\SendSmsJob::class);
    }

    public function test_inbound_reply_uses_dedicated_inbound_shortcode(): void
    {
        Queue::fake();
        config(['africastalking.sender_id' => 'MEGASOL', 'africastalking.inbound.sender_id' => '20880']);
        $this->customer(['phone' => '+254712345673', 'outstanding_balance' => 100]);

        app(InboundSmsHandler::class)->handle('+254712345673', 'BALANCE');

        Queue::assertPushed(\App\Jobs\SendSmsJob::class, function ($job) {
            return $job->senderId === '20880';
        });
    }

    public function test_inbound_complaint_is_flagged_for_follow_up(): void
    {
        Queue::fake();
        $customer = $this->customer(['phone' => '+254712345671']);

        $result = app(InboundSmsHandler::class)->handle('+254712345671', 'My unit is not working, please help');

        $this->assertSame('complaint', $result['intent']);
        $this->assertTrue((bool) $result['message']->meta['needs_follow_up']);
    }

    public function test_inbound_matches_customer_by_phone_tail(): void
    {
        Queue::fake();
        $customer = $this->customer(['phone' => '+254712345672']);

        // Provider sends without the leading + and country code variance.
        $result = app(InboundSmsHandler::class)->handle('0712345672', 'BALANCE');

        $this->assertNotNull($result['customer']);
        $this->assertSame($customer->id, $result['customer']->id);
    }
}
