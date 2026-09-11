<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section heading="Declaration status">
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::badge>{{ $this->statusLabel() }}</x-filament::badge>
                <span class="text-sm text-gray-600 dark:text-gray-400">{{ now()->year }} declaration</span>
            </div>

            @if (($declaration = $this->currentDeclaration())?->determined_at !== null)
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    This declaration was determined on {{ $declaration->determined_at->format('d M Y H:i') }}. Administrative determinations are immutable.
                </p>
            @else
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    Submit any current or potential conflicts. Your declaration will be reviewed by a platform administrator before your annual clearance is granted.
                </p>
            @endif
        </x-filament::section>

        @if ($this->canEdit())
            <form wire:submit="submitDeclaration" class="space-y-6">
                <x-filament::section heading="Your declaration">
                    <label for="disclosure" class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 text-sm font-medium leading-6 text-gray-950 dark:text-white">
                        Conflicts and disclosures
                    </label>
                    <textarea
                        id="disclosure"
                        wire:model="disclosure"
                        rows="7"
                        class="fi-input mt-2 block w-full rounded-lg border-none bg-white px-3 py-2 text-base text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                        placeholder="Describe any current or potential conflicts. Enter 'No known conflicts' if none apply."
                    ></textarea>
                </x-filament::section>

                <div class="flex justify-end">
                    <x-filament::button type="submit">
                        Submit declaration
                    </x-filament::button>
                </div>
            </form>
        @else
            <x-filament::section heading="Submitted disclosure">
                <p class="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">{{ $this->currentDeclaration()?->disclosure ?: 'No disclosure recorded.' }}</p>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
