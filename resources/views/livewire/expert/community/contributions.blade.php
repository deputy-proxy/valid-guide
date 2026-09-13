<div class="mx-auto w-full max-w-4xl space-y-8">
    <div>
        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Expert community</p>
        <h1 class="mt-1 text-3xl font-semibold text-zinc-900 dark:text-white">Share knowledge</h1>
        <p class="mt-2 text-zinc-600 dark:text-zinc-300">Community contributions are reviewed before publication and are independent from Validation and evaluation decisions.</p>
    </div>

    @if ($error)
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">{{ $error }}</div>
    @endif
    @if ($message)
        <div role="status" class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">{{ $message }}</div>
    @endif

    <section class="space-y-5 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
        <div>
            <h2 class="text-xl font-semibold">{{ $editingId ? 'Edit contribution' : 'New contribution' }}</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Save a draft, then submit it for moderation.</p>
        </div>
        <div>
            <label for="contribution-title" class="mb-2 block text-sm font-medium">Title</label>
            <input id="contribution-title" wire:model="title" maxlength="255" class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900" required>
        </div>
        <div>
            <label for="contribution-body" class="mb-2 block text-sm font-medium">Contribution</label>
            <textarea id="contribution-body" wire:model="body" rows="10" class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900" required></textarea>
        </div>
        <button type="button" wire:click="save" class="rounded-lg bg-zinc-950 px-4 py-2.5 text-sm font-medium text-white dark:bg-white dark:text-zinc-950">Save draft</button>
    </section>

    <section aria-labelledby="my-contributions-heading" class="space-y-4">
        <div>
            <h2 id="my-contributions-heading" class="text-xl font-semibold">My contributions</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Moderation status and available actions are shown below.</p>
        </div>
        @forelse ($contributions as $contribution)
            <article class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="font-semibold">{{ $contribution->title }}</h3>
                        <p class="mt-1 text-sm text-zinc-500">Status: {{ str($contribution->status->value)->replace('_', ' ')->title() }}</p>
                    </div>
                    @if (in_array($contribution->status->value, ['draft', 'rejected'], true))
                        <button type="button" wire:click="edit({{ $contribution->id }})" class="rounded-lg border px-3 py-2 text-sm">Edit</button>
                    @endif
                </div>
                @if ($contribution->moderation_reason)
                    <p class="mt-3 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800">Moderation note: {{ $contribution->moderation_reason }}</p>
                @endif
                @if (in_array($contribution->status->value, ['draft', 'rejected'], true))
                    <button type="button" wire:click="submit({{ $contribution->id }})" class="mt-4 rounded-lg bg-zinc-950 px-3 py-2 text-sm text-white dark:bg-white dark:text-zinc-950">Submit for review</button>
                @endif
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-zinc-300 p-6 text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">You have not created any community contributions yet.</div>
        @endforelse
    </section>
</div>
