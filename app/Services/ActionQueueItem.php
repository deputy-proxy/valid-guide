<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;

final readonly class ActionQueueItem
{
    public function __construct(
        public string $key,
        public string $category,
        public string $title,
        public string $description,
        public string $targetType,
        public int $targetId,
        public bool $stale,
        public CarbonImmutable $createdAt,
    ) {
    }

    /** @return array<string, bool|int|string> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'category' => $this->category,
            'title' => $this->title,
            'description' => $this->description,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'stale' => $this->stale,
            'created_at' => $this->createdAt->toIso8601String(),
        ];
    }
}
