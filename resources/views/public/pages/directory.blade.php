<x-layouts.public
    title="Validated product directory"
    description="Discover online learning products with current Valid.guide validation."
>
    <x-public.section>
        <div class="mx-auto max-w-7xl">
            <div class="max-w-3xl">
                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Valid.guide directory</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white sm:text-4xl">Find validated learning products</h1>
                <p class="mt-4 text-base leading-7 text-zinc-600 dark:text-zinc-400">
                    Browse products with a current public validation record. Validation is evidence about the product and release reviewed, not a guarantee of outcomes or universal suitability.
                </p>
            </div>

            <form method="GET" action="{{ route('public.directory') }}" class="mt-8 rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-800 dark:bg-zinc-900/40">
                <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    <div class="lg:col-span-3">
                        <label for="directory-search" class="block text-sm font-medium text-zinc-950 dark:text-white">Search</label>
                        <input id="directory-search" name="q" type="search" value="{{ $query }}" placeholder="Search products, creators, or subject areas" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-950 shadow-sm outline-none focus:border-zinc-950 focus:ring-2 focus:ring-zinc-950 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:focus:border-white dark:focus:ring-white">
                    </div>

                    <div>
                        <label for="directory-audience" class="block text-sm font-medium text-zinc-950 dark:text-white">Audience</label>
                        <select id="directory-audience" name="audience" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-950 shadow-sm focus:border-zinc-950 focus:ring-2 focus:ring-zinc-950 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:focus:ring-white">
                            <option value="">Any audience</option>
                            @foreach ($audiences as $option)
                                <option value="{{ $option->value }}" @selected($audience === $option->value)>{{ str($option->value)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="directory-goal" class="block text-sm font-medium text-zinc-950 dark:text-white">Use case</label>
                        <select id="directory-goal" name="goal" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-950 shadow-sm focus:border-zinc-950 focus:ring-2 focus:ring-zinc-950 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:focus:ring-white">
                            <option value="">Any use case</option>
                            @foreach ($goals as $option)
                                <option value="{{ $option->value }}" @selected($goal === $option->value)>{{ str($option->value)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="directory-type" class="block text-sm font-medium text-zinc-950 dark:text-white">Product type</label>
                        <select id="directory-type" name="product_type" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-950 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:focus:ring-white">
                            <option value="">Any type</option>
                            @foreach ($productTypes as $option)
                                <option value="{{ $option->value }}" @selected($productType === $option->value)>{{ str($option->value)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="directory-subject" class="block text-sm font-medium text-zinc-950 dark:text-white">Subject area</label>
                        <input id="directory-subject" name="subject_area" type="text" value="{{ $subjectArea }}" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-950 shadow-sm focus:border-zinc-950 focus:ring-2 focus:ring-zinc-950 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:focus:ring-white">
                    </div>

                    <div>
                        <label for="directory-language" class="block text-sm font-medium text-zinc-950 dark:text-white">Language</label>
                        <input id="directory-language" name="language" type="text" value="{{ $language }}" placeholder="e.g. en" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-950 shadow-sm focus:border-zinc-950 focus:ring-2 focus:ring-zinc-950 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:focus:ring-white">
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap gap-3">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-zinc-950 px-4 py-2.5 text-sm font-medium text-white hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200 dark:focus:ring-white">Search directory</button>
                    <a href="{{ route('public.directory') }}" class="inline-flex items-center justify-center rounded-lg border border-zinc-300 bg-white px-4 py-2.5 text-sm font-medium text-zinc-950 hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:hover:bg-zinc-900 dark:focus:ring-white">Clear filters</a>
                </div>
            </form>

            @if ($invalidFilter)
                <div class="mt-10">
                    <x-public.state
                        title="Invalid directory filter"
                        message="One or more selected filters are not recognized. Choose a supported filter value or clear the filters and try again."
                        :action-url="route('public.directory')"
                        action-label="Clear filters"
                    />
                </div>
            @elseif ($recommendations->isNotEmpty())
                <section class="mt-10" aria-labelledby="recommendations-heading">
                    <div class="max-w-3xl">
                        <h2 id="recommendations-heading" class="text-xl font-semibold text-zinc-950 dark:text-white">Recommended based on these criteria</h2>
                        <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                            These recommendations use only public product metadata and current validation status. They indicate relevance to the selected criteria, not product quality, guaranteed outcomes, or universal suitability.
                        </p>
                    </div>

                    <div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($recommendations as $recommendation)
                            <article class="flex h-full flex-col rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/40">
                                <div class="flex-1">
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="rounded-full border border-zinc-200 px-2.5 py-1 text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">{{ str($recommendation->productType->value)->replace('_', ' ')->title() }}</span>
                                        <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Validated</span>
                                    </div>
                                    <h3 class="mt-4 text-lg font-semibold text-zinc-950 dark:text-white">{{ $recommendation->title }}</h3>

                                    <div class="mt-5">
                                        <h4 class="text-xs font-medium uppercase tracking-wide text-zinc-500">Why this is recommended</h4>
                                        <ul class="mt-2 space-y-1.5 text-sm text-zinc-700 dark:text-zinc-300">
                                            @foreach ($recommendation->reasons as $reason)
                                                <li>{{ $reason }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>

                                <a href="{{ route('public.verify.show', ['verificationIdentifier' => $recommendation->verificationIdentifier]) }}" class="mt-6 inline-flex items-center justify-center rounded-lg border border-zinc-300 px-4 py-2.5 text-sm font-medium text-zinc-950 hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:border-zinc-700 dark:text-white dark:hover:bg-zinc-900 dark:focus:ring-white">View verification</a>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            <div class="mt-10">
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <h2 class="text-xl font-semibold text-zinc-950 dark:text-white">Validated products</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $entries->total() }} results</p>
                </div>

                @if ($entries->isEmpty() && ! $invalidFilter)
                    <div class="mt-5">
                        <x-public.state title="No matching products" message="No currently validated products match these discovery criteria. Try removing a filter or broadening your search." :action-url="route('public.directory')" action-label="Clear filters" />
                    </div>
                @elseif (! $invalidFilter)
                    <div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($entries as $entry)
                            <article class="flex h-full flex-col rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/40">
                                <div class="flex-1">
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="rounded-full border border-zinc-200 px-2.5 py-1 text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">{{ str($entry->product_type->value)->replace('_', ' ')->title() }}</span>
                                        <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">Validated</span>
                                    </div>
                                    <h3 class="mt-4 text-lg font-semibold text-zinc-950 dark:text-white">{{ $entry->title }}</h3>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $entry->creator_name }}</p>

                                    @if ($entry->subject_area || $entry->language)
                                        <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
                                            @if ($entry->subject_area)
                                                <div><dt class="text-zinc-500">Subject</dt><dd class="mt-1 font-medium text-zinc-800 dark:text-zinc-200">{{ $entry->subject_area }}</dd></div>
                                            @endif
                                            @if ($entry->language)
                                                <div><dt class="text-zinc-500">Language</dt><dd class="mt-1 font-medium text-zinc-800 dark:text-zinc-200">{{ $entry->language }}</dd></div>
                                            @endif
                                        </dl>
                                    @endif

                                    @if (is_array($entry->matching_audiences) && count($entry->matching_audiences) > 0)
                                        <div class="mt-5">
                                            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Audience</p>
                                            <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ collect($entry->matching_audiences)->map(fn ($value) => str((string) $value)->replace('_', ' ')->title())->implode(', ') }}</p>
                                        </div>
                                    @endif

                                    @if (is_array($entry->matching_goals) && count($entry->matching_goals) > 0)
                                        <div class="mt-3">
                                            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Use cases</p>
                                            <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ collect($entry->matching_goals)->map(fn ($value) => str((string) $value)->replace('_', ' ')->title())->implode(', ') }}</p>
                                        </div>
                                    @endif
                                </div>

                                <a href="{{ route('public.verify.show', ['verificationIdentifier' => $entry->verification_identifier]) }}" class="mt-6 inline-flex items-center justify-center rounded-lg border border-zinc-300 px-4 py-2.5 text-sm font-medium text-zinc-950 hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:border-zinc-700 dark:text-white dark:hover:bg-zinc-900 dark:focus:ring-white">View verification</a>
                            </article>
                        @endforeach
                    </div>

                    <div class="mt-8">
                        {{ $entries->links() }}
                    </div>
                @endif
            </div>
        </div>
    </x-public.section>
</x-layouts.public>
