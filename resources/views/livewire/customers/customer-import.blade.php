<div>
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('customers.index') }}" wire:navigate class="text-sm text-muted hover:text-ink">&larr; Back to customers</a>

        @if ($step === 1)
            <button type="button" wire:click="downloadTemplate" class="text-sm text-brand hover:underline">
                Download CSV template
            </button>
        @endif
    </div>

    {{-- Step indicator --}}
    <div class="mb-6 flex items-center">
        @foreach ([1 => 'Upload', 2 => 'Map Columns', 3 => 'Preview', 4 => 'Done'] as $num => $label)
            <div class="flex items-center {{ $num < 4 ? 'flex-1' : '' }}">
                <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold
                        {{ $step === $num ? 'bg-brand text-white' : ($step > $num ? 'bg-success/15 text-success' : 'bg-surface-2 text-muted border border-border') }}">
                        @if ($step > $num)
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        @else
                            {{ $num }}
                        @endif
                    </div>
                    <span class="text-sm font-medium hidden sm:inline {{ $step === $num ? 'text-ink' : 'text-muted' }}">{{ $label }}</span>
                </div>
                @if ($num < 4)
                    <div class="flex-1 h-px mx-3 {{ $step > $num ? 'bg-success/30' : 'bg-border' }}"></div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Step 1: Upload --}}
    @if ($step === 1)
        <div class="card">
            <div class="card-body space-y-4">
                <div>
                    <h2 class="text-base font-semibold text-ink mb-1">Upload a CSV file</h2>
                    <p class="text-sm text-muted">Export your customer list as a CSV file, then upload it here. The first row must contain column headers.</p>
                </div>

                <div>
                    <input type="file" wire:model="csvFile" accept=".csv,.txt" class="input">
                    @error('csvFile') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="csvFile" class="text-sm text-muted mt-1">Reading file…</div>
                </div>

                <p class="text-xs text-muted">
                    Not sure how to format your file? <button type="button" wire:click="downloadTemplate" class="text-brand hover:underline">Download a template</button> with the recommended columns.
                </p>
            </div>
        </div>
    @endif

    {{-- Step 2: Map columns --}}
    @if ($step === 2)
        <div class="card">
            <div class="card-body space-y-6">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div>
                        <h2 class="text-base font-semibold text-ink mb-1">Map your columns</h2>
                        <p class="text-sm text-muted">We've matched columns automatically where possible. Review and adjust the mapping below.</p>
                    </div>
                    <span class="text-xs text-muted bg-surface-2 border border-border rounded-full px-3 py-1">
                        {{ $totalRows }} {{ Str::plural('row', $totalRows) }} found · delimiter "{{ $delimiter === "\t" ? 'tab' : $delimiter }}"
                    </span>
                </div>

                <div class="space-y-3">
                    @foreach ($fieldDefinitions as $field => $def)
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start border border-border rounded-xl p-3">
                            <div class="md:col-span-3">
                                <label class="block text-sm font-medium text-ink">
                                    {{ $def['label'] }}
                                    @if ($def['required'] ?? false)
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                @if (! empty($def['hint']))
                                    <p class="text-xs text-muted mt-0.5">{{ $def['hint'] }}</p>
                                @endif
                            </div>
                            <div class="md:col-span-4">
                                <select wire:model.live="mapping.{{ $field }}" class="input @error('mapping.'.$field) !border-danger @enderror">
                                    <option value="">— Skip this field —</option>
                                    @foreach ($headers as $header)
                                        <option value="{{ $header }}">{{ $header }}</option>
                                    @endforeach
                                </select>
                                @error('mapping.'.$field) <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="md:col-span-5 min-w-0">
                                @if ($mapping[$field] !== '')
                                    <p class="text-xs font-semibold text-muted uppercase tracking-wider mb-1">Sample values</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($this->previewRows as $row)
                                            @php($value = $row[$field])
                                            <span class="text-xs bg-surface-2 border border-border rounded-md px-2 py-0.5 text-ink/80 truncate max-w-[10rem]" title="{{ $value }}">
                                                {{ $value !== null && $value !== '' ? $value : '—' }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-xs text-muted italic">Not imported</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center justify-between gap-3 pt-2">
                    <button type="button" wire:click="backToUpload" class="btn-secondary">&larr; Choose a different file</button>
                    <button type="button" wire:click="proceedToPreview" class="btn-primary">Preview &amp; Continue</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Step 3: Preview & confirm --}}
    @if ($step === 3)
        <div class="card">
            <div class="card-body space-y-4">
                <div>
                    <h2 class="text-base font-semibold text-ink mb-1">Preview</h2>
                    <p class="text-sm text-muted">
                        This is how the first {{ count($this->previewRows) }} of {{ $totalRows }} {{ Str::plural('row', $totalRows) }} will be imported.
                        Existing customers are matched by {{ $mapping['account_number'] !== '' ? 'account number (falling back to phone)' : 'phone number' }} and updated; everyone else is created as new.
                    </p>
                </div>

                <div class="overflow-x-auto -mx-2">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold text-muted uppercase tracking-wider">
                                @foreach ($fieldDefinitions as $field => $def)
                                    @if ($mapping[$field] !== '')
                                        <th class="px-2 py-2 whitespace-nowrap">{{ $def['label'] }}</th>
                                    @endif
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($this->previewRows as $row)
                                <tr>
                                    @foreach ($fieldDefinitions as $field => $def)
                                        @if ($mapping[$field] !== '')
                                            <td class="px-2 py-2 whitespace-nowrap text-ink/80">{{ $row[$field] !== null && $row[$field] !== '' ? $row[$field] : '—' }}</td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @error('csvFile') <p class="text-xs text-danger">{{ $message }}</p> @enderror

                <div class="flex items-center justify-between gap-3 pt-2">
                    <button type="button" wire:click="backToMapping" class="btn-secondary" wire:loading.attr="disabled" wire:target="import">&larr; Adjust mapping</button>
                    <button type="button" wire:click="import" class="btn-primary" wire:loading.attr="disabled" wire:target="import">
                        <span wire:loading.remove wire:target="import">Import {{ $totalRows }} {{ Str::plural('customer', $totalRows) }}</span>
                        <span wire:loading wire:target="import">Importing…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Step 4: Results --}}
    @if ($step === 4)
        <div class="card">
            <div class="card-body space-y-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-success/15 rounded-xl flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-ink">Import complete</h2>
                        <p class="text-sm text-muted">
                            {{ $imported }} {{ Str::plural('customer', $imported) }} imported successfully.
                            @if ($failed > 0)
                                {{ $failed }} {{ Str::plural('row', $failed) }} could not be imported.
                            @endif
                        </p>
                    </div>
                </div>

                @if (count($errorSamples) > 0)
                    <div class="border border-warning/30 bg-warning/10 rounded-xl p-4">
                        <p class="text-sm font-semibold text-ink mb-2">
                            Rows that were skipped {{ $failed > count($errorSamples) ? '(first '.count($errorSamples).' shown)' : '' }}
                        </p>
                        <ul class="text-sm text-muted space-y-1">
                            @foreach ($errorSamples as $sample)
                                <li><span class="font-medium text-ink">Row {{ $sample['row'] }}:</span> {{ $sample['reason'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex items-center gap-3 pt-2">
                    <a href="{{ route('customers.index') }}" wire:navigate class="btn-primary">Go to Customers</a>
                    <button type="button" wire:click="importAnother" class="btn-secondary">Import another file</button>
                </div>
            </div>
        </div>
    @endif
</div>
