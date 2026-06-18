<?php

namespace App\Livewire\Campaigns;

use App\Models\Campaign;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Campaigns')]
class CampaignList extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $activeTab = 'all';

    /** @var array<string> */
    public array $selectedCampaigns = [];

    public bool $selectAll = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->selectedCampaigns = [];
        $this->selectAll         = false;
    }

    public function updatingActiveTab(): void
    {
        $this->resetPage();
        $this->selectedCampaigns = [];
        $this->selectAll         = false;
    }

    public function toggleSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedCampaigns = $this->buildQuery()
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        } else {
            $this->selectedCampaigns = [];
        }
    }

    public function deleteCampaign(int $id): void
    {
        $campaign = Campaign::findOrFail($id);
        if (! in_array($campaign->status, ['draft', 'sent', 'paused', 'canceled', 'cancelled', 'failed'])) {
            $this->dispatch('toast', type: 'error', message: 'Only draft or completed campaigns can be deleted.');
            return;
        }
        $campaign->delete();
        $this->selectedCampaigns = array_filter($this->selectedCampaigns, fn ($i) => (int) $i !== $id);
        $this->dispatch('toast', type: 'success', message: 'Campaign deleted.');
    }

    public function duplicateCampaign(int $id): void
    {
        $original  = Campaign::findOrFail($id);
        $duplicate = $original->replicate();
        $duplicate->name       = $original->name.' (Copy)';
        $duplicate->status     = 'draft';
        $duplicate->started_at   = null;
        $duplicate->completed_at = null;
        $duplicate->scheduled_at = null;
        $duplicate->stats        = null;
        $duplicate->save();
        $this->dispatch('toast', type: 'success', message: 'Campaign duplicated.');
    }

    public function bulkDeleteCampaigns(): void
    {
        if (empty($this->selectedCampaigns)) {
            return;
        }
        Campaign::whereIn('id', $this->selectedCampaigns)
            ->whereIn('status', ['draft', 'sent', 'paused', 'canceled', 'cancelled', 'failed'])
            ->delete();
        $count = count($this->selectedCampaigns);
        $this->selectedCampaigns = [];
        $this->selectAll         = false;
        $this->dispatch('toast', type: 'success', message: "{$count} campaign(s) deleted.");
    }

    public function bulkDuplicateCampaigns(): void
    {
        if (empty($this->selectedCampaigns)) {
            return;
        }
        $campaigns = Campaign::whereIn('id', $this->selectedCampaigns)->get();
        foreach ($campaigns as $original) {
            $duplicate = $original->replicate();
            $duplicate->name         = $original->name.' (Copy)';
            $duplicate->status       = 'draft';
            $duplicate->started_at   = null;
            $duplicate->completed_at = null;
            $duplicate->scheduled_at = null;
            $duplicate->stats        = null;
            $duplicate->save();
        }
        $count = count($this->selectedCampaigns);
        $this->selectedCampaigns = [];
        $this->selectAll         = false;
        $this->dispatch('toast', type: 'success', message: "{$count} campaign(s) duplicated.");
    }

    private function buildQuery()
    {
        return Campaign::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->activeTab !== 'all', fn ($q) => $q->where('status', $this->activeTab));
    }

    public function render()
    {
        $campaigns = $this->buildQuery()
            ->with('creator')
            ->withCount('recipients')
            ->orderByDesc('created_at')
            ->paginate(15);

        $totalCampaigns  = Campaign::count();
        $totalSent       = Campaign::whereIn('status', ['sent', 'sending'])->count();
        $draftCount      = Campaign::where('status', 'draft')->count();
        $scheduledCount  = Campaign::where('status', 'scheduled')->count();
        $sentCount       = Campaign::where('status', 'sent')->count();

        return view('livewire.campaigns.campaign-list', compact(
            'campaigns',
            'totalCampaigns',
            'totalSent',
            'draftCount',
            'scheduledCount',
            'sentCount',
        ));
    }
}
