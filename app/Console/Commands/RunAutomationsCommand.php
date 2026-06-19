<?php

namespace App\Console\Commands;

use App\Jobs\RunAutomationJob;
use App\Models\Automation;
use App\Services\Automation\AutomationRunner;
use App\Services\System\CronHealthService;
use Illuminate\Console\Command;

class RunAutomationsCommand extends Command
{
    protected $signature = 'sms:run-automations {--sync : Run synchronously instead of dispatching jobs}';

    protected $description = 'Run all active SMS automations';

    public function handle(AutomationRunner $runner): int
    {
        if (AutomationRunner::isPaused()) {
            $this->warn('SMS automations are paused in settings. Skipping.');
            app(CronHealthService::class)->recordAutomationsRun();

            return self::SUCCESS;
        }

        $automations = Automation::query()
            ->where('is_active', true)
            ->whereIn('type', AutomationRunner::SCHEDULED_TYPES)
            ->get();

        if ($automations->isEmpty()) {
            $this->info('No active automations found.');
            app(CronHealthService::class)->recordAutomationsRun();

            return self::SUCCESS;
        }

        $total = 0;

        foreach ($automations as $automation) {
            if ($this->option('sync')) {
                $sent = $runner->run($automation);
                $this->line("Automation [{$automation->name}]: {$sent} message(s) queued.");
                $total += $sent;
            } else {
                RunAutomationJob::dispatch($automation->id);
                $this->line("Automation [{$automation->name}]: job dispatched.");
                $total++;
            }
        }

        $this->info($this->option('sync')
            ? "Completed. {$total} message(s) queued."
            : "Dispatched {$total} automation job(s).");

        app(CronHealthService::class)->recordAutomationsRun();

        return self::SUCCESS;
    }
}
