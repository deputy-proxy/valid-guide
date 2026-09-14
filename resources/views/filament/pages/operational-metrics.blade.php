<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Operational health</x-slot>
            <x-slot name="description">
                Aggregate signals derived from persisted audit events for the previous 30 days. Raw evidence, private deliberation, secrets and actor-level activity are excluded.
            </x-slot>

            <div class="grid gap-4 md:grid-cols-4">
                <x-filament::section>
                    <div class="text-sm text-gray-500">Total events</div>
                    <div class="text-2xl font-semibold">{{ number_format($metrics['total_events'] ?? 0) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-sm text-gray-500">Failures</div>
                    <div class="text-2xl font-semibold">{{ number_format($metrics['failure_events'] ?? 0) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-sm text-gray-500">Retries</div>
                    <div class="text-2xl font-semibold">{{ number_format($metrics['retry_events'] ?? 0) }}</div>
                </x-filament::section>
                <x-filament::section>
                    <div class="text-sm text-gray-500">Data-quality flags</div>
                    <div class="text-2xl font-semibold">
                        {{ number_format(($metrics['duplicate_events'] ?? 0) + ($metrics['out_of_order_events'] ?? 0)) }}
                    </div>
                </x-filament::section>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Workflow coverage</x-slot>

            <div class="grid gap-4 md:grid-cols-3 lg:grid-cols-5">
                @foreach ([
                    'evaluation_events' => 'Evaluation',
                    'auditor_events' => 'Auditor',
                    'validation_events' => 'Validation',
                    'subscription_events' => 'Subscriptions',
                    'public_discovery_events' => 'Public discovery',
                ] as $key => $label)
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="text-sm text-gray-500">{{ $label }}</div>
                        <div class="mt-1 text-xl font-semibold">{{ number_format($metrics[$key] ?? 0) }}</div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Operational infrastructure</x-slot>

            <div class="grid gap-4 md:grid-cols-3">
                @foreach ([
                    'notification_events' => 'Notifications',
                    'action_queue_events' => 'Action queue',
                    'monitoring_events' => 'Monitoring',
                ] as $key => $label)
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                        <div class="text-sm text-gray-500">{{ $label }}</div>
                        <div class="mt-1 text-xl font-semibold">{{ number_format($metrics[$key] ?? 0) }}</div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Lifecycle durations</x-slot>
            <x-slot name="description">Only explicitly paired <code>started</code>, <code>completed</code> and <code>failed</code> audit events are measured. Missing or out-of-order pairs are flagged instead of being assigned an invented duration.</x-slot>

            @if (($metrics['lifecycle_durations'] ?? []) === [])
                <p class="text-sm text-gray-500">No deterministic lifecycle duration data is available for this period.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-3 py-2 font-medium">Workflow</th>
                                <th class="px-3 py-2 font-medium">Count</th>
                                <th class="px-3 py-2 font-medium">Average</th>
                                <th class="px-3 py-2 font-medium">Minimum</th>
                                <th class="px-3 py-2 font-medium">Maximum</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($metrics['lifecycle_durations'] as $event => $duration)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="px-3 py-2 font-medium">{{ $event }}</td>
                                    <td class="px-3 py-2">{{ number_format($duration['count']) }}</td>
                                    <td class="px-3 py-2">{{ number_format($duration['average_seconds'], 2) }}s</td>
                                    <td class="px-3 py-2">{{ number_format($duration['minimum_seconds'], 2) }}s</td>
                                    <td class="px-3 py-2">{{ number_format($duration['maximum_seconds'], 2) }}s</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Event distribution</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-3 py-2 font-medium">Event</th>
                            <th class="px-3 py-2 font-medium">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($metrics['events_by_type'] ?? [] as $event => $count)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-2 font-medium">{{ $event }}</td>
                                <td class="px-3 py-2">{{ number_format($count) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-3 py-2 text-gray-500" colspan="2">No events were recorded in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Data quality</x-slot>
            <x-slot name="description">Duplicate events are reported rather than silently discarded. Lifecycle terminal events without a preceding start are reported as out-of-order observations.</x-slot>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <div class="text-sm text-gray-500">Duplicate observations</div>
                    <div class="mt-1 text-xl font-semibold">{{ number_format($metrics['duplicate_events'] ?? 0) }}</div>
                </div>
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                    <div class="text-sm text-gray-500">Out-of-order observations</div>
                    <div class="mt-1 text-xl font-semibold">{{ number_format($metrics['out_of_order_events'] ?? 0) }}</div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
