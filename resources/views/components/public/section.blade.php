@props(['title' => null, 'description' => null])

<section {{ $attributes->merge(['class' => 'py-16 sm:py-20']) }}>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @if ($title || $description)
            <div class="mb-10 max-w-3xl">
                @if ($title)
                    <h2 class="text-2xl font-semibold tracking-tight text-zinc-950 sm:text-3xl dark:text-white">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-3 text-base leading-7 text-zinc-600 dark:text-zinc-400">{{ $description }}</p>
                @endif
            </div>
        @endif

        {{ $slot }}
    </div>
</section>
