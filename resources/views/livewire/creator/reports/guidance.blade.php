<div>
    <x-layouts::app :title="__('Improvement guidance')">
        <div class="mx-auto w-full max-w-6xl space-y-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Creator report</p>
                    <h1 class="mt-1 text-3xl font-semibold text-zinc-900 dark:text-white">Improvement guidance</h1>
                    @if (($guidanceData['product']['name'] ?? null) !== null)
                        <p class="mt-2 text-zinc-600 dark:text-zinc-300">{{ $guidanceData['product']['name'] }}</p>
                    @endif
                </div>
                <a href="{{ route('creator.reports.show', ['evaluationId' => $evaluationId]) }}" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium dark:border-zinc-600">Back to report</a>
            </div>

            @error('guidance')
                <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">{{ $message }}</div>
            @enderror

            <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700" wire:loading.delay>
                <p role="status" class="text-sm text-zinc-600 dark:text-zinc-300">Loading improvement guidance…</p>
            </section>

            <section class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-zinc-900 dark:text-white">Structured guidance</h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Actionable recommendations derived from creator-visible evaluation findings.</p>
                    </div>
                    <button type="button" wire:click="refreshGuidance" wire:loading.attr="disabled" class="rounded-lg border border-zinc-300 px-3 py-2 text-sm font-medium dark:border-zinc-600">
                        Refresh
                    </button>
                </div>

                <div class="mt-6 space-y-4">
                    @forelse ($guidanceData['items'] ?? [] as $item)
                        <article class="rounded-lg border border-zinc-200 p-5 dark:border-zinc-700">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                <span class="font-medium uppercase tracking-wide">{{ str((string) $item['category'])->replace('_', ' ')->headline() }}</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ str((string) $item['priority'])->headline() }} priority</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ str((string) $item['status'])->replace('_', ' ')->headline() }}</span>
                            </div>
                            <h3 class="mt-2 text-lg font-medium text-zinc-900 dark:text-white">{{ $item['title'] }}</h3>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $item['guidance'] }}</p>
                            <div class="mt-4 rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Why this matters</p>
                                <p class="mt-1 whitespace-pre-line text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $item['rationale'] }}</p>
                            </div>
                            @if (($item['finding_title'] ?? null) !== null)
                                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">Based on: {{ $item['finding_title'] }}</p>
                            @elseif (($item['criterion_code'] ?? null) !== null)
                                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">Based on criterion: {{ $item['criterion_code'] }}</p>
                            @endif
                            @if (($item['action_id'] ?? null) !== null)
                                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Action queue status: {{ str((string) $item['action_status'])->replace('_', ' ')->headline() }}</p>
                            @endif
                        </article>
                    @empty
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">No structured improvement guidance is available for this evaluation.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </x-layouts::app>
</div>
