@props([
    'title',
    'message',
    'actionUrl' => null,
    'actionLabel' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-zinc-200 bg-white p-8 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900/40']) }} role="status">
    <h2 class="text-xl font-semibold text-zinc-950 dark:text-white">{{ $title }}</h2>
    <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $message }}</p>

    @if ($actionUrl && $actionLabel)
        <a
            href="{{ $actionUrl }}"
            class="mt-6 inline-flex items-center justify-center rounded-lg bg-zinc-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200 dark:focus:ring-white"
        >
            {{ $actionLabel }}
        </a>
    @endif
</div>
