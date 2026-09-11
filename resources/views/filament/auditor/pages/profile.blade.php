<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section heading="Profile status">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Your professional profile is reviewed separately from your profile edits. Approval status and administrative history cannot be changed here.
            </p>
        </x-filament::section>

        <form wire:submit="saveProfile" class="space-y-6">
            <x-filament::section heading="Professional information">
                <div class="space-y-5">
                    <div>
                        <label for="bio" class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 text-sm font-medium leading-6 text-gray-950 dark:text-white">
                            Bio
                        </label>
                        <textarea
                            id="bio"
                            wire:model="bio"
                            rows="5"
                            required
                            class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-base text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                        ></textarea>
                    </div>

                    <div>
                        <label for="credentials" class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 text-sm font-medium leading-6 text-gray-950 dark:text-white">
                            Credentials and relevant experience
                        </label>
                        <textarea
                            id="credentials"
                            wire:model="credentials"
                            rows="5"
                            required
                            class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-base text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                        ></textarea>
                    </div>

                    <div>
                        <label for="formatExperience" class="fi-fo-field-wrp-label inline-flex items-center gap-x-3 text-sm font-medium leading-6 text-gray-950 dark:text-white">
                            Format experience
                        </label>
                        <input
                            id="formatExperience"
                            wire:model="formatExperience"
                            type="text"
                            placeholder="course, workshop, cohort"
                            class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-base text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Separate formats with commas.</p>
                    </div>

                    <label class="flex items-center gap-3 text-sm text-gray-700 dark:text-gray-300">
                        <input wire:model="methodologyLiterate" type="checkbox" class="rounded border-gray-300 text-primary-600 shadow-sm" />
                        I confirm that I understand the evaluation methodology.
                    </label>
                </div>
            </x-filament::section>

            <div class="flex justify-end">
                <x-filament::button type="submit">
                    Save profile
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
