<x-layouts.public
    title="For creators"
    description="Learn how creators can submit an online learning product to Valid.guide for independent validation."
>
    <x-public.section>
        <div class="mx-auto max-w-4xl">
            <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">For creators</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white sm:text-4xl">Turn a quality claim into a verifiable trust signal</h1>
            <p class="mt-4 text-lg leading-8 text-zinc-600 dark:text-zinc-400">Submit a learning product for structured, independent evaluation. A published validation gives buyers a clear record of what was assessed and under which standard.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-lg bg-zinc-950 px-5 py-3 text-sm font-semibold text-white hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200 dark:focus:ring-white">Start as a creator</a>
                <a href="{{ route('public.how-it-works') }}" class="inline-flex items-center justify-center rounded-lg border border-zinc-300 px-5 py-3 text-sm font-semibold text-zinc-950 hover:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:border-zinc-700 dark:text-white dark:hover:bg-zinc-900 dark:focus:ring-white">See how validation works</a>
            </div>
        </div>
    </x-public.section>

    <x-public.section title="What the process involves" description="The creator journey is designed to give the evaluation a defined scope and sufficient evidence.">
        <div class="grid gap-6 md:grid-cols-3">
            <article class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800"><h2 class="font-semibold text-zinc-950 dark:text-white">Prepare</h2><p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Identify the product release, audience, promises and supporting material relevant to the evaluation.</p></article>
            <article class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800"><h2 class="font-semibold text-zinc-950 dark:text-white">Evaluate</h2><p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Independent Auditors assess the applicable criteria and document evidence, findings and conclusions.</p></article>
            <article class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800"><h2 class="font-semibold text-zinc-950 dark:text-white">Use the result</h2><p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Where validation is issued and published, the resulting record can be shared as a transparent reference for buyers.</p></article>
        </div>
    </x-public.section>

    <x-public.section title="Independence is part of the product" description="Creators pay for an evaluation, not for a favorable outcome.">
        <div class="max-w-3xl space-y-4 text-sm leading-7 text-zinc-700 dark:text-zinc-300">
            <p>Payment covers the evaluation service. It does not purchase a validation, rating or positive decision.</p>
            <p>The evaluation outcome is governed by the evidence, applicable methodology and controlled decision process.</p>
            <p>Validation is not a guarantee of learner outcomes, sales, employment or any other future result.</p>
        </div>
    </x-public.section>
</x-layouts.public>
