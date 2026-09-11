<?php

declare(strict_types=1);

namespace App\Livewire\Creator\Reports;

use App\Models\User;
use App\Services\CreatorImprovementGuidanceAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

final class ShowGuidance extends Component
{
    #[Locked]
    public int $evaluationId;

    /** @var array<string, mixed> */
    public array $guidanceData = [];

    public function mount(int|string $evaluationId): void
    {
        $this->evaluationId = (int) $evaluationId;
        $this->loadGuidance();
    }

    public function render(): View
    {
        return view('livewire.creator.reports.guidance');
    }

    public function refreshGuidance(): void
    {
        try {
            $this->resetErrorBag('guidance');
            $this->loadGuidance();
        } catch (AuthorizationException $exception) {
            $this->addError('guidance', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('guidance', 'The improvement guidance could not be loaded.');
        }
    }

    private function loadGuidance(): void
    {
        $this->guidanceData = app(CreatorImprovementGuidanceAccess::class)->show(
            $this->authenticatedUser(),
            $this->evaluationId,
        );
    }

    private function authenticatedUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new AuthorizationException('Authentication is required.');
        }

        return $user;
    }
}
