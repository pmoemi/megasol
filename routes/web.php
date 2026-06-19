<?php

use App\Http\Controllers\PublicStorageController;
use App\Livewire\Activity\ActivityLog;
use App\Livewire\Analytics\ReportDashboard;
use App\Livewire\Campaigns\CampaignEditor;
use App\Livewire\Campaigns\CampaignList;
use App\Livewire\Campaigns\CampaignReport;
use App\Livewire\Customers\CustomerIndex;
use App\Livewire\Customers\CustomerListManager;
use App\Livewire\Dashboard\Overview;
use App\Livewire\EmailTemplates\EmailBuilder;
use App\Livewire\EmailTemplates\EmailTemplateList;
use App\Livewire\Inventory\InventoryIndex;
use App\Livewire\Settings\BrandingSettings;
use App\Livewire\Settings\GeneralSettings;
use App\Livewire\Settings\MailSettings;
use App\Livewire\Settings\NotificationSettings;
use App\Livewire\Settings\PayGroSettings;
use App\Livewire\Settings\ThemeStudio;
use App\Livewire\Settings\ProfileSettings;
use App\Livewire\Settings\SecuritySettings;
use App\Livewire\Customers\CustomerProfile;
use App\Livewire\Customers\SegmentManager;
use App\Livewire\Settings\SmsSettings;
use App\Livewire\Settings\SystemSettings;
use App\Livewire\Sms\SmsLogIndex;
use App\Livewire\Staff\StaffManager;
use App\Livewire\Templates\TemplateEditor;
use App\Livewire\Templates\TemplateList;
use App\Livewire\Workflows\WorkflowBuilder;
use App\Livewire\Workflows\WorkflowList;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Public uploads (branding logo/favicon, etc.) — works without storage:link.
Route::get('/storage/{path}', [PublicStorageController::class, 'show'])
    ->where('path', '.+')
    ->name('storage.public');

// Email open/click tracking (no auth — UUID-based lookups).
Route::get('/track/campaign/{uuid}/open', [\App\Http\Controllers\TrackingController::class, 'campaignOpen']);
Route::get('/track/campaign/{uuid}/click', [\App\Http\Controllers\TrackingController::class, 'campaignClick']);

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', Overview::class)->name('dashboard');

    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', CustomerIndex::class)->name('index');
        Route::get('/groups', CustomerListManager::class)->name('groups');
        Route::get('/segments', SegmentManager::class)->name('segments');
        // Customer 360 profile — keep last so literal segments above win.
        Route::get('/{customer}', CustomerProfile::class)->name('show');
    });

    Route::get('/collections', \App\Livewire\Collections\CollectionsBoard::class)->name('collections');
    Route::get('/inventory', InventoryIndex::class)->name('inventory');

    Route::prefix('campaigns')->name('campaigns.')->group(function () {
        Route::get('/', CampaignList::class)->name('index');
        // Email template gallery under the campaigns area (parity with the
        // parent project's /campaigns/templates). Same gallery as
        // /email-templates — includes preview + "use in campaign".
        Route::get('/templates', EmailTemplateList::class)->name('templates');
        Route::get('/create', CampaignEditor::class)->name('create');
        Route::get('/{campaign}/edit', CampaignEditor::class)->name('edit');
        Route::get('/{campaign}/report', CampaignReport::class)->name('report');
    });

    Route::prefix('templates')->name('templates.')->group(function () {
        Route::get('/', TemplateList::class)->name('index');
        Route::get('/create', TemplateEditor::class)->name('create');
        Route::get('/{template}/edit', TemplateEditor::class)->name('edit');
    });

    Route::prefix('email-templates')->name('email-templates.')->group(function () {
        Route::get('/', EmailTemplateList::class)->name('index');
        Route::get('/create', EmailBuilder::class)->name('create');
        Route::get('/{template}/edit', EmailBuilder::class)->name('edit');
    });

    Route::prefix('workflows')->name('workflows.')->group(function () {
        Route::get('/', WorkflowList::class)->name('index');
        Route::get('/create', WorkflowBuilder::class)->name('create');
        Route::get('/{workflow}/edit', WorkflowBuilder::class)->name('edit');
    });

    Route::get('/analytics', ReportDashboard::class)->name('analytics');
    Route::get('/sms/logs', SmsLogIndex::class)->name('sms.logs');
    Route::get('/activity', ActivityLog::class)->name('activity');
    Route::get('/help', \App\Livewire\HelpCenter::class)->name('help');
    Route::get('/staff', StaffManager::class)->name('staff');
    Route::redirect('/settings', '/settings/general')->name('settings');

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/general', GeneralSettings::class)->name('general');
        Route::get('/branding', BrandingSettings::class)->name('branding');
        Route::get('/theme-studio', ThemeStudio::class)->name('theme-studio');
        Route::get('/paygro', PayGroSettings::class)->name('paygro');
        Route::get('/sms', SmsSettings::class)->name('sms');
        Route::get('/email', MailSettings::class)->name('email');
        Route::get('/profile', ProfileSettings::class)->name('profile');
        Route::get('/security', SecuritySettings::class)->name('security');
        Route::get('/notifications', NotificationSettings::class)->name('notifications');
        Route::get('/system', SystemSettings::class)->name('system');
    });
});
