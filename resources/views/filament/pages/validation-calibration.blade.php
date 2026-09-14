<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Methodology quality calibration</x-slot>
            <x-slot name="description">Aggregate, methodology-version-scoped quality signals from completed evaluations. Restricted evidence, rationale and deliberation are intentionally excluded.</x-slot>
        </x-filament::section>

        @if ($reports === [])
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">No completed evaluations are available for calibration yet.</p>
            </x-filament::section>
        @else
            @foreach ($reports as $report)
                <x-filament::section :heading="'Standard Version '.$report['standard_version']">
                    <x-slot name="description">{{ $report['evaluations_completed'] }} completed evaluations</x-slot>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Auditor evaluations</div>
                            <div class="mt-1 text-lg font-semibold">{{ $report['auditor_evaluations'] }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Auditor agreement</div>
                            <div class="mt-1 text-lg font-semibold">
                                {{ $report['criterion_agreement_rate'] === null ? 'Insufficient data' : number_format($report['criterion_agreement_rate'] * 100, 1).'%' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Insufficient evidence</div>
                            <div class="mt-1 text-lg font-semibold">
                                {{ $report['insufficient_evidence_rate'] === null ? 'Insufficient data' : number_format($report['insufficient_evidence_rate'] * 100, 1).'%' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Validated rate</div>
                            <div class="mt-1 text-lg font-semibold">
                                {{ $report['validated_rate'] === null ? 'Insufficient data' : number_format($report['validated_rate'] * 100, 1).'%' }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 lg:grid-cols-3">
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Audience/promise coherence</div>
                            <div class="mt-1 text-sm">
                                {{ $report['audience_coherence_rate'] === null ? 'Insufficient data' : number_format($report['audience_coherence_rate'] * 100, 1).'%' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Mean overall score</div>
                            <div class="mt-1 text-sm">{{ $report['score_mean'] === null ? 'Insufficient data' : number_format($report['score_mean'], 2) }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Score variance</div>
                            <div class="mt-1 text-sm">{{ $report['score_variance'] === null ? 'Insufficient data' : number_format($report['score_variance'], 4) }}</div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <div class="text-sm font-semibold">Decision outcomes</div>
                        @if ($report['decision_outcomes'] === [])
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No decision outcomes recorded.</p>
                        @else
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($report['decision_outcomes'] as $decision => $count)
                                    <x-filament::badge>{{ str($decision)->replace('_', ' ')->title() }}: {{ $count }}</x-filament::badge>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="mt-6">
                        <div class="text-sm font-semibold">Recurring criterion disagreement</div>
                        @if ($report['recurring_disagreements'] === [])
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No criterion-level disagreement has been recorded.</p>
                        @else
                            <div class="mt-2 overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-white/10">
                                            <th class="px-3 py-2">Criterion</th>
                                            <th class="px-3 py-2">Disagreements</th>
                                            <th class="px-3 py-2">Sample</th>
                                            <th class="px-3 py-2">Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($report['recurring_disagreements'] as $disagreement)
                                            <tr class="border-b border-gray-100 dark:border-white/5">
                                                <td class="px-3 py-2">{{ $disagreement['criterion'] }}</td>
                                                <td class="px-3 py-2">{{ $disagreement['disagreement_count'] }}</td>
                                                <td class="px-3 py-2">{{ $disagreement['sample_size'] }}</td>
                                                <td class="px-3 py-2">{{ $disagreement['rate'] === null ? 'Insufficient data' : number_format($disagreement['rate'] * 100, 1).'%' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    @if ($report['review_flags'] !== [])
                        <div class="mt-6 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-400/20 dark:bg-warning-400/10">
                            <div class="text-sm font-semibold">Review flags</div>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                                @foreach ($report['review_flags'] as $flag)
                                    <li>{{ str($flag)->replace('_', ' ')->title() }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="mt-6">
                        <x-filament::button
                            wire:click="recordReview({{ $report['standard_version_id'] }})"
                            icon="heroicon-o-check"
                            color="gray"
                        >
                            Record calibration review
                        </x-filament::button>
                    </div>
                </x-filament::section>
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
