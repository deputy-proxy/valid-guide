<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PublicDirectoryEntry;
use App\Models\PublicVerificationRecord;

class PublicDirectoryProjection
{
    public function sync(PublicVerificationRecord $record): PublicDirectoryEntry
    {
        $snapshot = is_array($record->snapshot) ? $record->snapshot : [];
        $product = is_array($snapshot['product'] ?? null) ? $snapshot['product'] : [];
        $verification = is_array($snapshot['verification'] ?? null) ? $snapshot['verification'] : [];
        $suitability = is_array($snapshot['suitability'] ?? null) ? $snapshot['suitability'] : [];
        $visibility = is_array($snapshot['visibility'] ?? null) ? $snapshot['visibility'] : [];

        $entry = PublicDirectoryEntry::query()->firstOrNew([
            'public_verification_record_id' => $record->getKey(),
        ]);

        $entry->fill([
            'verification_identifier' => (string) ($snapshot['verification_identifier'] ?? $verification['identifier'] ?? $record->public_slug),
            'title' => (string) ($product['title'] ?? 'Untitled product'),
            'slug' => (string) ($record->public_slug ?? ''),
            'creator_name' => (string) ($product['creator'] ?? 'Unknown creator'),
            'product_type' => (string) ($product['type'] ?? 'unknown'),
            'subject_area' => isset($suitability['subject_area']) && is_string($suitability['subject_area']) ? $suitability['subject_area'] : null,
            'language' => isset($suitability['language']) && is_string($suitability['language']) ? $suitability['language'] : null,
            'matching_audiences' => is_array($suitability['audiences'] ?? null) ? $suitability['audiences'] : null,
            'matching_goals' => is_array($suitability['goals'] ?? null) ? $suitability['goals'] : null,
            'validation_status' => (string) ($snapshot['status'] ?? 'unknown'),
            'release_identifier' => (string) ($product['release_identifier'] ?? 'unknown'),
            'issued_at' => $snapshot['issued_at'] ?? null,
            'directory_visible' => $record->directory_visible && ($visibility['directory'] ?? false) === true,
        ]);
        $entry->save();

        return $entry->refresh();
    }
}
