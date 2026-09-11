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
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Evaluation Scope</dt>
                    <dd class="mt-1 text-sm text-gray-950 dark:text-white">{{ $this->evaluationScope() }}</dd>
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

        <x-filament::section heading="Conflict of interest">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::badge>{{ $this->conflictStatusLabel() }}</x-filament::badge>
                </div>

                @if ($this->canEditConflictDeclaration())
                    <form wire:submit="submitConflictDeclaration" class="space-y-4">
                        <div>
                            <label for="conflictDisclosure" class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 text-sm font-medium leading-6 text-gray-950 dark:text-white">
                                Conflict declaration
                            </label>
                            <textarea
                                id="conflictDisclosure"
                                wire:model="conflictDisclosure"
                                rows="6"
                                required
                                class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-base text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                                placeholder="Describe any current or potential conflict for this assignment."
                            ></textarea>
                        </div>
                        <div class="flex justify-end">
                            <x-filament::button type="submit">
                                Submit conflict declaration
                            </x-filament::button>
                        </div>
                    </form>
                @else
                    <p class="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">{{ $this->conflictDeclaration()?->disclosure ?: 'No disclosure recorded.' }}</p>
                    @if ($this->conflictDeclaration()?->determined_at !== null)
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            This declaration was determined on {{ $this->conflictDeclaration()->determined_at->format('d M Y H:i') }} and cannot be changed.
                        </p>
                    @endif
                @endif
            </div>
        </x-filament::section>

        <x-filament::section heading="Readiness">
            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                <p>Your Auditor eligibility and current annual conflict clearance have been verified for this workspace.</p>
                <p>Assignment-level conflict clearance is required before substantive evaluation work can begin.</p>
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
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Auditor\Pages\Evaluation::getUrl(['assignment' => $assignment->getKey()])"
                    icon="heroicon-m-arrow-right"
                >
                    Evaluation workspace
                </x-filament::button>
            @endif
        </div>
    </div>
</x-filament-panels::page>
