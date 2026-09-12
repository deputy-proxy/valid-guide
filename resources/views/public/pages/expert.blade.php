<x-layouts.public
    :title="$expert->display_name.' | Validated Expert'"
    description="Public profile for a current Valid.guide Expert."
>
    <x-public.section>
        <div class="mx-auto max-w-4xl">
            <a href="{{ route('public.experts') }}" class="text-sm font-medium text-zinc-600 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-white">← Back to Experts</a>

            <div class="mt-8 rounded-2xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/40 sm:p-10">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Valid.guide Expert</p>
                        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white sm:text-4xl">{{ $expert->display_name }}</h1>
                    </div>
                    <span class="rounded-full border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">Currently available</span>
                </div>

                @if ($expert->bio)
                    <div class="mt-8">
                        <h2 class="text-sm font-semibold text-zinc-950 dark:text-white">About</h2>
                        <p class="mt-2 text-base leading-7 text-zinc-700 dark:text-zinc-300">{{ $expert->bio }}</p>
                    </div>
                @endif

                @if (is_array($expert->expertise_areas) && count($expert->expertise_areas) > 0)
                    <div class="mt-8">
                        <h2 class="text-sm font-semibold text-zinc-950 dark:text-white">Approved expertise</h2>
                        <ul class="mt-3 flex flex-wrap gap-2" aria-label="Approved expertise">
                            @foreach ($expert->expertise_areas as $area)
                                <li class="rounded-full border border-zinc-200 px-3 py-1.5 text-sm text-zinc-700 dark:border-zinc-700 dark:text-zinc-300">{{ str((string) $area)->replace('_', ' ')->title() }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if (is_array($expert->product_types) && count($expert->product_types) > 0)
                    <div class="mt-8">
                        <h2 class="text-sm font-semibold text-zinc-950 dark:text-white">Product-type experience</h2>
                        <p class="mt-2 text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ collect($expert->product_types)->map(fn ($value) => str((string) $value)->replace('_', ' ')->title())->implode(', ') }}</p>
                    </div>
                @endif

                @if ($expert->credentials)
                    <div class="mt-8">
                        <h2 class="text-sm font-semibold text-zinc-950 dark:text-white">Credentials</h2>
                        <p class="mt-2 text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $expert->credentials }}</p>
                    </div>
                @endif

                <div class="mt-10 border-t border-zinc-200 pt-6 text-sm leading-6 text-zinc-600 dark:border-zinc-800 dark:text-zinc-400">
                    This public profile contains only approved profile information and verified expertise. Private contact details, conflicts, assignments, evaluations, compensation and internal eligibility decisions are not part of the public profile.
                </div>
            </div>
        </div>
    </x-public.section>
</x-layouts.public>
