<?php

namespace App\Support;

/**
 * Shared catalogue of customer fields that segments can filter on, plus the
 * operators available per field type. Used by both the segment rule builder
 * UI and CampaignService when applying a segment's rules to a query.
 */
class SegmentFields
{
    /**
     * @var array<string, array{label: string, type: string, options?: array<string, string>}>
     */
    public const FIELDS = [
        'first_name' => ['label' => 'First Name', 'type' => 'text'],
        'last_name' => ['label' => 'Last Name', 'type' => 'text'],
        'email' => ['label' => 'Email', 'type' => 'text'],
        'phone' => ['label' => 'Phone', 'type' => 'text'],
        'account_number' => ['label' => 'Account Number', 'type' => 'text'],
        'product_type' => ['label' => 'Product Type', 'type' => 'text'],
        'location' => ['label' => 'Location', 'type' => 'text'],
        'payment_status' => [
            'label' => 'Payment Status',
            'type' => 'select',
            'options' => [
                'current' => 'Current',
                'due_soon' => 'Due Soon',
                'overdue' => 'Overdue',
                'paid_off' => 'Paid Off',
            ],
        ],
        'lifecycle_stage' => [
            'label' => 'Lifecycle Stage',
            'type' => 'select',
            'options' => [
                'new' => 'New',
                'active' => 'Active',
                'at_risk' => 'At Risk',
                'loyal' => 'Loyal',
                'inactive' => 'Inactive',
            ],
        ],
        'outstanding_balance' => ['label' => 'Outstanding Balance', 'type' => 'number'],
        'next_payment_date' => ['label' => 'Next Payment Date', 'type' => 'date'],
        'activated_at' => ['label' => 'Activated Date', 'type' => 'date'],
    ];

    /**
     * @var array<string, array<string, string>>
     */
    public const OPERATORS_BY_TYPE = [
        'text' => [
            'equals' => 'is',
            'not_equals' => 'is not',
            'contains' => 'contains',
            'not_contains' => 'does not contain',
            'starts_with' => 'starts with',
            'ends_with' => 'ends with',
            'is_empty' => 'is empty',
            'is_not_empty' => 'is not empty',
        ],
        'select' => [
            'equals' => 'is',
            'not_equals' => 'is not',
            'in' => 'is any of',
            'not_in' => 'is none of',
        ],
        'number' => [
            'equals' => '=',
            'not_equals' => '!=',
            'greater_than' => '>',
            'less_than' => '<',
            'greater_or_equal' => '>=',
            'less_or_equal' => '<=',
            'between' => 'between',
            'is_empty' => 'is empty',
            'is_not_empty' => 'is not empty',
        ],
        'date' => [
            'equals' => 'on',
            'date_before' => 'before',
            'date_after' => 'after',
            'date_between' => 'between',
            'is_empty' => 'is empty',
            'is_not_empty' => 'is not empty',
        ],
    ];

    /**
     * Operators whose value is a two-element [from, to] range.
     *
     * @var array<int, string>
     */
    public const RANGE_OPERATORS = ['between', 'date_between'];

    /**
     * Operators whose value is a list of options.
     *
     * @var array<int, string>
     */
    public const LIST_OPERATORS = ['in', 'not_in'];

    /**
     * Operators that don't need a value at all.
     *
     * @var array<int, string>
     */
    public const VALUELESS_OPERATORS = ['is_empty', 'is_not_empty'];

    public static function fieldType(string $field): string
    {
        return self::FIELDS[$field]['type'] ?? 'text';
    }

    public static function fieldLabel(string $field): string
    {
        return self::FIELDS[$field]['label'] ?? $field;
    }

    /**
     * @return array<string, string>
     */
    public static function optionsFor(string $field): array
    {
        return self::FIELDS[$field]['options'] ?? [];
    }

    /**
     * @return array<string, string>
     */
    public static function operatorsFor(string $field): array
    {
        return self::OPERATORS_BY_TYPE[self::fieldType($field)] ?? self::OPERATORS_BY_TYPE['text'];
    }

    public static function defaultOperatorFor(string $field): string
    {
        return array_key_first(self::operatorsFor($field)) ?? 'equals';
    }

    public static function isValidField(string $field): bool
    {
        return array_key_exists($field, self::FIELDS);
    }
}
