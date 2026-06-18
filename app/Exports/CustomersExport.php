<?php

namespace App\Exports;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CustomersExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        protected ?string $search = null,
        /** @var array<int>|null */
        protected ?array $ids = null,
    ) {}

    /**
     * @return Builder<Customer>
     */
    public function query(): Builder
    {
        $query = Customer::query()->orderBy('last_name')->orderBy('first_name');

        if ($this->ids !== null && count($this->ids) > 0) {
            $query->whereIn('id', $this->ids);
        }

        if ($this->search) {
            $term = '%'.$this->search.'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('account_number', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Account Number',
            'First Name',
            'Last Name',
            'Phone',
            'Email',
            'Product Type',
            'Location',
            'Payment Status',
            'Next Payment Date',
            'Outstanding Balance',
            'Lifecycle Stage',
            'Activated At',
        ];
    }

    /**
     * @param  Customer  $customer
     * @return array<int, mixed>
     */
    public function map($customer): array
    {
        return [
            $customer->account_number,
            $customer->first_name,
            $customer->last_name,
            $customer->phone,
            $customer->email,
            $customer->product_type,
            $customer->location,
            $customer->payment_status,
            $customer->next_payment_date?->format('Y-m-d'),
            $customer->outstanding_balance,
            $customer->lifecycle_stage,
            $customer->activated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
