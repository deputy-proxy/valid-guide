@props([
    'label',
    'tone' => 'neutral',
])

@php
    $classes = match ($tone) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200',
        'danger' => 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200',
        default => 'border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full border px-3 py-1 text-sm font-medium {$classes}"]) }}>
    {{ $label }}
</span>
