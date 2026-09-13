<x-layouts.app :title="'Marketplace services'">
    <div class="mx-auto max-w-6xl space-y-8 p-6">
        <div>
            <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Expert marketplace</p>
            <h1 class="mt-1 text-2xl font-semibold text-zinc-950 dark:text-white">Your services</h1>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Create and govern services offered to buyers. Marketplace activity is separate from Validation decisions.</p>
        </div>

        @if (session('status'))
            <div role="status" class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('expert.marketplace.store') }}" class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            @csrf
            <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">Create a service</h2>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label for="title" class="block text-sm font-medium">Title</label>
                    <input id="title" name="title" required maxlength="255" class="mt-2 block w-full rounded-lg border border-zinc-300 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-950">
                </div>
                <div>
                    <label for="slug" class="block text-sm font-medium">Slug</label>
                    <input id="slug" name="slug" required maxlength="255" class="mt-2 block w-full rounded-lg border border-zinc-300 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-950">
                </div>
                <div>
                    <label for="price_minor" class="block text-sm font-medium">Price (minor units)</label>
                    <input id="price_minor" name="price_minor" type="number" min="1" required class="mt-2 block w-full rounded-lg border border-zinc-300 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-950">
                </div>
                <div>
                    <label for="currency" class="block text-sm font-medium">Currency</label>
                    <input id="currency" name="currency" value="EUR" maxlength="3" required class="mt-2 block w-full rounded-lg border border-zinc-300 px-3 py-2.5 uppercase dark:border-zinc-700 dark:bg-zinc-950">
                </div>
                <div class="md:col-span-2">
                    <label for="description" class="block text-sm font-medium">Description</label>
                    <textarea id="description" name="description" rows="4" required class="mt-2 block w-full rounded-lg border border-zinc-300 px-3 py-2.5 dark:border-zinc-700 dark:bg-zinc-950"></textarea>
                </div>
            </div>
            <input type="hidden" name="expertise_areas[]" value="general">
            <div class="mt-5">
                <button type="submit" class="rounded-lg bg-zinc-950 px-4 py-2.5 text-sm font-medium text-white dark:bg-white dark:text-zinc-950">Save draft</button>
            </div>
        </form>

        <section aria-labelledby="services-heading">
            <h2 id="services-heading" class="text-lg font-semibold text-zinc-950 dark:text-white">Published and draft services</h2>
            @if ($services->isEmpty())
                <div class="mt-4 rounded-2xl border border-zinc-200 p-6 text-sm text-zinc-600 dark:border-zinc-800 dark:text-zinc-400">You have no marketplace services yet.</div>
            @else
                <div class="mt-4 space-y-4">
                    @foreach ($services as $service)
                        <article class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <h3 class="font-semibold text-zinc-950 dark:text-white">{{ $service->title }}</h3>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ str($service->status->value)->replace('_', ' ')->title() }} · {{ number_format($service->price_minor / 100, 2) }} {{ $service->currency }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @if ($service->status->value === 'draft')
                                        <form method="POST" action="{{ route('expert.marketplace.publish', $service) }}">@csrf<button class="rounded-lg bg-zinc-950 px-3 py-2 text-sm text-white dark:bg-white dark:text-zinc-950">Publish</button></form>
                                    @elseif ($service->status->value === 'published')
                                        <form method="POST" action="{{ route('expert.marketplace.pause', $service) }}">@csrf<button class="rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700">Pause</button></form>
                                    @endif
                                    @if ($service->status->value !== 'archived')
                                        <form method="POST" action="{{ route('expert.marketplace.archive', $service) }}">@csrf<button class="rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700">Archive</button></form>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-layouts.app>
