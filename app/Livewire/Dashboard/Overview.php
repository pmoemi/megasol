<?php

namespace App\Livewire\Dashboard;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\SmsMessage;
use App\Models\Workflow;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class Overview extends Component
{
    public int $totalCustomers = 0;

    public int $smsSentToday = 0;

    public float $deliveryRate = 0;

    public int $activeCampaigns = 0;

    public int $overdueCustomers = 0;

    public int $activeWorkflows = 0;

    /** @var array<int> sparkline bar heights (percentages 0-100) */
    public array $sparklineHeights = [];

    public function mount(): void
    {
        $this->totalCustomers = Customer::count();
        $this->overdueCustomers = Customer::where('payment_status', 'overdue')->count();
        $this->activeCampaigns = Campaign::whereIn('status', ['scheduled', 'sending'])->count();
        $this->activeWorkflows = Workflow::where('is_active', true)->count();

        $reporting = SmsMessage::query()->forReporting();

        $this->smsSentToday = (clone $reporting)
            ->successfullySent()
            ->sentOnDate(today()->toDateString())
            ->count();

        $sent = (clone $reporting)->successfullySent()->count();
        $delivered = (clone $reporting)->where('status', 'delivered')->count();
        $this->deliveryRate = $sent > 0 ? round(($delivered / $sent) * 100, 1) : 0;

        // 7-day SMS sparkline — successfully sent only, excluding tests.
        $this->sparklineHeights = collect(range(6, 0))->map(function ($daysAgo) use ($reporting) {
            $date = now()->subDays($daysAgo)->toDateString();

            return (clone $reporting)
                ->successfullySent()
                ->sentOnDate($date)
                ->count();
        })->pipe(function ($counts) {
            $max = $counts->max() ?: 1;

            return $counts->map(fn ($c) => (int) round(($c / $max) * 100))->toArray();
        });
    }

    public function render()
    {
        $recentCustomers = Customer::latest()->limit(5)->get();

        return view('livewire.dashboard.overview', compact('recentCustomers'));
    }
}
