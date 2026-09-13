<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\MarketplaceServiceStatus;
use App\Models\MarketplaceService;
use App\Services\MarketplaceServiceManagement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpertMarketplaceController extends Controller
{
    public function index(Request $request): View
    {
        $services = MarketplaceService::query()
            ->whereHas('auditorProfile', fn ($query) => $query->where('auditor_id', $request->user()->getKey()))
            ->latest('id')
            ->get();

        return view('expert.marketplace', ['services' => $services]);
    }

    public function store(Request $request, MarketplaceServiceManagement $management): RedirectResponse
    {
        $management->create($request->user(), $request->all());

        return to_route('expert.marketplace')->with('status', 'Marketplace service saved as a draft.');
    }

    public function publish(MarketplaceService $service, MarketplaceServiceManagement $management, Request $request): RedirectResponse
    {
        $management->publish($request->user(), $service);

        return to_route('expert.marketplace')->with('status', 'Marketplace service published.');
    }

    public function pause(MarketplaceService $service, MarketplaceServiceManagement $management, Request $request): RedirectResponse
    {
        $management->pause($request->user(), $service);

        return to_route('expert.marketplace')->with('status', 'Marketplace service paused.');
    }

    public function archive(MarketplaceService $service, MarketplaceServiceManagement $management, Request $request): RedirectResponse
    {
        $management->archive($request->user(), $service);

        return to_route('expert.marketplace')->with('status', 'Marketplace service archived.');
    }

    public function startTransaction(MarketplaceService $service, Request $request): RedirectResponse
    {
        abort_unless($service->status === MarketplaceServiceStatus::Published, 404);

        return to_route('public.marketplace.show', $service->slug);
    }
}
