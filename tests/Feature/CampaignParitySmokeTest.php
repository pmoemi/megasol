<?php

namespace Tests\Feature;

use App\Jobs\SendCampaignEmailJob;
use App\Models\AbTestVariant;
use App\Models\Campaign;
use App\Models\CampaignLink;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Models\Setting;
use App\Services\Campaign\CampaignService;
use App\Support\EmailBlockRenderer;
use App\Support\MailConfigurator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CampaignParitySmokeTest extends TestCase
{
    use RefreshDatabase;

    private function customer(array $attrs = []): Customer
    {
        return Customer::create(array_merge([
            'first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane'.Str::random(4).'@test.com',
            'phone' => '+25470'.random_int(1000000, 9999999), 'account_number' => 'A'.Str::random(4),
            'payment_status' => 'current', 'lifecycle_stage' => 'active',
        ], $attrs));
    }

    private function user(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('x'), 'is_active' => true]);
    }

    public function test_block_renderer_compiles_full_email_html(): void
    {
        $blocks = [
            ['type' => 'header', 'data' => ['company_name' => 'Acme', 'bg_color' => '#111111']],
            ['type' => 'text', 'data' => ['content' => '<p>Hello there</p>', 'align' => 'left', 'font_size' => '16']],
            ['type' => 'button', 'data' => ['text' => 'Go', 'url' => 'https://example.com']],
        ];

        $html = EmailBlockRenderer::compile($blocks);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('Acme', $html);
        $this->assertStringContainsString('Hello there', $html);
        $this->assertStringContainsString('https://example.com', $html);
    }

    public function test_specific_customers_audience_resolves_only_picked(): void
    {
        $a = $this->customer();
        $b = $this->customer();
        $this->customer(); // not picked

        $service = app(CampaignService::class);
        $count = $service->estimateAudienceCount('customers', ['customer_ids' => [$a->id, $b->id]], true);

        $this->assertSame(2, $count);
    }

    public function test_ab_test_send_splits_recipients_and_sets_uuid(): void
    {
        Queue::fake();
        $user = $this->user();
        for ($i = 0; $i < 4; $i++) {
            $this->customer();
        }

        $campaign = Campaign::create([
            'name' => 'AB', 'channel' => 'email', 'type' => 'ab_test', 'subject' => 'Base',
            'body_html' => '<p>Hi {first_name}</p>', 'status' => 'draft', 'audience_type' => 'all',
            'sends_per_minute' => 60, 'created_by' => $user->id,
        ]);
        $campaign->abTestVariants()->create(['variant' => 'A', 'subject' => 'Subject A', 'body_html' => '<p>A</p>', 'percentage' => 50]);
        $campaign->abTestVariants()->create(['variant' => 'B', 'subject' => 'Subject B', 'body_html' => '<p>B</p>', 'percentage' => 50]);

        app(CampaignService::class)->sendCampaign($campaign);

        $recipients = CampaignRecipient::where('campaign_id', $campaign->id)->get();
        $this->assertCount(4, $recipients);
        $this->assertTrue($recipients->every(fn ($r) => ! empty($r->uuid)));
        $this->assertSame(2, $recipients->where('ab_variant', 'A')->count());
        $this->assertSame(2, $recipients->where('ab_variant', 'B')->count());
        // Variant A subject was merged in (per-recipient subject persisted).
        $this->assertTrue($recipients->where('ab_variant', 'A')->every(fn ($r) => $r->subject === 'Subject A'));
        Queue::assertPushed(SendCampaignEmailJob::class, 4);
    }

    public function test_open_tracking_pixel_marks_recipient_and_campaign(): void
    {
        $user = $this->user();
        $customer = $this->customer();
        $campaign = Campaign::create([
            'name' => 'T', 'channel' => 'email', 'type' => 'regular', 'subject' => 'S',
            'body_html' => '<p>x</p>', 'status' => 'sent', 'audience_type' => 'all',
            'sends_per_minute' => 60, 'created_by' => $user->id, 'stats' => ['total' => 1, 'sent' => 1],
        ]);
        $recipient = CampaignRecipient::create([
            'uuid' => (string) Str::uuid(), 'campaign_id' => $campaign->id, 'customer_id' => $customer->id,
            'email' => $customer->email, 'phone' => '', 'status' => 'sent',
        ]);

        $response = $this->get("/track/campaign/{$recipient->uuid}/open");
        $response->assertOk();
        $this->assertSame('image/gif', $response->headers->get('Content-Type'));
        $this->assertNotNull($recipient->fresh()->opened_at);
        $this->assertSame(1, (int) ($campaign->fresh()->stats['opened'] ?? 0));
    }

    public function test_email_template_gallery_renders_with_preview(): void
    {
        $this->actingAs($this->user());

        $blocks = [['type' => 'text', 'data' => ['content' => '<p>Hi</p>', 'align' => 'left', 'font_size' => '16']]];
        $tpl = EmailTemplate::create([
            'name' => 'WelcomeTpl', 'subject' => 'Hi', 'category' => 'onboarding',
            'body_html' => EmailBlockRenderer::compile($blocks), 'blocks' => $blocks, 'is_active' => true,
        ]);

        Livewire::test(\App\Livewire\EmailTemplates\EmailTemplateList::class)
            ->assertSee('WelcomeTpl')
            ->call('preview', $tpl->id)
            ->assertSee('Use This Template');
    }

    public function test_campaign_editor_renders_ab_fields_and_customer_picker(): void
    {
        $this->actingAs($this->user());
        $this->customer(['first_name' => 'Jane', 'last_name' => 'Doe']);

        Livewire::test(\App\Livewire\Campaigns\CampaignEditor::class)
            ->set('channel', 'email')
            ->set('type', 'ab_test')
            ->assertSee('Subject A')
            ->set('currentStep', 3)
            ->set('audience_type', 'customers')
            ->set('customerSearch', 'Jane')
            ->assertSee('Specific Customers')
            ->assertSee('Jane');
    }

    public function test_campaign_report_renders_with_tabs(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $customer = $this->customer();

        $campaign = Campaign::create([
            'name' => 'Rep', 'channel' => 'email', 'type' => 'ab_test', 'subject' => 'Hi',
            'body_html' => '<p>x</p>', 'status' => 'sent', 'audience_type' => 'all',
            'sends_per_minute' => 60, 'created_by' => $user->id, 'stats' => ['opened' => 1, 'sent' => 1],
        ]);
        AbTestVariant::create(['campaign_id' => $campaign->id, 'variant' => 'A', 'subject' => 'A', 'percentage' => 50]);
        AbTestVariant::create(['campaign_id' => $campaign->id, 'variant' => 'B', 'subject' => 'B', 'percentage' => 50]);
        CampaignRecipient::create([
            'uuid' => (string) Str::uuid(), 'campaign_id' => $campaign->id, 'customer_id' => $customer->id,
            'email' => $customer->email, 'phone' => '', 'ab_variant' => 'A', 'status' => 'sent', 'opened_at' => now(),
        ]);

        Livewire::test(\App\Livewire\Campaigns\CampaignReport::class, ['campaign' => $campaign])
            ->assertSee('A/B Test Results')
            ->set('activeTab', 'opens')
            ->assertSee('Opened By')
            ->set('activeTab', 'recipients')
            ->assertSee('Recipients');
    }

    public function test_selecting_template_swaps_editor_content(): void
    {
        $this->actingAs($this->user());

        $blocksA = [['type' => 'text', 'data' => ['content' => '<p>AAA body</p>', 'align' => 'left', 'font_size' => '16']]];
        $blocksB = [['type' => 'text', 'data' => ['content' => '<p>BBB body</p>', 'align' => 'left', 'font_size' => '16']]];
        $a = EmailTemplate::create(['name' => 'Tpl A', 'subject' => 'Subject A', 'category' => 'general', 'body_html' => EmailBlockRenderer::compile($blocksA), 'blocks' => $blocksA, 'is_active' => true]);
        $b = EmailTemplate::create(['name' => 'Tpl B', 'subject' => 'Subject B', 'category' => 'general', 'body_html' => EmailBlockRenderer::compile($blocksB), 'blocks' => $blocksB, 'is_active' => true]);

        $component = Livewire::test(\App\Livewire\Campaigns\CampaignEditor::class)
            ->set('channel', 'email')
            ->set('email_template_id', $a->id);
        $component->assertSet('subject', 'Subject A');
        $this->assertStringContainsString('AAA body', $component->get('body_html'));

        // Switching to a different template must replace the content + subject.
        $component->set('email_template_id', $b->id);
        $component->assertSet('subject', 'Subject B');
        $this->assertStringContainsString('BBB body', $component->get('body_html'));
        $this->assertStringNotContainsString('AAA body', $component->get('body_html'));
    }

    public function test_mail_settings_save_persists_and_applies(): void
    {
        $this->actingAs($this->user());

        Livewire::test(\App\Livewire\Settings\MailSettings::class)
            ->set('mail_mailer', 'smtp')
            ->set('mail_host', 'smtp.example.com')
            ->set('mail_port', '587')
            ->set('mail_encryption', 'tls')
            ->set('mail_from_address', 'sender@example.com')
            ->set('mail_from_name', 'My Sender')
            ->call('save');

        $this->assertSame('smtp.example.com', Setting::get(MailConfigurator::KEY_HOST));
        $this->assertSame('sender@example.com', Setting::get(MailConfigurator::KEY_FROM_ADDRESS));
        $this->assertSame('sender@example.com', config('mail.from.address'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
    }

    public function test_campaign_from_persists_via_service(): void
    {
        $user = $this->user();
        $service = app(CampaignService::class);

        $campaign = $service->createCampaign([
            'name' => 'Sender test', 'channel' => 'email', 'subject' => 'Hi',
            'body_html' => '<p>x</p>', 'audience_type' => 'all', 'status' => 'draft',
            'from_name' => 'Acme', 'from_email' => 'hi@acme.com',
        ], $user);

        $this->assertSame('Acme', $campaign->from_name);
        $this->assertSame('hi@acme.com', $campaign->from_email);
    }

    public function test_click_tracking_redirects_and_counts(): void
    {
        $user = $this->user();
        $customer = $this->customer();
        $campaign = Campaign::create([
            'name' => 'T', 'channel' => 'email', 'type' => 'regular', 'subject' => 'S',
            'body_html' => '<p>x</p>', 'status' => 'sent', 'audience_type' => 'all',
            'sends_per_minute' => 60, 'created_by' => $user->id,
        ]);
        $recipient = CampaignRecipient::create([
            'uuid' => (string) Str::uuid(), 'campaign_id' => $campaign->id, 'customer_id' => $customer->id,
            'email' => $customer->email, 'phone' => '', 'status' => 'sent',
        ]);
        $link = CampaignLink::create(['campaign_id' => $campaign->id, 'original_url' => 'https://example.com/promo', 'tracking_hash' => 'abc', 'clicks_count' => 0]);

        $response = $this->get("/track/campaign/{$recipient->uuid}/click?url=".urlencode('https://example.com/promo'));
        $response->assertRedirect('https://example.com/promo');
        $this->assertNotNull($recipient->fresh()->clicked_at);
        $this->assertSame(1, $link->fresh()->clicks_count);
    }
}
