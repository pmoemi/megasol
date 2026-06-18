<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\Campaign\CampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessCampaignJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $campaignId,
    ) {}

    public function handle(CampaignService $campaignService): void
    {
        $campaign = Campaign::find($this->campaignId);

        if (! $campaign) {
            return;
        }

        try {
            $campaignService->sendCampaign($campaign);
        } catch (\Throwable $e) {
            $campaign->update(['status' => 'failed']);

            Log::error('ProcessCampaignJob failed', [
                'campaign_id' => $this->campaignId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
