<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\RefundStatus;
use App\Models\EvaluationRequest;
use App\Models\User;
use App\Services\CreatorDashboard as CreatorDashboardService;
use App\Services\CreatorRefundService;
use App\Services\DomainStateTransitionException;
use App\Services\OrganizationContext;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

final class CreatorDashboardPage extends Page
{
    protected string $view = 'filament.pages.creator-dashboard';

    protected static string|UnitEnum|null $navigationGroup = 'Creator';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $slug = 'creator-dashboard';

    protected static ?string $title = 'Creator Dashboard';

    /** @var array<string, mixed> */
    public array $dashboard = [];

    public function mount(): void
    {
        $this->loadDashboard();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->organizations()->exists();
    }

    public function requestRefund(int $evaluationRequestId): void
    {
        $actor = self::authenticatedUser();
        $organization = app(OrganizationContext::class)->current($actor);
        $request = EvaluationRequest::query()
            ->whereKey($evaluationRequestId)
            ->where('organization_id', $organization->getKey())
            ->firstOrFail();

        try {
            $refund = app(CreatorRefundService::class)->refund($request, $actor);

            if ($refund->status === RefundStatus::Succeeded) {
                Notification::make()->success()->title('Refund completed')->send();
            } elseif ($refund->status === RefundStatus::Processing) {
                Notification::make()->warning()->title('Refund is processing')->send();
            } else {
                Notification::make()->danger()->title('Refund was not completed')->send();
            }
        } catch (AuthorizationException) {
            Notification::make()->danger()->title('Refund is not authorized.')->send();
        } catch (DomainStateTransitionException) {
            Notification::make()->danger()->title('The request is no longer eligible for a refund.')->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->danger()->title('The refund could not be completed.')->send();
        }

        $this->loadDashboard();
    }

    public function actionUrl(string $action, int|string $requestId): ?string
    {
        $requestId = (int) $requestId;
        $actor = self::authenticatedUser();
        $organization = app(OrganizationContext::class)->current($actor);

        return match ($action) {
            'resume_request', 'continue_payment', 'complete_intake' => route('creator.evaluation-requests.create', [
                'organizationId' => $organization->getKey(),
                'evaluationRequestId' => $requestId,
            ]),
            default => null,
        };
    }

    private function loadDashboard(): void
    {
        $actor = self::authenticatedUser();
        $organization = app(OrganizationContext::class)->current($actor);

        $this->dashboard = app(CreatorDashboardService::class)->forOrganization(
            $actor,
            $organization->getKey(),
        );
    }

    private static function authenticatedUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
