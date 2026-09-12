@props([
    'title' => null,
    'description' => null,
    'canonicalUrl' => null,
    'robots' => 'index,follow',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description ?? 'Independent validation for online learning products.' }}">
    <meta name="robots" content="{{ $robots }}">
    <link rel="canonical" href="{{ $canonicalUrl ?? url()->current() }}">

    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title ?? config('app.name', 'Valid.guide') }}">
    <meta property="og:description" content="{{ $description ?? 'Independent validation for online learning products.' }}">
    <meta property="og:url" content="{{ $canonicalUrl ?? url()->current() }}">

    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name', 'Valid.guide') }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-zinc-950 antialiased dark:bg-zinc-950 dark:text-zinc-50">
    <a
        href="#main-content"
        class="sr-only z-50 rounded-md bg-white px-4 py-2 text-sm font-medium text-zinc-950 focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:ring-2 focus:ring-zinc-900 focus:ring-offset-2"
    >
        Skip to content
    </a>

    <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="shrink-0 text-xl font-semibold tracking-tight text-zinc-950 dark:text-white" aria-label="Valid.guide home">
                Valid.guide
            </a>

            <nav aria-label="Primary navigation">
                <ul class="flex flex-wrap items-center justify-end gap-x-5 gap-y-2 text-sm font-medium text-zinc-600 dark:text-zinc-300">
                    <li><a class="rounded-md px-2 py-1 hover:text-zinc-950 focus:outline-none focus:ring-2 focus:ring-zinc-900 dark:hover:text-white dark:focus:ring-white" href="{{ route('public.directory') }}">Directory</a></li>
                    <li><a class="rounded-md px-2 py-1 hover:text-zinc-950 focus:outline-none focus:ring-2 focus:ring-zinc-900 dark:hover:text-white dark:focus:ring-white" href="{{ route('public.experts') }}">Experts</a></li>
                    <li><a class="rounded-md px-2 py-1 hover:text-zinc-950 focus:outline-none focus:ring-2 focus:ring-zinc-900 dark:hover:text-white dark:focus:ring-white" href="{{ route('public.how-it-works') }}">How it works</a></li>
                    <li><a class="rounded-md px-2 py-1 hover:text-zinc-950 focus:outline-none focus:ring-2 focus:ring-zinc-900 dark:hover:text-white dark:focus:ring-white" href="{{ route('public.creators') }}">For creators</a></li>
                    <li><a class="rounded-md px-2 py-1 hover:text-zinc-950 focus:outline-none focus:ring-2 focus:ring-zinc-900 dark:hover:text-white dark:focus:ring-white" href="{{ route('public.buyers') }}">For buyers</a></li>
                    <li><a class="rounded-md px-2 py-1 hover:text-zinc-950 focus:outline-none focus:ring-2 focus:ring-zinc-900 dark:hover:text-white dark:focus:ring-white" href="{{ route('public.verify') }}">Verify</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main id="main-content" class="min-h-[60vh]">
        {{ $slot }}
    </main>

    <footer class="border-t border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900/40">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-10 sm:px-6 md:flex-row md:items-start md:justify-between lg:px-8">
            <div>
                <div class="font-semibold">Valid.guide</div>
                <p class="mt-2 max-w-sm text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                    Independent validation for courses, guides, and other online learning products.
                </p>
            </div>
            <nav aria-label="Footer navigation">
                <ul class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm text-zinc-600 dark:text-zinc-400 sm:grid-cols-3">
                    <li><a class="rounded-md focus:outline-none focus:ring-2 focus:ring-zinc-900 focus:ring-offset-2 hover:text-zinc-950 dark:focus:ring-white dark:hover:text-white" href="{{ route('public.about') }}">About</a></li>
                    <li><a class="rounded-md focus:outline-none focus:ring-2 focus:ring-zinc-900 focus:ring-offset-2 hover:text-zinc-950 dark:focus:ring-white dark:hover:text-white" href="{{ route('public.pricing') }}">Pricing</a></li>
                    <li><a class="rounded-md focus:outline-none focus:ring-2 focus:ring-zinc-900 focus:ring-offset-2 hover:text-zinc-950 dark:focus:ring-white dark:hover:text-white" href="{{ route('public.faq') }}">FAQ</a></li>
                    <li><a class="rounded-md focus:outline-none focus:ring-2 focus:ring-zinc-900 focus:ring-offset-2 hover:text-zinc-950 dark:focus:ring-white dark:hover:text-white" href="{{ route('public.how-it-works') }}">Methodology</a></li>
                    <li><a class="rounded-md focus:outline-none focus:ring-2 focus:ring-zinc-900 focus:ring-offset-2 hover:text-zinc-950 dark:focus:ring-white dark:hover:text-white" href="{{ route('public.verify') }}">Verify a badge</a></li>
                    <li><a class="rounded-md focus:outline-none focus:ring-2 focus:ring-zinc-900 focus:ring-offset-2 hover:text-zinc-950 dark:focus:ring-white dark:hover:text-white" href="{{ route('home') }}#contact">Contact</a></li>
                </ul>
            </nav>
        </div>
    </footer>
</body>
</html>
