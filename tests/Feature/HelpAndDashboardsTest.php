<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HelpAndDashboardsTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $this->actingAs($user);

        return $user;
    }

    public function test_help_center_lists_filters_and_shows_article(): void
    {
        $this->actingUser();
        $article = HelpArticle::create([
            'slug' => 'creating-your-first-campaign', 'title' => 'Creating Your First Campaign',
            'category' => 'campaigns', 'excerpt' => 'Walk through the steps.', 'content' => 'Step one...',
            'is_published' => true, 'sort_order' => 1,
        ]);
        HelpArticle::create([
            'slug' => 'sms-length', 'title' => 'SMS Length', 'category' => 'sms',
            'content' => 'About segments', 'is_published' => true, 'sort_order' => 2,
        ]);

        Livewire::test(\App\Livewire\HelpCenter::class)
            ->assertSee('Creating Your First Campaign')
            ->assertSee('SMS Length')
            ->call('selectCategory', 'campaigns')
            ->assertSee('Creating Your First Campaign')
            ->assertDontSee('SMS Length')
            ->call('selectArticle', $article->id)
            ->assertSee('Step one...');
    }

    public function test_help_center_search(): void
    {
        $this->actingUser();
        HelpArticle::create(['slug' => 'a', 'title' => 'Unique Widget Topic', 'category' => 'sms', 'content' => 'x', 'is_published' => true]);

        Livewire::test(\App\Livewire\HelpCenter::class)
            ->set('search', 'Unique Widget')
            ->assertSee('Unique Widget Topic');
    }

    public function test_sms_templates_grid_renders(): void
    {
        $this->actingUser();
        MessageTemplate::create(['name' => 'GridTpl', 'type' => 'custom', 'channel' => 'sms', 'body' => 'Hi there', 'is_active' => true]);

        Livewire::test(\App\Livewire\Templates\TemplateList::class)
            ->assertSee('GridTpl')
            ->assertSee('Use');
    }

    public function test_analytics_dashboard_renders(): void
    {
        $this->actingUser();

        Livewire::test(\App\Livewire\Analytics\ReportDashboard::class)
            ->assertSee('Analytics')
            ->assertSee('Customers')
            ->assertSee('SMS Sent')
            ->assertSee('Emails Sent');
    }

    public function test_activity_log_renders(): void
    {
        $this->actingUser();

        Livewire::test(\App\Livewire\Activity\ActivityLog::class)
            ->assertSee('Activity Log');
    }

    public function test_sms_logs_page_renders_and_filters(): void
    {
        $this->actingUser();

        $customer = \App\Models\Customer::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@test.com',
            'phone' => '254712345678',
            'account_number' => 'ACC-001',
        ]);

        \App\Models\SmsMessage::create([
            'customer_id' => $customer->id,
            'to' => '254712345678',
            'body' => 'Your token is 12345',
            'direction' => 'outbound',
            'status' => 'success',
            'meta' => ['source' => 'paygro_latest_token'],
            'provider_message_id' => 'ATMSG001',
            'sent_at' => now(),
        ]);

        \App\Models\SmsMessage::create([
            'to' => '254725584124',
            'body' => 'Settings test ping',
            'direction' => 'outbound',
            'status' => 'success',
            'meta' => ['source' => 'settings_test'],
            'sent_at' => now(),
        ]);

        Livewire::test(\App\Livewire\Sms\SmsLogIndex::class)
            ->assertSee('SMS Logs')
            ->assertSee('Your token is 12345')
            ->assertSee('Jane Doe')
            ->assertSee('Settings test ping')
            ->set('hideTests', true)
            ->assertSee('Your token is 12345')
            ->assertDontSee('Settings test ping')
            ->set('search', 'ATMSG001')
            ->assertSee('Your token is 12345')
            ->assertDontSee('Settings test ping');
    }
}
