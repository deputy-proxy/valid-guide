<?php

declare(strict_types=1);

namespace App\Livewire\Creator\EvaluationRequests;

use App\Enums\EvaluationComplexity;
use App\Enums\EvaluationMaterialType;
use App\Enums\EvaluationRequestStatus;
use App\Models\EvaluationRequest;
use App\Models\Product;
use App\Models\ProductRelease;
use App\Services\CreatorEvaluationRequestIntake;
use App\Services\DomainStateTransitionException;
use App\Services\EvaluationMaterialIntake;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

final class CreateEvaluationRequest extends Component
{
    public int $organizationId;

    public EvaluationRequest $request;

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
        $actor = Auth::user();

        if ($actor === null) {
            throw new AuthorizationException('Authentication is required.');
        }

        $this->request = app(CreatorEvaluationRequestIntake::class)->start(
            $actor,
            $this->organizationId,
            $evaluationRequestId === null ? null : (int) $evaluationRequestId,
        );

        $this->hydrateFromRequest();
    }

    public function render(): View
    {
        return view('livewire.creator.evaluation-requests.create');
    }

    /** @return array<int, string> */
    public function productOptions(): array
    {
        $products = Product::query()
            ->where('organization_id', $this->organizationId)
            ->where('status', 'active')
            ->orderBy('title')
            ->get(['id', 'title']);

        return $products->pluck('title', 'id')->mapWithKeys(
            fn (mixed $title, mixed $id): array => [(int) $id => (string) $title],
        )->all();
    }

    /** @return array<int, string> */
    public function releaseOptions(): array
    {
        if ($this->productId === null) {
            return [];
        }

        $releases = ProductRelease::query()
            ->where('product_id', $this->productId)
            ->where('status', 'current')
            ->orderByDesc('published_at')
            ->get(['id', 'release_identifier', 'version', 'edition']);

        return $releases->mapWithKeys(function (ProductRelease $release): array {
            $label = collect([
                $release->release_identifier,
                $release->version !== null ? 'v'.$release->version : null,
                $release->edition,
            ])->filter()->implode(' · ');

            return [(int) $release->getKey() => $label !== '' ? $label : 'Current release'];
        })->all();
    }

    /** @return array<int, string> */
    public function packageOptions(): array
    {
        $product = $this->request->product;
        if ($product === null) {
            return [];
        }

        return $product->organization_id === $this->organizationId
            ? app('App\\Models\\ServicePackage')::query()
                ->where('status', 'active')
                ->whereJsonContains('product_types', $product->product_type->value)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->pluck('name', 'id')
                ->mapWithKeys(fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
                ->all()
            : [];
    }

    /** @return array<string, string> */
    public function complexityOptions(): array
    {
        return collect(EvaluationComplexity::cases())
            ->mapWithKeys(fn (EvaluationComplexity $complexity): array => [
                $complexity->value => Str::headline($complexity->value),
            ])->all();
    }

    public function next(): void
    {
        $this->clearErrorBag();

        try {
            match ($this->currentStep) {
                1 => $this->saveProduct(),
                2 => $this->saveReleaseAndScope(),
                3 => $this->saveMaterials(),
                4 => $this->saveClaimsAndAudience(),
                5 => $this->saveCommercialTerms(),
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
        if ($this->request->status !== EvaluationRequestStatus::Draft) {
            return;
        }

        $this->clearErrorBag();
        $this->currentStep = max(1, $this->currentStep - 1);
    }

    public function submitForPayment(): void
    {
        $this->clearErrorBag();

        try {
            $actor = Auth::user();
            if ($actor === null) {
                throw new AuthorizationException('Authentication is required.');
            }

            $this->request = app(CreatorEvaluationRequestIntake::class)->validateForPayment($actor, $this->request);
            $this->currentStep = 6;
        } catch (AuthorizationException|DomainStateTransitionException $exception) {
            $this->addError('form', $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('form', 'The request could not be submitted for payment. Please review the information and try again.');
        }
    }

    private function saveProduct(): void
    {
        $this->validate(['productId' => ['required', 'integer']]);

        $actor = $this->authenticatedUser();
        $product = Product::query()->findOrFail($this->productId);
        $this->request = app(CreatorEvaluationRequestIntake::class)->selectProduct($actor, $this->request, $product);
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
        $release = ProductRelease::query()->findOrFail($this->productReleaseId);
        $this->request = app(CreatorEvaluationRequestIntake::class)->selectRelease($actor, $this->request, $release);
        $this->request = app(CreatorEvaluationRequestIntake::class)->updateScope(
            $actor,
            $this->request,
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

        if ($this->request->materials()->exists()) {
            return;
        }

        $type = EvaluationMaterialType::tryFrom($this->materialType);
        if ($type === null) {
            throw new DomainStateTransitionException('The selected material type is invalid.');
        }

        $actor = $this->authenticatedUser();
        app(EvaluationMaterialIntake::class)->submit(
            $this->request,
            $actor,
            $type,
            $this->materialLabel,
            $this->materialDescription !== '' ? $this->materialDescription : null,
            $this->materialLocation !== '' ? $this->materialLocation : null,
            ['source' => 'creator_wizard'],
        );

        $this->request = $this->request->refresh()->load(['product', 'productRelease', 'materials', 'servicePackage']);
    }

    private function saveClaimsAndAudience(): void
    {
        $this->validate([
            'claimsConfirmed' => ['accepted'],
            'audienceConfirmed' => ['accepted'],
        ]);

        $actor = $this->authenticatedUser();
        $intake = app(CreatorEvaluationRequestIntake::class);
        $notes = $intake->intakeNotes($this->request);
        $notes['claims_confirmed'] = true;
        $notes['audience_confirmed'] = true;
        $this->request->intake_notes = json_encode($notes, JSON_THROW_ON_ERROR);
        $this->request->save();
        $this->request = $this->request->refresh()->load(['product', 'productRelease', 'materials', 'servicePackage']);
        unset($actor);
    }

    private function saveCommercialTerms(): void
    {
        $this->validate([
            'servicePackageId' => ['required', 'integer'],
            'complexity' => ['required', 'string'],
        ]);

        $actor = $this->authenticatedUser();
        $this->request = app(CreatorEvaluationRequestIntake::class)->applyCommercialTerms(
            $actor,
            $this->request,
            $this->servicePackageId,
            $this->complexity,
        );
        $this->currentStep = 5;
    }

    private function hydrateFromRequest(): void
    {
        $this->request = $this->request->load(['product', 'productRelease', 'materials', 'servicePackage']);
        $this->productId = $this->request->product_id;
        $this->productReleaseId = $this->request->product_release_id;
        $notes = app(CreatorEvaluationRequestIntake::class)->intakeNotes($this->request);
        $this->scope = isset($notes['scope']) && is_string($notes['scope']) ? $notes['scope'] : '';
        $this->completionWindow = isset($notes['requested_completion_window']) && is_string($notes['requested_completion_window'])
            ? $notes['requested_completion_window']
            : '';
        $this->claimsConfirmed = ($notes['claims_confirmed'] ?? false) === true;
        $this->audienceConfirmed = ($notes['audience_confirmed'] ?? false) === true;
        $this->servicePackageId = $this->request->service_package_id;
        $this->complexity = $this->request->complexity?->value ?? 'standard';

        if ($this->request->status !== EvaluationRequestStatus::Draft) {
            $this->currentStep = 6;
        }
    }

    private function authenticatedUser(): \App\Models\User
    {
        $user = Auth::user();

        if ($user === null) {
            throw new AuthorizationException('Authentication is required.');
        }

        return $user;
    }
}
