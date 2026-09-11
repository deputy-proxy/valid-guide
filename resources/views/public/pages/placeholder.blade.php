<x-layouts.public
    :title="$title"
    :description="$description"
    :robots="$robots ?? 'index,follow'"
>
    <x-public.section>
        <div class="mx-auto max-w-3xl">
            <x-public.state
                :title="$title"
                :message="$message"
                :action-url="route('home')"
                action-label="Back to Valid.guide"
            />
        </div>
    </x-public.section>
</x-layouts.public>
