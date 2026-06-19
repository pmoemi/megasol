<?php

namespace App\Livewire\Sms;

use App\Models\SmsMessage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('SMS Logs')]
class SmsLogIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $direction = 'outbound';

    #[Url]
    public string $status = '';

    #[Url]
    public string $source = '';

    #[Url]
    public bool $hideTests = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingDirection(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSource(): void
    {
        $this->resetPage();
    }

    public function updatingHideTests(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'direction', 'status', 'source', 'hideTests']);
        $this->direction = 'outbound';
        $this->resetPage();
    }

    protected function baseQuery()
    {
        return SmsMessage::query()
            ->with(['customer:id,first_name,last_name,account_number', 'campaign:id,name'])
            ->when($this->direction !== '', fn ($q) => $q->where('direction', $this->direction))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->source !== '', fn ($q) => $q->where('meta->source', $this->source))
            ->when($this->hideTests, fn ($q) => $q->excludingTests())
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('to', 'like', $term)
                        ->orWhere('from', 'like', $term)
                        ->orWhere('body', 'like', $term)
                        ->orWhere('provider_message_id', 'like', $term)
                        ->orWhereHas('customer', function ($customer) use ($term) {
                            $customer->where('first_name', 'like', $term)
                                ->orWhere('last_name', 'like', $term)
                                ->orWhere('account_number', 'like', $term);
                        });
                });
            });
    }

    public function render()
    {
        $query = $this->baseQuery();

        $messages = (clone $query)
            ->orderByDesc('created_at')
            ->paginate(25);

        $filtered = clone $query;

        $stats = [
            'total' => (clone $filtered)->count(),
            'success' => (clone $filtered)->whereIn('status', SmsMessage::SUCCESS_STATUSES)->count(),
            'failed' => (clone $filtered)->where('status', 'failed')->count(),
            'today' => (clone $filtered)->whereDate('created_at', today())->count(),
        ];

        $sources = SmsMessage::query()
            ->whereNotNull('meta')
            ->get()
            ->pluck('meta')
            ->filter(fn ($meta) => is_array($meta) && ! empty($meta['source']))
            ->pluck('source')
            ->unique()
            ->sort()
            ->values();

        return view('livewire.sms.sms-log-index', compact('messages', 'stats', 'sources'));
    }
}
