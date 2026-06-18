<?php

namespace App\Livewire\Settings;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.settings', ['title' => 'Notifications'])]
class NotificationSettings extends Component
{
    public function render()
    {
        return view('livewire.settings.notification-settings');
    }
}
