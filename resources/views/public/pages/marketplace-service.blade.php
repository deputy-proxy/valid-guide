<x-layouts.public
    :title="$service->title"
    :description="$service->description"
>
    <x-public.section>
        <div class="mx-auto max-w-4xl">
            <a href="{{ route('public.marketplace') }}" class="text-sm font-medium text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white">← Back to marketplace</a>
            <div class="mt-6 rounded-2xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/40">
                <div class="flex flex-wrap items-start justify-between gap-6">
                    <div>
                        <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Expert service</p>
                        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ $service->title }}</h1>
                    </div>
                    <p class="text-xl font-semibold text-zinc-950 dark:text-white">{{ number_format($service->price_minor / 100, 2) }} {{ $service->currency }}</p>
                </div>
                <div class="mt-8 border-t border-zinc-200 pt-8 dark:border-zinc-800">
                    <p class="whitespace-pre-line text-base leading-7 text-zinc-700 dark:text-zinc-300">{{ $service->description }}</p>
                </div>
                @if ($service->auditorProfile?->expertPublicProfile)
                    <div class="mt-8 rounded-xl bg-zinc-50 p-5 dark:bg-zinc-950">
                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Provider</p>
                        <p class="mt-1 text-base font-semibold text-zinc-950 dark:text-white">{{ $service->auditorProfile->expertPublicProfile->display_name }}</p>
                    </div>
                @endif
                <div class="mt-8 rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
                    <p class="text-sm font-medium text-zinc-950 dark:text-white">Independent marketplace</p>
                    <p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Purchasing or completing a marketplace service does not influence Validation status, scoring, Auditor assignment, recommendations, or public trust records.</p>
                </div>
            </div>
        </div>
    </x-public.section>
</x-layouts.public>
