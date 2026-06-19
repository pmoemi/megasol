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
        $overdue = $this->customer([
            'next_payment_date' => now()->subDays(30),
            'meta' => ['paygro_has_hire_purchase' => true],
        ]);
        $current = $this->customer(['next_payment_date' => now()->addDays(5)]);
        $dailyPaygo = $this->customer(['next_payment_date' => now()->subDays(30)]);

        $this->assertSame(30, $overdue->days_in_arrears);
        $this->assertSame(0, $current->days_in_arrears);
        $this->assertSame(0, $dailyPaygo->days_in_arrears);
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

        $component->call('setTab', 'payments')->assertSee('Repayment Schedule');
        $component->call('setPaymentsSubTab', 'history')->assertSee('MPX123')->assertSee('Total Paid');
        $component->call('setTab', 'tokens')->assertSee('30-day token');
        $component->call('setTab', 'assets')->assertSee('SN-ABC')->assertSee('Solar Unit');
    }

    public function test_payment_history_can_filter_by_asset(): void
    {
        $this->actingAs($this->user());
        $customer = $this->customer();

        $assetA = CustomerAsset::create([
            'customer_id' => $customer->id,
            'unit_serial' => 'SN-FILTER-A',
            'product_name' => 'Solar Unit A',
            'status' => 'active',
            'meta' => ['paygro_sales_identifier' => 'SALE-A'],
        ]);
        CustomerAsset::create([
            'customer_id' => $customer->id,
            'unit_serial' => 'SN-FILTER-B',
            'product_name' => 'Solar Unit B',
            'status' => 'active',
            'meta' => ['paygro_sales_identifier' => 'SALE-B'],
        ]);

        CustomerPayment::create([
            'customer_id' => $customer->id,
            'amount' => 100,
            'type' => 'payment',
            'reference' => 'PAY-A',
            'paid_at' => now(),
            'meta' => ['product_serial_number' => 'SN-FILTER-A', 'sales_identifier' => 'SALE-A'],
        ]);
        CustomerPayment::create([
            'customer_id' => $customer->id,
            'amount' => 200,
            'type' => 'payment',
            'reference' => 'PAY-B',
            'paid_at' => now(),
            'meta' => ['product_serial_number' => 'SN-FILTER-B', 'sales_identifier' => 'SALE-B'],
        ]);

        Livewire::test(CustomerProfile::class, ['customer' => $customer])
            ->call('setTab', 'payments')
            ->call('setPaymentsSubTab', 'history')
            ->assertSee('PAY-A')
            ->assertSee('PAY-B')
            ->set('paymentAssetFilter', (string) $assetA->id)
            ->assertSee('PAY-A')
            ->assertDontSee('PAY-B');
    }

    public function test_repayment_schedule_tab_shows_installments_not_payments(): void
    {
        $this->actingAs($this->user());
        $customer = $this->customer();

        CustomerAsset::create([
            'customer_id' => $customer->id,
            'unit_serial' => 'SN-SCHEDULE',
            'product_name' => 'Solar Unit',
            'status' => 'active',
            'meta' => ['paygro_sales_identifier' => 'SALE-SCH'],
        ]);

        RepaymentSchedule::create([
            'customer_id' => $customer->id,
            'entry_type' => RepaymentSchedule::ENTRY_PLAN,
            'source' => 'paygro',
            'external_reference' => 'plan:SALE-SCH',
            'sales_identifier' => 'SALE-SCH',
            'installment_number' => 0,
            'due_date' => now(),
            'amount_due' => 0,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        RepaymentSchedule::create([
            'customer_id' => $customer->id,
            'entry_type' => RepaymentSchedule::ENTRY_INSTALLMENT,
            'source' => 'paygro',
            'external_reference' => 'installment:1',
            'sales_identifier' => 'SALE-SCH',
            'installment_number' => 1,
            'due_date' => now()->addMonth(),
            'amount_due' => 3500,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        RepaymentSchedule::create([
            'customer_id' => $customer->id,
            'entry_type' => RepaymentSchedule::ENTRY_PAYMENT,
            'source' => 'paygro',
            'external_reference' => 'payment:ONLY-IN-HISTORY',
            'sales_identifier' => 'SALE-SCH',
            'installment_number' => 1,
            'due_date' => now(),
            'amount_due' => 75,
            'amount_paid' => 75,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Livewire::test(CustomerProfile::class, ['customer' => $customer])
            ->call('setTab', 'payments')
            ->assertSee('3,500.00')
            ->assertDontSee('75.00');
    }

    public function test_assets_tab_displays_synced_units(): void
    {
        $this->actingAs($this->user());
        $customer = $this->customer();
        CustomerAsset::create([
            'customer_id' => $customer->id,
            'unit_serial' => 'SN-UNIT-001',
            'product_name' => 'Solar Home Plus',
            'model' => 'MS-300W',
            'status' => 'active',
            'meta' => [
                'paygro_sales_identifier' => 'Z123456789',
                'paygro_payment_plan' => 'KOSAP MEGA 20',
            ],
        ]);

        Livewire::test(CustomerProfile::class, ['customer' => $customer])
            ->call('setTab', 'assets')
            ->assertSee('SN-UNIT-001')
            ->assertSee('Solar Home Plus')
            ->assertSee('Z123456789')
            ->assertSee('KOSAP MEGA 20')
            ->assertDontSee('Assign Unit');
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
