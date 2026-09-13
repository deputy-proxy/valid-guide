<div class="space-y-6" wire:key="workflow-inbox">
    <section class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900">Action queue</h2>
                <p class="mt-1 text-sm text-zinc-600">Pending work derived from the current workflow state.</p>
            </div>
            <span class="rounded-full bg-zinc-100 px-3 py-1 text-sm font-medium text-zinc-700">
                {{ count($actions) }} pending
            </span>
        </div>

        <div class="mt-5 divide-y divide-zinc-100">
            @forelse ($actions as $action)
                <div class="flex items-start justify-between gap-4 py-4">
                    <div>
                        <p class="font-medium text-zinc-900">{{ $action['title'] }}</p>
                        <p class="mt-1 text-sm text-zinc-600">{{ $action['description'] }}</p>
                    </div>
                    <span class="shrink-0 rounded-md bg-zinc-100 px-2 py-1 text-xs font-medium uppercase tracking-wide text-zinc-600">
                        {{ $action['category'] }}
                    </span>
                </div>
            @empty
                <p class="py-6 text-sm text-zinc-500">No pending actions.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900">Notifications</h2>
                <p class="mt-1 text-sm text-zinc-600">{{ $unreadCount }} unread</p>
            </div>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllRead" class="text-sm font-medium text-zinc-700 hover:text-zinc-950">
                    Mark all as read
                </button>
            @endif
        </div>

        <div class="mt-5 divide-y divide-zinc-100">
            @forelse ($notifications as $notification)
                <div class="py-4 {{ $notification['read'] ? 'opacity-70' : '' }}">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-medium text-zinc-900">{{ $notification['title'] }}</p>
                            <p class="mt-1 text-sm text-zinc-600">{{ $notification['body'] }}</p>
                            @if ($notification['action_url'])
                                <a href="{{ $notification['action_url'] }}" wire:navigate class="mt-2 inline-block text-sm font-medium text-zinc-900 underline">
                                    View details
                                </a>
                            @endif
                            @if ($notification['stale'])
                                <p class="mt-2 text-xs font-medium text-amber-700">This notification is stale. The underlying workflow state has changed.</p>
                            @endif
                        </div>
                        @if (! $notification['read'])
                            <button type="button" wire:click="markRead('{{ $notification['id'] }}')" class="shrink-0 text-sm font-medium text-zinc-700 hover:text-zinc-950">
                                Mark read
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <p class="py-6 text-sm text-zinc-500">No notifications.</p>
            @endforelse
        </div>
    </section>
</div>
