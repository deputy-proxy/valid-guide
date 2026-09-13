<x-layouts.public
    :title="$contribution->title"
    description="Expert community contribution on Valid.guide."
>
    <x-public.section>
        <article class="mx-auto max-w-3xl">
            <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Expert community</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white">{{ $contribution->title }}</h1>
            <p class="mt-3 text-sm text-zinc-500">By {{ $contribution->auditorProfile?->expertPublicProfile?->display_name ?? 'Valid.guide Expert' }} · {{ $contribution->published_at?->format('M j, Y') }}</p>

            <div class="mt-8 whitespace-pre-wrap text-base leading-8 text-zinc-700 dark:text-zinc-300">{{ $contribution->body }}</div>

            <div class="mt-10 rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
                <h2 class="font-semibold text-zinc-950 dark:text-white">Community content is independent of Validation</h2>
                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">This contribution is knowledge-sharing content. It is not a Validation result, evaluation finding, recommendation score or substitute for independent assessment.</p>
            </div>

            @auth
                @if (auth()->id() !== $contribution->auditorProfile?->auditor_id)
                    @if (session('community_report_message'))
                        <div role="status" class="mt-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('community_report_message') }}</div>
                    @endif
                    <form method="POST" action="{{ route('community.reports.store', ['contribution' => $contribution]) }}" class="mt-8 space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
                        @csrf
                        <div>
                            <h2 class="font-semibold text-zinc-950 dark:text-white">Report this contribution</h2>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Use a report for inappropriate content, privacy concerns or misleading Validation claims.</p>
                        </div>
                        <div>
                            <label for="report-reason" class="block text-sm font-medium text-zinc-950 dark:text-white">Reason</label>
                            <select id="report-reason" name="reason" required class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-white">
                                @foreach (\App\Enums\CommunityReportReason::cases() as $reason)
                                    <option value="{{ $reason->value }}">{{ str($reason->value)->replace('_', ' ')->title() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="report-details" class="block text-sm font-medium text-zinc-950 dark:text-white">Details <span class="font-normal text-zinc-500">(optional)</span></label>
                            <textarea id="report-details" name="details" rows="4" maxlength="2000" class="mt-2 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-white"></textarea>
                        </div>
                        @if ($errors->any())
                            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $errors->first() }}</div>
                        @endif
                        <button type="submit" class="rounded-lg border border-zinc-300 px-4 py-2.5 text-sm font-medium text-zinc-950 dark:border-zinc-700 dark:text-white">Submit report</button>
                    </form>
                @endif
            @endauth
        </article>
    </x-public.section>
</x-layouts.public>
