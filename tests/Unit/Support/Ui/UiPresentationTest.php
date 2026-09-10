<?php

declare(strict_types=1);

use App\Support\Ui\UiAction;
use App\Support\Ui\UiState;

it('represents a reusable domain state without owning domain rules', function (): void {
    $state = UiState::make(
        label: 'Active',
        color: 'success',
        icon: 'heroicon-o-check-circle',
        description: 'The record is currently active.',
    );

    expect($state->label)->toBe('Active')
        ->and($state->color)->toBe('success')
        ->and($state->icon)->toBe('heroicon-o-check-circle')
        ->and($state->description)->toBe('The record is currently active.');
});

it('represents an available action independently from its authorization decision', function (): void {
    $action = UiAction::available(
        name: 'archive',
        label: 'Archive',
        confirmation: 'Archive this product?',
    );

    expect($action->name)->toBe('archive')
        ->and($action->visible)->toBeTrue()
        ->and($action->enabled)->toBeTrue()
        ->and($action->confirmation)->toBe('Archive this product?')
        ->and($action->disabledReason)->toBeNull();
});

it('represents unavailable and disabled actions explicitly', function (): void {
    $unavailable = UiAction::unavailable('refund', 'Request refund', 'Not authorized.');
    $disabled = UiAction::disabled('submit', 'Submit evaluation', 'Required evidence is missing.');

    expect($unavailable->visible)->toBeFalse()
        ->and($unavailable->enabled)->toBeFalse()
        ->and($unavailable->disabledReason)->toBe('Not authorized.')
        ->and($disabled->visible)->toBeTrue()
        ->and($disabled->enabled)->toBeFalse()
        ->and($disabled->disabledReason)->toBe('Required evidence is missing.');
});
