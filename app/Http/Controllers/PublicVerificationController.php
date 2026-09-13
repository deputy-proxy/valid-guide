<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PublicVerificationReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicVerificationController extends Controller
{
    public function __construct(
        private readonly PublicVerificationReader $reader,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $identifier = $request->string('identifier')->trim()->value();

        if ($identifier === '') {
            return view('public.pages.verify');
        }

        if (! $this->reader->isValidIdentifier($identifier)) {
            return view('public.pages.verification-unavailable', [
                'identifier' => $identifier,
                'reason' => 'invalid',
            ]);
        }

        return redirect()->route('public.verify.show', [
            'verificationIdentifier' => $identifier,
        ]);
    }

    public function show(string $verificationIdentifier): View
    {
        $snapshot = $this->reader->read($verificationIdentifier);

        if ($snapshot === null) {
            return view('public.pages.verification-unavailable', [
                'identifier' => $verificationIdentifier,
                'reason' => 'unavailable',
            ]);
        }

        $visibility = is_array($snapshot['visibility'] ?? null)
            ? $snapshot['visibility']
            : [];
        $verification = is_array($snapshot['verification'] ?? null)
            ? $snapshot['verification']
            : [];
        $product = is_array($snapshot['product'] ?? null)
            ? $snapshot['product']
            : [];
        $status = is_string($verification['status'] ?? null)
            ? $verification['status']
            : (is_string($snapshot['status'] ?? null) ? $snapshot['status'] : 'unknown');
        $canonicalIdentifier = is_string($verification['identifier'] ?? null)
            ? $verification['identifier']
            : $verificationIdentifier;

        [$statusLabel, $statusExplanation, $statusTone] = match ($status) {
            'active' => ['Active', 'This public record captures an active validation state at the time it was published.', 'success'],
            'revoked' => ['Revoked', 'This verification record remains available for historical auditability, but its captured trust state is revoked.', 'danger'],
            'suspended' => ['Suspended', 'This verification record is preserved, but its captured trust state is suspended and must not be treated as currently active.', 'warning'],
            'expired' => ['Expired', 'This verification record is preserved for history, but the captured validation period has ended and it must not be treated as currently active.', 'warning'],
            'superseded' => ['Superseded', 'This verification record remains historically valid as a record, but a later verification superseded its trust state.', 'neutral'],
            default => [str($status)->replace('_', ' ')->title()->toString(), 'The public record contains a trust state that is not currently classified by the public presentation. The record is shown without inferring a stronger claim.', 'neutral'],
        };

        return view('public.pages.verification', [
            'snapshot' => $snapshot,
            'status' => $status,
            'statusLabel' => $statusLabel,
            'statusExplanation' => $statusExplanation,
            'statusTone' => $statusTone,
            'title' => sprintf(
                '%s verification',
                is_string($product['title'] ?? null) ? $product['title'] : 'Public verification',
            ),
            'description' => sprintf(
                'Public verification record for %s, verification %s.',
                is_string($product['title'] ?? null) ? $product['title'] : 'this learning product',
                $canonicalIdentifier,
            ),
            'robots' => ($visibility['directory'] ?? false) === true ? 'index,follow' : 'noindex,follow',
            'canonicalUrl' => route('public.verify.show', [
                'verificationIdentifier' => $canonicalIdentifier,
            ]),
        ]);
    }
}
