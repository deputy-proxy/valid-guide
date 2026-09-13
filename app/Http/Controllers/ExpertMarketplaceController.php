<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MarketplaceService;
use App\Models\MarketplaceTransaction;
use App\Models\User;
use App\Services\MarketplaceServiceManagement;
use App\Services\MarketplaceTransactionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpertMarketplaceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user() instanceof User, 401);
        $user = $request->user();

        $services = MarketplaceService::query()
            ->whereHas('auditorProfile', function (Builder $builder) use ($user): void {
                $builder->where('auditor_id', $user->getKey());
            })
            ->latest('id')
            ->get();
        $transactions = MarketplaceTransaction::query()
            ->with(['marketplaceService', 'buyer'])
            ->whereHas('auditorProfile', function (Builder $builder) use ($user): void {
                $builder->where('auditor_id', $user->getKey());
            })
            ->latest('id')
            ->get();

        return view('expert.marketplace.index', ['services' => $services, 'transactions' => $transactions]);
    }

    public function store(Request $request, MarketplaceServiceManagement $management): RedirectResponse
    {
        abort_unless($request->user() instanceof User, 401);
        $management->create($request->user(), $request->all());

        return to_route('expert.marketplace')->with('status', 'Marketplace service saved as a draft.');
    }

    public function publish(MarketplaceService $service, MarketplaceServiceManagement $management, Request $request): RedirectResponse
    {
        abort_unless($request->user() instanceof User, 401);
        $management->publish($request->user(), $service);

        return to_route('expert.marketplace')->with('status', 'Marketplace service published.');
    }

    public function pause(MarketplaceService $service, MarketplaceServiceManagement $management, Request $request): RedirectResponse
    {
        abort_unless($request->user() instanceof User, 401);
        $management->pause($request->user(), $service);

        return to_route('expert.marketplace')->with('status', 'Marketplace service paused.');
    }

    public function archive(MarketplaceService $service, MarketplaceServiceManagement $management, Request $request): RedirectResponse
    {
        abort_unless($request->user() instanceof User, 401);
        $management->archive($request->user(), $service);

        return to_route('expert.marketplace')->with('status', 'Marketplace service archived.');
    }

    public function startTransaction(MarketplaceTransaction $transaction, MarketplaceTransactionService $transactions, Request $request): RedirectResponse
    {
        abort_unless($request->user() instanceof User, 401);
        $transactions->start($transaction, $request->user());

        return to_route('expert.marketplace')->with('status', 'Marketplace engagement started.');
    }

    public function completeTransaction(MarketplaceTransaction $transaction, MarketplaceTransactionService $transactions, Request $request): RedirectResponse
    {
        abort_unless($request->user() instanceof User, 401);
        $transactions->complete($transaction, $request->user());

        return to_route('expert.marketplace')->with('status', 'Marketplace engagement completed.');
    }
}
