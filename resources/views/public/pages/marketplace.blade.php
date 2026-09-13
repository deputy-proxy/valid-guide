<x-layouts.public
    title="Expert Marketplace"
    description="Discover services offered by eligible Valid.guide Experts."
>
    <x-public.section>
        <div class="mx-auto max-w-7xl">
            <div class="max-w-3xl">
                <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Valid.guide Marketplace</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white sm:text-4xl">Find Expert services</h1>
                <p class="mt-4 text-base leading-7 text-zinc-600 dark:text-zinc-400">Discover services from eligible Experts. Marketplace activity is kept separate from Validation decisions and public trust outcomes.</p>
            </div>

            <form method="GET" action="{{ route('public.marketplace') }}" class="mt-8 rounded-2xl border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-800 dark:bg-zinc-900/40">
                <div class="grid gap-5 md:grid-cols-3">
                    <div>
                        <label for="marketplace-search" class="block text-sm font-medium text-zinc-950 dark:text-white">Search</label>
                        <input id="marketplace-search" name="q" type="search" value="{{ $query }}" placeholder="Search services" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-950 shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                    </div>
                    <div>
                        <label for="marketplace-expertise" class="block text-sm font-medium text-zinc-950 dark:text-white">Expertise</label>
                        <select id="marketplace-expertise" name="expertise" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                            <option value="">Any expertise</option>
                            @foreach ($expertiseAreas as $option)
                                <option value="{{ $option->value }}" @selected($expertise === $option->value)>{{ str($option->value)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="marketplace-product-type" class="block text-sm font-medium text-zinc-950 dark:text-white">Product type</label>
                        <select id="marketplace-product-type" name="product_type" class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                            <option value="">Any product type</option>
                            @foreach ($productTypes as $option)
                                <option value="{{ $option->value }}" @selected($productType === $option->value)>{{ str($option->value)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-5 flex flex-wrap gap-3">
                    <button type="submit" class="rounded-lg bg-zinc-950 px-4 py-2.5 text-sm font-medium text-white dark:bg-white dark:text-zinc-950">Search services</button>
                    <a href="{{ route('public.marketplace') }}" class="rounded-lg border border-zinc-300 bg-white px-4 py-2.5 text-sm font-medium text-zinc-950 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">Clear filters</a>
                </div>
            </form>

            <div class="mt-10">
                @if ($services->isEmpty())
                    <x-public.state title="No marketplace services" message="No published services match these criteria. Try removing a filter or broadening your search." :action-url="route('public.marketplace')" action-label="Clear filters" />
                @else
                    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($services as $service)
                            <article class="flex h-full flex-col rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/40">
                                <div class="flex-1">
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="rounded-full border border-zinc-200 px-2.5 py-1 text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">Expert service</span>
                                        <span class="text-sm font-semibold text-zinc-950 dark:text-white">{{ number_format($service->price_minor / 100, 2) }} {{ $service->currency }}</span>
                                    </div>
                                    <h2 class="mt-4 text-lg font-semibold text-zinc-950 dark:text-white">{{ $service->title }}</h2>
                                    <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $service->description }}</p>
                                    @if ($service->auditorProfile?->expertPublicProfile)
                                        <p class="mt-5 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $service->auditorProfile->expertPublicProfile->display_name }}</p>
                                    @endif
                                </div>
                                <a href="{{ route('public.marketplace.show', $service->slug) }}" class="mt-6 inline-flex justify-center rounded-lg border border-zinc-300 px-4 py-2.5 text-sm font-medium text-zinc-950 dark:border-zinc-700 dark:text-white">View service</a>
                            </article>
                        @endforeach
                    </div>
                    <div class="mt-8">{{ $services->links() }}</div>
                @endif
            </div>
        </div>
    </x-public.section>
</x-layouts.public>
