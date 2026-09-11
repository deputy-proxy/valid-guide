<div>
    <x-layouts::app :title="__('Evaluation report')">
        <div class="mx-auto w-full max-w-6xl space-y-8">
            <div>
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Creator report</p>
                <h1 class="mt-1 text-3xl font-semibold text-zinc-900 dark:text-white">
                    {{ $reportData['product']['name'] ?? 'Evaluation report' }}
                </h1>
                <p class="mt-2 text-zinc-600 dark:text-zinc-300">
                    Release {{ $reportData['release']['version'] ?? '—' }}
                </p>
            </div>

            @if (session('success'))
                <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                    <h2 class="font-semibold text-zinc-900 dark:text-white">Result</h2>
                    <p class="mt-4 text-2xl font-semibold capitalize text-zinc-900 dark:text-white">
                        {{ str((string) ($reportData['evaluation']['decision'] ?? 'unknown'))->replace('_', ' ')->headline() }}
                    </p>
                    @if (($reportData['evaluation']['overall_score'] ?? null) !== null)
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                            Overall score: {{ $reportData['evaluation']['overall_score'] }}/100
                        </p>
                    @endif
                </section>

                <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                    <h2 class="font-semibold text-zinc-900 dark:text-white">Methodology</h2>
                    <p class="mt-4 text-zinc-900 dark:text-white">{{ $reportData['standard']['name'] ?? '—' }}</p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                        Standard Version {{ $reportData['standard']['version'] ?? '—' }}
                    </p>
                </section>

                <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                    <h2 class="font-semibold text-zinc-900 dark:text-white">Validation</h2>
                    @if ($reportData['validation'] === null)
                        <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-300">No Validation record is attached to this evaluation.</p>
                    @else
                        <p class="mt-4 text-lg font-semibold capitalize text-zinc-900 dark:text-white">
                            {{ str($reportData['validation']['status'])->replace('_', ' ')->headline() }}
                        </p>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                            Issued {{ $reportData['validation']['issued_at'] ?? '—' }}
                        </p>
                        @if (($reportData['validation']['status_reason'] ?? null) !== null)
                            <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">{{ $reportData['validation']['status_reason'] }}</p>
                        @endif
                        @if (($reportData['validation']['verification_url'] ?? null) !== null)
                            <a href="{{ $reportData['validation']['verification_url'] }}" class="mt-4 inline-flex text-sm font-medium underline">Open verification record</a>
                        @endif
                    @endif
                </section>
            </div>

            <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">Criterion results</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Only creator-permitted evaluation outcomes are shown.</p>
                <div class="mt-6 overflow-x-auto">
                    @if (count($reportData['criteria'] ?? []) > 0)
                        <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                            <thead>
                                <tr>
                                    <th scope="col" class="px-3 py-3 font-medium text-zinc-500">Criterion</th>
                                    <th scope="col" class="px-3 py-3 font-medium text-zinc-500">Assessment</th>
                                    <th scope="col" class="px-3 py-3 font-medium text-zinc-500">Score</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach ($reportData['criteria'] as $criterion)
                                    <tr>
                                        <td class="px-3 py-3">
                                            <div class="font-medium text-zinc-900 dark:text-white">{{ $criterion['name'] ?? $criterion['code'] ?? 'Criterion' }}</div>
                                            @if (($criterion['code'] ?? null) !== null)
                                                <div class="text-xs text-zinc-500">{{ $criterion['code'] }}</div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 capitalize">{{ str((string) ($criterion['assessment'] ?? '—'))->replace('_', ' ')->headline() }}</td>
                                        <td class="px-3 py-3">{{ $criterion['score'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">No creator-visible criterion outcomes are recorded.</p>
                    @endif
                </div>
            </section>

            @php
                $selectedVersion = collect($reportData['report']['versions'] ?? [])->firstWhere('id', $selectedVersionId);
            @endphp

            <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">Report</h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Historical versions are read-only.</p>
                    </div>
                    <div class="flex flex-wrap gap-2" aria-label="Report versions">
                        @foreach (($reportData['report']['versions'] ?? []) as $version)
                            <button
                                type="button"
                                wire:click="selectVersion({{ $version['id'] }})"
                                aria-pressed="{{ $selectedVersionId === $version['id'] ? 'true' : 'false' }}"
                                class="rounded-lg border px-3 py-2 text-sm {{ $selectedVersionId === $version['id'] ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-100 dark:bg-zinc-800' : 'border-zinc-200 dark:border-zinc-700' }}"
                            >
                                Version {{ $version['version_number'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
                @error('version') <p class="mt-3 text-sm text-red-700">{{ $message }}</p> @enderror

                @if ($selectedVersion !== null)
                    <div class="mt-6 space-y-6">
                        <div>
                            <h3 class="font-medium text-zinc-900 dark:text-white">Abstract</h3>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $selectedVersion['abstract'] ?? 'No abstract available.' }}</p>
                        </div>

                        @if (is_array($selectedVersion['content_structure'] ?? null))
                            <div>
                                <h3 class="font-medium text-zinc-900 dark:text-white">Report details</h3>
                                <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                                    @foreach ($selectedVersion['content_structure'] as $key => $value)
                                        @if (is_scalar($value) && $key !== 'generated_from')
                                            <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ str((string) $key)->replace('_', ' ')->headline() }}</dt>
                                                <dd class="mt-1 text-sm text-zinc-900 dark:text-white">{{ (string) $value }}</dd>
                                            </div>
                                        @endif
                                    @endforeach
                                </dl>
                            </div>
                        @endif
                    </div>
                @else
                    <p class="mt-6 text-sm text-zinc-600 dark:text-zinc-300">No report version is available.</p>
                @endif
            </section>

            @foreach ([
                'strengths' => 'Strengths',
                'weaknesses' => 'Weaknesses',
            ] as $key => $title)
                <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                    <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">{{ $title }}</h2>
                    <div class="mt-6 space-y-4">
                        @forelse ($reportData[$key] ?? [] as $finding)
                            <article class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                                <h3 class="font-medium text-zinc-900 dark:text-white">{{ $finding['title'] }}</h3>
                                <p class="mt-1 whitespace-pre-line text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $finding['description'] }}</p>
                            </article>
                        @empty
                            <p class="text-sm text-zinc-600 dark:text-zinc-300">No {{ strtolower($title) }} are recorded.</p>
                        @endforelse
                    </div>
                </section>
            @endforeach

            <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">Creator Action Plan</h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Actionable next steps derived from creator-visible findings.</p>
                    </div>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $reportData['actions']['summary']['total'] ?? 0 }} total · {{ $reportData['actions']['summary']['completed'] ?? 0 }} completed
                    </p>
                </div>
                @error('action') <p class="mt-3 text-sm text-red-700" role="alert">{{ $message }}</p> @enderror
                <div class="mt-6 space-y-4">
                    @forelse ($reportData['actions']['items'] ?? [] as $action)
                        <article class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                <span class="font-medium uppercase tracking-wide">{{ str((string) $action['priority'])->headline() }}</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ str((string) $action['status'])->replace('_', ' ')->headline() }}</span>
                            </div>
                            <h3 class="mt-2 font-medium text-zinc-900 dark:text-white">{{ $action['title'] }}</h3>
                            <p class="mt-1 whitespace-pre-line text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $action['description'] }}</p>
                            @if (($action['finding_title'] ?? null) !== null)
                                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">Based on: {{ $action['finding_title'] }}</p>
                            @endif
                            @if (($action['assignee_name'] ?? null) !== null)
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Assigned to: {{ $action['assignee_name'] }}</p>
                            @endif
                            @if (($action['due_at'] ?? null) !== null)
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Due: {{ $action['due_at'] }}</p>
                            @endif
                            <div class="mt-4 flex flex-wrap gap-2">
                                @if ($action['status'] === 'pending')
                                    <button type="button" wire:click="transitionAction({{ $action['id'] }}, 'in_progress')" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium dark:border-zinc-600">Start action</button>
                                @elseif ($action['status'] === 'in_progress')
                                    <button type="button" wire:click="transitionAction({{ $action['id'] }}, 'completed')" class="rounded-lg bg-zinc-900 px-3 py-2 text-sm font-medium text-white">Mark complete</button>
                                    <button type="button" wire:click="transitionAction({{ $action['id'] }}, 'pending')" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium dark:border-zinc-600">Move back to pending</button>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">No actionable creator steps were identified from this evaluation.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">Findings and recommendations</h2>
                <div class="mt-6 space-y-4">
                    @forelse ($reportData['findings'] ?? [] as $finding)
                        <article class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500">
                                <span>{{ str($finding['type'])->replace('_', ' ')->headline() }}</span>
                                @if ($finding['severity'] !== null)
                                    <span>· {{ $finding['severity'] }}</span>
                                @endif
                                @if ($finding['criterion'] !== null)
                                    <span>· {{ $finding['criterion'] }}</span>
                                @endif
                            </div>
                            <h3 class="mt-2 font-medium text-zinc-900 dark:text-white">{{ $finding['title'] }}</h3>
                            <p class="mt-1 whitespace-pre-line text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $finding['description'] }}</p>
                        </article>
                    @empty
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">No creator-visible findings are recorded.</p>
                    @endforelse
                </div>
                <p class="mt-6 text-xs text-zinc-500 dark:text-zinc-400">Private Auditor evidence and deliberation are intentionally excluded.</p>
            </section>

            @if (($reportData['validation']['badge'] ?? null) !== null)
                <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                    <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">Validation badge</h2>
                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-zinc-500">Status</p>
                            <p class="mt-1 capitalize">{{ str($reportData['validation']['badge']['status'])->replace('_', ' ')->headline() }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-zinc-500">Embed version</p>
                            <p class="mt-1">{{ $reportData['validation']['badge']['embed_version'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-zinc-500">Verification ID</p>
                            <p class="mt-1 break-all">{{ $reportData['validation']['badge']['verification_identifier'] }}</p>
                        </div>
                    </div>
                </section>
            @endif

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                    <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">Request clarification</h2>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Use clarification for questions or factual/process issues. It does not change the historical report.</p>
                    <form wire:submit="submitClarification" class="mt-6 space-y-4">
                        <label class="block text-sm font-medium">
                            Type
                            <select wire:model="clarificationType" class="mt-1 block w-full rounded-lg border-zinc-300">
                                @foreach ($this->clarificationTypeOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block text-sm font-medium">
                            Message
                            <textarea wire:model="clarificationMessage" rows="5" class="mt-1 block w-full rounded-lg border-zinc-300" required></textarea>
                        </label>
                        @error('clarification') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                        <button type="submit" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white">Submit clarification</button>
                    </form>
                </section>

                <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                    <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">Formal dispute</h2>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Disputes are limited to approved procedural, factual, conflict-of-interest or methodology grounds.</p>
                    <form wire:submit="submitDispute" class="mt-6 space-y-4">
                        <fieldset>
                            <legend class="text-sm font-medium">Grounds</legend>
                            <div class="mt-2 space-y-2">
                                @foreach ($this->disputeGroundOptions() as $value => $label)
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" wire:model="disputeGrounds" value="{{ $value }}">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <label class="block text-sm font-medium">
                            Statement
                            <textarea wire:model="disputeStatement" rows="5" class="mt-1 block w-full rounded-lg border-zinc-300" required></textarea>
                        </label>
                        @error('dispute') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                        @error('disputeGrounds') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                        @error('disputeStatement') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                        <button type="submit" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white">Submit formal dispute</button>
                    </form>
                </section>
            </div>
        </div>
    </x-layouts::app>
</div>
