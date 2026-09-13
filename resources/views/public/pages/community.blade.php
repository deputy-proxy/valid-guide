<x-layouts.public
    title="Expert community"
    description="Read knowledge-sharing contributions from current Valid.guide Experts."
>
    <x-public.section>
        <div class="mx-auto max-w-7xl">
            <div class="max-w-3xl">
                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Expert community</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white sm:text-4xl">Knowledge from Valid.guide Experts</h1>
                <p class="mt-4 text-base leading-7 text-zinc-600 dark:text-zinc-400">Practical knowledge shared by current Experts. Community contributions are separate from Validation results, evaluation history and recommendation ranking.</p>
            </div>

            <form method="GET" action="{{ route('public.community') }}" class="mt-8 flex gap-3">
                <label class="sr-only" for="community-search">Search community</label>
                <input id="community-search" name="q" value="{{ $query }}" type="search" placeholder="Search contributions" class="min-w-0 flex-1 rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                <button type="submit" class="rounded-lg bg-zinc-950 px-4 py-2.5 text-sm font-medium text-white dark:bg-white dark:text-zinc-950">Search</button>
            </form>

            @if ($contributions->isEmpty())
                <div class="mt-10">
                    <x-public.state title="No community contributions" message="There are no published contributions matching your search." :action-url="route('public.community')" action-label="Clear search" />
                </div>
            @else
                <div class="mt-10 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($contributions as $contribution)
                        <article class="flex h-full flex-col rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/40">
                            <div class="flex-1">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Expert contribution</p>
                                <h2 class="mt-3 text-lg font-semibold text-zinc-950 dark:text-white">{{ $contribution->title }}</h2>
                                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($contribution->body, 180) }}</p>
                                <p class="mt-4 text-sm text-zinc-500">By {{ $contribution->auditorProfile?->expertPublicProfile?->display_name ?? 'Valid.guide Expert' }}</p>
                            </div>
                            <a href="{{ route('public.community.show', ['slug' => $contribution->slug]) }}" class="mt-6 inline-flex items-center justify-center rounded-lg border border-zinc-300 px-4 py-2.5 text-sm font-medium text-zinc-950 hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:border-zinc-700 dark:text-white dark:hover:bg-zinc-900">Read contribution</a>
                        </article>
                    @endforeach
                </div>
                <div class="mt-8">{{ $contributions->links() }}</div>
            @endif
        </div>
    </x-public.section>
</x-layouts.public>
