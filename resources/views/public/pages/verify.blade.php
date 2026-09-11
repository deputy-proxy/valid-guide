<x-layouts.public
    title="Verify a Valid.guide record"
    description="Verify a Valid.guide validation record using its verification identifier."
    robots="noindex,follow"
>
    <x-public.section class="py-16 sm:py-24">
        <div class="mx-auto max-w-2xl">
            <div class="max-w-xl">
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">Trust verification</p>
                <h1 class="mt-3 text-4xl font-semibold tracking-tight text-zinc-950 sm:text-5xl dark:text-white">
                    Verify a Valid.guide record
                </h1>
                <p class="mt-5 text-lg leading-8 text-zinc-600 dark:text-zinc-300">
                    Enter the verification identifier shown on the Valid.guide badge or verification link.
                </p>
            </div>

            <form method="GET" action="{{ route('public.verify') }}" class="mt-10 rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/40 sm:p-8">
                <label for="verification-identifier" class="block text-sm font-medium text-zinc-950 dark:text-white">
                    Verification identifier
                </label>
                <div class="mt-2 flex flex-col gap-3 sm:flex-row">
                    <input
                        id="verification-identifier"
                        name="identifier"
                        type="text"
                        inputmode="text"
                        autocomplete="off"
                        required
                        maxlength="100"
                        placeholder="VG-XXXXXXXX"
                        class="min-w-0 flex-1 rounded-lg border border-zinc-300 bg-white px-4 py-3 text-sm text-zinc-950 outline-none placeholder:text-zinc-400 focus:border-zinc-950 focus:ring-2 focus:ring-zinc-950 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:focus:border-white dark:focus:ring-white"
                    >
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-zinc-950 px-5 py-3 text-sm font-medium text-white hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-zinc-950 focus:ring-offset-2 dark:bg-white dark:text-zinc-950 dark:hover:bg-zinc-200 dark:focus:ring-white"
                    >
                        Verify record
                    </button>
                </div>
                <p class="mt-3 text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                    Verification identifiers are public trust references. Do not enter private account or evaluation information here.
                </p>
            </form>
        </div>
    </x-public.section>
</x-layouts.public>
