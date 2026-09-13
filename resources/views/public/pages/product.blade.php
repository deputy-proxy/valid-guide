@php
    $title = is_string($product['title'] ?? null) ? $product['title'] : $entry->title;
    $description = is_string($product['description'] ?? null) ? $product['description'] : 'Public product information and its current Valid.guide validation record.';
    $status = is_string($entry->validation_status?->value ?? null) ? $entry->validation_status->value : 'unknown';
    $statusLabel = str($status)->replace('_', ' ')->title()->toString();
@endphp

<x-layouts.public
    :title="$title"
    :description="$description"
    :canonical-url="$canonicalUrl"
>
    <x-public.section class="pb-10 pt-12 sm:pb-12 sm:pt-20">
        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">Validated product</p>
                    <x-public.status label="Current validation" tone="success" />
                </div>
                <h1 class="mt-4 max-w-4xl text-4xl font-semibold tracking-tight text-zinc-950 sm:text-5xl dark:text-white">{{ $title }}</h1>
                <p class="mt-4 max-w-3xl text-lg leading-8 text-zinc-600 dark:text-zinc-300">{{ $description }}</p>
                <p class="mt-4 max-w-3xl text-sm leading-6 text-zinc-500 dark:text-zinc-400">This page is built from the public directory projection and persisted public verification record. It does not expose private creator, auditor, evaluation, or commercial information.</p>
            </div>

            <aside class="rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-zinc-500 dark:text-zinc-400">Authoritative verification</p>
                <p class="mt-2 break-all font-mono text-sm font-semibold text-zinc-950 dark:text-white">{{ $verificationIdentifier }}</p>
                <a href="{{ $verificationUrl }}" class="mt-5 inline-flex w-full items-center justify-center rounded-lg bg-zinc-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200 dark:focus:ring-white">View verification record</a>
            </aside>
        </div>
    </x-public.section>

    <x-public.section title="Product information" description="Public metadata from the validated product projection.">
        <dl class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Product type</dt><dd class="mt-1 font-medium">{{ $product['type'] ?? 'Not available' }}</dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Creator</dt><dd class="mt-1 font-medium">{{ $product['creator'] ?? 'Not disclosed' }}</dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Release</dt><dd class="mt-1 font-medium">{{ $product['release_identifier'] ?? 'Not available' }}</dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Version</dt><dd class="mt-1 font-medium">{{ $product['version'] ?? 'Not available' }}</dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Subject area</dt><dd class="mt-1 font-medium">{{ $suitability['subject_area'] ?? 'Not specified' }}</dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Language</dt><dd class="mt-1 font-medium">{{ $suitability['language'] ?? 'Not specified' }}</dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Validation status</dt><dd class="mt-1 font-medium">{{ $statusLabel }}</dd></div>
            <div><dt class="text-sm text-zinc-500 dark:text-zinc-400">Verification ID</dt><dd class="mt-1 break-all font-mono text-sm font-medium">{{ $verificationIdentifier }}</dd></div>
        </dl>
    </x-public.section>

    <x-public.section title="Validation result" description="The current public product page summarizes the published validation result. The verification record remains the authoritative trust destination.">
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Standard</p>
                <p class="mt-1 text-lg font-semibold">{{ $standard['name'] ?? 'Not available' }}</p>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Version {{ $standard['version'] ?? 'not available' }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Outcome</p>
                <p class="mt-1 text-lg font-semibold">{{ $result['decision'] ?? 'Not available' }}</p>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Overall score: {{ $result['overall_score'] ?? 'Not scored' }}</p>
            </div>
        </div>
        @if (is_string($result['decision_rationale'] ?? null) && $result['decision_rationale'] !== '')
            <div class="mt-6 rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800">
                <h3 class="font-semibold">Outcome interpretation</h3>
                <p class="mt-3 whitespace-pre-line text-sm leading-7 text-zinc-600 dark:text-zinc-400">{{ $result['decision_rationale'] }}</p>
            </div>
        @endif
    </x-public.section>

    @if (is_array($suitability['audiences'] ?? null) || is_array($suitability['goals'] ?? null))
        <x-public.section title="Suitability context">
            <div class="grid gap-6 sm:grid-cols-2">
                @if (is_array($suitability['audiences'] ?? null))
                    <div><h3 class="font-semibold">Audiences</h3><p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ collect($suitability['audiences'])->map(fn ($value) => str((string) $value)->replace('_', ' ')->title())->implode(', ') ?: 'Not specified' }}</p></div>
                @endif
                @if (is_array($suitability['goals'] ?? null))
                    <div><h3 class="font-semibold">Use cases</h3><p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ collect($suitability['goals'])->map(fn ($value) => str((string) $value)->replace('_', ' ')->title())->implode(', ') ?: 'Not specified' }}</p></div>
                @endif
            </div>
        </x-public.section>
    @endif

    <x-public.section>
        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-6 dark:border-zinc-800 dark:bg-zinc-900/40">
            <h2 class="text-lg font-semibold">Current product page vs. verification history</h2>
            <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">This page represents the currently discoverable public product projection. The linked verification page represents the immutable public verification record and preserves its historical trust state even when internal records later change.</p>
            <a href="{{ $verificationUrl }}" class="mt-5 inline-flex rounded-lg border border-zinc-300 px-4 py-2.5 text-sm font-medium text-zinc-950 hover:bg-zinc-100 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:border-zinc-700 dark:text-white dark:hover:bg-zinc-900 dark:focus:ring-white">Open authoritative record</a>
        </div>
    </x-public.section>
</x-layouts.public>
