<?php

namespace App\Exports;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class CampaignReportExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(
        protected ?int $campaignId = null,
    ) {}

    public function collection(): Collection
    {
        if ($this->campaignId) {
            return CampaignRecipient::query()
                ->with(['customer', 'campaign'])
                ->where('campaign_id', $this->campaignId)
                ->orderBy('id')
                ->get();
        }

        return Campaign::query()
            ->with('creator')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        if ($this->campaignId) {
            return [
                'Campaign',
                'Customer',
                'Phone',
                'Status',
                'Sent At',
                'Delivered At',
                'Error',
            ];
        }

        return [
            'Name',
            'Status',
            'Audience Type',
            'Total Recipients',
            'Sent',
            'Delivered',
            'Failed',
            'Created By',
            'Started At',
            'Completed At',
        ];
    }

    /**
     * @param  Campaign|CampaignRecipient  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        if ($row instanceof CampaignRecipient) {
            return [
                $row->campaign?->name,
                $row->customer?->full_name,
                $row->phone,
                $row->status,
                $row->sent_at?->format('Y-m-d H:i:s'),
                $row->delivered_at?->format('Y-m-d H:i:s'),
                $row->error_message,
            ];
        }

        $stats = $row->stats ?? [];

        return [
            $row->name,
            $row->status,
            $row->audience_type,
            $stats['total'] ?? $row->recipients()->count(),
            $stats['sent'] ?? 0,
            $stats['delivered'] ?? 0,
            $stats['failed'] ?? 0,
            $row->creator?->name,
            $row->started_at?->format('Y-m-d H:i:s'),
            $row->completed_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function title(): string
    {
        return $this->campaignId ? 'Campaign Recipients' : 'Campaign Summary';
    }
}
