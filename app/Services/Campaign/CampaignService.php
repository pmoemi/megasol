<?php

namespace App\Services\Campaign;

use App\Jobs\SendCampaignEmailJob;
use App\Jobs\SendSmsJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use App\Models\CustomerList;
use App\Models\EmailMessage;
use App\Models\Segment;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\Sms\AfricasTalkingSmsService;
use App\Support\SegmentFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CampaignService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createCampaign(array $data, User $creator): Campaign
    {
        return Campaign::create([
            'name' => $data['name'],
            'channel' => $data['channel'] ?? 'sms',
            'type' => $data['type'] ?? 'regular',
            'subject' => $data['subject'] ?? null,
            'message_template_id' => $data['message_template_id'] ?? null,
            'email_template_id' => $data['email_template_id'] ?? null,
            'body' => $data['body'] ?? null,
            'body_html' => $data['body_html'] ?? null,
            'preview_text' => $data['preview_text'] ?? null,
            'from_name' => $data['from_name'] ?? null,
            'from_email' => $data['from_email'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'audience_type' => $data['audience_type'],
            'audience_meta' => $data['audience_meta'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'sends_per_minute' => $data['sends_per_minute'] ?? 60,
            'batch_size' => $data['batch_size'] ?? 0,
            'batch_delay_seconds' => $data['batch_delay_seconds'] ?? 0,
            'created_by' => $creator->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCampaign(Campaign $campaign, array $data): Campaign
    {
        $campaign->update([
            'name' => $data['name'] ?? $campaign->name,
            'channel' => $data['channel'] ?? $campaign->channel,
            'type' => $data['type'] ?? $campaign->type,
            'subject' => $data['subject'] ?? $campaign->subject,
            'message_template_id' => $data['message_template_id'] ?? $campaign->message_template_id,
            'email_template_id' => $data['email_template_id'] ?? $campaign->email_template_id,
            'body' => $data['body'] ?? $campaign->body,
            'body_html' => $data['body_html'] ?? $campaign->body_html,
            'preview_text' => $data['preview_text'] ?? $campaign->preview_text,
            'from_name' => array_key_exists('from_name', $data) ? $data['from_name'] : $campaign->from_name,
            'from_email' => array_key_exists('from_email', $data) ? $data['from_email'] : $campaign->from_email,
            'audience_type' => $data['audience_type'] ?? $campaign->audience_type,
            'audience_meta' => $data['audience_meta'] ?? $campaign->audience_meta,
            'scheduled_at' => $data['scheduled_at'] ?? $campaign->scheduled_at,
            'status' => $data['status'] ?? $campaign->status,
            'sends_per_minute' => $data['sends_per_minute'] ?? $campaign->sends_per_minute,
            'batch_size' => $data['batch_size'] ?? $campaign->batch_size,
            'batch_delay_seconds' => $data['batch_delay_seconds'] ?? $campaign->batch_delay_seconds,
        ]);

        return $campaign->fresh();
    }

    public function isEmail(Campaign $campaign): bool
    {
        return ($campaign->channel ?: 'sms') === 'email';
    }

    /**
     * @return Collection<int, Customer>
     */
    public function resolveAudience(Campaign $campaign): Collection
    {
        return $this->resolveAudienceQuery(
            $campaign->audience_type,
            $campaign->audience_meta ?? [],
            $this->isEmail($campaign),
        )->get();
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return Builder<Customer>
     */
    public function resolveAudienceQuery(string $audienceType, array $meta = [], bool $email = false): Builder
    {
        $query = Customer::query();

        if ($email) {
            $query->whereNotNull('email')->where('email', '!=', '');
        } else {
            $query->whereNotNull('phone')->where('phone', '!=', '');
        }

        return match ($audienceType) {
            'all' => $query,
            'segment' => $this->applySegmentRules($query, (int) ($meta['segment_id'] ?? 0)),
            'list' => $this->applyListFilter($query, (int) ($meta['list_id'] ?? 0)),
            'customers' => $query->whereIn('id', array_map('intval', $meta['customer_ids'] ?? []) ?: [0]),
            'payment_status' => $query->where('payment_status', $meta['payment_status'] ?? 'current'),
            'lifecycle' => $query->where('lifecycle_stage', $meta['lifecycle_stage'] ?? 'active'),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    protected function applyListFilter(Builder $query, int $listId): Builder
    {
        if ($listId <= 0 || ! CustomerList::whereKey($listId)->exists()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            'customerLists',
            fn (Builder $q) => $q->where('customer_lists.id', $listId),
        );
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    protected function applySegmentRules(Builder $query, int $segmentId): Builder
    {
        $segment = Segment::find($segmentId);

        if (! $segment || ! is_array($segment->rules)) {
            return $query->whereRaw('1 = 0');
        }

        return $this->applyRules($query, $segment->rules);
    }

    /**
     * Apply a segment's rules (`{"match": "all"|"any", "conditions": [...]}`)
     * to a customer query. Each condition is `{field, operator, value}`,
     * where `field` is one of App\Support\SegmentFields::FIELDS.
     *
     * @param  Builder<Customer>  $query
     * @param  array<string, mixed>  $rules
     * @return Builder<Customer>
     */
    public function applyRules(Builder $query, array $rules): Builder
    {
        $conditions = array_filter(
            (array) ($rules['conditions'] ?? []),
            fn ($condition) => SegmentFields::isValidField($condition['field'] ?? ''),
        );

        if (empty($conditions)) {
            return $query->whereRaw('1 = 0');
        }

        $boolean = ($rules['match'] ?? 'all') === 'any' ? 'or' : 'and';

        $query->where(function (Builder $q) use ($conditions, $boolean) {
            foreach ($conditions as $condition) {
                $this->applyCondition($q, $condition, $boolean);
            }
        });

        return $query;
    }

    /**
     * @param  Builder<Customer>  $query
     * @param  array<string, mixed>  $condition
     */
    protected function applyCondition(Builder $query, array $condition, string $boolean): void
    {
        $field = $condition['field'];
        $operator = $condition['operator'] ?? SegmentFields::defaultOperatorFor($field);
        $value = $condition['value'] ?? null;
        $isDate = SegmentFields::fieldType($field) === 'date';

        $callback = function (Builder $q) use ($field, $operator, $value, $isDate) {
            match ($operator) {
                'equals' => $isDate ? $q->whereDate($field, '=', $value) : $q->where($field, '=', $value),
                'not_equals' => $isDate ? $q->whereDate($field, '!=', $value) : $q->where($field, '!=', $value),
                'contains' => $q->where($field, 'like', "%{$value}%"),
                'not_contains' => $q->where($field, 'not like', "%{$value}%"),
                'starts_with' => $q->where($field, 'like', "{$value}%"),
                'ends_with' => $q->where($field, 'like', "%{$value}"),
                'greater_than' => $q->where($field, '>', $value),
                'less_than' => $q->where($field, '<', $value),
                'greater_or_equal' => $q->where($field, '>=', $value),
                'less_or_equal' => $q->where($field, '<=', $value),
                'in' => $q->whereIn($field, (array) $value),
                'not_in' => $q->whereNotIn($field, (array) $value),
                'between' => is_array($value) && count($value) === 2
                    ? $q->whereBetween($field, $value)
                    : null,
                'date_before' => $q->whereDate($field, '<', $value),
                'date_after' => $q->whereDate($field, '>', $value),
                'date_between' => is_array($value) && count($value) === 2
                    ? $q->whereDate($field, '>=', $value[0])->whereDate($field, '<=', $value[1])
                    : null,
                'is_empty' => $q->where(fn (Builder $qq) => $qq->whereNull($field)->orWhere($field, '')),
                'is_not_empty' => $q->where(fn (Builder $qq) => $qq->whereNotNull($field)->where($field, '!=', '')),
                default => null,
            };
        };

        $boolean === 'or' ? $query->orWhere($callback) : $query->where($callback);
    }

    /**
     * Live preview of how many customers match a (not-yet-saved) set of
     * segment rules. Used by the segment rule builder UI.
     *
     * @param  array<string, mixed>  $rules
     */
    public function previewSegmentCount(array $rules): int
    {
        return $this->applyRules(Customer::query(), $rules)->count();
    }

    public function mergeTags(string $body, Customer $customer): string
    {
        $replacements = [
            '{first_name}' => $customer->first_name ?? '',
            '{last_name}' => $customer->last_name ?? '',
            '{phone}' => $customer->phone ?? '',
            '{email}' => $customer->email ?? '',
            '{account_number}' => $customer->account_number ?? '',
            '{balance}' => number_format((float) ($customer->outstanding_balance ?? 0), 2),
            '{next_payment_date}' => $customer->next_payment_date?->format('M j, Y') ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $body);
    }

    public function sendCampaign(Campaign $campaign): void
    {
        if (in_array($campaign->status, ['sent', 'sending', 'cancelled'], true)) {
            return;
        }

        $campaign->update([
            'status' => 'sending',
            'started_at' => now(),
        ]);

        $customers = $this->resolveAudience($campaign);
        $isEmail = $this->isEmail($campaign);
        $perMinute = max(1, (int) ($campaign->sends_per_minute ?: 60));
        $batchSize = max(0, (int) $campaign->batch_size);
        $batchDelaySeconds = max(0, (int) $campaign->batch_delay_seconds);

        // A/B test: pre-compute how many recipients get variant A so we can
        // split the audience deterministically by the configured percentage.
        $variants = ($isEmail && $campaign->isAbTest())
            ? $campaign->abTestVariants()->get()->keyBy('variant')
            : collect();
        $countA = $variants->isNotEmpty()
            ? (int) ceil($customers->count() * (int) ($variants['A']->percentage ?? 50) / 100)
            : 0;

        $dispatched = 0;
        $failed = 0;
        $smsService = app(AfricasTalkingSmsService::class);

        DB::transaction(function () use ($campaign, $customers, $isEmail, $perMinute, $batchSize, $batchDelaySeconds, $variants, $countA, &$dispatched, &$failed, $smsService) {
            foreach ($customers as $customer) {
                $delay = $this->dispatchDelay($dispatched, $perMinute, $batchSize, $batchDelaySeconds);

                if ($isEmail) {
                    // Resolve the A/B variant (if any) for this recipient.
                    $variantKey = null;
                    $subjectTemplate = $campaign->subject ?? 'Message from MegaSol';
                    $htmlTemplate = $campaign->body_html ?? '';
                    if ($variants->isNotEmpty()) {
                        $variantKey = $dispatched < $countA ? 'A' : 'B';
                        $variant = $variants[$variantKey] ?? null;
                        $subjectTemplate = $variant?->subject ?: $subjectTemplate;
                        $htmlTemplate = $variant?->body_html ?: $htmlTemplate;
                    }

                    $subject = $this->mergeTags($subjectTemplate, $customer);
                    $html = $this->mergeTags($htmlTemplate, $customer);

                    $recipient = CampaignRecipient::create([
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'campaign_id' => $campaign->id,
                        'customer_id' => $customer->id,
                        'email' => $customer->email,
                        'phone' => $customer->phone ?? '',
                        'subject' => $subject,
                        'body' => '',
                        'body_html' => $html,
                        'ab_variant' => $variantKey,
                        'status' => 'queued',
                    ]);

                    SendCampaignEmailJob::dispatch($recipient->id)
                        ->onQueue('campaigns')
                        ->delay($delay);
                } else {
                    if ($customer->sms_opted_out) {
                        CampaignRecipient::create([
                            'uuid' => (string) \Illuminate\Support\Str::uuid(),
                            'campaign_id' => $campaign->id,
                            'customer_id' => $customer->id,
                            'phone' => $customer->phone,
                            'email' => $customer->email,
                            'body' => $this->mergeTags($campaign->body ?? '', $customer),
                            'status' => 'failed',
                        ]);
                        $failed++;

                        continue;
                    }

                    $phone = $smsService->resolveRecipientPhone((string) ($customer->phone ?? ''));

                    if ($phone === null) {
                        $body = $this->mergeTags($campaign->body ?? '', $customer);

                        CampaignRecipient::create([
                            'uuid' => (string) \Illuminate\Support\Str::uuid(),
                            'campaign_id' => $campaign->id,
                            'customer_id' => $customer->id,
                            'phone' => $customer->phone,
                            'email' => $customer->email,
                            'body' => $body,
                            'status' => 'failed',
                        ]);

                        SmsMessage::create([
                            'customer_id' => $customer->id,
                            'campaign_id' => $campaign->id,
                            'to' => $customer->phone ?? '',
                            'body' => $body,
                            'direction' => 'outbound',
                            'status' => 'failed',
                            'error_message' => 'Invalid Kenyan mobile number.',
                            'meta' => ['source' => 'campaign', 'campaign_id' => $campaign->id],
                        ]);

                        $failed++;

                        continue;
                    }

                    $body = $this->mergeTags($campaign->body ?? '', $customer);

                    $recipient = CampaignRecipient::create([
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'campaign_id' => $campaign->id,
                        'customer_id' => $customer->id,
                        'phone' => $phone,
                        'email' => $customer->email,
                        'body' => $body,
                        'status' => 'queued',
                    ]);

                    $smsMessage = SmsMessage::create([
                        'customer_id' => $customer->id,
                        'campaign_id' => $campaign->id,
                        'campaign_recipient_id' => $recipient->id,
                        'to' => $phone,
                        'body' => $body,
                        'direction' => 'outbound',
                        'status' => 'queued',
                        'meta' => ['source' => 'campaign', 'campaign_id' => $campaign->id],
                    ]);

                    SendSmsJob::dispatch(
                        to: $phone,
                        message: $body,
                        smsMessageId: $smsMessage->id,
                        meta: [
                            'source' => 'campaign',
                            'campaign_id' => $campaign->id,
                            'campaign_recipient_id' => $recipient->id,
                            'customer_id' => $customer->id,
                        ],
                    )->onQueue('campaigns')->delay($delay);
                }

                $dispatched++;
            }
        });

        $stats = [
            'total' => $customers->count(),
            'queued' => max(0, $customers->count() - $failed),
            'sent' => 0,
            'delivered' => 0,
            'failed' => $failed,
        ];

        $campaign->update([
            'status' => $customers->isEmpty() ? 'sent' : 'sending',
            'completed_at' => $customers->isEmpty() ? now() : null,
            'stats' => $stats,
        ]);
    }

    /**
     * Compute the queue delay for the Nth dispatched message, honouring both
     * the steady send rate (sends_per_minute) and optional batching
     * (batch_size + batch_delay_seconds pause between batches).
     */
    protected function dispatchDelay(int $index, int $perMinute, int $batchSize, int $batchDelaySeconds): \Illuminate\Support\Carbon
    {
        $delayMs = (int) ($index * (60000 / max(1, $perMinute)));

        if ($batchSize > 0 && $batchDelaySeconds > 0) {
            $delayMs += intdiv($index, $batchSize) * $batchDelaySeconds * 1000;
        }

        return now()->addMilliseconds($delayMs);
    }

    public function incrementCampaignStats(?Campaign $campaign, string $field): void
    {
        if (! $campaign) {
            return;
        }

        $stats = $campaign->stats ?? [];
        $stats[$field] = ($stats[$field] ?? 0) + 1;

        $total = $stats['total'] ?? 0;
        $sent = $stats['sent'] ?? 0;
        $failed = $stats['failed'] ?? 0;

        if ($total > 0 && ($sent + $failed) >= $total) {
            $campaign->update([
                'stats' => $stats,
                'status' => 'sent',
                'completed_at' => now(),
            ]);

            return;
        }

        $campaign->update(['stats' => $stats]);
    }

    public function estimateAudienceCount(string $audienceType, array $meta = [], bool $email = false): int
    {
        return $this->resolveAudienceQuery($audienceType, $meta, $email)->count();
    }
}
