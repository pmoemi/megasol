<?php

namespace App\Livewire\Customers;

use App\Models\Segment;
use App\Services\Campaign\CampaignService;
use App\Support\SegmentFields;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Segments')]
class SegmentManager extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public string $match = 'all';

    /** @var array<int, array{field: string, operator: string, value: mixed}> */
    public array $conditions = [];

    public ?int $confirmDeleteId = null;

    public function mount(): void
    {
        $this->addCondition();
    }

    public function createSegment(): void
    {
        $this->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'match' => 'required|in:all,any',
            'conditions' => 'required|array|min:1',
            'conditions.*.field' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! SegmentFields::isValidField($value)) {
                    $fail('Invalid filter field selected.');
                }
            }],
            'conditions.*.operator' => 'required|string',
        ]);

        $rules = [
            'match' => $this->match,
            'conditions' => $this->cleanConditions(),
        ];

        $count = app(CampaignService::class)->previewSegmentCount($rules);

        if ($this->editingId) {
            $segment = Segment::findOrFail($this->editingId);
            $segment->update([
                'name' => $this->name,
                'description' => $this->description,
                'rules' => $rules,
                'customers_count' => $count,
            ]);
            session()->flash('success', "Segment \"{$this->name}\" updated.");
        } else {
            Segment::create([
                'name' => $this->name,
                'description' => $this->description,
                'rules' => $rules,
                'customers_count' => $count,
            ]);
            session()->flash('success', "Segment \"{$this->name}\" created.");
        }

        $this->resetForm();
    }

    public function editSegment(int $id): void
    {
        $segment = Segment::findOrFail($id);

        $this->editingId = $id;
        $this->name = $segment->name;
        $this->description = $segment->description ?? '';
        $this->match = $segment->rules['match'] ?? 'all';
        $this->conditions = $segment->rules['conditions'] ?? [];

        if (empty($this->conditions)) {
            $this->addCondition();
        }

        $this->showForm = true;
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteId = $id;
    }

    public function deleteSegment(): void
    {
        if ($this->confirmDeleteId) {
            $segment = Segment::findOrFail($this->confirmDeleteId);
            $segment->delete();
            session()->flash('success', "Segment \"{$segment->name}\" deleted.");
        }

        $this->confirmDeleteId = null;
    }

    public function addCondition(): void
    {
        $this->conditions[] = [
            'field' => 'payment_status',
            'operator' => SegmentFields::defaultOperatorFor('payment_status'),
            'value' => $this->defaultValueFor(SegmentFields::defaultOperatorFor('payment_status')),
        ];
    }

    public function removeCondition(int $index): void
    {
        unset($this->conditions[$index]);
        $this->conditions = array_values($this->conditions);

        if (empty($this->conditions)) {
            $this->addCondition();
        }
    }

    /**
     * When a condition's field or operator changes, reset the operator/value
     * to sensible defaults for the new field type / operator shape.
     */
    public function updated(string $property): void
    {
        if (preg_match('/^conditions\.(\d+)\.field$/', $property, $m)) {
            $index = (int) $m[1];
            $field = $this->conditions[$index]['field'] ?? '';
            $operator = SegmentFields::defaultOperatorFor($field);
            $this->conditions[$index]['operator'] = $operator;
            $this->conditions[$index]['value'] = $this->defaultValueFor($operator);

            return;
        }

        if (preg_match('/^conditions\.(\d+)\.operator$/', $property, $m)) {
            $index = (int) $m[1];
            $operator = $this->conditions[$index]['operator'] ?? 'equals';
            $this->conditions[$index]['value'] = $this->defaultValueFor($operator);
        }
    }

    public function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
        $this->match = 'all';
        $this->conditions = [];
        $this->addCondition();
    }

    /**
     * @return array<int, array{field: string, operator: string, value: mixed}>
     */
    protected function cleanConditions(): array
    {
        return array_values(array_filter(array_map(function (array $condition) {
            $field = $condition['field'] ?? '';

            if (! SegmentFields::isValidField($field)) {
                return null;
            }

            return [
                'field' => $field,
                'operator' => $condition['operator'] ?? SegmentFields::defaultOperatorFor($field),
                'value' => $condition['value'] ?? null,
            ];
        }, $this->conditions)));
    }

    protected function defaultValueFor(string $operator): array|string|null
    {
        return match (true) {
            in_array($operator, SegmentFields::LIST_OPERATORS, true) => [],
            in_array($operator, SegmentFields::RANGE_OPERATORS, true) => ['', ''],
            in_array($operator, SegmentFields::VALUELESS_OPERATORS, true) => null,
            default => '',
        };
    }

    public function getPreviewCountProperty(): int
    {
        $rules = [
            'match' => $this->match,
            'conditions' => $this->cleanConditions(),
        ];

        return app(CampaignService::class)->previewSegmentCount($rules);
    }

    public function render()
    {
        $segments = Segment::query()->orderBy('name')->get();

        return view('livewire.customers.segment-manager', [
            'segments' => $segments,
            'fields' => SegmentFields::FIELDS,
            'operatorsByType' => SegmentFields::OPERATORS_BY_TYPE,
            'rangeOperators' => SegmentFields::RANGE_OPERATORS,
            'listOperators' => SegmentFields::LIST_OPERATORS,
            'valuelessOperators' => SegmentFields::VALUELESS_OPERATORS,
        ]);
    }
}
