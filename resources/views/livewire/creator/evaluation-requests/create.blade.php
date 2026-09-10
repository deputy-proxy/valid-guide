<div>
<x-layouts::app :title="__('Request an Evaluation')">
    <div class="mx-auto w-full max-w-4xl space-y-8">
        <div>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Creator application</p>
            <h1 class="mt-1 text-3xl font-semibold text-zinc-900 dark:text-white">Request an evaluation</h1>
            <p class="mt-2 text-zinc-600 dark:text-zinc-300">
                Complete the intake for an exact product release. Payment purchases an evaluation, not a validation result.
            </p>
        </div>

        <nav aria-label="Evaluation request steps">
            <ol class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ([1 => 'Product', 2 => 'Scope', 3 => 'Access & materials', 4 => 'Claims & audience', 5 => 'Review', 6 => 'Payment'] as $step => $label)
                    <li class="rounded-lg border px-3 py-2 text-sm {{ $currentStep === $step ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-100 dark:bg-zinc-800' : 'border-zinc-200 dark:border-zinc-700' }}">
                        <span class="font-medium">{{ $step }}.</span> {{ $label }}
                    </li>
                @endforeach
            </ol>
        </nav>

        @if ($errors->has('form'))
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                {{ $errors->first('form') }}
            </div>
        @endif

        @if ($currentStep === 1)
            <section class="space-y-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <div>
                    <h2 class="text-xl font-semibold">1. Product</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Choose a product managed by this organization.</p>
                </div>
                <div>
                    <label for="productId" class="mb-2 block text-sm font-medium">Product</label>
                    <select id="productId" wire:model="productId" class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900">
                        <option value="">Select a product</option>
                        @foreach ($this->productOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('productId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </section>
        @elseif ($currentStep === 2)
            <section class="space-y-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <div>
                    <h2 class="text-xl font-semibold">2. Scope & product release</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Select the exact release that will be evaluated and define what you want evaluated.</p>
                </div>
                <div>
                    <label for="productReleaseId" class="mb-2 block text-sm font-medium">Product release</label>
                    <select id="productReleaseId" wire:model="productReleaseId" class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900">
                        <option value="">Select the current release</option>
                        @foreach ($this->releaseOptions() as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('productReleaseId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="scope" class="mb-2 block text-sm font-medium">Evaluation scope</label>
                    <textarea id="scope" wire:model="scope" rows="6" class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900" placeholder="Describe the scope you want the evaluation to cover."></textarea>
                    @error('scope') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="completionWindow" class="mb-2 block text-sm font-medium">Requested completion window <span class="font-normal text-zinc-500">(optional)</span></label>
                    <input id="completionWindow" type="text" wire:model="completionWindow" class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900" placeholder="e.g. 10 business days">
                </div>
            </section>
        @elseif ($currentStep === 3)
            <section class="space-y-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <div>
                    <h2 class="text-xl font-semibold">3. Access & materials</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Provide at least one item the evaluation team can use for intake.</p>
                </div>
                @if ($request->materials->isNotEmpty())
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <p class="font-medium">Material already supplied</p>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $request->materials->first()->label }}</p>
                    </div>
                @else
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="materialType" class="mb-2 block text-sm font-medium">Type</label>
                            <select id="materialType" wire:model="materialType" class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900">
                                <option value="url">URL</option>
                                <option value="access">Access</option>
                                <option value="file">File / storage reference</option>
                                <option value="note">Note</option>
                            </select>
                        </div>
                        <div>
                            <label for="materialLabel" class="mb-2 block text-sm font-medium">Label</label>
                            <input id="materialLabel" type="text" wire:model="materialLabel" class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900" placeholder="Course landing page">
                            @error('materialLabel') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label for="materialLocation" class="mb-2 block text-sm font-medium">Location</label>
                        <input id="materialLocation" type="text" wire:model="materialLocation" class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900" placeholder="https://example.com/course">
                        @error('materialLocation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="materialDescription" class="mb-2 block text-sm font-medium">Description <span class="font-normal text-zinc-500">(optional)</span></label>
                        <textarea id="materialDescription" wire:model="materialDescription" rows="4" class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900"></textarea>
                    </div>
                @endif
            </section>
        @elseif ($currentStep === 4)
            <section class="space-y-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <div>
                    <h2 class="text-xl font-semibold">4. Claims & audience</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Confirm that the evaluation should use the product's current claims and target audience.</p>
                </div>
                <div class="space-y-4 rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                    <div>
                        <h3 class="font-medium">Target audience</h3>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-zinc-600 dark:text-zinc-300">{{ $request->product?->target_audience ?: 'Not provided' }}</p>
                    </div>
                    <div>
                        <h3 class="font-medium">Claimed outcomes</h3>
                        @if (is_array($request->product?->claimed_outcomes))
                            <ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-zinc-600 dark:text-zinc-300">
                                @foreach ($request->product->claimed_outcomes as $outcome)
                                    <li>{{ is_scalar($outcome) ? $outcome : json_encode($outcome) }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Not provided</p>
                        @endif
                    </div>
                </div>
                <label class="flex items-start gap-3">
                    <input type="checkbox" wire:model="claimsConfirmed" class="mt-1 rounded border-zinc-300">
                    <span class="text-sm">I confirm these are the claims/outcomes the evaluation should assess.</span>
                </label>
                @error('claimsConfirmed') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <label class="flex items-start gap-3">
                    <input type="checkbox" wire:model="audienceConfirmed" class="mt-1 rounded border-zinc-300">
                    <span class="text-sm">I confirm the displayed target audience is current for this release.</span>
                </label>
                @error('audienceConfirmed') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </section>
        @elseif ($currentStep === 5)
            @php
                $notes = $this->intakeNotes($request);
                $material = $notes['material'] ?? null;
                $quote = $this->quotePreview($request);
            @endphp
            <section class="space-y-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <div>
                    <h2 class="text-xl font-semibold">5. Review</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">Review the complete request and confirm the package and complexity before payment.</p>
                </div>
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm text-zinc-500">Product</dt>
                        <dd class="font-medium">{{ $request->product?->title }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-zinc-500">Exact release</dt>
                        <dd class="font-medium">
                            {{ $request->productRelease?->release_identifier ?: 'Current release' }}
                            @if ($request->productRelease?->version)
                                · v{{ $request->productRelease->version }}
                            @endif
                            @if ($request->productRelease?->edition)
                                · {{ $request->productRelease->edition }}
                            @endif
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-sm text-zinc-500">Scope</dt>
                        <dd class="whitespace-pre-wrap">{{ data_get($notes, 'scope') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-zinc-500">Access & materials</dt>
                        <dd class="font-medium">
                            {{ is_array($material) ? '1 intake item' : 'Not supplied' }}
                        </dd>
                        @if (is_array($material))
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                {{ data_get($material, 'label') }}
                                @if (data_get($material, 'location'))
                                    · {{ data_get($material, 'location') }}
                                @endif
                            </p>
                        @endif
                    </div>
                    <div>
                        <dt class="text-sm text-zinc-500">Claims & audience</dt>
                        <dd class="font-medium">Confirmed</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-zinc-500">Evaluation package</dt>
                        <dd class="font-medium">{{ $quote?->servicePackageName ?? $request->service_package_name_snapshot ?? 'Select a package' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-zinc-500">Complexity</dt>
                        <dd class="font-medium">{{ \Illuminate\Support\Str::headline($quote?->complexity->value ?? $complexity) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-zinc-500">Amount</dt>
                        <dd class="font-medium">
                            @if ($quote)
                                {{ number_format($quote->amountMinor / 100, 2) }} {{ $quote->currency }}
                            @elseif ($request->quoted_amount_minor !== null)
                                {{ $request->quoted_price }} {{ $request->currency }}
                            @else
                                Select a valid package and complexity
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-zinc-500">Refund boundary</dt>
                        <dd class="font-medium">Before an evaluation report is delivered</dd>
                    </div>
                </dl>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="servicePackageId" class="mb-2 block text-sm font-medium">Evaluation package</label>
                        <select id="servicePackageId" wire:model="servicePackageId" class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900">
                            <option value="">Select a package</option>
                            @foreach ($this->packageOptions() as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('servicePackageId') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="complexity" class="mb-2 block text-sm font-medium">Complexity</label>
                        <select id="complexity" wire:model="complexity" class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-900">
                            @foreach ($this->complexityOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('complexity') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                    <p class="font-medium">Refund policy</p>
                    <p class="mt-1 text-zinc-600 dark:text-zinc-300">A paid evaluation may be refunded before an evaluation report is delivered, subject to the applicable refund workflow. A validation result is never refundable merely because the result is negative.</p>
                </div>
            </section>
        @elseif ($currentStep === 6)
            <section class="space-y-6 rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <div>
                    <p class="text-sm font-medium text-green-700 dark:text-green-300">Payment handoff ready</p>
                    <h2 class="mt-1 text-xl font-semibold">Evaluation request submitted</h2>
                    <p class="mt-2 text-zinc-600 dark:text-zinc-300">The request has passed intake validation and is now awaiting payment.</p>
                </div>
                <div class="rounded-lg bg-zinc-50 p-5 dark:bg-zinc-800">
                    <p class="text-sm text-zinc-500">{{ $request->product?->title }}</p>
                    <p class="mt-1 font-medium">{{ $request->productRelease?->release_identifier ?: $request->productRelease?->version }}</p>
                    <p class="mt-3 text-2xl font-semibold">{{ $request->quoted_price }} {{ $request->currency }}</p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">{{ $request->service_package_name_snapshot }} · {{ $request->complexity?->value ? \Illuminate\Support\Str::headline($request->complexity->value) : '' }}</p>
                </div>
            </section>
        @endif

        @if ($currentStep < 6)
            <div class="flex items-center justify-between gap-4">
                <button type="button" wire:click="back" @disabled($currentStep === 1) class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-600">
                    Back
                </button>
                @if ($currentStep < 5)
                    <button type="button" wire:click="next" class="rounded-lg bg-zinc-900 px-5 py-2 text-sm font-medium text-white dark:bg-white dark:text-zinc-900">Continue</button>
                @else
                    <button type="button" wire:click="submitForPayment" class="rounded-lg bg-zinc-900 px-5 py-2 text-sm font-medium text-white dark:bg-white dark:text-zinc-900">Confirm & continue to payment</button>
                @endif
            </div>
        @endif
    </div>
</x-layouts::app>
</div>
