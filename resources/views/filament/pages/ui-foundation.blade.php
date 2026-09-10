<x-filament-panels::page>
    <div class="grid gap-6 md:grid-cols-2">
        <x-filament::section heading="State presentation">
            <div class="space-y-3 text-sm">
                <p class="text-gray-600 dark:text-gray-400">
                    Domain state is authoritative. This page demonstrates presentation only.
                </p>

                <div class="flex items-center gap-3">
                    <x-filament::badge color="success" icon="heroicon-o-check-circle">
                        Active
                    </x-filament::badge>
                    <span class="text-gray-600 dark:text-gray-400">Positive state</span>
                </div>

                <div class="flex items-center gap-3">
                    <x-filament::badge color="gray" icon="heroicon-o-archive-box">
                        Archived
                    </x-filament::badge>
                    <span class="text-gray-600 dark:text-gray-400">Historical state</span>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Action presentation">
            <div class="space-y-3 text-sm">
                <p class="text-gray-600 dark:text-gray-400">
                    Visibility and enabled state are presentation concerns. Server-side authorization remains authoritative.
                </p>

                <div class="flex flex-wrap gap-3">
                    <x-filament::button icon="heroicon-o-arrow-path">
                        Permitted action
                    </x-filament::button>

                    <x-filament::button disabled icon="heroicon-o-lock-closed">
                        Unavailable action
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
