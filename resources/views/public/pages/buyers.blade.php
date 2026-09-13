<x-layouts.public
    title="For buyers and learners"
    description="Learn how to use Valid.guide records to make better-informed decisions about online learning products."
>
    <x-public.section>
        <div class="mx-auto max-w-4xl">
            <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">For buyers and learners</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white sm:text-4xl">Know what was evaluated before you trust the claim</h1>
            <p class="mt-4 text-lg leading-8 text-zinc-600 dark:text-zinc-400">Valid.guide gives you a public record of what product and release were evaluated, which standard was applied, and what the resulting validation status means.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('public.directory') }}" class="inline-flex items-center justify-center rounded-lg bg-zinc-950 px-5 py-3 text-sm font-semibold text-white hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200 dark:focus:ring-white">Browse validated products</a>
                <a href="{{ route('public.verify') }}" class="inline-flex items-center justify-center rounded-lg border border-zinc-300 px-5 py-3 text-sm font-semibold text-zinc-950 hover:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:border-zinc-700 dark:text-white dark:hover:bg-zinc-900 dark:focus:ring-white">Verify a record</a>
            </div>
        </div>
    </x-public.section>

    <x-public.section title="What to look for" description="A validation record gives you evidence and context. Your decision still depends on your goals, circumstances and needs.">
        <div class="grid gap-6 md:grid-cols-2">
            <article class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800"><h2 class="font-semibold text-zinc-950 dark:text-white">Scope and release</h2><p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Check that the validated product and release correspond to the one you are considering.</p></article>
            <article class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800"><h2 class="font-semibold text-zinc-950 dark:text-white">Methodology</h2><p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">See which version of the evaluation standard was applied and what dimensions were considered.</p></article>
            <article class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800"><h2 class="font-semibold text-zinc-950 dark:text-white">Current status</h2><p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Use the authoritative verification record to understand the recorded validation status rather than relying on a badge image alone.</p></article>
            <article class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800"><h2 class="font-semibold text-zinc-950 dark:text-white">Fit for you</h2><p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Validation is not a guarantee that a product is right for every learner or will produce a particular result.</p></article>
        </div>
    </x-public.section>

    <x-public.section title="Validation is evidence, not a promise" description="Use the record to ask better questions and make a better-informed choice.">
        <p class="max-w-3xl text-sm leading-7 text-zinc-700 dark:text-zinc-300">Valid.guide is not a star-rating marketplace, an affiliate catalogue whose incentives depend on recommendations, or a guarantee of learner outcomes. The public record is designed to make the basis and status of a validation understandable.</p>
    </x-public.section>
</x-layouts.public>
