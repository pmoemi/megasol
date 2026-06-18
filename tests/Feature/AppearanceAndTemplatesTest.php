<?php

namespace Tests\Feature;

use App\Models\MessageTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Support\AppTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AppearanceAndTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('x'), 'is_active' => true]);
        $this->actingAs($user);

        return $user;
    }

    public function test_sms_template_preview_and_use_in_campaign(): void
    {
        $this->actingUser();
        $tpl = MessageTemplate::create([
            'name' => 'Reminder', 'type' => 'custom', 'channel' => 'sms',
            'body' => 'Hi {first_name}, pay your balance.', 'is_active' => true,
        ]);

        Livewire::test(\App\Livewire\Templates\TemplateList::class)
            ->call('preview', $tpl->id)
            ->assertSee('Reminder')
            ->assertSee('pay your balance')
            ->assertSee('Use in campaign');

        Livewire::test(\App\Livewire\Templates\TemplateList::class)
            ->call('useInCampaign', $tpl->id)
            ->assertRedirect(route('campaigns.create', ['message_template' => $tpl->id]));
    }

    public function test_email_channel_template_preview_renders_html(): void
    {
        $this->actingUser();
        $tpl = MessageTemplate::create([
            'name' => 'Promo Email', 'type' => 'campaign', 'channel' => 'email',
            'subject' => 'Big sale', 'body' => 'fallback', 'body_html' => '<h1>Sale time</h1>', 'is_active' => true,
        ]);

        Livewire::test(\App\Livewire\Templates\TemplateList::class)
            ->call('preview', $tpl->id)
            ->assertSee('Big sale')
            ->assertSee('Email preview', false); // iframe title present
    }

    public function test_campaign_editor_preloads_sms_message_template(): void
    {
        $this->actingUser();
        $tpl = MessageTemplate::create([
            'name' => 'SMS T', 'type' => 'custom', 'channel' => 'sms',
            'body' => 'Hello from SMS template', 'is_active' => true,
        ]);

        Livewire::withQueryParams(['message_template' => $tpl->id])
            ->test(\App\Livewire\Campaigns\CampaignEditor::class)
            ->assertSet('channel', 'sms')
            ->assertSet('message_template_id', $tpl->id)
            ->assertSet('body', 'Hello from SMS template');
    }

    public function test_campaign_editor_preloads_email_message_template(): void
    {
        $this->actingUser();
        $tpl = MessageTemplate::create([
            'name' => 'Email T', 'type' => 'campaign', 'channel' => 'email',
            'subject' => 'Subject X', 'body' => 'f', 'body_html' => '<p>Body X</p>', 'is_active' => true,
        ]);

        Livewire::withQueryParams(['message_template' => $tpl->id])
            ->test(\App\Livewire\Campaigns\CampaignEditor::class)
            ->assertSet('channel', 'email')
            ->assertSet('subject', 'Subject X')
            ->assertSet('body_html', '<p>Body X</p>');
    }

    public function test_general_settings_saves_and_applies_timezone(): void
    {
        $this->actingUser();

        Livewire::test(\App\Livewire\Settings\GeneralSettings::class)
            ->set('app_name', 'MegaSol')
            ->set('timezone', 'Africa/Nairobi')
            ->set('currency', 'KES')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Africa/Nairobi', Setting::get(AppTheme::KEY_TIMEZONE));
        $this->assertSame('Africa/Nairobi', config('app.timezone'));
        $this->assertSame('MegaSol', Setting::get(AppTheme::KEY_APP_NAME));
        $this->assertSame('MegaSol', config('app.name'));
    }

    public function test_branding_save_persists_colors_and_css_vars(): void
    {
        $this->actingUser();

        Livewire::test(\App\Livewire\Settings\BrandingSettings::class)
            ->set('brand', '#123456')
            ->set('brand_strong', '#0a1a2a')
            ->set('accent', '#abcdef')
            ->call('save')
            ->assertRedirect(route('settings.branding'));

        $this->assertSame('#123456', Setting::get(AppTheme::KEY_BRAND));
        $this->assertStringContainsString('#123456', AppTheme::cssVariables());
        $this->assertSame('#123456', AppTheme::colors()['brand']);
    }

    public function test_branding_logo_and_favicon_can_be_uploaded_and_removed(): void
    {
        Storage::fake('public');
        $this->actingUser();

        $logo = UploadedFile::fake()->image('logo.png', 200, 200);
        $favicon = UploadedFile::fake()->image('favicon.png', 32, 32);

        $component = Livewire::test(\App\Livewire\Settings\BrandingSettings::class)
            ->set('logo', $logo)
            ->set('favicon', $favicon)
            ->call('save')
            ->assertHasNoErrors();

        $logoPath = Setting::get(AppTheme::KEY_LOGO_PATH);
        $faviconPath = Setting::get(AppTheme::KEY_FAVICON_PATH);

        $this->assertNotNull($logoPath);
        $this->assertNotNull($faviconPath);
        Storage::disk('public')->assertExists($logoPath);
        Storage::disk('public')->assertExists($faviconPath);
        $this->assertNotNull(AppTheme::logoUrl());
        $this->assertNotNull(AppTheme::faviconUrl());

        Livewire::test(\App\Livewire\Settings\BrandingSettings::class)
            ->call('removeLogo')
            ->assertHasNoErrors();

        $this->assertNull(Setting::get(AppTheme::KEY_LOGO_PATH));
        Storage::disk('public')->assertMissing($logoPath);
        $this->assertNull(AppTheme::logoUrl());
    }

    public function test_branding_rejects_oversized_logo(): void
    {
        Storage::fake('public');
        $this->actingUser();

        $logo = UploadedFile::fake()->create('logo.png', 3000, 'image/png');

        Livewire::test(\App\Livewire\Settings\BrandingSettings::class)
            ->set('logo', $logo)
            ->call('save')
            ->assertHasErrors(['logo' => 'max']);
    }

    public function test_public_storage_route_serves_uploaded_branding_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/logo.png', 'fake-image');

        $this->get('/storage/branding/logo.png')
            ->assertOk()
            ->assertHeader('Cache-Control');
    }

    public function test_branding_accepts_svg_logo(): void
    {
        Storage::fake('public');
        $this->actingUser();

        $logo = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>',
            'image/svg+xml',
        );

        Livewire::test(\App\Livewire\Settings\BrandingSettings::class)
            ->set('logo', $logo)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNotNull(Setting::get(AppTheme::KEY_LOGO_PATH));
    }

    public function test_email_builder_image_block_accepts_file_upload(): void
    {
        Storage::fake('public');
        $this->actingUser();

        $image = UploadedFile::fake()->image('photo.png', 400, 200);

        $component = Livewire::test(\App\Livewire\EmailTemplates\EmailBuilder::class)
            ->call('addBlock', 'image')
            ->set('imageUpload', $image)
            ->assertHasNoErrors();

        $src = $component->get('blocks.0.data.src');
        $this->assertNotEmpty($src);
        $this->assertStringContainsString('/storage/email-templates/', $src);
        $this->assertNull($component->get('imageUpload'));
    }

    public function test_email_builder_header_block_accepts_logo_upload(): void
    {
        Storage::fake('public');
        $this->actingUser();

        $logo = UploadedFile::fake()->image('logo.png', 100, 100);

        $component = Livewire::test(\App\Livewire\EmailTemplates\EmailBuilder::class)
            ->call('addBlock', 'header')
            ->set('headerLogoUpload', $logo)
            ->assertHasNoErrors();

        $logoUrl = $component->get('blocks.0.data.logo_url');
        $this->assertNotEmpty($logoUrl);
        $this->assertStringContainsString('/storage/email-templates/', $logoUrl);
        $this->assertNull($component->get('headerLogoUpload'));
    }

    public function test_email_builder_image_upload_rejects_invalid_type(): void
    {
        Storage::fake('public');
        $this->actingUser();

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        Livewire::test(\App\Livewire\EmailTemplates\EmailBuilder::class)
            ->call('addBlock', 'image')
            ->set('imageUpload', $file)
            ->assertHasErrors(['imageUpload']);
    }

    public function test_theme_studio_preset_and_save(): void
    {
        $this->actingUser();

        Livewire::test(\App\Livewire\Settings\ThemeStudio::class)
            ->call('applyPreset', 'emerald')
            ->assertSet('brand', '#10b981')
            ->call('save')
            ->assertRedirect(route('settings.theme-studio'));

        $this->assertSame('#10b981', Setting::get(AppTheme::KEY_BRAND));
    }
}
