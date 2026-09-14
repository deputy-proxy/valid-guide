<?php

declare(strict_types=1);

it('exposes readiness diagnostics without private workflow data', function () {
    $response = $this->get('/ready');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database',
                'monitoring' => [
                    'total',
                    'due',
                    'failed',
                    'invalid',
                    'stale',
                ],
            ],
        ]);

    expect($response->json('status'))->toBe('ready')
        ->and($response->json('checks.database'))->toBe('ok')
        ->and($response->json('checks.monitoring.total'))->toBe(0)
        ->and($response->json())->not->toHaveKey('organization_id')
        ->and($response->json())->not->toHaveKey('validation_id')
        ->and($response->json())->not->toHaveKey('failure_reason');
});
