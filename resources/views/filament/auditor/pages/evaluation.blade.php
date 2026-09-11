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
                Assess each applicable criterion independently. Record evidence and rationale for your professional judgement. Auditor work remains private until the methodology permits disclosure.
            </p>

            @if ($this->saveState === 'saving')
                <div class="mb-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-300" role="status">
                    Saving draft…
                </div>
            @elseif ($this->saveState === 'failed')
                <div class="mb-4 rounded-lg bg-danger-50 p-3 text-sm text-danger-700 dark:bg-danger-400/10 dark:text-danger-400" role="alert">
                    The last operation failed. Your current form values remain available. Reload only if you need to recover from a stale workspace.
                </div>
            @elseif ($this->saveState === 'saved')
                <div class="mb-4 text-sm text-gray-500 dark:text-gray-400" role="status">
                    Draft changes are saved.
                </div>
            @endif

            @if ($this->isLocked())
                <div class="mb-5 rounded-lg bg-gray-50 p-4 text-sm text-gray-700 dark:bg-white/5 dark:text-gray-300">
                    This evaluation has been submitted and is now read-only. Historical Auditor results, evidence and findings cannot be changed.
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
                                @if ($criterion->voting_mode->isCollective())
                                    <span>Collective vote</span>
                                @endif
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
                            </div>
                        </div>

                        @if ($result?->evidence->isNotEmpty())
                            <div class="mt-5 rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                                <h4 class="text-sm font-medium text-gray-950 dark:text-white">Evidence references</h4>
                                <ul class="mt-2 space-y-2 text-sm text-gray-700 dark:text-gray-300">
                                    @foreach ($result->evidence as $evidence)
                                        <li class="flex flex-wrap items-center justify-between gap-2">
                                            <span>
                                                <span class="font-medium">{{ $evidence->title }}</span>
                                                <span class="ml-1 text-xs">{{ str($evidence->provenance)->replace('_', ' ')->headline() }}</span>
                                            </span>
                                            <span>
                                                @if ($evidence->source_url)
                                                    <a href="{{ $evidence->source_url }}" target="_blank" rel="noopener noreferrer" class="underline">Open source</a>
                                                @endif
                                                @unless ($this->isLocked())
                                                    <button type="button" wire:click="deleteEvidence({{ $evidence->id }})" class="ml-3 font-medium text-danger-600">Remove</button>
                                                @endunless
                                            </span>
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

        <x-filament::section heading="Evidence references">
            <p class="mb-5 text-sm text-gray-600 dark:text-gray-400">
                Record observable or supplied evidence that supports an assessment. Evidence is private to this Auditor evaluation by default.
            </p>

            @unless ($this->isLocked())
                <div class="grid gap-5 lg:grid-cols-2">
                    <div>
                        <label for="evidence-criterion" class="text-sm font-medium text-gray-950 dark:text-white">Criterion</label>
                        <select id="evidence-criterion" wire:model="evidenceDraft.criterion_id" class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-base shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20">
                            <option value="">General evaluation evidence</option>
                            @foreach (app(\App\Services\AuditorEvaluationWorkspace::class)->criteria($auditorEvaluation) as $criterion)
                                <option value="{{ $criterion->id }}">{{ $criterion->code }} · {{ $criterion->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="evidence-type" class="text-sm font-medium text-gray-950 dark:text-white">Evidence type</label>
                        <select id="evidence-type" wire:model="evidenceDraft.type" class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-base shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20">
                            @foreach ($this->evidenceTypeOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="evidence-provenance" class="text-sm font-medium text-gray-950 dark:text-white">Provenance</label>
                        <select id="evidence-provenance" wire:model="evidenceDraft.provenance" class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-base shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20">
                            @foreach ($this->evidenceProvenanceOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="evidence-title" class="text-sm font-medium text-gray-950 dark:text-white">Title</label>
                        <input id="evidence-title" wire:model="evidenceDraft.title" type="text" class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-base shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20" placeholder="Evidence title">
                    </div>
                    <div>
                        <label for="evidence-url" class="text-sm font-medium text-gray-950 dark:text-white">Source URL</label>
                        <input id="evidence-url" wire:model="evidenceDraft.source_url" type="url" class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-base shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20" placeholder="https://…">
                    </div>
                    <div class="lg:col-span-2">
                        <label for="evidence-description" class="text-sm font-medium text-gray-950 dark:text-white">Description</label>
                        <textarea id="evidence-description" wire:model="evidenceDraft.description" rows="3" class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-base shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20" placeholder="Describe what was observed or supplied and why it matters."></textarea>
                    </div>
                    <div class="lg:col-span-2 flex justify-end">
                        <x-filament::button wire:click="addEvidence" wire:loading.attr="disabled" wire:target="addEvidence">
                            Add evidence reference
                        </x-filament::button>
                    </div>
                </div>
            @endunless

            @if ($auditorEvaluation->evidence->isNotEmpty())
                <ul class="mt-6 divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-white/10 dark:border-white/10">
                    @foreach ($auditorEvaluation->evidence as $evidence)
                        <li class="flex flex-wrap items-start justify-between gap-3 p-4">
                            <div>
                                <p class="font-medium text-gray-950 dark:text-white">{{ $evidence->title }}</p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ str($evidence->type)->headline() }} · {{ str($evidence->provenance)->replace('_', ' ')->headline() }}</p>
                                @if ($evidence->description)
                                    <p class="mt-2 whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">{{ $evidence->description }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                @if ($evidence->source_url)
                                    <a href="{{ $evidence->source_url }}" target="_blank" rel="noopener noreferrer" class="underline">Open source</a>
                                @endif
                                @unless ($this->isLocked())
                                    <button type="button" wire:click="deleteEvidence({{ $evidence->id }})" class="font-medium text-danger-600">Remove</button>
                                @endunless
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-5 rounded-lg border border-dashed border-gray-300 p-5 text-sm text-gray-600 dark:border-white/20 dark:text-gray-400">No evidence references recorded yet.</p>
            @endif
        </x-filament::section>

        <x-filament::section heading="Auditor findings">
            <p class="mb-5 text-sm text-gray-600 dark:text-gray-400">
                Record strengths, weaknesses, risks, recommendations and factual clarifications arising from the evaluation.
            </p>

            @unless ($this->isLocked())
                <div class="grid gap-5 lg:grid-cols-2">
                    <div>
                        <label for="finding-criterion" class="text-sm font-medium text-gray-950 dark:text-white">Criterion</label>
                        <select id="finding-criterion" wire:model="findingDraft.criterion_id" class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-base shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20">
                            <option value="">General evaluation finding</option>
                            @foreach (app(\App\Services\AuditorEvaluationWorkspace::class)->criteria($auditorEvaluation) as $criterion)
                                <option value="{{ $criterion->id }}">{{ $criterion->code }} · {{ $criterion->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="finding-type" class="text-sm font-medium text-gray-950 dark:text-white">Finding type</label>
                        <select id="finding-type" wire:model="findingDraft.type" class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-base shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20">
                            @foreach ($this->findingTypeOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="finding-severity" class="text-sm font-medium text-gray-950 dark:text-white">Severity</label>
                        <select id="finding-severity" wire:model="findingDraft.severity" class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-base shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20">
                            @foreach ($this->findingSeverityOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="finding-title" class="text-sm font-medium text-gray-950 dark:text-white">Title</label>
                        <input id="finding-title" wire:model="findingDraft.title" type="text" class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-base shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20" placeholder="Finding title">
                    </div>
                    <div class="lg:col-span-2">
                        <label for="finding-description" class="text-sm font-medium text-gray-950 dark:text-white">Description</label>
                        <textarea id="finding-description" wire:model="findingDraft.description" rows="4" class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-base shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/20" placeholder="Describe the finding and its significance."></textarea>
                    </div>
                    <div class="lg:col-span-2 flex justify-end">
                        <x-filament::button wire:click="addFinding" wire:loading.attr="disabled" wire:target="addFinding">
                            Add finding
                        </x-filament::button>
                    </div>
                </div>
            @endunless

            @if ($auditorEvaluation->findings->isNotEmpty())
                <ul class="mt-6 divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-white/10 dark:border-white/10">
                    @foreach ($auditorEvaluation->findings as $finding)
                        <li class="flex flex-wrap items-start justify-between gap-3 p-4">
                            <div>
                                <div class="flex flex-wrap gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    <span>{{ str($finding->type)->replace('_', ' ')->headline() }}</span>
                                    <span>{{ str($finding->severity)->headline() }}</span>
                                    @if ($finding->criterion)
                                        <span>{{ $finding->criterion->code }}</span>
                                    @endif
                                </div>
                                <p class="mt-1 font-medium text-gray-950 dark:text-white">{{ $finding->title }}</p>
                                <p class="mt-2 whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">{{ $finding->description }}</p>
                            </div>
                            @unless ($this->isLocked())
                                <button type="button" wire:click="deleteFinding({{ $finding->id }})" class="font-medium text-sm text-danger-600">Remove</button>
                            @endunless
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-5 rounded-lg border border-dashed border-gray-300 p-5 text-sm text-gray-600 dark:border-white/20 dark:text-gray-400">No Auditor findings recorded yet.</p>
            @endif
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
