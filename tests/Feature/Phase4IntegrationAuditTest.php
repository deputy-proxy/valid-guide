<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

test('phase 4 integration audit has complete lifecycle coverage', function (): void {
    $requiredTests = [
        'ExpertBoardGovernanceTest.php',
        'ExpertBoardMembershipUiTest.php',
        'AuditorProfileGovernanceTest.php',
        'PublicExpertDirectoryTest.php',
        'ExpertOpportunityTest.php',
        'ExpertOpportunityParticipationTest.php',
        'CommunityContributionTest.php',
        'MarketplaceTest.php',
        'MarketplaceGovernanceTest.php',
        'MarketplaceUiTest.php',
        'WorkflowNotificationTest.php',
        'AuthorizationTest.php',
        'AuditLogTest.php',
        'PublicUiFoundationTest.php',
        'Phase2UiAuditTest.php',
    ];

    foreach ($requiredTests as $test) {
        expect(File::exists(base_path('tests/Feature/'.$test)))->toBeTrue("Missing Phase 4 coverage: {$test}");
    }

    $audit = File::get(base_path('docs/phase-4-integration-audit.md'));

    foreach ([
        'Expert Board',
        'Expert profile & expertise',
        'Expert opportunities',
        'Community participation',
        'Marketplace services',
        'Marketplace transactions',
        'Commercial independence',
        'Notifications & action queues',
        'Public privacy & disclosure',
        'Query/performance review',
    ] as $area) {
        expect($audit)->toContain($area);
    }
});

test('phase 4 public discovery paths keep relationship loading explicit', function (): void {
    $services = [
        'app/Services/PublicExpertDirectory.php' => [
            "->with('auditorProfile.expertBoardMembership')",
        ],
        'app/Services/PublicCommunityDirectory.php' => [
            "->with('auditorProfile.expertPublicProfile')",
        ],
        'app/Services/MarketplaceDiscovery.php' => [
            "->with('auditorProfile.expertPublicProfile')",
        ],
    ];

    foreach ($services as $path => $requiredSnippets) {
        $source = File::get(base_path($path));

        foreach ($requiredSnippets as $snippet) {
            expect($source)->toContain($snippet, "Expected eager loading in {$path}");
        }

        expect($source)->toContain('->paginate(12)');
    }
});

test('phase 4 commercial and community domains cannot become validation mutation paths', function (): void {
    $independentServices = [
        'app/Services/MarketplaceServiceManagement.php',
        'app/Services/MarketplaceTransactionService.php',
        'app/Services/MarketplaceDiscovery.php',
        'app/Services/CommunityContributionGovernance.php',
        'app/Services/PublicCommunityDirectory.php',
    ];

    foreach ($independentServices as $path) {
        $source = File::get(base_path($path));

        expect($source)
            ->not->toContain('Validation::')
            ->not->toContain('EvaluationDecision')
            ->not->toContain('ValidationStateTransition');
    }
});

test('phase 4 independence decision is documented as fail closed and historical', function (): void {
    $decisions = File::get(base_path('docs/implementation-decisions-issue-33.md'));

    expect($decisions)
        ->toContain('The conflict is fail-closed.')
        ->toContain('historical relationship itself can compromise perceived independence')
        ->toContain('Marketplace participation is not a Validation quality signal')
        ->toContain('Internal conflict records, governance notes and audit metadata remain private.');
});
