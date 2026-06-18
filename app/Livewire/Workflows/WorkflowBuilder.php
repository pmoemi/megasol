<?php

namespace App\Livewire\Workflows;

use App\Models\CustomerList;
use App\Models\EmailTemplate;
use App\Models\Workflow;
use App\Support\EmailBlockRenderer;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class WorkflowBuilder extends Component
{
    public ?int $workflowId = null;

    public string $name = '';

    public string $description = '';

    public string $triggerType = '';

    public array $triggerConfig = [];

    /** @var array<int, array<string, mixed>> */
    public array $nodes = [];

    public ?int $editingNodeIndex = null;

    public array $undoStack = [];

    public array $redoStack = [];

    public int $maxUndoLevels = 50;

    public bool $showGuidedMode = false;

    public ?string $selectedTemplate = null;

    public int $guidedStep = 1;

    /** @var array<int, array<string, mixed>> */
    public array $templates = [
        [
            'id' => 'payment-reminder',
            'name' => 'Payment Reminder Sequence',
            'description' => 'SMS reminder followed by email when payment is due soon',
            'icon' => 'clock',
            'category' => 'payment',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'payment_due', 'config' => ['label' => 'Payment Due Soon']],
                ['type' => 'action', 'subtype' => 'send_sms', 'config' => ['label' => 'SMS Reminder', 'body' => 'Hi {first_name}, your payment of {balance} is due on {next_payment_date}.']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 2 Days', 'duration' => 2, 'unit' => 'days']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Email Reminder', 'subject' => 'Payment reminder for {first_name}']],
            ],
        ],
        [
            'id' => 'overdue-escalation',
            'name' => 'Overdue Payment Escalation',
            'description' => 'Escalating SMS and email when payment is overdue',
            'icon' => 'alert',
            'category' => 'payment',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'payment_overdue', 'config' => ['label' => 'Payment Overdue']],
                ['type' => 'action', 'subtype' => 'send_sms', 'config' => ['label' => 'Urgent SMS', 'body' => 'Hi {first_name}, your account {account_number} is overdue. Please contact us.']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 3 Days', 'duration' => 3, 'unit' => 'days']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Overdue Email', 'subject' => 'Important: overdue payment on account {account_number}']],
            ],
        ],
        [
            'id' => 'welcome-journey',
            'name' => 'New Customer Welcome',
            'description' => 'Welcome email and follow-up SMS for new customers',
            'icon' => 'rocket',
            'category' => 'onboarding',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'customer_created', 'config' => ['label' => 'New Customer Added']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Welcome Email', 'subject' => 'Welcome to MegaSol, {first_name}!']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 1 Day', 'duration' => 1, 'unit' => 'days']],
                ['type' => 'action', 'subtype' => 'send_sms', 'config' => ['label' => 'Welcome SMS', 'body' => 'Welcome {first_name}! Reply if you need help with your account.']],
            ],
        ],
        [
            'id' => 'monthly-checkin',
            'name' => 'Monthly Customer Check-in',
            'description' => 'Scheduled monthly SMS check-in with active customers',
            'icon' => 'calendar',
            'category' => 'engagement',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'scheduled', 'config' => ['label' => 'Monthly (1st, 9 AM)', 'cron_expression' => '0 9 1 * *']],
                ['type' => 'action', 'subtype' => 'send_sms', 'config' => ['label' => 'Monthly SMS', 'body' => 'Hi {first_name}, hope all is well. Your next payment is {next_payment_date}.']],
            ],
        ],
    ];

    public array $triggerTypes = [
        'customer_created' => 'New Customer Added',
        'payment_due' => 'Payment Due Soon',
        'payment_overdue' => 'Payment Overdue',
        'scheduled' => 'Scheduled / Recurring',
        'manual' => 'Manual Run',
    ];

    public array $actionSubtypes = [
        'send_sms' => 'Send SMS',
        'send_email' => 'Send Email',
        'wait_delay' => 'Wait / Delay',
    ];

    public array $conditionSubtypes = [
        'contact_field' => 'Customer Field Check',
    ];

    /** @var array<string, array<int, string>> */
    protected array $allowedConfigKeys = [
        'send_sms' => ['body', 'label'],
        'send_email' => ['subject', 'body', 'body_html', 'email_template_id', 'label'],
        'wait_delay' => ['duration', 'unit', 'label'],
        'contact_field' => ['field', 'operator', 'value', 'label'],
    ];

    public function mount(?Workflow $workflow = null): void
    {
        $this->workflowId = $workflow?->id;

        if (! $this->workflowId && request()->boolean('guided')) {
            $this->showGuidedMode = true;
        }

        if ($workflow) {
            $this->name = $workflow->name;
            $this->description = $workflow->description ?? '';
            $canvas = $workflow->definition['canvas'] ?? [];

            $this->triggerType = $canvas['trigger'] ?? $workflow->trigger_type ?? '';
            $this->triggerConfig = $canvas['trigger_config'] ?? [];
            $this->nodes = $canvas['nodes'] ?? $this->stepsToNodes($workflow->definition['steps'] ?? []);
        }
    }

    public function undo(): void
    {
        if (empty($this->undoStack)) {
            return;
        }

        $this->redoStack[] = $this->snapshot();
        $previous = array_pop($this->undoStack);
        $this->restoreSnapshot($previous);
    }

    public function redo(): void
    {
        if (empty($this->redoStack)) {
            return;
        }

        $this->undoStack[] = $this->snapshot();
        $next = array_pop($this->redoStack);
        $this->restoreSnapshot($next);
    }

    public function canUndo(): bool
    {
        return count($this->undoStack) > 0;
    }

    public function canRedo(): bool
    {
        return count($this->redoStack) > 0;
    }

    public function addNode(string $type, string $subtype): void
    {
        if (! in_array($type, ['action', 'condition'], true)) {
            return;
        }

        $allSubtypes = array_merge(array_keys($this->actionSubtypes), array_keys($this->conditionSubtypes));
        if (! in_array($subtype, $allSubtypes, true)) {
            return;
        }

        $this->pushState();

        $this->nodes[] = [
            'id' => null,
            'type' => $type,
            'subtype' => $subtype,
            'config' => $this->getDefaultConfig($subtype),
        ];

        $this->editingNodeIndex = count($this->nodes) - 1;
        $this->dispatch('node-added', index: $this->editingNodeIndex);
    }

    public function insertNodeAfter(int $afterIndex, string $type, string $subtype): void
    {
        if (! in_array($type, ['action', 'condition'], true)) {
            return;
        }

        $allSubtypes = array_merge(array_keys($this->actionSubtypes), array_keys($this->conditionSubtypes));
        if (! in_array($subtype, $allSubtypes, true)) {
            return;
        }

        $this->pushState();

        $insertAt = min($afterIndex + 1, count($this->nodes));
        $newNode = [
            'id' => null,
            'type' => $type,
            'subtype' => $subtype,
            'config' => $this->getDefaultConfig($subtype),
        ];

        array_splice($this->nodes, $insertAt, 0, [$newNode]);
        $this->nodes = array_values($this->nodes);
        $this->editingNodeIndex = $insertAt;
        $this->dispatch('node-added', index: $insertAt);
    }

    public function removeNode(int $index): void
    {
        if (! isset($this->nodes[$index])) {
            return;
        }

        $this->pushState();
        array_splice($this->nodes, $index, 1);
        $this->nodes = array_values($this->nodes);
        $this->editingNodeIndex = null;
    }

    public function editNode(int $index): void
    {
        $this->editingNodeIndex = $this->editingNodeIndex === $index ? null : $index;
    }

    public function moveNodeUp(int $index): void
    {
        if ($index <= 0) {
            return;
        }

        $this->pushState();
        [$this->nodes[$index - 1], $this->nodes[$index]] = [$this->nodes[$index], $this->nodes[$index - 1]];

        if ($this->editingNodeIndex === $index) {
            $this->editingNodeIndex = $index - 1;
        } elseif ($this->editingNodeIndex === $index - 1) {
            $this->editingNodeIndex = $index;
        }
    }

    public function moveNodeDown(int $index): void
    {
        if ($index >= count($this->nodes) - 1) {
            return;
        }

        $this->pushState();
        [$this->nodes[$index + 1], $this->nodes[$index]] = [$this->nodes[$index], $this->nodes[$index + 1]];

        if ($this->editingNodeIndex === $index) {
            $this->editingNodeIndex = $index + 1;
        } elseif ($this->editingNodeIndex === $index + 1) {
            $this->editingNodeIndex = $index;
        }
    }

    public function save(): void
    {
        $this->persist(false);
    }

    public function activate(): void
    {
        $this->persist(true);
    }

    public function startGuidedMode(): void
    {
        $this->showGuidedMode = true;
        $this->guidedStep = 1;
        $this->selectedTemplate = null;
    }

    public function selectTemplate(string $templateId): void
    {
        $this->selectedTemplate = $templateId;
        $this->guidedStep = 2;
    }

    public function applyTemplate(): void
    {
        $template = collect($this->templates)->firstWhere('id', $this->selectedTemplate);
        if (! $template) {
            return;
        }

        $this->pushState();

        $triggerDef = collect($template['nodes'])->firstWhere('type', 'trigger');
        if ($triggerDef) {
            $this->triggerType = $triggerDef['subtype'];
            $this->triggerConfig = $triggerDef['config'] ?? [];
        }

        $this->nodes = collect($template['nodes'])
            ->where('type', '!=', 'trigger')
            ->values()
            ->map(fn (array $node) => [
                'id' => null,
                'type' => $node['type'],
                'subtype' => $node['subtype'],
                'config' => array_merge($this->getDefaultConfig($node['subtype']), $node['config'] ?? []),
            ])
            ->toArray();

        $this->name = $template['name'];
        $this->description = $template['description'];
        $this->showGuidedMode = false;
        $this->selectedTemplate = null;
        $this->guidedStep = 1;
    }

    public function exitGuidedMode(): void
    {
        $this->showGuidedMode = false;
        $this->selectedTemplate = null;
        $this->guidedStep = 1;
    }

    public function applyEmailTemplate(int $nodeIndex, mixed $templateId): void
    {
        if (! isset($this->nodes[$nodeIndex]) || $this->nodes[$nodeIndex]['subtype'] !== 'send_email') {
            return;
        }

        $this->pushState();

        if (empty($templateId)) {
            $this->nodes[$nodeIndex]['config']['email_template_id'] = '';
            return;
        }

        $template = EmailTemplate::find((int) $templateId);
        if (! $template) {
            return;
        }

        $compiledHtml = $template->body_html ?: EmailBlockRenderer::renderBlocksPreview($template->blocks ?? []);

        $this->nodes[$nodeIndex]['config']['email_template_id'] = (int) $template->id;
        $this->nodes[$nodeIndex]['config']['body_html'] = $compiledHtml;
        $this->nodes[$nodeIndex]['config']['body'] = strip_tags($compiledHtml);

        if (empty($this->nodes[$nodeIndex]['config']['subject'] ?? '')) {
            $this->nodes[$nodeIndex]['config']['subject'] = $template->subject ?: $template->name;
        }

        $template->incrementUsage();
    }

    public function render()
    {
        return view('livewire.workflows.workflow-builder', [
            'contactGroups' => CustomerList::query()->orderBy('name')->get(['id', 'name']),
            'emailTemplates' => EmailTemplate::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'category']),
            'planFeatures' => ['email_templates' => true, 'workflow_in_app_notify' => true],
        ])->title($this->name ?: 'Workflow Builder');
    }

    protected function persist(bool $activate): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'triggerType' => 'required|string',
        ]);

        if (! array_key_exists($this->triggerType, $this->triggerTypes)) {
            $this->addError('triggerType', 'Invalid trigger type.');
            return;
        }

        $definition = [
            'canvas' => [
                'trigger' => $this->triggerType,
                'trigger_config' => $this->triggerConfig,
                'nodes' => $this->nodes,
            ],
            'steps' => $this->convertNodesToSteps(),
        ];

        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'trigger_type' => $this->triggerType,
            'schedule_cron' => $this->triggerType === 'scheduled'
                ? ($this->triggerConfig['cron_expression'] ?? null)
                : null,
            'definition' => $definition,
            'is_active' => $activate,
            'created_by' => auth()->id(),
        ];

        if ($this->workflowId) {
            Workflow::findOrFail($this->workflowId)->update($data);
        } else {
            $workflow = Workflow::create($data);
            $this->workflowId = $workflow->id;
        }

        session()->flash('success', $activate ? 'Workflow saved and activated.' : 'Workflow saved as draft.');
        $this->redirect(route('workflows.index'), navigate: true);
    }

    protected function pushState(): void
    {
        $this->undoStack[] = $this->snapshot();
        $this->redoStack = [];

        if (count($this->undoStack) > $this->maxUndoLevels) {
            $this->undoStack = array_slice($this->undoStack, -$this->maxUndoLevels);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(): array
    {
        return [
            'nodes' => $this->nodes,
            'triggerType' => $this->triggerType,
            'triggerConfig' => $this->triggerConfig,
            'editingNodeIndex' => $this->editingNodeIndex,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function restoreSnapshot(array $snapshot): void
    {
        $this->nodes = $snapshot['nodes'];
        $this->triggerType = $snapshot['triggerType'];
        $this->triggerConfig = $snapshot['triggerConfig'];
        $this->editingNodeIndex = $snapshot['editingNodeIndex'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultConfig(string $subtype): array
    {
        return match ($subtype) {
            'send_sms' => ['label' => 'Send SMS', 'body' => 'Hi {first_name}, message from MegaSol.'],
            'send_email' => ['label' => 'Send Email', 'subject' => '', 'body' => '', 'body_html' => '', 'email_template_id' => ''],
            'wait_delay' => ['label' => 'Wait', 'duration' => 1, 'unit' => 'hours'],
            'contact_field' => ['label' => 'Field Check', 'field' => 'payment_status', 'operator' => 'equals', 'value' => 'overdue'],
            default => [],
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function convertNodesToSteps(): array
    {
        $steps = [];

        foreach ($this->nodes as $node) {
            if (($node['type'] ?? '') === 'condition') {
                continue;
            }

            $config = $this->sanitizeNodeConfig($node['subtype'] ?? '', $node['config'] ?? []);

            match ($node['subtype'] ?? '') {
                'send_sms' => $steps[] = ['type' => 'send_sms', 'body' => $config['body'] ?? ''],
                'send_email' => $steps[] = [
                    'type' => 'send_email',
                    'subject' => $config['subject'] ?? 'Message from MegaSol',
                    'body_html' => $config['body_html'] ?: ($config['body'] ?? ''),
                ],
                'wait_delay' => $steps[] = ['type' => 'delay', 'minutes' => $this->delayToMinutes($config)],
                default => null,
            };
        }

        return $steps;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function delayToMinutes(array $config): int
    {
        $duration = max(1, (int) ($config['duration'] ?? 1));

        return match ($config['unit'] ?? 'hours') {
            'minutes' => $duration,
            'days' => $duration * 24 * 60,
            default => $duration * 60,
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function sanitizeNodeConfig(string $subtype, array $config): array
    {
        $allowed = $this->allowedConfigKeys[$subtype] ?? [];

        return $allowed ? array_intersect_key($config, array_flip($allowed)) : $config;
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<int, array<string, mixed>>
     */
    protected function stepsToNodes(array $steps): array
    {
        $nodes = [];

        foreach ($steps as $step) {
            $type = $step['type'] ?? '';

            $nodes[] = match ($type) {
                'send_sms' => [
                    'id' => null,
                    'type' => 'action',
                    'subtype' => 'send_sms',
                    'config' => ['label' => 'Send SMS', 'body' => $step['body'] ?? ''],
                ],
                'send_email' => [
                    'id' => null,
                    'type' => 'action',
                    'subtype' => 'send_email',
                    'config' => [
                        'label' => 'Send Email',
                        'subject' => $step['subject'] ?? '',
                        'body_html' => $step['body_html'] ?? '',
                        'body' => strip_tags($step['body_html'] ?? ''),
                    ],
                ],
                'delay' => [
                    'id' => null,
                    'type' => 'action',
                    'subtype' => 'wait_delay',
                    'config' => ['label' => 'Wait', 'duration' => max(1, (int) ($step['minutes'] ?? 60)), 'unit' => 'minutes'],
                ],
                default => null,
            };
        }

        return array_values(array_filter($nodes));
    }
}
