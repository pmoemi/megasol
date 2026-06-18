<?php

namespace App\Console\Commands;

use App\Jobs\RunAutomationJob;
use App\Models\Automation;
use App\Services\Automation\AutomationRunner;
use Illuminate\Console\Command;

class RunAutomationsCommand extends Command
{
    protected $signature = 'sms:run-automations {--sync : Run synchronously instead of dispatching jobs}';

    protected $description = 'Run all active SMS automations';

    public function handle(AutomationRunner $runner): int
    {
        $automations = Automation::query()->where('is_active', true)->get();

        if ($automations->isEmpty()) {
            $this->info('No active automations found.');

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

        return self::SUCCESS;
    }
}
