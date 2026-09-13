<x-layouts.public
    title="About Valid.guide"
    description="Learn what Valid.guide is, why it exists and how its independent validation model works."
>
    <x-public.section>
        <div class="mx-auto max-w-4xl">
            <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">About Valid.guide</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-zinc-950 dark:text-white sm:text-4xl">A clearer trust signal for online learning</h1>
            <p class="mt-4 text-lg leading-8 text-zinc-600 dark:text-zinc-400">Valid.guide exists to make quality claims easier to understand and verify. It combines independent evaluation, versioned methodology and historical public records without turning validation into a star-rating marketplace.</p>
        </div>
    </x-public.section>

    <x-public.section title="What we believe" description="The product is built around a small set of principles that protect the usefulness of validation.">
        <div class="grid gap-6 md:grid-cols-2">
            <article class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800"><h2 class="font-semibold text-zinc-950 dark:text-white">Independence</h2><p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Evaluation outcomes are independent of commercial relationships.</p></article>
            <article class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800"><h2 class="font-semibold text-zinc-950 dark:text-white">Evidence</h2><p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Conclusions should be grounded in submitted product material and documented evaluation work.</p></article>
            <article class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800"><h2 class="font-semibold text-zinc-950 dark:text-white">Historical integrity</h2><p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Published trust records retain their historical meaning rather than changing silently with internal data.</p></article>
            <article class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800"><h2 class="font-semibold text-zinc-950 dark:text-white">Buyer usefulness</h2><p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Public information should help people make informed decisions without overstating what validation proves.</p></article>
        </div>
    </x-public.section>

    <x-public.section title="What Valid.guide is not" description="Clear boundaries matter as much as the features themselves.">
        <ul class="max-w-3xl space-y-3 text-sm leading-7 text-zinc-700 dark:text-zinc-300">
            <li>Not a generic review marketplace or star-rating website.</li>
            <li>Not a pay-for-positive-review service.</li>
            <li>Not an affiliate catalogue whose incentives depend on recommendations.</li>
            <li>Not a guarantee of learner outcomes.</li>
            <li>Not a substitute for buyer judgement.</li>
        </ul>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('public.how-it-works') }}" class="inline-flex items-center justify-center rounded-lg bg-zinc-950 px-5 py-3 text-sm font-semibold text-white hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200 dark:focus:ring-white">How validation works</a>
            <a href="{{ route('public.buyers') }}" class="inline-flex items-center justify-center rounded-lg border border-zinc-300 px-5 py-3 text-sm font-semibold text-zinc-950 hover:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:border-zinc-700 dark:text-white dark:hover:bg-zinc-900 dark:focus:ring-white">For buyers</a>
        </div>
    </x-public.section>
</x-layouts.public>
