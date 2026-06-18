<?php

namespace App\Providers;

use App\Services\Sms\AfricasTalkingSmsService;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AfricasTalkingSmsService::class);
        $this->app->singleton(WorkflowEngine::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Apply SMTP / mail settings managed from the Settings UI over config.
        \App\Support\MailConfigurator::apply();

        // Apply Africa's Talking SMS settings managed from the Settings UI over config.
        \App\Support\SmsConfigurator::apply();

        // Apply general settings (app name, timezone) from the Settings UI.
        \App\Support\AppTheme::applyAppName();
        \App\Support\AppTheme::applyTimezone();

        // XSS-safe SVG icon rendering — outputs sanitized inner SVG markup
        Blade::directive('safeSvg', function (string $expression) {
            return "<?php echo strip_tags($expression, '<path><circle><rect><line><polyline><polygon><ellipse><g>'); ?>";
        });
    }
}
