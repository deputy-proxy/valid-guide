<?php

declare(strict_types=1);

namespace App\Livewire\Creator\EvaluationRequests;

use App\Enums\EvaluationComplexity;
use App\Enums\EvaluationMaterialType;
use App\Enums\EvaluationRequestStatus;
use App\Models\EvaluationRequest;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Models\ServicePackage;
use App\Models\User;
use App\Services\CreatorEvaluationRequestIntake;
use App\Services\DomainStateTransitionException;
use App\Services\StripePaymentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

final class CreateEvaluationRequest extends Component
{
    public int $organizationId;

    public ?int $evaluationRequestId = null;

    public int $currentStep = 1;

    public ?int $productId = null;

    public ?int $productReleaseId = null;

    public string $scope = '';

    public string $completionWindow = '';

    public bool $claimsConfirmed = false;

    public bool $audienceConfirmed = false;

    public string $materialType = 'url';

    public string $materialLabel = '';

    public string $materialDescription = '';

    public string $materialLocation = '';

    public ?int $servicePackageId = null;

    public string $complexity = 'standard';

    public function mount(
        int|string $organizationId,
        int|string|null $evaluationRequestId = null,
    ): void {
        $this->organizationId = (int) $organizationId;
        $this->evaluationRequestId = $evaluationRequestId === null ? null : (int) $evaluationRequestId;
        $actor = $this->authenticatedUser();
        $intake = app(CreatorEvaluationRequestIntake::class);

        $intake->start($actor, $this->organizationId, $this->evaluationRequestId);

        if ($this->evaluationRequestId !== null) {
            $this->hydrateFromRequest($this->requestModel());
        }
    }

    public function render(): View
    {
        return view('livewire.creator.evaluation-requests.create', [
            'request' => $this->requestModel(),
        ]);
    }

    /** @return array<int, string> */
    public function productOptions(): array
    {
        return Product::query()
            ->where('organization_id', $this->organizationId)
            ->where('status', 'active')
            ->orderBy('title')
            ->pluck('title', 'id')
            ->mapWithKeys(
                fn (mixed $title, mixed $id): array => [(int) $id => (string) $title],
            )
            ->all();
    }

    /** @return array<int, string> */
    public function releaseOptions(): array
    {
        if ($this->productId === null) {
            return [];
        }

        return ProductRelease::query()
            ->where('product_id', $this->productId)
            ->where('status', 'current')
            ->orderByDesc('published_at')
            ->get(['id', 'release_identifier', 'version', 'edition'])
            ->mapWithKeys(function (ProductRelease $release): array {
                $label = collect([
                    $release->release_identifier,
                    $release->version !== null ? 'v'.$release->version : null,
                    $release->edition,
                ])->filter()->implode(' · ');

                return [(int) $release->getKey() => $label !== '' ? $label : 'Current release'];
            })
            ->all();
    }

    /** @return array<int, string> */
    public function packageOptions(): array
    {
        $product = $this->requestModel()->product;
        if ($product === null || (int) $product->organization_id !== $this->organizationId) {
            return [];
        }

        return ServicePackage::query()
            ->where('status', 'active')
            ->whereJsonContains('product_types', $product->product_type->value)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(
                fn (mixed $name, mixed $id): array => [(int) $id => (string) $name],
            )
            ->all();
    }

    /** @return array<string, string> */
    public function complexityOptions(): array
    {
        return collect(EvaluationComplexity::cases())
            ->mapWithKeys(
                fn (EvaluationComplexity $complexity): array => [
                    $complexity->value => Str::headline($complexity->value),
                ],
            )
            ->all();
    }

    /** @return array<string, mixed> */
    public function intakeNotes(EvaluationRequest $request): array
    {
        return app(CreatorEvaluationRequestIntake::class)->intakeNotes($request);
    }

