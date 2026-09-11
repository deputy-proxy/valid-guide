<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section heading="Assignment status">
            <div class="flex flex-wrap items-center gap-2">
                <x-filament::badge>{{ $this->statusLabel() }}</x-filament::badge>
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $this->readinessLabel() }}</span>
            </div>
        </x-filament::section>

        <x-filament::section heading="Evaluation context">
            <dl class="grid gap-5 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Product</dt>
                    <dd class="mt-1 text-sm text-gray-950 dark:text-white">{{ $assignment->evaluation->product->title }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Product Release</dt>
                    <dd class="mt-1 text-sm text-gray-950 dark:text-white">{{ $assignment->evaluation->productRelease->release_identifier }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Standard Version</dt>
                    <dd class="mt-1 text-sm text-gray-950 dark:text-white">{{ $assignment->evaluation->standardVersion->version }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Due</dt>
                    <dd class="mt-1 text-sm text-gray-950 dark:text-white">{{ $assignment->due_at?->format('d M Y H:i') ?? 'No deadline recorded' }}</dd>
                </div>
            </dl>
        </x-filament::section>

        <x-filament::section heading="Readiness">
            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                <p>Your Auditor eligibility and current annual conflict clearance have been verified for this workspace.</p>
                <p>Assignment-level conflict clearance is required before the assignment appears here.</p>
                @if ($this->canContinue())
                    <p class="font-medium text-gray-950 dark:text-white">This assignment is ready for the Auditor evaluation workspace.</p>
                @else
                    <p>This assignment is not currently available for substantive Auditor work.</p>
                @endif
            </div>
        </x-filament::section>

        <div class="flex justify-between gap-3">
            <x-filament::button
                tag="a"
                :href="\App\Filament\Auditor\Pages\Assignments::getUrl()"
                color="gray"
            >
                Back to assignments
            </x-filament::button>

            @if ($this->canContinue())
                <x-filament::button disabled icon="heroicon-m-arrow-right">
                    Evaluation workspace
                </x-filament::button>
            @endif
        </div>
    </div>
</x-filament-panels::page>
