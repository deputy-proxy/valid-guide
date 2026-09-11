<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PublicVerificationRecord;

class PublicVerificationReader
{
    /** @return array<string, mixed>|null */
    public function read(string $verificationIdentifier): ?array
    {
        $identifier = $this->normalizeIdentifier($verificationIdentifier);

        if ($identifier === null) {
            return null;
        }

        $record = PublicVerificationRecord::query()
            ->select(['public_slug', 'snapshot', 'directory_visible', 'published_at'])
            ->where('public_slug', $identifier)
            ->first();

        if ($record === null || $record->published_at === null || $record->snapshot === null) {
            return null;
        }

        return $record->snapshot;
    }

    public function isValidIdentifier(string $verificationIdentifier): bool
    {
        return $this->normalizeIdentifier($verificationIdentifier) !== null;
    }

    private function normalizeIdentifier(string $verificationIdentifier): ?string
    {
        $identifier = trim($verificationIdentifier);

        if ($identifier === '' || strlen($identifier) > 100) {
            return null;
        }

        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/D', $identifier) !== 1) {
            return null;
        }

        return strtolower($identifier);
    }
}
