<x-filament-panels::page>
    @php
        $organization = $dashboard['organization'];
        $products = $dashboard['products'];
        $requests = $dashboard['evaluation_requests'];
        $opportunities = $dashboard['improvement_opportunities'] ?? [];
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">{{ $organization['name'] }}</x-slot>
            <x-slot name="description">Current role: {{ str($organization['role'])->replace('_', ' ')->title() }}</x-slot>

            <div class="flex flex-wrap gap-3">
                <x-filament::button tag="a" :href="\App\Filament\Resources\Products\ProductResource::getUrl()" icon="heroicon-o-academic-cap">
                    Products
                </x-filament::button>
                <x-filament::button tag="a" :href="\App\Filament\Resources\ProductReleases\ProductReleaseResource::getUrl()" icon="heroicon-o-rectangle-stack" color="gray">
                    Product Releases
                </x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::section heading="Improvement Opportunities">
            @if ($opportunities === [])
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    No active improvement opportunities require attention.
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($opportunities as $opportunity)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-semibold">{{ $opportunity['title'] }}</h3>
                                        <x-filament::badge>{{ str($opportunity['status'])->replace('_', ' ')->title() }}</x-filament::badge>
                                        <x-filament::badge color="gray">{{ str($opportunity['priority'])->title() }}</x-filament::badge>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ $opportunity['target_outcome'] }}</p>
                                    @if ($opportunity['product_title'] !== null)
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $opportunity['product_title'] }}</p>
                                    @endif
                                    @if ($opportunity['assignee_name'] !== null)
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Responsible: {{ $opportunity['assignee_name'] }}</p>
                                    @endif
                                </div>

                                <x-filament::button
                                    tag="a"
                                    size="sm"
                                    icon="heroicon-o-arrow-right"
                                    :href="route('creator.reports.show', ['evaluationId' => $opportunity['evaluation_id']])"
                                >
                                    Open evaluation
                                </x-filament::button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        <x-filament::section heading="Products">
            @if ($products === [])
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    No products are available in this organization yet.
                </div>
            @else
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($products as $product)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="font-semibold">{{ $product['title'] }}</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $product['status'] }}</p>
                                </div>
                                <x-filament::button
                                    tag="a"
                                    size="sm"
                                    color="gray"
                                    :href="\App\Filament\Resources\Products\ProductResource::getUrl('edit', ['record' => $product['id']])"
                                >
                                    Manage
                                </x-filament::button>
                            </div>

                            @if ($product['releases'] === [])
                                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No releases yet.</p>
                            @else
                                <div class="mt-4 space-y-2">
                                    @foreach ($product['releases'] as $release)
                                        <div class="flex items-center justify-between gap-3 text-sm">
                                            <span>
                                                {{ $release['identifier'] }}
                                                @if ($release['version'] !== null)
                                                    · v{{ $release['version'] }}
                                                @endif
                                            </span>
                                            <x-filament::badge>{{ $release['status'] }}</x-filament::badge>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        <x-filament::section heading="Evaluation Requests">
            @if ($requests === [])
                <div class="space-y-3 text-sm text-gray-500 dark:text-gray-400">
                    <p>No evaluation requests are available for this organization.</p>
                    <x-filament::button
                        tag="a"
                        :href="route('creator.evaluation-requests.create', ['organizationId' => $organization['id']])"
                        icon="heroicon-o-plus"
                    >
                        Start an evaluation request
                    </x-filament::button>
                </div>
            @else
                <div class="space-y-4">
                    @foreach ($requests as $request)
                        <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-semibold">Request #{{ $request['id'] }}</h3>
                                        <x-filament::badge>{{ str($request['stage'])->replace('_', ' ')->title() }}</x-filament::badge>
                                    </div>

                                    @if (isset($request['product']) && $request['product'] !== null)
                                        <p class="text-sm text-gray-600 dark:text-gray-300">
                                            {{ $request['product']['title'] }}
                                            @if ($request['product_release'] !== null)
                                                · {{ $request['product_release']['identifier'] }}
                                            @endif
                                        </p>
                                    @endif
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    @if ($request['next_action'] !== null && $this->actionUrl($request['next_action'], $request['id']) !== null)
                                        <x-filament::button
                                            tag="a"
                                            :href="$this->actionUrl($request['next_action'], $request['id'])"
                                            icon="heroicon-o-arrow-right"
                                        >
                                            {{ str($request['next_action'])->replace('_', ' ')->title() }}
                                        </x-filament::button>
                                    @elseif ($request['next_action'] === 'request_refund' && ($request['commerce']['refund_eligible'] ?? false))
                                        <x-filament::button
                                            wire:click="requestRefund({{ (int) $request['id'] }})"
                                            wire:loading.attr="disabled"
                                            color="danger"
                                            icon="heroicon-o-arrow-uturn-left"
                                        >
                                            Request refund
                                        </x-filament::button>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Package</div>
                                    <div class="mt-1 text-sm">{{ $request['commerce']['package'] ?? '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Complexity</div>
                                    <div class="mt-1 text-sm">{{ str($request['commerce']['complexity'] ?? '—')->replace('_', ' ')->title() }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Amount</div>
                                    <div class="mt-1 text-sm">
                                        @if (isset($request['commerce']['amount_minor'], $request['commerce']['currency']))
                                            {{ number_format(((int) $request['commerce']['amount_minor']) / 100, 2) }} {{ $request['commerce']['currency'] }}
                                        @else
                                            —
                                        @endif
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">Payment</div>
                                    <div class="mt-1 text-sm">{{ $request['commerce']['payment_status'] !== null ? str($request['commerce']['payment_status'])->replace('_', ' ')->title() : 'Not started' }}</div>
                                </div>
                            </div>

                            @if (isset($request['intake']) && $request['intake'] !== null)
                                <div class="mt-5 rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                                    <div class="text-sm font-medium">Intake readiness</div>
                                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-5 text-sm">
                                        <span>Claims: {{ $request['intake']['claims_confirmed'] ? 'Complete' : 'Pending' }}</span>
                                        <span>Audience: {{ $request['intake']['audience_confirmed'] ? 'Complete' : 'Pending' }}</span>
                                        <span>Scope: {{ $request['intake']['scope_complete'] ? 'Complete' : 'Pending' }}</span>
                                        <span>Material: {{ $request['intake']['material_complete'] ? 'Complete' : 'Pending' }}</span>
                                        <span>Ready: {{ $request['intake']['ready'] ? 'Yes' : 'No' }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
