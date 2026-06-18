<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <a href="{{ route('campaigns.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-muted hover:text-ink mb-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to campaigns
            </a>
            <h1 class="text-2xl font-bold text-ink">{{ $this->title }}</h1>
            <p class="text-sm text-muted mt-0.5">Configure your campaign content, audience, and delivery schedule</p>
        </div>
        <button type="button" wire:click="saveDraft" class="btn-secondary shrink-0">Save Draft</button>
    </div>

    {{-- Progress Stepper --}}
    <div class="bg-surface-2 rounded-2xl border border-border p-5">
        <div class="flex items-center">
            @foreach ($this->steps as $num => $step)
                <div class="flex items-center {{ $num < 4 ? 'flex-1' : '' }}">
                    <button
                        type="button"
                        wire:click="goToStep({{ $num }})"
                        @if ($num > $maxStepReached) disabled @endif
                        class="flex items-center gap-3 shrink-0 {{ $num > $maxStepReached ? 'cursor-default' : 'cursor-pointer' }} text-left"
                    >
                        <span @class([
                            'flex items-center justify-center w-9 h-9 rounded-full text-sm font-semibold border-2 shrink-0 transition-colors',
                            'bg-brand border-brand text-white' => $currentStep === $num,
                            'bg-brand/10 border-brand text-brand' => $num < $currentStep,
                            'bg-surface border-border text-muted' => $num > $currentStep,
                        ])>
                            @if ($num < $currentStep)
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            @else
                                {{ $num }}
                            @endif
                        </span>
                        <span @class([
                            'text-sm font-medium hidden sm:inline',
                            'text-ink' => $currentStep === $num,
                            'text-muted' => $currentStep !== $num,
                        ])>{{ $step['label'] }}</span>
                    </button>

                    @if ($num < 4)
                        <div @class([
                            'flex-1 h-px mx-3 transition-colors',
                            'bg-brand' => $num < $currentStep,
                            'bg-border' => $num >= $currentStep,
                        ])></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Step 1: Basics --}}
    @if ($currentStep === 1)
        <div class="space-y-4">
            @if ($channel === 'email')
                <div x-data="{ show: true }" x-show="show" x-transition class="bg-info/10 border border-info/20 rounded-2xl p-4 flex items-start gap-3">
                    <div class="w-9 h-9 rounded-lg bg-info/15 text-info flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-ink">Improve your email deliverability</p>
                        <p class="text-sm text-muted mt-1">Make sure your sending domain has the following DNS records configured correctly to avoid landing in spam folders.</p>
                        <ul class="text-xs text-muted mt-2 space-y-1 list-disc list-inside">
                            <li><span class="font-semibold text-ink/80">SPF</span> — authorizes which mail servers can send on behalf of your domain.</li>
                            <li><span class="font-semibold text-ink/80">DKIM</span> — signs outgoing emails so receivers can verify they weren't tampered with.</li>
                            <li><span class="font-semibold text-ink/80">DMARC</span> — tells receiving servers what to do with messages that fail SPF/DKIM checks.</li>
                        </ul>
                    </div>
                    <button type="button" @click="show = false" class="p-1 text-muted hover:text-ink rounded-lg hover:bg-surface transition-colors shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            @endif

            <div class="bg-surface-2 rounded-2xl border border-border p-6 space-y-5">
                <div>
                    <h2 class="text-base font-semibold text-ink">Campaign Details</h2>
                    <p class="text-sm text-muted mt-0.5">Choose how you want to reach your audience</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-muted mb-2">Send via</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <button type="button" wire:click="$set('channel', 'email')" @class([
                            'flex items-start gap-3 p-4 rounded-xl border-2 text-left transition-colors',
                            'border-brand bg-brand/5' => $channel === 'email',
                            'border-border hover:border-brand/30' => $channel !== 'email',
                        ])>
                            <span @class([
                                'w-10 h-10 rounded-lg flex items-center justify-center shrink-0',
                                'bg-brand text-white' => $channel === 'email',
                                'bg-surface text-muted' => $channel !== 'email',
                            ])>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </span>
                            <span>
                                <span class="block text-sm font-semibold text-ink">Email Campaign</span>
                                <span class="block text-xs text-muted mt-0.5">Design a rich HTML email with the visual builder</span>
                            </span>
                        </button>

                        <button type="button" wire:click="$set('channel', 'sms')" @class([
                            'flex items-start gap-3 p-4 rounded-xl border-2 text-left transition-colors',
                            'border-brand bg-brand/5' => $channel === 'sms',
                            'border-border hover:border-brand/30' => $channel !== 'sms',
                        ])>
                            <span @class([
                                'w-10 h-10 rounded-lg flex items-center justify-center shrink-0',
                                'bg-brand text-white' => $channel === 'sms',
                                'bg-surface text-muted' => $channel !== 'sms',
                            ])>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                            </span>
                            <span>
                                <span class="block text-sm font-semibold text-ink">SMS Campaign</span>
                                <span class="block text-xs text-muted mt-0.5">Send a short text message via Africa's Talking</span>
                            </span>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Campaign Name *</label>
                    <input type="text" wire:model="name" placeholder="e.g. June Payment Reminders" class="input @error('name') !border-danger @enderror">
                    @error('name') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                @if ($channel === 'email')
                    <div>
                        <label class="block text-sm font-medium text-muted mb-2">Campaign Type</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <button type="button" wire:click="$set('type', 'regular')" @class([
                                'flex items-start gap-3 p-4 rounded-xl border-2 text-left transition-colors',
                                'border-brand bg-brand/5' => $type === 'regular',
                                'border-border hover:border-brand/30' => $type !== 'regular',
                            ])>
                                <span>
                                    <span class="block text-sm font-semibold text-ink">Regular</span>
                                    <span class="block text-xs text-muted mt-0.5">A single email sent to your whole audience</span>
                                </span>
                            </button>
                            <button type="button" wire:click="$set('type', 'ab_test')" @class([
                                'flex items-start gap-3 p-4 rounded-xl border-2 text-left transition-colors',
                                'border-brand bg-brand/5' => $type === 'ab_test',
                                'border-border hover:border-brand/30' => $type !== 'ab_test',
                            ])>
                                <span>
                                    <span class="block text-sm font-semibold text-ink">A/B Test</span>
                                    <span class="block text-xs text-muted mt-0.5">Split your audience to test two subject lines</span>
                                </span>
                            </button>
                        </div>
                    </div>

                    @if ($type === 'ab_test')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-surface rounded-xl border border-border">
                            <div>
                                <label class="block text-sm font-medium text-muted mb-1">Subject A *</label>
                                <input type="text" wire:model="subjectA" class="input @error('subjectA') !border-danger @enderror">
                                @error('subjectA') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-muted mb-1">Subject B *</label>
                                <input type="text" wire:model="subjectB" class="input @error('subjectB') !border-danger @enderror">
                                @error('subjectB') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-muted mb-1">Variant A audience split: {{ $splitPercentage }}% / {{ 100 - $splitPercentage }}%</label>
                                <input type="range" min="10" max="90" step="5" wire:model.live="splitPercentage" class="w-full accent-brand">
                                @error('splitPercentage') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endif

    {{-- Step 2: Content --}}
    @if ($currentStep === 2)
        <div class="bg-surface-2 rounded-2xl border border-border p-6 space-y-5">
            <div>
                <h2 class="text-base font-semibold text-ink">{{ $channel === 'email' ? 'Email Content' : 'SMS Content' }}</h2>
                <p class="text-sm text-muted mt-0.5">
                    {{ $channel === 'email' ? 'Design your email using the visual builder or write raw HTML' : 'Write the SMS message your audience will receive' }}
                </p>
            </div>

            @if ($channel === 'email')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Email Template</label>
                        <select wire:model.live="email_template_id" class="input">
                            <option value="">— Select template —</option>
                            @foreach ($emailTemplates as $template)
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Preview Text</label>
                        <input type="text" wire:model="preview_text" class="input" placeholder="Shown in inbox preview">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-muted mb-1">Subject *</label>
                        <input type="text" wire:model="subject" class="input @error('subject') !border-danger @enderror">
                        @error('subject') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Send From (Name)</label>
                        <input type="text" wire:model="from_name" placeholder="MegaSol" class="input @error('from_name') !border-danger @enderror">
                        @error('from_name') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-muted mb-1">Send From (Email)</label>
                        <input type="email" wire:model="from_email" placeholder="no-reply@yourdomain.com" class="input @error('from_email') !border-danger @enderror">
                        @error('from_email') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                        <p class="text-xs text-muted mt-1">Defaults to your <a href="{{ route('settings.email') }}" class="text-brand hover:underline" wire:navigate>Email settings</a> sender. Some providers require a verified domain.</p>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-3">
                        <label class="block text-sm font-medium text-muted">Email Body *</label>
                        <div class="inline-flex items-center gap-1 bg-surface rounded-lg p-1">
                            <button type="button" wire:click="$set('editorMode', 'visual')" class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors {{ $editorMode === 'visual' ? 'bg-surface-2 text-ink shadow-sm' : 'text-muted hover:text-ink/80' }}">Visual Builder</button>
                            <button type="button" wire:click="$set('editorMode', 'html')" class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors {{ $editorMode === 'html' ? 'bg-surface-2 text-ink shadow-sm' : 'text-muted hover:text-ink/80' }}">HTML Editor</button>
                        </div>
                    </div>

                    @if ($editorMode === 'visual')
                        @if ($body_html && ! empty($bodyBlocks))
                            <div class="space-y-4">
                                <div class="border border-success/20 bg-success/10 rounded-xl p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-success/15 rounded-lg flex items-center justify-center">
                                                <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            </div>
                                            <div>
                                                <p class="text-sm font-semibold text-green-900">{{ __('Email content ready') }}</p>
                                                <p class="text-xs text-success">{{ count($bodyBlocks) }} block(s) built with the visual editor</p>
                                            </div>
                                        </div>
                                        <button type="button" wire:click="$set('showEmailBuilder', true)" class="px-4 py-2 text-sm font-medium text-success bg-surface-2 border border-green-300 rounded-xl hover:bg-success/10 transition-colors">{{ __('Edit in Builder') }}</button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Preview') }}</label>
                                    <div class="border border-border rounded-xl bg-surface" style="height: 600px;">
                                        <iframe srcdoc="{{ $body_html }}" class="w-full h-full border-0 rounded-xl" sandbox="allow-same-origin" title="Email preview"></iframe>
                                    </div>
                                </div>
                            </div>
                        @elseif (! empty($body_html))
                            <div class="space-y-4">
                                <div class="border border-success/20 bg-success/10 rounded-xl p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-success/15 rounded-lg flex items-center justify-center">
                                                <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            </div>
                                            <div>
                                                <p class="text-sm font-semibold text-green-900">{{ __('Email content ready') }}</p>
                                                <p class="text-xs text-success">{{ __('This email was added directly or loaded from a template.') }}</p>
                                            </div>
                                        </div>
                                        <button type="button" wire:click="$set('showEmailBuilder', true)" class="px-4 py-2 text-sm font-medium text-success bg-surface-2 border border-green-300 rounded-xl hover:bg-success/10 transition-colors">{{ __('Edit in Builder') }}</button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Preview') }}</label>
                                    <div class="border border-border rounded-xl bg-surface" style="height: 600px;">
                                        <iframe srcdoc="{{ $body_html }}" class="w-full h-full border-0 rounded-xl" sandbox="allow-same-origin" title="Email preview"></iframe>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="border-2 border-dashed border-border rounded-xl p-8 text-center">
                                <div class="w-16 h-16 bg-primary-50 dark:bg-primary-900/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                </div>
                                <h3 class="text-base font-semibold text-ink mb-1">{{ __('Design your email visually') }}</h3>
                                <p class="text-sm text-muted mb-4 max-w-sm mx-auto">{{ __('Drag and drop content blocks to build beautiful, responsive emails without writing any code.') }}</p>
                                <button type="button" wire:click="$set('showEmailBuilder', true)" class="btn-primary inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    {{ __('Open Email Builder') }}
                                </button>
                            </div>
                        @endif
                    @else
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-muted mb-1">{{ __('Email Body (HTML)') }}</label>
                                <textarea wire:model="body_html" rows="16" placeholder="{{ __('Enter your email HTML content here...') }}" class="w-full px-4 py-3 text-sm font-mono border border-border rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-y"></textarea>
                                @error('body_html') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                                <p class="text-xs text-muted mt-1">
                                    {{ __('You can use HTML tags. Use') }} @{{first_name}}, @{{last_name}}, @{{email}}, @{{company}} {{ __('as merge tags.') }}
                                </p>
                            </div>

                            @if (! empty($body_html))
                                <div>
                                    <label class="block text-sm font-medium text-ink/80 mb-1">{{ __('Preview') }}</label>
                                    <div class="border border-border rounded-xl p-4 bg-surface max-h-64 overflow-y-auto">
                                        <div class="bg-surface-2 rounded-lg p-4 shadow-sm">
                                            {!! $this->safeBodyHtml !!}
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
                {{-- Send a test email --}}
                <div class="p-4 bg-surface rounded-xl border border-border">
                    <label class="block text-sm font-medium text-ink mb-1">{{ __('Send a test email') }}</label>
                    <p class="text-xs text-muted mb-3">{{ __('Preview how this email looks in a real inbox before sending the campaign.') }}</p>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <input type="email" wire:model="testEmail" placeholder="you@example.com" class="input flex-1 @error('testEmail') !border-danger @enderror">
                        <button type="button" wire:click="sendTestEmail" wire:loading.attr="disabled" wire:target="sendTestEmail" class="btn-secondary shrink-0">
                            <span wire:loading.remove wire:target="sendTestEmail">{{ __('Send Test') }}</span>
                            <span wire:loading wire:target="sendTestEmail">{{ __('Sending…') }}</span>
                        </button>
                    </div>
                    @if ($testResult)
                        @php([$testType, $testMsg] = array_pad(explode(':', $testResult, 2), 2, ''))
                        <p class="text-xs mt-2 {{ $testType === 'success' ? 'text-success' : 'text-danger' }}">{{ $testMsg }}</p>
                    @endif
                </div>
            @else
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">SMS Template</label>
                    <select wire:model.live="message_template_id" class="input">
                        <option value="">— Select template —</option>
                        @foreach ($smsTemplates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">SMS Message *</label>
                    <textarea wire:model="body" rows="6" class="input @error('body') !border-danger @enderror"></textarea>
                    @error('body') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-muted mt-1">{{ strlen($body) }} characters</p>
                </div>
            @endif

            <p class="text-xs text-muted">Merge tags: {first_name}, {last_name}, {phone}, {email}, {account_number}, {balance}, {next_payment_date}</p>
        </div>
    @endif

    {{-- Step 3: Audience --}}
    @if ($currentStep === 3)
        <div class="bg-surface-2 rounded-2xl border border-border p-6 space-y-5">
            <div>
                <h2 class="text-base font-semibold text-ink">Select Audience</h2>
                <p class="text-sm text-muted mt-0.5">Choose who will receive this campaign</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button type="button" wire:click="$set('audience_type', 'all')" @class([
                    'flex items-start gap-3 p-4 rounded-xl border-2 text-left transition-colors',
                    'border-brand bg-brand/5' => $audience_type === 'all',
                    'border-border hover:border-brand/30' => $audience_type !== 'all',
                ])>
                    <span @class([
                        'w-10 h-10 rounded-lg flex items-center justify-center shrink-0',
                        'bg-brand text-white' => $audience_type === 'all',
                        'bg-surface text-muted' => $audience_type !== 'all',
                    ])>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </span>
                    <span>
                        <span class="block text-sm font-semibold text-ink">All Customers</span>
                        <span class="block text-xs text-muted mt-0.5">Send to everyone in your customer database</span>
                    </span>
                </button>

                <button type="button" wire:click="$set('audience_type', 'list')" @class([
                    'flex items-start gap-3 p-4 rounded-xl border-2 text-left transition-colors',
                    'border-brand bg-brand/5' => $audience_type === 'list',
                    'border-border hover:border-brand/30' => $audience_type !== 'list',
                ])>
                    <span @class([
                        'w-10 h-10 rounded-lg flex items-center justify-center shrink-0',
                        'bg-brand text-white' => $audience_type === 'list',
                        'bg-surface text-muted' => $audience_type !== 'list',
                    ])>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    </span>
                    <span>
                        <span class="block text-sm font-semibold text-ink">Customer Group</span>
                        <span class="block text-xs text-muted mt-0.5">Target a specific group of customers</span>
                    </span>
                </button>

                <button type="button" wire:click="$set('audience_type', 'segment')" @class([
                    'flex items-start gap-3 p-4 rounded-xl border-2 text-left transition-colors',
                    'border-brand bg-brand/5' => $audience_type === 'segment',
                    'border-border hover:border-brand/30' => $audience_type !== 'segment',
                ])>
                    <span @class([
                        'w-10 h-10 rounded-lg flex items-center justify-center shrink-0',
                        'bg-brand text-white' => $audience_type === 'segment',
                        'bg-surface text-muted' => $audience_type !== 'segment',
                    ])>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    </span>
                    <span>
                        <span class="block text-sm font-semibold text-ink">Segment</span>
                        <span class="block text-xs text-muted mt-0.5">Use dynamic rules to filter customers</span>
                    </span>
                </button>

                <button type="button" wire:click="$set('audience_type', 'payment_status')" @class([
                    'flex items-start gap-3 p-4 rounded-xl border-2 text-left transition-colors',
                    'border-brand bg-brand/5' => in_array($audience_type, ['payment_status', 'lifecycle']),
                    'border-border hover:border-brand/30' => ! in_array($audience_type, ['payment_status', 'lifecycle']),
                ])>
                    <span @class([
                        'w-10 h-10 rounded-lg flex items-center justify-center shrink-0',
                        'bg-brand text-white' => in_array($audience_type, ['payment_status', 'lifecycle']),
                        'bg-surface text-muted' => ! in_array($audience_type, ['payment_status', 'lifecycle']),
                    ])>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                    </span>
                    <span>
                        <span class="block text-sm font-semibold text-ink">Filter by Status</span>
                        <span class="block text-xs text-muted mt-0.5">Target by payment status or lifecycle stage</span>
                    </span>
                </button>

                <button type="button" wire:click="$set('audience_type', 'customers')" @class([
                    'flex items-start gap-3 p-4 rounded-xl border-2 text-left transition-colors',
                    'border-brand bg-brand/5' => $audience_type === 'customers',
                    'border-border hover:border-brand/30' => $audience_type !== 'customers',
                ])>
                    <span @class([
                        'w-10 h-10 rounded-lg flex items-center justify-center shrink-0',
                        'bg-brand text-white' => $audience_type === 'customers',
                        'bg-surface text-muted' => $audience_type !== 'customers',
                    ])>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </span>
                    <span>
                        <span class="block text-sm font-semibold text-ink">Specific Customers</span>
                        <span class="block text-xs text-muted mt-0.5">Hand-pick individual customers to message</span>
                    </span>
                </button>
            </div>

            @if ($audience_type === 'list')
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Customer Group *</label>
                    @if ($lists->isEmpty())
                        <p class="text-sm text-muted mb-2">No customer groups yet.</p>
                        <a href="{{ route('customers.groups') }}" wire:navigate class="text-sm text-brand font-medium hover:underline">
                            Create a customer group first →
                        </a>
                    @else
                        <select wire:model.live="list_id" class="input @error('list_id') !border-danger @enderror">
                            <option value="">— Select group —</option>
                            @foreach ($lists as $list)
                                <option value="{{ $list->id }}">{{ $list->name }} ({{ $list->customers_count }} customers)</option>
                            @endforeach
                        </select>
                        @error('list_id') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                        <p class="text-xs text-muted mt-1">
                            <a href="{{ route('customers.groups') }}" wire:navigate class="text-brand hover:underline">Manage groups</a>
                        </p>
                    @endif
                </div>
            @elseif ($audience_type === 'segment')
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Segment *</label>
                    @if ($segments->isEmpty())
                        <p class="text-sm text-muted mb-2">No segments yet.</p>
                        <a href="{{ route('customers.segments') }}" wire:navigate class="text-sm text-brand font-medium hover:underline">
                            Create a segment first →
                        </a>
                    @else
                        <select wire:model.live="segment_id" class="input @error('segment_id') !border-danger @enderror">
                            <option value="">— Select segment —</option>
                            @foreach ($segments as $segment)
                                <option value="{{ $segment->id }}">{{ $segment->name }} ({{ number_format($segment->customers_count) }} customers)</option>
                            @endforeach
                        </select>
                        @error('segment_id') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                        <p class="text-xs text-muted mt-1">
                            <a href="{{ route('customers.segments') }}" wire:navigate class="text-brand hover:underline">Manage segments</a>
                        </p>
                    @endif
                </div>
            @elseif ($audience_type === 'customers')
                <div class="space-y-3" x-data>
                    <label class="block text-sm font-medium text-muted mb-1">Pick Customers *</label>
                    <div class="relative">
                        <input type="text" wire:model.live.debounce.300ms="customerSearch" placeholder="Search by name, email, phone or account number…" class="input">
                        @if (! empty($customerSearchResults))
                            <div class="absolute z-20 mt-1 w-full bg-surface-2 border border-border rounded-xl shadow-lg max-h-64 overflow-y-auto">
                                @foreach ($customerSearchResults as $result)
                                    <button type="button" wire:click="addSpecificCustomer({{ $result['id'] }})" class="w-full flex items-center justify-between gap-3 px-4 py-2.5 text-left hover:bg-surface transition-colors">
                                        <span class="min-w-0">
                                            <span class="block text-sm font-medium text-ink truncate">{{ $result['name'] }}</span>
                                            <span class="block text-xs text-muted truncate">{{ $result['detail'] }}</span>
                                        </span>
                                        <svg class="w-4 h-4 text-brand shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @error('customer_ids') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror

                    @if (! empty($this->selectedCustomersList))
                        <div class="flex flex-wrap gap-2">
                            @foreach ($this->selectedCustomersList as $selected)
                                <span class="inline-flex items-center gap-1.5 pl-3 pr-2 py-1.5 bg-brand/10 text-brand rounded-full text-xs font-medium">
                                    {{ $selected['name'] }}
                                    <button type="button" wire:click="removeSpecificCustomer({{ $selected['id'] }})" class="hover:text-brand-strong">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @elseif (in_array($audience_type, ['payment_status', 'lifecycle']))
                <div class="space-y-3">
                    <div class="inline-flex items-center gap-1 bg-surface rounded-lg p-1">
                        <button type="button" wire:click="$set('audience_type', 'payment_status')" @class([
                            'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
                            'bg-surface-2 text-ink shadow-sm' => $audience_type === 'payment_status',
                            'text-muted hover:text-ink/80' => $audience_type !== 'payment_status',
                        ])>Payment Status</button>
                        <button type="button" wire:click="$set('audience_type', 'lifecycle')" @class([
                            'px-3 py-1.5 text-xs font-medium rounded-md transition-colors',
                            'bg-surface-2 text-ink shadow-sm' => $audience_type === 'lifecycle',
                            'text-muted hover:text-ink/80' => $audience_type !== 'lifecycle',
                        ])>Lifecycle Stage</button>
                    </div>

                    @if ($audience_type === 'payment_status')
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Payment Status</label>
                            <select wire:model.live="payment_status" class="input">
                                <option value="current">Current</option>
                                <option value="due_soon">Due Soon</option>
                                <option value="overdue">Overdue</option>
                                <option value="paid_off">Paid Off</option>
                            </select>
                        </div>
                    @else
                        <div>
                            <label class="block text-sm font-medium text-muted mb-1">Lifecycle Stage</label>
                            <select wire:model.live="lifecycle_stage" class="input">
                                <option value="new">New</option>
                                <option value="active">Active</option>
                                <option value="at_risk">At Risk</option>
                                <option value="loyal">Loyal</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    @endif
                </div>
            @endif

            <div class="p-4 bg-brand/5 border border-brand/20 rounded-xl">
                <p class="text-2xl font-bold text-ink">{{ number_format($audience_count) }}</p>
                <p class="text-sm text-muted">
                    recipient{{ $audience_count === 1 ? '' : 's' }} will receive this campaign
                    <span class="text-muted/70">— only customers with a valid {{ $channel === 'email' ? 'email address' : 'phone number' }} are counted.</span>
                </p>
            </div>
        </div>
    @endif

    {{-- Step 4: Schedule --}}
    @if ($currentStep === 4)
        <div class="bg-surface-2 rounded-2xl border border-border p-6 space-y-5">
            <div>
                <h2 class="text-base font-semibold text-ink">Schedule &amp; Send</h2>
                <p class="text-sm text-muted mt-0.5">Choose when this campaign should go out</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Schedule For</label>
                    <input type="datetime-local" wire:model="scheduled_at" class="input @error('scheduled_at') !border-danger @enderror">
                    @error('scheduled_at') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-muted mt-1">Leave empty to save as a draft you can schedule later.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Send Rate (per minute)</label>
                    <input type="number" wire:model.live="sends_per_minute" min="1" max="600" class="input @error('sends_per_minute') !border-danger @enderror">
                    @error('sends_per_minute') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Batch Size <span class="text-muted/60">(0 = no batching)</span></label>
                    <input type="number" wire:model.live="batch_size" min="0" max="5000" class="input @error('batch_size') !border-danger @enderror">
                    @error('batch_size') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-muted mb-1">Pause Between Batches (seconds)</label>
                    <input type="number" wire:model.live="batch_delay_seconds" min="0" max="3600" class="input @error('batch_delay_seconds') !border-danger @enderror">
                    @error('batch_delay_seconds') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            @if ($this->estimatedSendDuration)
                <div class="flex items-center gap-2 text-sm text-muted">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Estimated send time for {{ number_format($audience_count) }} recipients: <span class="font-semibold text-ink">{{ $this->estimatedSendDuration }}</span></span>
                </div>
            @endif

            <label class="flex items-start gap-3 p-4 bg-surface rounded-xl border border-border cursor-pointer">
                <input type="checkbox" wire:model="send_now" class="w-4 h-4 mt-0.5 rounded border-border text-brand">
                <span>
                    <span class="block text-sm font-medium text-ink">Send immediately after saving</span>
                    <span class="block text-xs text-muted mt-0.5">Skip scheduling and start sending as soon as this campaign is saved.</span>
                </span>
            </label>

            <div class="p-4 bg-surface rounded-xl border border-border space-y-2 text-sm">
                <p class="text-xs font-semibold text-muted uppercase tracking-wider mb-1">Summary</p>
                <div class="flex justify-between"><span class="text-muted">Campaign</span><span class="font-medium text-ink">{{ $name ?: '—' }}</span></div>
                <div class="flex justify-between"><span class="text-muted">Channel</span><span class="font-medium text-ink">{{ $channel === 'email' ? 'Email' : 'SMS' }}</span></div>
                <div class="flex justify-between"><span class="text-muted">Audience</span><span class="font-medium text-ink">{{ number_format($audience_count) }} recipients</span></div>
            </div>
        </div>
    @endif

    {{-- Footer Navigation --}}
    <div class="flex items-center justify-between">
        <div>
            @if ($currentStep > 1)
                <button type="button" wire:click="previousStep" class="btn-secondary">Back</button>
            @else
                <a href="{{ route('campaigns.index') }}" wire:navigate class="btn-secondary">Cancel</a>
            @endif
        </div>
        <div>
            @if ($currentStep < 4)
                <button type="button" wire:click="nextStep" class="btn-primary">Next: {{ $this->steps[$currentStep + 1]['label'] }}</button>
            @else
                <button type="button" wire:click="save" class="btn-primary">
                    {{ $send_now ? 'Save & Send Now' : ($scheduled_at ? 'Schedule Campaign' : 'Save Campaign') }}
                </button>
            @endif
        </div>
    </div>

    {{-- Full-page Email Builder --}}
    @if ($showEmailBuilder)
        <div wire:key="campaign-email-builder" class="fixed inset-0 z-50 bg-surface-2 flex flex-col">
            <livewire:email-templates.email-builder :standalone="false" :blocks="$bodyBlocks" :subject="$subject" />
        </div>
    @endif
</div>
