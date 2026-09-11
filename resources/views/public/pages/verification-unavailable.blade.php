<x-layouts.public
    title="Verification unavailable"
    description="The requested Valid.guide verification record is unavailable."
    robots="noindex,follow"
>
    <x-public.section class="py-16 sm:py-24">
        <div class="mx-auto max-w-2xl">
            <x-public.state
                title="Verification record unavailable"
                message="We could not verify that identifier. The identifier may be malformed, unknown, unpublished, or temporarily unavailable. No private evaluation information is disclosed by this page."
                :action-url="route('public.verify')"
                action-label="Try another identifier"
            />
        </div>
    </x-public.section>
</x-layouts.public>
