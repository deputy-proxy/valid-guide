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

        return view('public.pages.verification', [
            'snapshot' => $snapshot,
            'title' => sprintf(
                '%s verification',
                is_string($product['title'] ?? null) ? $product['title'] : 'Public verification',
            ),
            'description' => sprintf(
                'Public verification record for %s, verification %s.',
                is_string($product['title'] ?? null) ? $product['title'] : 'this learning product',
                is_string($verification['identifier'] ?? null) ? $verification['identifier'] : $verificationIdentifier,
            ),
            'robots' => ($visibility['directory'] ?? false) === true ? 'index,follow' : 'noindex,follow',
            'canonicalUrl' => route('public.verify.show', [
                'verificationIdentifier' => $verificationIdentifier,
            ]),
        ]);
    }
}
