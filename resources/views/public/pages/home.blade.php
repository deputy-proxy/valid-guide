<x-layouts.public
    title="Independent validation for online learning products"
    description="Valid.guide provides independent, evidence-based validation for courses, guides, workshops, programs, and other online learning products."
>
    <section class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900/30">
        <div class="mx-auto grid max-w-7xl gap-12 px-4 py-20 sm:px-6 sm:py-28 lg:grid-cols-[1.15fr_.85fr] lg:items-center lg:px-8">
            <div>
                <x-public.status label="Independent validation" tone="success" />
                <h1 class="mt-6 max-w-4xl text-4xl font-semibold tracking-tight text-zinc-950 sm:text-6xl dark:text-white">
                    Know what was evaluated before you trust the claim.
                </h1>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-zinc-600 dark:text-zinc-400">
                    Valid.guide turns product quality into a transparent trust signal backed by an independent evaluation and a verifiable public record.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('public.verify') }}" class="inline-flex items-center justify-center rounded-lg bg-zinc-950 px-5 py-3 text-sm font-semibold text-white hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200 dark:focus:ring-white">
                        Verify a badge
                    </a>
                    <a href="{{ route('public.how-it-works') }}" class="inline-flex items-center justify-center rounded-lg border border-zinc-300 px-5 py-3 text-sm font-semibold text-zinc-950 hover:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:border-zinc-700 dark:text-white dark:hover:bg-zinc-900 dark:focus:ring-white">
                        How validation works
                    </a>
                </div>
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">A public validation record answers</p>
                <ul class="mt-5 space-y-4 text-sm leading-6 text-zinc-700 dark:text-zinc-300">
                    <li class="border-b border-zinc-100 pb-4 dark:border-zinc-800">What product and release was evaluated?</li>
                    <li class="border-b border-zinc-100 pb-4 dark:border-zinc-800">Which version of the standard was applied?</li>
                    <li class="border-b border-zinc-100 pb-4 dark:border-zinc-800">What was the overall result?</li>
                    <li>What is the current validation status?</li>
                </ul>
            </div>
        </div>
    </section>

    <x-public.section title="Independent by design" description="Creators pay for an evaluation, never for a positive result. The public trust layer is built around historical records rather than mutable operational data.">
        <div class="grid gap-6 md:grid-cols-3">
            <article class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800">
                <h3 class="font-semibold">Evidence</h3>
                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Conclusions are grounded in submitted product material and documented evaluation work.</p>
            </article>
            <article class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800">
                <h3 class="font-semibold">Consistency</h3>
                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Evaluations use controlled, versioned standards so the basis of a result can be understood.</p>
            </article>
            <article class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800">
                <h3 class="font-semibold">Historical integrity</h3>
                <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Published trust records preserve their historical meaning after internal records change.</p>
            </article>
        </div>
    </x-public.section>

    <section id="contact" class="border-t border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900/30">
        <x-public.section title="Built for informed decisions" description="Valid.guide is a validation service, not a star-rating marketplace or a guarantee of learner outcomes.">
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('public.creators') }}" class="rounded-lg bg-zinc-950 px-5 py-3 text-sm font-semibold text-white hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200 dark:focus:ring-white">For creators</a>
                <a href="{{ route('public.buyers') }}" class="rounded-lg border border-zinc-300 px-5 py-3 text-sm font-semibold text-zinc-950 hover:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:border-zinc-700 dark:text-white dark:hover:bg-zinc-900 dark:focus:ring-white">For buyers and learners</a>
            </div>
        </x-public.section>
    </section>
</x-layouts.public>
