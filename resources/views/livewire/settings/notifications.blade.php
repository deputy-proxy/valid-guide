<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Notification settings') }}</flux:heading>

    <x-settings.layout :heading="__('Notifications')" :subheading="__('Choose which workflow notifications you receive')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <div class="space-y-4">
                @foreach ($this->categories() as $category)
                    <label class="flex items-start gap-3">
                        <input
                            type="checkbox"
                            wire:model="preferences.{{ $category['key'] }}"
                            class="mt-1 rounded border-zinc-300"
                        />
                        <span>
                            <span class="block font-medium text-zinc-900">{{ __($category['label']) }}</span>
                            <span class="block text-sm text-zinc-600">{{ __('Receive :category lifecycle notifications.', ['category' => strtolower($category['label'])]) }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
        </form>
    </x-settings.layout>
</section>
