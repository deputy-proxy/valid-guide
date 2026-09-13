<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ValidationStatus;
use App\Models\PublicDirectoryEntry;
use Illuminate\View\View;

class PublicProductController extends Controller
{
    public function show(string $slug): View
    {
        $entry = PublicDirectoryEntry::query()
            ->where('slug', strtolower(trim($slug)))
            ->where('directory_visible', true)
            ->where('validation_status', ValidationStatus::Active->value)
            ->with('publicVerificationRecord')
            ->first();

        if ($entry === null || $entry->publicVerificationRecord === null) {
            return view('public.pages.product-unavailable');
        }

        $snapshot = is_array($entry->publicVerificationRecord->snapshot)
            ? $entry->publicVerificationRecord->snapshot
            : [];
        $verification = is_array($snapshot['verification'] ?? null)
            ? $snapshot['verification']
            : [];
        $suitability = is_array($snapshot['suitability'] ?? null)
            ? $snapshot['suitability']
            : [];
        $standard = is_array($snapshot['standard'] ?? null)
            ? $snapshot['standard']
            : [];
        $result = is_array($snapshot['result'] ?? null)
            ? $snapshot['result']
            : [];
        $identifier = is_string($verification['identifier'] ?? null)
            ? $verification['identifier']
            : $entry->verification_identifier;

        return view('public.pages.product', [
            'entry' => $entry,
            'snapshot' => $snapshot,
            'product' => is_array($snapshot['product'] ?? null) ? $snapshot['product'] : [],
            'suitability' => $suitability,
            'standard' => $standard,
            'result' => $result,
            'verificationIdentifier' => $identifier,
            'verificationUrl' => route('public.verify.show', ['verificationIdentifier' => $identifier]),
            'canonicalUrl' => route('public.products.show', ['slug' => $entry->slug]),
        ]);
    }
}
