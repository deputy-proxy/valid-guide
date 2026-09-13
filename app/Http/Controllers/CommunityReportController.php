<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CommunityReportReason;
use App\Models\CommunityContribution;
use App\Models\User;
use App\Services\CommunityContributionGovernance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

final class CommunityReportController extends Controller
{
    public function store(Request $request, CommunityContribution $contribution, CommunityContributionGovernance $governance): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $data = $request->validate([
            'reason' => ['required', new Enum(CommunityReportReason::class)],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $governance->report($contribution, $user, CommunityReportReason::from((string) $data['reason']), $data['details'] ?? null);

        return back()->with('community_report_message', 'Thank you. Your report has been submitted for review.');
    }
}
