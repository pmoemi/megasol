<?php

namespace App\Jobs;

use App\Models\Automation;
use App\Services\Automation\AutomationRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RunAutomationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $automationId,
    ) {}

    public function handle(AutomationRunner $runner): void
    {
        $automation = Automation::find($this->automationId);

        if (! $automation || ! $automation->is_active) {
            return;
        }

        try {
            $runner->run($automation);
        } catch (\Throwable $e) {
            Log::error('RunAutomationJob failed', [
                'automation_id' => $this->automationId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
