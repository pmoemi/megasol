<?php

namespace App\Livewire\Analytics;

use App\Exports\CampaignReportExport;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\EmailMessage;
use App\Models\SmsMessage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Layout('components.layouts.app', ['title' => 'Analytics'])]
class ReportDashboard extends Component
{
    /** @var array<int, array{date: string, count: int}> */
    public array $dailyStats = [];

    /** @var array<string, int> */
    public array $statusBreakdown = [];

    public int $totalSent = 0;

    public int $totalDelivered = 0;

    public int $totalFailed = 0;

    public int $totalCustomers = 0;

    public int $totalEmailsSent = 0;

    public int $totalEmailsOpened = 0;

    public function mount(): void
    {
        $this->totalCustomers = Customer::query()->count();

        $this->totalEmailsSent = EmailMessage::query()
            ->where('direction', 'outbound')
            ->where('status', 'sent')
            ->count();

        $this->totalEmailsOpened = EmailMessage::query()
            ->where('direction', 'outbound')
            ->whereNotNull('opened_at')
            ->count();

        $reporting = SmsMessage::query()->forReporting();

        $this->dailyStats = (clone $reporting)
            ->successfullySent()
            ->where('created_at', '>=', now()->subDays(14))
            ->selectRaw('DATE(COALESCE(sent_at, created_at)) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => $row->date, 'count' => (int) $row->count])
            ->all();

        $this->statusBreakdown = (clone $reporting)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $this->totalSent = (clone $reporting)->successfullySent()->count();

        $this->totalDelivered = (clone $reporting)->where('status', 'delivered')->count();

        $this->totalFailed = (clone $reporting)->where('status', 'failed')->count();
    }

    public function exportCampaigns(): BinaryFileResponse
    {
        return Excel::download(
            new CampaignReportExport,
            'campaign-report-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    public function render()
    {
        $recentCampaigns = Campaign::query()
            ->with('creator')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('livewire.analytics.report-dashboard', compact('recentCampaigns'));
    }
}
