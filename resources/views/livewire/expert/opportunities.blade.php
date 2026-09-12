<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold">Expert opportunities</h1>
        <p class="text-sm text-zinc-600">Published opportunities available to qualified Experts.</p>
    </div>

    @if ($error)
        <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $error }}</div>
    @endif

    @forelse ($opportunities as $opportunity)
        @php($participation = $opportunity->participations->first())
        <article class="rounded-xl border p-6 space-y-4">
            <div>
                <h2 class="text-lg font-semibold">{{ $opportunity->title }}</h2>
                <p class="mt-2 text-sm text-zinc-700">{{ $opportunity->description }}</p>
            </div>
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div><dt class="font-medium">Type</dt><dd>{{ str($opportunity->type->value)->title() }}</dd></div>
                <div><dt class="font-medium">Workload</dt><dd>{{ $opportunity->workload ?: 'Not specified' }}</dd></div>
                <div><dt class="font-medium">Starts</dt><dd>{{ $opportunity->starts_at?->format('M j, Y H:i') ?: 'Flexible' }}</dd></div>
                <div><dt class="font-medium">Deadline</dt><dd>{{ $opportunity->application_deadline?->format('M j, Y H:i') ?: 'No deadline' }}</dd></div>
            </dl>

            @if ($participation)
                <div class="rounded-lg bg-zinc-50 p-4 text-sm">Application status: <strong>{{ str($participation->status->value)->title() }}</strong></div>
                @if ($participation->status->value === 'applied')
                    <button type="button" wire:click="withdraw({{ $participation->id }})" class="rounded-lg border px-4 py-2 text-sm">Withdraw application</button>
                @elseif ($participation->status->value === 'selected')
                    <button type="button" wire:click="accept({{ $participation->id }})" class="rounded-lg bg-black px-4 py-2 text-sm text-white">Accept engagement</button>
                @endif
            @else
                <div class="space-y-3">
                    <label class="block text-sm font-medium" for="disclosure-{{ $opportunity->id }}">Conflict-of-interest disclosure</label>
                    <textarea id="disclosure-{{ $opportunity->id }}" wire:model="disclosure" rows="3" required class="w-full rounded-lg border"></textarea>
                    <button type="button" wire:click="apply({{ $opportunity->id }})" class="rounded-lg bg-black px-4 py-2 text-sm text-white">Apply</button>
                </div>
            @endif
        </article>
    @empty
        <p class="rounded-xl border p-6 text-sm text-zinc-600">There are currently no published opportunities.</p>
    @endforelse
</div>
