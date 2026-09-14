<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Methodology quality measurement</x-slot>
            <x-slot name="description">Aggregate, methodology-version-scoped signals only. Restricted evidence, rationale and Auditor deliberation are intentionally excluded.</x-slot>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Minimum sample</div>
                    <div class="mt-1 text-lg font-semibold">3 completed evaluations</div>
                </div>
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Historical records</div>
                    <div class="mt-1 text-lg font-semibold">Read-only</div>
                </div>
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Review logging</div>
                    <div class="mt-1 text-lg font-semibold">AuditLogger</div>
                </div>
            </div>
        </x-filament::section>

        @if ($measurements === [])
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400">No methodology versions are available.</div>
            </x-filament::section>
        @else
            @foreach ($measurements as $measurement)
                <x-filament::section>
                    <x-slot name="heading">Standard Version {{ $measurement['standard_version'] }}</x-slot>
                    <x-slot name="description">Version ID {{ $measurement['standard_version_id'] }} · Calibration status: {{ str($measurement['status'])->replace('_', ' ')->title() }}</x-slot>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Completed evaluations</div>
                            <div class="mt-1 text-lg font-semibold">{{ $measurement['completed_evaluations'] }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Insufficient evidence</div>
                            <div class="mt-1 text-lg font-semibold">
                                {{ $measurement['insufficient_evidence'] }}
                                @if ($measurement['insufficient_evidence_rate'] !== null)
                                    <span class="text-sm font-normal">({{ $measurement['insufficient_evidence_rate'] }}%)</span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Auditor agreement</div>
                            <div class="mt-1 text-lg font-semibold">
                                {{ $measurement['agreement_rate'] !== null ? $measurement['agreement_rate'].'%' : 'Insufficient data' }}
                            </div>
                            <div class="text-xs text-gray-500">{{ $measurement['comparable_criterion_groups'] }} comparable criterion groups</div>
                        </div>
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Score variance</div>
                            <div class="mt-1 text-lg font-semibold">{{ $measurement['score_variance'] !== null ? $measurement['score_variance'] : 'Insufficient data' }}</div>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-6 lg:grid-cols-2">
                        <div>
                            <h3 class="font-semibold">Decision outcomes</h3>
                            @if ($measurement['decision_outcomes'] === [])
                                <p class="mt-2 text-sm text-gray-500">No completed decision outcomes are available.</p>
                            @else
                                <dl class="mt-3 space-y-2 text-sm">
                                    @foreach ($measurement['decision_outcomes'] as $decision => $count)
                                        <div class="flex items-center justify-between gap-4">
                                            <dt>{{ str($decision)->replace('_', ' ')->title() }}</dt>
                                            <dd class="font-medium">{{ $count }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @endif
                        </div>

                        <div>
                            <h3 class="font-semibold">Review flags</h3>
                            @if ($measurement['review_flags'] === [])
                                <p class="mt-2 text-sm text-gray-500">No calibration review flags.</p>
                            @else
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($measurement['review_flags'] as $flag)
                                        <x-filament::badge color="warning">{{ str($flag)->replace('_', ' ')->title() }}</x-filament::badge>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($measurement['recurring_disagreements'] !== [])
                        <div class="mt-6">
                            <h3 class="font-semibold">Recurring criterion disagreement</h3>
                            <div class="mt-3 overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-white/10">
                                            <th class="px-2 py-2 font-medium">Criterion</th>
                                            <th class="px-2 py-2 font-medium">Evaluations</th>
                                            <th class="px-2 py-2 font-medium">Disagreements</th>
                                            <th class="px-2 py-2 font-medium">Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($measurement['recurring_disagreements'] as $disagreement)
                                            <tr class="border-b border-gray-100 dark:border-white/5">
                                                <td class="px-2 py-2">{{ $disagreement['criterion'] }}</td>
                                                <td class="px-2 py-2">{{ $disagreement['evaluations'] }}</td>
                                                <td class="px-2 py-2">{{ $disagreement['disagreements'] }}</td>
                                                <td class="px-2 py-2">{{ $disagreement['rate'] }}%</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <div class="mt-6">
                        <x-filament::button
                            wire:click="recordReview({{ $measurement['standard_version_id'] }})"
                            wire:loading.attr="disabled"
                            icon="heroicon-o-clipboard-document-check"
                        >
                            Record calibration review
                        </x-filament::button>
                    </div>
                </x-filament::section>
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
