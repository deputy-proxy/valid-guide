@php
    $verification = is_array($snapshot['verification'] ?? null) ? $snapshot['verification'] : [];
    $statusHistory = is_array($verification['status_history'] ?? null) ? $verification['status_history'] : [];
    $product = is_array($snapshot['product'] ?? null) ? $snapshot['product'] : [];
    $standard = is_array($snapshot['standard'] ?? null) ? $snapshot['standard'] : [];
    $scope = is_array($snapshot['scope'] ?? null) ? $snapshot['scope'] : [];
    $result = is_array($snapshot['result'] ?? null) ? $snapshot['result'] : [];
    $auditors = is_array($snapshot['auditors'] ?? null) ? $snapshot['auditors'] : [];
    $report = is_array($snapshot['report'] ?? null) ? $snapshot['report'] : [];
    $status = is_string($verification['status'] ?? null) ? $verification['status'] : 'unknown';
    $statusLabel = str($status)->replace('_', ' ')->title()->toString();
    $statusTone = match ($status) {
        'active' => 'success',
        'revoked' => 'danger',
        'suspended' => 'warning',
        default => 'neutral',
    };
@endphp

<x-layouts.public
    :title="$title"
    :description="$description"
    :canonical-url="$canonicalUrl"
    :robots="$robots"
