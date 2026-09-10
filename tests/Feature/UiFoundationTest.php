<?php

declare(strict_types=1);

use App\Filament\Pages\UiFoundation;
use App\Models\User;
use Livewire\Livewire;

it('renders the representative Filament UI foundation page', function (): void {
    $user = User::factory()->create();
    $user->forceFill(['platform_role' => 'admin'])->save();

    Livewire::actingAs($user)
        ->test(UiFoundation::class)
        ->assertSee('State presentation')
        ->assertSee('Action presentation')
        ->assertSee('Permitted action')
        ->assertSee('Unavailable action');
});
