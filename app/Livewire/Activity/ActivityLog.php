<?php

namespace App\Livewire\Activity;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

#[Layout('components.layouts.app', ['title' => 'Activity Log'])]
class ActivityLog extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $activities = Activity::query()
            ->with('causer')
            ->when($this->search, function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(function ($q) use ($term) {
                    $q->where('description', 'like', $term)
                        ->orWhere('log_name', 'like', $term);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('livewire.activity.activity-log', compact('activities'));
    }
}