>
    <x-public.section class="pb-10 pt-12 sm:pb-12 sm:pt-20">
        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-start">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">Valid.guide verification</p>
                    <x-public.status :label="$statusLabel" :tone="$statusTone" />
                </div>
                <h1 class="mt-4 max-w-4xl text-4xl font-semibold tracking-tight text-zinc-950 sm:text-5xl dark:text-white">
                    {{ $product['title'] ?? 'Verified learning product' }}
                </h1>
                <p class="mt-4 max-w-3xl text-lg leading-8 text-zinc-600 dark:text-zinc-300">
                    This page presents the persisted public verification record for the evaluated product release.
                </p>
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">Verification ID</p>
                <p class="mt-2 break-all font-mono text-sm font-semibold text-zinc-950 dark:text-white">{{ $verification['identifier'] ?? $snapshot['verification_identifier'] ?? 'Unavailable' }}</p>
                <p class="mt-4 text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                    Issued {{ $verification['issued_at'] ?? 'on record' }}
                </p>
            </div>
        </div>
    </x-public.section>

    @if ($status === 'revoked')
        <x-public.section class="py-8">
            <div class="rounded-2xl border border-red-200 bg-red-50 p-6 dark:border-red-900 dark:bg-red-950/30">
                <h2 class="text-xl font-semibold text-red-900 dark:text-red-100">This verification has been revoked</h2>
                <p class="mt-2 text-sm leading-6 text-red-800 dark:text-red-200">
                    The verification identifier remains resolvable so its trust history can be checked. The current status is revoked.
                </p>
                @if ($statusHistory['revoked_at'] ?? null)
                    <p class="mt-3 text-sm font-medium text-red-900 dark:text-red-100">Revoked: {{ $statusHistory['revoked_at'] }}</p>
                @endif
                @if ($statusHistory['reason'] ?? null)
                    <p class="mt-2 text-sm leading-6 text-red-800 dark:text-red-200">Reason: {{ $statusHistory['reason'] }}</p>
                @endif
            </div>
        </x-public.section>
    @endif

    <x-public.section title="Product and validation" description="The public identity and result are tied to the exact evaluated release and methodology version.">
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Creator</dt><dd class="mt-1 font-medium">{{ $product['creator'] ?? 'Not disclosed' }}</dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Release</dt><dd class="mt-1 font-medium">{{ $product['release_identifier'] ?? 'Not available' }}</dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Version</dt><dd class="mt-1 font-medium">{{ $product['version'] ?? 'Not available' }}</dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Product type</dt><dd class="mt-1 font-medium">{{ $product['type'] ?? 'Not available' }}</dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Standard</dt><dd class="mt-1 font-medium">{{ $standard['name'] ?? 'Not available' }}</dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Standard version</dt><dd class="mt-1 font-medium">{{ $standard['version'] ?? 'Not available' }}</dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Decision</dt><dd class="mt-1 font-medium">{{ $result['decision'] ?? 'Not available' }}</dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Overall score</dt><dd class="mt-1 font-medium">{{ $result['overall_score'] ?? 'Not scored' }}</dd></div>
        </div>
    </x-public.section>

    <x-public.section title="Evaluation scope">
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900/40">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Complexity</p>
            <p class="mt-1 font-medium">{{ $scope['complexity'] ?? 'Not specified' }}</p>
            @if (!empty($scope['criteria']) && is_array($scope['criteria']))
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    @foreach ($scope['criteria'] as $criterion)
                        @if (is_array($criterion))
                            <article class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="flex items-start justify-between gap-4">
                                    <h3 class="font-semibold">{{ $criterion['code'] ?? 'Criterion' }} · {{ $criterion['name'] ?? 'Unnamed criterion' }}</h3>
                                    @if (($criterion['mandatory'] ?? false) === true)
                                        <span class="shrink-0 text-xs font-medium text-zinc-500 dark:text-zinc-400">Mandatory</span>
                                    @endif
                                </div>
                                @if ($criterion['description'] ?? null)
                                    <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $criterion['description'] }}</p>
                                @endif
                            </article>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </x-public.section>

    <x-public.section title="Criterion results">
        <div class="overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-800">
            @if (!empty($snapshot['criteria']) && is_array($snapshot['criteria']))
                <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($snapshot['criteria'] as $criterion)
                        @if (is_array($criterion))
                            <article class="grid gap-4 p-5 sm:grid-cols-[minmax(0,1fr)_8rem_8rem_6rem] sm:items-center">
                                <div>
                                    <h3 class="font-semibold">{{ $criterion['code'] ?? 'Criterion' }} · {{ $criterion['name'] ?? 'Unnamed criterion' }}</h3>
                                </div>
                                <div><p class="text-xs text-zinc-500 dark:text-zinc-400">Assessment</p><p class="mt-1 font-medium">{{ $criterion['assessment'] ?? 'Not assessed' }}</p></div>
                                <div><p class="text-xs text-zinc-500 dark:text-zinc-400">Score</p><p class="mt-1 font-medium">{{ $criterion['score'] ?? 'Not scored' }}</p></div>
                                <div><p class="text-xs text-zinc-500 dark:text-zinc-400">Voters</p><p class="mt-1 font-medium">{{ $criterion['voter_count'] ?? 0 }}</p></div>
                            </article>
                        @endif
                    @endforeach
                </div>
            @else
                <div class="p-6 text-sm text-zinc-600 dark:text-zinc-400">No criterion-level results are available in this public record.</div>
            @endif
        </div>
    </x-public.section>

    <x-public.section title="Strengths and weaknesses">
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800">
                <h3 class="text-lg font-semibold">Strengths</h3>
                <div class="mt-5 space-y-5">
                    @forelse ($snapshot['strengths'] ?? [] as $finding)
                        @if (is_array($finding))
                            <article>
                                <h4 class="font-medium">{{ $finding['title'] ?? 'Strength' }}</h4>
                                <p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $finding['description'] ?? '' }}</p>
                            </article>
                        @endif
                    @empty
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">No public strengths were recorded.</p>
                    @endforelse
                </div>
            </div>
            <div class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800">
                <h3 class="text-lg font-semibold">Weaknesses</h3>
                <div class="mt-5 space-y-5">
                    @forelse ($snapshot['weaknesses'] ?? [] as $finding)
                        @if (is_array($finding))
                            <article>
                                <h4 class="font-medium">{{ $finding['title'] ?? 'Weakness' }}</h4>
                                <p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $finding['description'] ?? '' }}</p>
                            </article>
                        @endif
                    @empty
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">No public weaknesses were recorded.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </x-public.section>

    <x-public.section title="Auditors" description="Auditor identity and credentials are disclosed only for approved public profiles.">
        <div class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Auditors involved</p>
            <p class="mt-1 text-2xl font-semibold">{{ $auditors['count'] ?? 0 }}</p>
            @if (!empty($auditors['disclosed']) && is_array($auditors['disclosed']))
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    @foreach ($auditors['disclosed'] as $auditor)
                        @if (is_array($auditor))
                            <article class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                                <h3 class="font-semibold">{{ $auditor['name'] ?? 'Approved auditor' }}</h3>
                                @if ($auditor['credentials'] ?? null)<p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $auditor['credentials'] }}</p>@endif
                                @if ($auditor['bio'] ?? null)<p class="mt-3 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $auditor['bio'] }}</p>@endif
                            </article>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </x-public.section>

    @if ($report['abstract'] ?? null)
        <x-public.section title="Report">
            <div class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800">
                <h3 class="text-lg font-semibold">Abstract</h3>
                <p class="mt-4 whitespace-pre-line text-sm leading-7 text-zinc-600 dark:text-zinc-400">{{ $report['abstract'] }}</p>

                @if (($visibility['full_report'] ?? false) === true && ($report['content'] ?? null) !== null)
                    <details class="mt-8 rounded-xl border border-zinc-200 dark:border-zinc-800">
                        <summary class="cursor-pointer px-5 py-4 font-medium focus:outline-none focus:ring-2 focus:ring-zinc-950 dark:focus:ring-white">Full report</summary>
                        <div class="border-t border-zinc-200 p-5 dark:border-zinc-800">
                            <pre class="overflow-x-auto whitespace-pre-wrap break-words text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ json_encode($report['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </details>
                @endif
            </div>
        </x-public.section>
    @endif

    <x-public.section title="Verification history">
        <dl class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800"><dt class="text-xs text-zinc-500 dark:text-zinc-400">Issued</dt><dd class="mt-1 text-sm font-medium">{{ $statusHistory['issued_at'] ?? 'Not recorded' }}</dd></div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800"><dt class="text-xs text-zinc-500 dark:text-zinc-400">Suspended</dt><dd class="mt-1 text-sm font-medium">{{ $statusHistory['suspended_at'] ?? 'Not recorded' }}</dd></div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800"><dt class="text-xs text-zinc-500 dark:text-zinc-400">Revoked</dt><dd class="mt-1 text-sm font-medium">{{ $statusHistory['revoked_at'] ?? 'Not recorded' }}</dd></div>
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800"><dt class="text-xs text-zinc-500 dark:text-zinc-400">Superseded</dt><dd class="mt-1 text-sm font-medium">{{ $statusHistory['superseded_at'] ?? 'Not recorded' }}</dd></div>
        </dl>
    </x-public.section>
</x-layouts.public>
