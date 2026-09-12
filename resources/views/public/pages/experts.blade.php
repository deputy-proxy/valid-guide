<x-layouts.public
    title="Validated Experts"
    description="Discover current Valid.guide Experts and their approved areas of expertise."
>
    <x-public.section>
        <div class="mx-auto max-w-7xl">
            <div class="max-w-3xl">
                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Valid.guide Experts</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white sm:text-4xl">Find validated Experts</h1>
                <p class="mt-4 text-base leading-7 text-zinc-600 dark:text-zinc-400">
                    Browse current Experts with approved public profiles and verified areas of expertise. Expert availability reflects current Expert Board membership and public profile status.
                </p>
            </div>

            <form method="GET" action="{{ route('public.experts') }}" class="mt-8 rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-800 dark:bg-zinc-900/40">
                <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    <div class="lg:col-span-3">
                        <label for="expert-search" class="block text-sm font-medium text-zinc-950 dark:text-white">Search</label>
                        <input id="expert-search" name="q" type="search" value="{{ $query }}" placeholder="Search Experts, expertise, or credentials" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-950 shadow-sm outline-none focus:border-zinc-950 focus:ring-2 focus:ring-zinc-950 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:focus:border-white dark:focus:ring-white">
                    </div>

                    <div>
                        <label for="expert-expertise" class="block text-sm font-medium text-zinc-950 dark:text-white">Expertise</label>
                        <select id="expert-expertise" name="expertise" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-950 shadow-sm focus:border-zinc-950 focus:ring-2 focus:ring-zinc-950 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:focus:ring-white">
                            <option value="">Any expertise</option>
                            @foreach ($expertiseAreas as $option)
                                <option value="{{ $option->value }}" @selected($expertise === $option->value)>{{ str($option->value)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="expert-product-type" class="block text-sm font-medium text-zinc-950 dark:text-white">Product type</label>
                        <select id="expert-product-type" name="product_type" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-950 shadow-sm focus:border-zinc-950 focus:ring-2 focus:ring-zinc-950 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:focus:ring-white">
                            <option value="">Any product type</option>
                            @foreach ($productTypes as $option)
                                <option value="{{ $option->value }}" @selected($productType === $option->value)>{{ str($option->value)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap gap-3">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-zinc-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200 dark:focus:ring-white">Search Experts</button>
                    <a href="{{ route('public.experts') }}" class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-4 py-2.5 text-sm font-medium text-zinc-950 hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:hover:bg-zinc-900 dark:focus:ring-white">Clear filters</a>
                </div>
            </form>

            <div class="mt-10">
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <h2 class="text-xl font-semibold text-zinc-950 dark:text-white">Current Experts</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $experts->total() }} results</p>
                </div>

                @if ($experts->isEmpty())
                    <div class="mt-5">
                        <x-public.state title="No matching Experts" message="No current Experts match these discovery criteria. Try removing a filter or broadening your search." :action-url="route('public.experts')" action-label="Clear filters" />
                    </div>
                @else
                    <div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($experts as $expert)
                            <article class="flex h-full flex-col rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/40">
                                <div class="flex-1">
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="rounded-full border border-zinc-200 px-2.5 py-1 text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">Expert</span>
                                        <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Available</span>
                                    </div>
                                    <h3 class="mt-4 text-lg font-semibold text-zinc-950 dark:text-white">{{ $expert->display_name }}</h3>

                                    @if ($expert->bio)
                                        <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $expert->bio }}</p>
                                    @endif

                                    @if (is_array($expert->expertise_areas) && count($expert->expertise_areas) > 0)
                                        <div class="mt-5">
                                            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Expertise</p>
                                            <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ collect($expert->expertise_areas)->map(fn ($value) => str((string) $value)->replace('_', ' ')->title())->implode(', ') }}</p>
                                        </div>
                                    @endif

                                    @if (is_array($expert->product_types) && count($expert->product_types) > 0)
                                        <div class="mt-3">
                                            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Product types</p>
                                            <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ collect($expert->product_types)->map(fn ($value) => str((string) $value)->replace('_', ' ')->title())->implode(', ') }}</p>
                                        </div>
                                    @endif
                                </div>

                                <a href="{{ route('public.experts.show', ['slug' => $expert->slug]) }}" class="mt-6 inline-flex items-center justify-center rounded-lg border border-zinc-300 px-4 py-2.5 text-sm font-medium text-zinc-950 hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:border-zinc-700 dark:text-white dark:hover:bg-zinc-900 dark:focus:ring-white">View Expert profile</a>
                            </article>
                        @endforeach
                    </div>

                    <div class="mt-8">
                        {{ $experts->links() }}
                    </div>
                @endif
            </div>
        </div>
    </x-public.section>
</x-layouts.public>
