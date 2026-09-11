<x-filament-panels::page>
    <div class="space-y-6">
        <div>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Assignments cleared for your Auditor account are shown here. Private evidence and other Auditors' work are never exposed.
            </p>
        </div>

        @php($assignments = $this->getAssignments())

        @if ($assignments->isEmpty())
            <x-filament::section>
                <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                    <x-filament::icon icon="heroicon-o-clipboard-document-list" class="h-10 w-10 text-gray-400" />
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">No assignments</h2>
                    <p class="max-w-lg text-sm text-gray-500 dark:text-gray-400">
                        You currently have no assignments cleared for access.
                    </p>
                </div>
            </x-filament::section>
        @else
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($assignments as $assignment)
                    <x-filament::section :heading="$assignment->evaluation->product->title">
                        <div class="space-y-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-filament::badge>
                                    {{ $this->statusLabel($assignment) }}
                                </x-filament::badge>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $this->readinessLabel($assignment) }}
                                </span>
                            </div>

                            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="font-medium text-gray-500 dark:text-gray-400">Product Release</dt>
                                    <dd class="text-gray-950 dark:text-white">
                                        {{ $assignment->evaluation->productRelease->release_identifier }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-gray-500 dark:text-gray-400">Evaluation Scope</dt>
                                    <dd class="text-gray-950 dark:text-white">
                                        {{ $this->evaluationScope($assignment) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-gray-500 dark:text-gray-400">Standard Version</dt>
                                    <dd class="text-gray-950 dark:text-white">
                                        {{ $assignment->evaluation->standardVersion->version }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-gray-500 dark:text-gray-400">Due</dt>
                                    <dd class="text-gray-950 dark:text-white">
                                        {{ $assignment->due_at?->format('d M Y H:i') ?? 'No deadline recorded' }}
                                    </dd>
                                </div>
                            </dl>

                            <div class="flex justify-end">
                                <x-filament::button
                                    tag="a"
                                    :href="\App\Filament\Auditor\Pages\Assignment::getUrl(['assignment' => $assignment->getKey()])"
                                    icon="heroicon-m-arrow-right"
                                >
                                    View assignment
                                </x-filament::button>
                            </div>
                        </div>
                    </x-filament::section>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
