<x-layouts.public
    title="How validation works"
    description="Understand how Valid.guide evaluates online learning products and publishes transparent trust records."
>
    <x-public.section>
        <div class="mx-auto max-w-4xl">
            <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">The Valid.guide methodology</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white sm:text-4xl">How validation works</h1>
            <p class="mt-4 text-lg leading-8 text-zinc-600 dark:text-zinc-400">
                Validation is a structured assessment of a specific learning product and release against a controlled, versioned standard. It is evidence about what was reviewed, not a promise about what every learner will experience.
            </p>
        </div>

        <div class="mx-auto mt-10 grid max-w-5xl gap-6 md:grid-cols-3">
            <article class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800">
                <p class="text-sm font-medium text-zinc-500">01</p>
                <h2 class="mt-2 text-lg font-semibold text-zinc-950 dark:text-white">Define the scope</h2>
                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">The product release, intended audience, promises and evaluation scope are established before the assessment begins.</p>
            </article>
            <article class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800">
                <p class="text-sm font-medium text-zinc-500">02</p>
                <h2 class="mt-2 text-lg font-semibold text-zinc-950 dark:text-white">Evaluate the evidence</h2>
                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Qualified independent Auditors assess the applicable criteria using submitted product material and documented evidence.</p>
            </article>
            <article class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800">
                <p class="text-sm font-medium text-zinc-500">03</p>
                <h2 class="mt-2 text-lg font-semibold text-zinc-950 dark:text-white">Publish the result</h2>
                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Where a validation is published, its public record preserves the product and release context, methodology and resulting trust status.</p>
            </article>
        </div>
    </x-public.section>

    <x-public.section title="What is assessed" description="The validation methodology covers the parts of a learning product that can be assessed from evidence.">
        <div class="grid gap-6 md:grid-cols-2">
            <ul class="space-y-3 text-sm leading-6 text-zinc-700 dark:text-zinc-300">
                <li>Promise and audience fit</li>
                <li>Subject-matter credibility</li>
                <li>Content quality and completeness</li>
                <li>Structure and coherence</li>
                <li>Learning design and engagement</li>
            </ul>
            <ul class="space-y-3 text-sm leading-6 text-zinc-700 dark:text-zinc-300">
                <li>Practical applicability and transfer</li>
                <li>Evidence, support and intellectual integrity</li>
                <li>Usability, accessibility and delivery integrity</li>
                <li>Differentiation and added value</li>
                <li>Outcome realism and overall product integrity</li>
            </ul>
        </div>
    </x-public.section>

    <x-public.section title="What validation does not mean" description="A Valid.guide result is deliberately narrower than a blanket endorsement.">
        <div class="max-w-3xl space-y-4 text-sm leading-7 text-zinc-700 dark:text-zinc-300">
            <p>Validation does not guarantee a particular learner outcome, employment result, completion rate or personal experience.</p>
            <p>Validation does not mean that a product is suitable for every learner or every use case.</p>
            <p>Creators pay for an evaluation, never for a positive result. Commercial relationships must not determine evaluation outcomes.</p>
        </div>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('public.directory') }}" class="inline-flex items-center justify-center rounded-lg bg-zinc-950 px-5 py-3 text-sm font-semibold text-white hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200 dark:focus:ring-white">Browse validated products</a>
            <a href="{{ route('public.verify') }}" class="inline-flex items-center justify-center rounded-lg border border-zinc-300 px-5 py-3 text-sm font-semibold text-zinc-950 hover:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:border-zinc-700 dark:text-white dark:hover:bg-zinc-900 dark:focus:ring-white">Verify a record</a>
        </div>
    </x-public.section>
</x-layouts.public>
