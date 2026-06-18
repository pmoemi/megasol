<?php

namespace App\Livewire\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignLink;
use App\Models\CampaignRecipient;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
class CampaignReport extends Component
{
    use WithPagination;

    public Campaign $campaign;

    public string $recipientFilter = 'all';

    public string $activeTab = 'overview';

    public function mount(Campaign $campaign): void
    {
        $this->campaign = $campaign;
    }

    public function updatedRecipientFilter(): void
    {
        $this->resetPage();
    }

    public function updatedActiveTab(): void
    {
        $this->resetPage();
        $this->recipientFilter = 'all';
    }

    public function getTitleProperty(): string
    {
        return $this->campaign->name.' — Report';
    }

    public function render()
    {
        $campaign = $this->campaign;

        $recipientsCount = $campaign->recipients()->count();
        $deliveredCount = $campaign->recipients()->whereIn('status', ['delivered', 'sent'])->count();
        $failedCount = $campaign->recipients()->where('status', 'failed')->count();

        $stats = [
            'recipients' => $recipientsCount,
            'sent' => $campaign->recipients()->whereIn('status', ['sent', 'delivered'])->count(),
            'delivered' => $deliveredCount,
            'opened' => $campaign->opened_count,
            'open_rate' => $campaign->open_rate,
            'clicked' => $campaign->clicked_count,
            'click_rate' => $campaign->click_rate,
            'failed' => $failedCount,
        ];

        // Main recipient table (filterable by status)
        $recipientQuery = CampaignRecipient::where('campaign_id', $campaign->id)->with('customer');
        if ($this->recipientFilter !== 'all') {
            $recipientQuery->where('status', $this->recipientFilter);
        }
        $recipients = $recipientQuery->orderByDesc('updated_at')->paginate(20);

        // Tab-specific data
        $links = collect();
        $openedRecipients = collect();
        $clickedRecipients = collect();

        if (in_array($this->activeTab, ['overview', 'clicks'], true)) {
            $links = CampaignLink::where('campaign_id', $campaign->id)
                ->orderByDesc('clicks_count')
                ->limit(50)
                ->get();
        }

        if ($this->activeTab === 'opens') {
            $openedRecipients = CampaignRecipient::where('campaign_id', $campaign->id)
                ->whereNotNull('opened_at')
                ->with('customer')
                ->orderByDesc('opened_at')
                ->paginate(20);
        }

        if ($this->activeTab === 'clicks') {
            $clickedRecipients = CampaignRecipient::where('campaign_id', $campaign->id)
                ->whereNotNull('clicked_at')
                ->with('customer')
                ->orderByDesc('clicked_at')
                ->paginate(20);
        }

        // A/B test per-variant breakdown
        $variantStats = collect();
        if ($campaign->isAbTest()) {
            $variantStats = $campaign->abTestVariants()->orderBy('variant')->get()->map(function ($variant) use ($campaign) {
                $base = CampaignRecipient::where('campaign_id', $campaign->id)->where('ab_variant', $variant->variant);
                $sent = (clone $base)->whereIn('status', ['sent', 'delivered'])->count();
                $opened = (clone $base)->whereNotNull('opened_at')->count();
                $clicked = (clone $base)->whereNotNull('clicked_at')->count();

                return [
                    'variant' => $variant->variant,
                    'subject' => $variant->subject,
                    'percentage' => $variant->percentage,
                    'sent' => $sent,
                    'opened' => $opened,
                    'clicked' => $clicked,
                    'open_rate' => $sent > 0 ? round($opened / $sent * 100, 1) : 0,
                    'click_rate' => $sent > 0 ? round($clicked / $sent * 100, 1) : 0,
                ];
            });
        }

        return view('livewire.campaigns.campaign-report', [
            'campaign' => $campaign,
            'stats' => $stats,
            'recipients' => $recipients,
            'links' => $links,
            'openedRecipients' => $openedRecipients,
            'clickedRecipients' => $clickedRecipients,
            'variantStats' => $variantStats,
        ])->title($this->title);
    }
}
