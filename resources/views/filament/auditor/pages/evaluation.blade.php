<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section heading="Evaluation context">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $auditorEvaluation->evaluation->product?->title ?? 'Evaluated product' }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Release {{ $auditorEvaluation->evaluation->productRelease?->release_identifier ?? 'Not recorded' }}
                        · Standard {{ $auditorEvaluation->evaluation->standardVersion->version }}
                    </p>
                </div>
                <x-filament::badge>{{ $this->statusLabel() }}</x-filament::badge>
            </div>

            <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Evaluation scope</dt>
                    <dd class="mt-1 text-sm text-gray-950 dark:text-white">
                        {{ data_get(json_decode($auditorEvaluation->evaluation->request->intake_notes ?? '', true), 'scope', 'Not specified') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Audience</dt>
                    <dd class="mt-1 text-sm text-gray-950 dark:text-white">
                        {{ data_get(json_decode($auditorEvaluation->evaluation->request->intake_notes ?? '', true), 'audience', 'Not specified') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Standard version</dt>
                    <dd class="mt-1 text-sm text-gray-950 dark:text-white">{{ $auditorEvaluation->evaluation->standardVersion->version }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Due</dt>
                    <dd class="mt-1 text-sm text-gray-950 dark:text-white">
                        {{ $auditorEvaluation->assignment->due_at?->format('d M Y H:i') ?? 'No deadline recorded' }}
                    </dd>
                </div>
            </dl>
        </x-filament::section>

        <x-filament::section heading="Auditor work">
            <p class="mb-5 text-sm text-gray-600 dark:text-gray-400">
                Assess each criterion independently. Your rationale and evidence remain private to your Auditor evaluation until the methodology permits disclosure.
            </p>

            @if ($this->saveState === 'saving')
                <div class="mb-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-300" role="status">
                    Saving draft…
                </div>
            @elseif ($this->saveState === 'failed')
                <div class="mb-4 rounded-lg bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-400/10 dark:text-danger-400" role="alert">
                    The last save failed. Your current form values remain available. Reload only if you need to recover from a stale workspace.
                </div>
            @elseif ($this->saveState === 'saved')
                <div class="mb-4 text-sm text-gray-500 dark:text-gray-400" role="status">
                    Draft changes are saved.
                </div>
            @endif

            @if ($this->isLocked())
                <div class="mb-5 rounded-lg bg-gray-50 p-4 text-sm text-gray-700 dark:bg-white/5 dark:text-gray-300">
                    This evaluation has been submitted and is now read-only. Historical Auditor results cannot be changed.
                </div>
            @endif

            <div class="space-y-6">
                @forelse (app(\App\Services\AuditorEvaluationWorkspace::class)->criteria($auditorEvaluation) as $criterion)
                    @php
                        $criterionId = $criterion->getKey();
                        $result = $this->resultFor($criterionId);
                        $draft = $drafts[$criterionId] ?? ['assessment' => '', 'score' => '', 'rationale' => '', 'confidence' => ''];
                        $range = $this->scoreRangeFor($criterionId);
                    @endphp

                    <article class="rounded-xl border border-gray-200 p-5 dark:border-white/10">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $criterion->code }}</p>
                                <h3 class="mt-1 text-base font-semibold text-gray-950 dark:text-white">{{ $criterion->name }}</h3>
                            </div>
                            <div class="flex gap-2 text-xs text-gray-500 dark:text-gray-400">
                                @if ($criterion->is_mandatory)
                                    <span>Mandatory</span>
                                @endif
                                <span>Weight {{ $criterion->weight }}</span>
                            </div>
                        </div>

                        @if ($criterion->description)
                            <p class="mt-4 text-sm text-gray-700 dark:text-gray-300">{{ $criterion->description }}</p>
                        @endif

                        @if ($criterion->guidance->isNotEmpty())
                            <details class="mt-4 rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                                <summary class="cursor-pointer text-sm font-medium text-gray-950 dark:text-white">Criterion guidance</summary>
                                <div class="mt-3 space-y-3 text-sm text-gray-700 dark:text-gray-300">
                                    @foreach ($criterion->guidance as $guidance)
                                        @if ($guidance->title)
                                            <h4 class="font-medium text-gray-950 dark:text-white">{{ $guidance->title }}</h4>
                                        @endif
                                        <p class="whitespace-pre-wrap">{{ $guidance->content }}</p>
                                        @if (is_array($guidance->evidence_expectations) && $guidance->evidence_expectations !== [])
                                            <div>
                                                <p class="font-medium">Evidence expectations</p>
                                                <ul class="mt-1 list-disc pl-5">
                                                    @foreach ($guidance->evidence_expectations as $expectation)
                                                        <li>{{ is_string($expectation) ? $expectation : json_encode($expectation) }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </details>
                        @endif

                        <div class="mt-5 grid gap-5 lg:grid-cols-2">
                            <div>
                                <label for="assessment-{{ $criterionId }}" class="text-sm font-medium text-gray-950 dark:text-white">Assessment</label>
                                <select
                                    id="assessment-{{ $criterionId }}"
                                    wire:model.live="drafts.{{ $criterionId }}.assessment"
                                    @disabled($this->isLocked())
                                    class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-base text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white/5 dark:text-white dark:ring-white/20"
                                >
                                    <option value="">Select assessment</option>
                                    @foreach ($this->assessmentOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error("drafts.$criterionId.assessment")
                                    <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="score-{{ $criterionId }}" class="text-sm font-medium text-gray-950 dark:text-white">Score</label>
                                <input
                                    id="score-{{ $criterionId }}"
                                    type="number"
                                    step="0.01"
                                    min="{{ $range['min'] ?? 0 }}"
                                    max="{{ $range['max'] ?? 100 }}"
                                    wire:model="drafts.{{ $criterionId }}.score"
                                    @disabled($this->isLocked() || $range === null)
                                    class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-base text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white/5 dark:text-white dark:ring-white/20"
                                    placeholder="{{ $range ? $range['min'].'–'.$range['max'] : 'Not scored' }}"
                                >
                                @if ($range)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Allowed range: {{ $range['min'] }}–{{ $range['max'] }}.</p>
                                @else
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">This assessment is not numerically scored.</p>
                                @endif
                                @error("drafts.$criterionId.score")
                                    <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="confidence-{{ $criterionId }}" class="text-sm font-medium text-gray-950 dark:text-white">Confidence</label>
                                <input
                                    id="confidence-{{ $criterionId }}"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    wire:model="drafts.{{ $criterionId }}.confidence"
                                    @disabled($this->isLocked())
                                    class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-base text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white/5 dark:text-white dark:ring-white/20"
                                    placeholder="0–100"
                                >
                            </div>

                            <div class="lg:col-span-2">
                                <label for="rationale-{{ $criterionId }}" class="text-sm font-medium text-gray-950 dark:text-white">Auditor rationale</label>
                                <textarea
                                    id="rationale-{{ $criterionId }}"
                                    wire:model="drafts.{{ $criterionId }}.rationale"
                                    rows="5"
                                    @disabled($this->isLocked())
                                    class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-base text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white/5 dark:text-white dark:ring-white/20"
                                    placeholder="Record the reasoning supporting this assessment."
                                ></textarea>
                                @error("drafts.$criterionId.rationale")
                                    <p class="mt-1 text-sm text-danger-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        @if ($result?->evidence->isNotEmpty())
                            <div class="mt-5 rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                                <h4 class="text-sm font-medium text-gray-950 dark:text-white">Evidence references</h4>
                                <ul class="mt-2 space-y-2 text-sm text-gray-700 dark:text-gray-300">
                                    @foreach ($result->evidence as $evidence)
                                        <li>
                                            <span class="font-medium">{{ $evidence->title }}</span>
                                            @if ($evidence->source_url)
                                                <a href="{{ $evidence->source_url }}" target="_blank" rel="noopener noreferrer" class="ml-1 underline">Open source</a>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @unless ($this->isLocked())
                            <div class="mt-5 flex justify-end">
                                <x-filament::button wire:click="saveCriterion({{ $criterionId }})" wire:loading.attr="disabled" wire:target="saveCriterion({{ $criterionId }})">
                                    Save draft
                                </x-filament::button>
                            </div>
                        @endunless
                    </article>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-600 dark:border-white/20 dark:text-gray-400">
                        No applicable criteria are available for this frozen Standard Version.
                    </div>
                @endforelse
            </div>
        </x-filament::section>

        <div class="flex flex-wrap justify-between gap-3">
            <x-filament::button tag="a" :href="$this->assignmentUrl()" color="gray">
                Back to assignment
            </x-filament::button>

            @unless ($this->isLocked())
                <x-filament::button wire:click="submit" wire:loading.attr="disabled" wire:target="submit">
                    Submit evaluation
                </x-filament::button>
            @endunless
        </div>
    </div>
</x-filament-panels::page>
