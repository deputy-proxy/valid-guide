<x-layouts::app :title="__('Payment status')">
    <div class="mx-auto w-full max-w-2xl space-y-6">
        @if ($request->status?->value === 'paid')
            <div>
                <p class="text-sm font-medium text-green-700 dark:text-green-300">Payment confirmed</p>
                <h1 class="mt-1 text-3xl font-semibold text-zinc-900 dark:text-white">Evaluation payment received</h1>
                <p class="mt-2 text-zinc-600 dark:text-zinc-300">
                    Your payment has been confirmed by Valid.guide. Payment does not itself mean that the evaluation has started or that a validation result has been issued.
                </p>
            </div>
        @else
            <div>
                <p class="text-sm font-medium text-amber-700 dark:text-amber-300">Payment processing</p>
                <h1 class="mt-1 text-3xl font-semibold text-zinc-900 dark:text-white">We are waiting for payment confirmation</h1>
                <p class="mt-2 text-zinc-600 dark:text-zinc-300">
                    The browser return is not proof of payment. Valid.guide will update this request after Stripe sends a verified payment event.
                </p>
            </div>
        @endif

        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <p class="text-sm text-zinc-500">Evaluation request</p>
            <p class="mt-1 font-medium">#{{ $request->getKey() }} · {{ $request->product?->title }}</p>
            <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">Current status: {{ $request->status?->value }}</p>
        </div>
    </div>
</x-layouts::app>
