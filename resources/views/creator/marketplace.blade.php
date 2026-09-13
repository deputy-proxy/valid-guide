<x-layouts.app :title="'Marketplace'">
    <div class="mx-auto max-w-6xl space-y-8 p-6">
        <div>
            <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Expert marketplace</p>
            <h1 class="mt-1 text-2xl font-semibold text-zinc-950 dark:text-white">Your marketplace engagements</h1>
            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Request Expert services and keep the resulting commercial history separate from Validation.</p>
        </div>

        @if (session('status'))
            <div role="status" class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300">{{ session('status') }}</div>
        @endif

        @if ($transactions->isEmpty())
            <div class="rounded-2xl border border-zinc-200 p-6 text-sm text-zinc-600 dark:border-zinc-800 dark:text-zinc-400">You have no marketplace engagements yet. Browse the public marketplace to find an Expert service.</div>
        @else
            <div class="space-y-4">
                @foreach ($transactions as $transaction)
                    <article class="rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="font-semibold text-zinc-950 dark:text-white">{{ $transaction->marketplaceService->title }}</h2>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ str($transaction->status->value)->replace('_', ' ')->title() }} · {{ number_format($transaction->amount_minor / 100, 2) }} {{ $transaction->currency }}</p>
                                @if ($transaction->auditorProfile?->expertPublicProfile)
                                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Expert: {{ $transaction->auditorProfile->expertPublicProfile->display_name }}</p>
                                @endif
                            </div>
                            @if (in_array($transaction->status->value, ['pending', 'paid', 'in_progress'], true))
                                <form method="POST" action="{{ route('creator.marketplace.cancel', $transaction) }}">
                                    @csrf
                                    <input type="hidden" name="reason" value="Cancelled by buyer">
                                    <button class="rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-zinc-700">Cancel</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
