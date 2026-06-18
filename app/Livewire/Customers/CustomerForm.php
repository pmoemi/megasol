<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class CustomerForm extends Component
{
    public ?Customer $customer = null;

    public string $account_number = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $phone = '';

    public string $email = '';

    public string $product_type = '';

    public string $location = '';

    public string $payment_status = 'current';

    public ?string $next_payment_date = null;

    public ?string $outstanding_balance = null;

    public string $lifecycle_stage = 'new';

    public ?string $activated_at = null;

    public function mount(?Customer $customer = null): void
    {
        $this->customer = $customer;

        if ($customer) {
            $this->fill([
                'account_number' => $customer->account_number ?? '',
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name ?? '',
                'phone' => $customer->phone,
                'email' => $customer->email ?? '',
                'product_type' => $customer->product_type ?? '',
                'location' => $customer->location ?? '',
                'payment_status' => $customer->payment_status,
                'next_payment_date' => $customer->next_payment_date?->format('Y-m-d'),
                'outstanding_balance' => $customer->outstanding_balance !== null ? (string) $customer->outstanding_balance : null,
                'lifecycle_stage' => $customer->lifecycle_stage,
                'activated_at' => $customer->activated_at?->format('Y-m-d\TH:i'),
            ]);
        }
    }

    public function getTitleProperty(): string
    {
        return $this->customer ? 'Edit Customer' : 'Create Customer';
    }

    protected function rules(): array
    {
        return [
            'account_number' => 'nullable|string|max:255',
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'product_type' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'payment_status' => 'required|in:current,due_soon,overdue,paid_off',
            'next_payment_date' => 'nullable|date',
            'outstanding_balance' => 'nullable|numeric|min:0',
            'lifecycle_stage' => 'required|in:new,active,at_risk,loyal,inactive',
            'activated_at' => 'nullable|date',
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->customer) {
            $this->customer->update($data);
            session()->flash('success', 'Customer updated successfully.');
        } else {
            Customer::create($data);
            session()->flash('success', 'Customer created successfully.');
        }

        $this->redirect(route('customers.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.customers.customer-form')
            ->title($this->title);
    }
}
