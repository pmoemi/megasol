<?php

namespace App\Http\Controllers;

use App\Models\CampaignRecipient;
use App\Services\Campaign\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrackingController extends Controller
{
    /** 1x1 transparent GIF pixel (base64). */
    protected const TRACKING_PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /**
     * Track a campaign email open via the invisible pixel.
     * Records the first open on the recipient and bumps the campaign counter.
     */
    public function campaignOpen(string $uuid, CampaignService $campaigns): Response
    {
        try {
            $recipient = CampaignRecipient::where('uuid', $uuid)->first();

            if ($recipient && ! $recipient->opened_at) {
                $recipient->update(['opened_at' => now()]);
                $campaigns->incrementCampaignStats($recipient->campaign, 'opened');
            }
        } catch (\Throwable $e) {
            // Never fail the tracking pixel — always return the image.
            Log::warning('Tracking: campaign open failed', ['uuid' => $uuid, 'error' => $e->getMessage()]);
        }

        return response(base64_decode(self::TRACKING_PIXEL), 200, [
            'Content-Type' => 'image/gif',
            'Content-Length' => 43,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
        ]);
    }

    /**
     * Track a campaign email link click, then 302 redirect to the original URL.
     */
    public function campaignClick(string $uuid, Request $request, CampaignService $campaigns): RedirectResponse
    {
        $originalUrl = (string) $request->query('url', '/');

        try {
            $recipient = CampaignRecipient::where('uuid', $uuid)->first();

            if ($recipient) {
                if (! $recipient->clicked_at) {
                    $recipient->update(['clicked_at' => now()]);
                    $campaigns->incrementCampaignStats($recipient->campaign, 'clicked');
                }

                DB::table('campaign_links')
                    ->where('campaign_id', $recipient->campaign_id)
                    ->where('original_url', $originalUrl)
                    ->increment('clicks_count');
            }
        } catch (\Throwable $e) {
            Log::warning('Tracking: campaign click failed', ['uuid' => $uuid, 'error' => $e->getMessage()]);
        }

        return redirect()->away($this->safeUrl($originalUrl));
    }

    /**
     * Only allow redirects to absolute http(s) URLs; otherwise fall back home.
     */
    protected function safeUrl(string $url): string
    {
        if (preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        return url('/');
    }
}
