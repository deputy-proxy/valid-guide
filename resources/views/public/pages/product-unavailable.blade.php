<x-layouts.public
    title="Product unavailable"
    description="This public product record is not currently available."
    robots="noindex,follow"
>
    <x-public.section class="py-16 sm:py-24">
        <div class="mx-auto max-w-2xl text-center">
            <p class="text-sm font-semibold uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">Product unavailable</p>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white sm:text-4xl">This product is not currently available</h1>
            <p class="mt-4 text-base leading-7 text-zinc-600 dark:text-zinc-400">
                The public product page is only available while the product has an eligible current public validation record. Historical verification records remain authoritative when available.
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ route('public.directory') }}" class="inline-flex items-center justify-center rounded-lg bg-zinc-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200 dark:focus:ring-white">Browse directory</a>
                <a href="{{ route('public.verify') }}" class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-4 py-2.5 text-sm font-medium text-zinc-950 hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:hover:bg-zinc-900 dark:focus:ring-white">Verify a record</a>
            </div>
        </div>
    </x-public.section>
</x-layouts.public>
