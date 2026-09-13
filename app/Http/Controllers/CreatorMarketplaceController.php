<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MarketplaceService;
use App\Models\MarketplaceTransaction;
use App\Models\User;
use App\Services\MarketplaceTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreatorMarketplaceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user() instanceof User, 401);
        $user = $request->user();

        $transactions = MarketplaceTransaction::query()
            ->with(['marketplaceService', 'auditorProfile.expertPublicProfile'])
            ->where('buyer_id', $user->getKey())
            ->latest('id')
            ->get();

        return view('creator.marketplace.index', ['transactions' => $transactions]);
    }

    public function purchase(Request $request, MarketplaceService $service, MarketplaceTransactionService $transactions): RedirectResponse
    {
        abort_unless($request->user() instanceof User, 401);
        $transactions->create($request->user(), $service, $request->integer('organization_id') ?: null);

        return to_route('creator.marketplace')->with('status', 'Marketplace service requested.');
    }

    public function cancel(Request $request, MarketplaceTransaction $transaction, MarketplaceTransactionService $transactions): RedirectResponse
    {
        abort_unless($request->user() instanceof User, 401);
        $transactions->cancel($transaction, $request->user(), $request->string('reason')->trim()->value() ?: null);

        return to_route('creator.marketplace')->with('status', 'Marketplace transaction cancelled.');
    }
}
