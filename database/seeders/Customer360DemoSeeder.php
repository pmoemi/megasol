<?php

namespace Database\Seeders;

use App\Models\AgentAssignment;
use App\Models\Customer;
use App\Models\CustomerAsset;
use App\Models\CustomerPayment;
use App\Models\RepaymentSchedule;
use App\Models\SmsMessage;
use App\Models\TokenTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds a handful of realistic customers with full Customer-360 context
 * (payments, token ledger, assets, repayment schedules, collections
 * assignments, and inbound SMS) so the profile and collections views are
 * demonstrable out of the box. Idempotent via updateOrCreate on account_number.
 */
class Customer360DemoSeeder extends Seeder
{
    public function run(): void
    {
        $agent = User::updateOrCreate(
            ['email' => 'agent@megasol.com'],
            ['name' => 'Field Agent One', 'password' => Hash::make('password'), 'is_active' => true, 'email_verified_at' => now()],
        );
        if (method_exists($agent, 'assignRole')) {
            $agent->syncRoles(['Field Agent']);
        }

        $samples = [
            [
                'account_number' => 'PG-100001', 'first_name' => 'Amina', 'last_name' => 'Otieno',
                'phone' => '+254700100001', 'email' => 'amina@example.com', 'product_type' => 'Solar Home',
                'location' => 'Nairobi', 'payment_status' => 'current', 'lifecycle_stage' => 'active',
                'account_status' => 'active', 'token_balance' => 24, 'outstanding_balance' => 12500,
                'next_payment_date' => now()->addDays(12), 'defaulting' => false,
            ],
            [
                'account_number' => 'PG-100002', 'first_name' => 'Brian', 'last_name' => 'Mwangi',
                'phone' => '+254700100002', 'email' => 'brian@example.com', 'product_type' => 'Solar Home Plus',
                'location' => 'Kisumu', 'payment_status' => 'overdue', 'lifecycle_stage' => 'at_risk',
                'account_status' => 'defaulting', 'token_balance' => 0, 'outstanding_balance' => 34800,
                'next_payment_date' => now()->subDays(52), 'defaulting' => true,
            ],
            [
                'account_number' => 'PG-100003', 'first_name' => 'Christine', 'last_name' => 'Wanjiru',
                'phone' => '+254700100003', 'email' => 'christine@example.com', 'product_type' => 'Solar Commercial',
                'location' => 'Nakuru', 'payment_status' => 'paid_off', 'lifecycle_stage' => 'loyal',
                'account_status' => 'paid_off', 'token_balance' => 9999, 'outstanding_balance' => 0,
                'next_payment_date' => null, 'defaulting' => false,
            ],
        ];

        foreach ($samples as $data) {
            $defaulting = $data['defaulting'];
            unset($data['defaulting']);

            $customer = Customer::updateOrCreate(['account_number' => $data['account_number']], array_merge($data, [
                'activated_at' => now()->subMonths(8),
            ]));

            // Fresh demo data each run.
            $customer->payments()->delete();
            $customer->tokenTransactions()->delete();
            $customer->assets()->delete();
            $customer->repaymentSchedules()->delete();

            // Payments + token ledger
            $balance = 0;
            for ($i = 6; $i >= 1; $i--) {
                $payment = CustomerPayment::create([
                    'customer_id' => $customer->id,
                    'amount' => 3500,
                    'type' => $i === 6 ? 'deposit' : 'token_purchase',
                    'method' => 'mpesa',
                    'reference' => 'MPX'.strtoupper(substr(md5($customer->id.$i), 0, 8)),
                    'tokens_credited' => 30,
                    'days_credited' => 30,
                    'paid_at' => now()->subMonths($i),
                ]);
                $balance += 30;
                TokenTransaction::create([
                    'customer_id' => $customer->id,
                    'customer_payment_id' => $payment->id,
                    'type' => 'purchase',
                    'tokens' => 30,
                    'days' => 30,
                    'balance_after' => $balance,
                    'source' => 'payment',
                    'description' => '30-day token from M-Pesa payment',
                    'occurred_at' => now()->subMonths($i),
                ]);
            }

            // Asset
            CustomerAsset::create([
                'customer_id' => $customer->id,
                'unit_serial' => 'SN-'.strtoupper(substr(md5($customer->account_number), 0, 10)),
                'product_name' => $customer->product_type,
                'model' => 'MS-300W',
                'installation_date' => now()->subMonths(8),
                'warranty_expiry' => now()->addMonths(16),
                'status' => 'active',
                'notes' => 'Installed and commissioned on site.',
            ]);

            // Repayment schedule (12 installments)
            for ($n = 1; $n <= 12; $n++) {
                $due = now()->subMonths(8)->addMonths($n);
                $paid = $due->isPast() && ! $defaulting;
                RepaymentSchedule::create([
                    'customer_id' => $customer->id,
                    'installment_number' => $n,
                    'due_date' => $due,
                    'amount_due' => 3500,
                    'amount_paid' => $paid ? 3500 : ($defaulting && $due->isPast() ? 0 : 0),
                    'status' => $paid ? 'paid' : ($due->isPast() ? 'overdue' : 'pending'),
                    'paid_at' => $paid ? $due : null,
                ]);
            }

            // Inbound SMS examples
            SmsMessage::create([
                'customer_id' => $customer->id, 'to' => 'MEGASOL', 'from' => $customer->phone,
                'body' => 'BALANCE', 'direction' => 'inbound', 'status' => 'delivered',
                'meta' => ['intent' => 'balance'], 'delivered_at' => now()->subDays(3),
            ]);
            if ($defaulting) {
                SmsMessage::create([
                    'customer_id' => $customer->id, 'to' => 'MEGASOL', 'from' => $customer->phone,
                    'body' => 'My unit is not working since last week, please help',
                    'direction' => 'inbound', 'status' => 'delivered',
                    'meta' => ['intent' => 'complaint', 'needs_follow_up' => true, 'follow_up_reason' => 'complaint'],
                    'delivered_at' => now()->subDays(1),
                ]);

                // Collections assignment for the defaulting customer
                AgentAssignment::updateOrCreate(
                    ['customer_id' => $customer->id, 'agent_id' => $agent->id, 'status' => 'assigned'],
                    [
                        'assigned_by' => User::where('email', 'admin@megasol.com')->value('id'),
                        'reason' => '52 days overdue, no response to reminders',
                        'amount_at_assignment' => $customer->outstanding_balance,
                        'assigned_at' => now()->subDays(2),
                    ],
                );
                $customer->update(['assigned_agent_id' => $agent->id]);
            }
        }
    }
}
