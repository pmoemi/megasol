<?php

namespace App\Livewire\Dashboard;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\SmsMessage;
use App\Models\Workflow;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class Overview extends Component
{
    public int   $totalCustomers   = 0;
    public int   $smsSentToday     = 0;
    public float $deliveryRate     = 0;
    public int   $activeCampaigns  = 0;
    public int   $overdueCustomers = 0;
    public int   $activeWorkflows  = 0;

    /** @var array<int> sparkline bar heights (percentages 0-100) */
    public array $sparklineHeights = [];

    public function mount(): void
    {
        $this->totalCustomers   = Customer::count();
        $this->overdueCustomers = Customer::where('payment_status', 'overdue')->count();
        $this->activeCampaigns  = Campaign::whereIn('status', ['scheduled', 'sending'])->count();
        $this->activeWorkflows  = Workflow::where('is_active', true)->count();

        $this->smsSentToday = SmsMessage::query()
            ->where('direction', 'outbound')
            ->whereDate('created_at', today())
            ->count();

        $sent      = SmsMessage::where('direction', 'outbound')->whereIn('status', ['sent', 'delivered', 'success'])->count();
        $delivered = SmsMessage::where('direction', 'outbound')->where('status', 'delivered')->count();
        $this->deliveryRate = $sent > 0 ? round(($delivered / $sent) * 100, 1) : 0;

        // 7-day SMS sparkline
        $this->sparklineHeights = collect(range(6, 0))->map(function ($daysAgo) {
            $count = SmsMessage::where('direction', 'outbound')
                ->whereDate('created_at', now()->subDays($daysAgo)->toDateString())
                ->count();
            return $count;
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
