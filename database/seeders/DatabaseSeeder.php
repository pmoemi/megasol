<?php

namespace Database\Seeders;

use App\Models\Automation;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'view customers',
            'manage customers',
            'view campaigns',
            'manage campaigns',
            'view automations',
            'manage automations',
            'view templates',
            'manage templates',
            'view analytics',
            'view activity',
            'view collections',
            'manage collections',
            'manage staff',
            'manage settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $managerRole = Role::firstOrCreate(['name' => 'Manager']);
        $agentRole = Role::firstOrCreate(['name' => 'Agent']);
        $fieldAgentRole = Role::firstOrCreate(['name' => 'Field Agent']);

        $adminRole->syncPermissions($permissions);

        $managerRole->syncPermissions([
            'view customers', 'manage customers',
            'view campaigns', 'manage campaigns',
            'view automations', 'manage automations',
            'view templates', 'manage templates',
            'view analytics', 'view activity',
            'view collections', 'manage collections',
        ]);

        $agentRole->syncPermissions([
            'view customers',
            'view campaigns',
            'view automations',
            'view templates',
            'view analytics',
        ]);

        // Field agents focus on assigned collections cases.
        $fieldAgentRole->syncPermissions([
            'view customers',
            'view collections',
            'manage collections',
        ]);

        $admin = User::updateOrCreate(
            ['email' => 'admin@megasol.com'],
            [
                'name' => 'MegaSol Admin',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $admin->syncRoles(['Admin']);

        $templateTypes = [
            'payment_reminder' => [
                'name' => 'Payment Reminder',
                'body' => 'Hi {first_name}, your payment of KES {balance} for account {account_number} is due on {next_payment_date}. Please pay to avoid service interruption.',
            ],
            'overdue' => [
                'name' => 'Overdue Notice',
                'body' => 'Dear {first_name}, your account {account_number} has an overdue balance of KES {balance}. Please contact us or pay immediately.',
            ],
            'welcome' => [
                'name' => 'Welcome Message',
                'body' => 'Welcome to MegaSol, {first_name}! Your account {account_number} is now active. We are glad to have you on board.',
            ],
            'seasonal' => [
                'name' => 'Seasonal Greeting',
                'body' => 'Season\'s greetings {first_name}! From all of us at MegaSol, thank you for being a valued customer.',
            ],
            'offer' => [
                'name' => 'Special Offer',
                'body' => 'Hi {first_name}, exclusive offer for account {account_number}! Contact us today for details on our latest promotion.',
            ],
            'tip' => [
                'name' => 'Energy Saving Tip',
                'body' => 'Hi {first_name}, tip of the week: Regular maintenance of your solar system helps maximize efficiency. Need help? Call us anytime.',
            ],
        ];

        // Every type gets a reusable SMS template (promotional ones are picked
        // when composing a Campaign). Only the genuinely triggered types become
        // scheduled automations — promotional blasts must go through Campaigns,
        // not the unattended hourly runner.
        foreach ($templateTypes as $type => $data) {
            $template = MessageTemplate::updateOrCreate(
                ['type' => $type, 'name' => $data['name']],
                ['body' => $data['body'], 'channel' => 'sms', 'is_active' => true],
            );

            if (! in_array($type, \App\Services\Automation\AutomationRunner::SCHEDULED_TYPES, true)) {
                continue;
            }

            Automation::updateOrCreate(
                ['type' => $type, 'name' => $data['name'].' Automation'],
                [
                    'message_template_id' => $template->id,
                    'is_active' => true,
                    'audience_type' => 'all',
                    'schedule_cron' => '0 8 * * *',
                ],
            );
        }

        // Core email template set (block-based, used by the email builder +
        // gallery). Kept intentionally small — just the curated base catalog,
        // matching the parent project. Industry-specific premium/enterprise
        // catalogs were removed.
        $this->call([
            EmailTemplateSeeder::class,
            HelpArticleSeeder::class,
            Customer360DemoSeeder::class,
        ]);

        \App\Models\Workflow::updateOrCreate(
            ['name' => 'Welcome Journey'],
            [
                'description' => 'Send welcome SMS then follow-up email',
                'trigger_type' => 'customer_created',
                'definition' => [
                    'steps' => [
                        ['type' => 'send_sms', 'body' => 'Welcome to MegaSol, {first_name}! Account {account_number} is active.'],
                        ['type' => 'delay', 'minutes' => 1],
                        ['type' => 'send_email', 'subject' => 'Welcome to MegaSol', 'body_html' => '<p>Dear {first_name},</p><p>Welcome to MegaSol. We are glad to have you.</p>'],
                    ],
                ],
                'is_active' => true,
                'created_by' => $admin->id,
            ],
        );
    }
}
