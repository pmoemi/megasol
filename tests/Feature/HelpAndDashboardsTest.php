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
}