    public function next(): void
    {
        $this->resetValidation();

        try {
            match ($this->currentStep) {
                1 => $this->saveProduct(),
                2 => $this->saveReleaseAndScope(),
                3 => $this->saveMaterials(),
                4 => $this->saveClaimsAndAudience(),
                default => null,
            };

            if ($this->currentStep < 5) {
                $this->currentStep++;
            }
        } catch (AuthorizationException|DomainStateTransitionException $exception) {
            $this->addError('form', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('form', 'The request could not be saved. Please review the information and try again.');
        }
    }

    public function back(): void
    {
        if ($this->requestModel()->status !== EvaluationRequestStatus::Draft) {
            return;
        }

        $this->resetValidation();
        $this->currentStep = max(1, $this->currentStep - 1);
    }

    public function submitForPayment(): ?RedirectResponse
    {
        $this->resetValidation();

        try {
            $this->validate([
                'servicePackageId' => ['required', 'integer'],
                'complexity' => ['required', 'string'],
            ]);

            $actor = $this->authenticatedUser();
            $intake = app(CreatorEvaluationRequestIntake::class);
            $request = $intake->applyCommercialTerms(
                $actor,
                $this->requestModel(),
                $this->servicePackageId,
                $this->complexity,
            );
            $intake->validateForPayment($actor, $request);

            $checkout = app(StripePaymentService::class)->createCheckout($request, $actor);

            return redirect()->away($checkout['url']);
        } catch (AuthorizationException|DomainStateTransitionException $exception) {
            $this->addError('form', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('form', 'The request could not be submitted for payment. Please review the information and try again.');
        }

        return null;
    }

    private function saveProduct(): void
    {
        $this->validate(['productId' => ['required', 'integer']]);

        $actor = $this->authenticatedUser();
        $request = app(CreatorEvaluationRequestIntake::class)->selectProduct(
            $actor,
            $this->requestModel(),
            Product::query()->findOrFail($this->productId),
        );
        $this->evaluationRequestId = (int) $request->getKey();
        $this->productReleaseId = null;
        $this->servicePackageId = null;
    }

    private function saveReleaseAndScope(): void
    {
        $this->validate([
            'productReleaseId' => ['required', 'integer'],
            'scope' => ['required', 'string', 'max:5000'],
            'completionWindow' => ['nullable', 'string', 'max:255'],
        ]);

        $actor = $this->authenticatedUser();
        $intake = app(CreatorEvaluationRequestIntake::class);
        $request = $intake->selectRelease(
            $actor,
            $this->requestModel(),
            ProductRelease::query()->findOrFail($this->productReleaseId),
        );
        $intake->updateScope(
            $actor,
            $request,
            $this->scope,
            $this->completionWindow !== '' ? $this->completionWindow : null,
        );
    }

    private function saveMaterials(): void
    {
        $this->validate([
            'materialType' => ['required', 'string'],
            'materialLabel' => ['required', 'string', 'max:255'],
            'materialDescription' => ['nullable', 'string', 'max:5000'],
            'materialLocation' => ['nullable', 'string', 'max:2048'],
        ]);

        $type = EvaluationMaterialType::tryFrom($this->materialType);
        if ($type === null) {
            throw new DomainStateTransitionException('The selected material type is invalid.');
        }

        app(CreatorEvaluationRequestIntake::class)->saveMaterialDraft(
            $this->authenticatedUser(),
            $this->requestModel(),
            $type,
            $this->materialLabel,
            $this->materialDescription !== '' ? $this->materialDescription : null,
            $this->materialLocation !== '' ? $this->materialLocation : null,
        );
    }

    private function saveClaimsAndAudience(): void
    {
        $this->validate([
            'claimsConfirmed' => ['accepted'],
            'audienceConfirmed' => ['accepted'],
        ]);

        app(CreatorEvaluationRequestIntake::class)->confirmClaimsAndAudience(
            $this->authenticatedUser(),
            $this->requestModel(),
        );
    }

    private function hydrateFromRequest(EvaluationRequest $request): void
    {
        $request->load(['product', 'productRelease', 'materials', 'servicePackage']);
        $this->productId = $request->product_id;
        $this->productReleaseId = $request->product_release_id;
        $notes = app(CreatorEvaluationRequestIntake::class)->intakeNotes($request);
        $this->scope = isset($notes['scope']) && is_string($notes['scope']) ? $notes['scope'] : '';
        $this->completionWindow = isset($notes['requested_completion_window']) && is_string($notes['requested_completion_window'])
            ? $notes['requested_completion_window']
            : '';
        $material = $notes['material'] ?? null;
        if (is_array($material)) {
            $this->materialType = isset($material['type']) && is_string($material['type']) ? $material['type'] : $this->materialType;
            $this->materialLabel = isset($material['label']) && is_string($material['label']) ? $material['label'] : '';
            $this->materialDescription = isset($material['description']) && is_string($material['description']) ? $material['description'] : '';
            $this->materialLocation = isset($material['location']) && is_string($material['location']) ? $material['location'] : '';
        }
        $this->claimsConfirmed = ($notes['claims_confirmed'] ?? false) === true;
        $this->audienceConfirmed = ($notes['audience_confirmed'] ?? false) === true;
        $this->servicePackageId = $request->service_package_id;
        $this->complexity = $request->complexity->value;

        if ($request->status !== EvaluationRequestStatus::Draft) {
            $this->currentStep = 6;
        }
    }

    private function requestModel(): EvaluationRequest
    {
        if ($this->evaluationRequestId !== null) {
            return EvaluationRequest::query()
                ->with(['product', 'productRelease', 'materials', 'servicePackage'])
                ->findOrFail($this->evaluationRequestId);
        }

        return app(CreatorEvaluationRequestIntake::class)->start(
            $this->authenticatedUser(),
            $this->organizationId,
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
