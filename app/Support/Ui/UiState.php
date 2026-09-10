<?php

declare(strict_types=1);

namespace App\Support\Ui;

final readonly class UiState
{
    public function __construct(
        public string $label,
        public string $color,
        public string $icon,
        public ?string $description = null,
    ) {}

    public static function make(
        string $label,
        string $color = 'gray',
        string $icon = 'heroicon-o-information-circle',
        ?string $description = null,
    ): self {
        return new self(
            label: $label,
            color: $color,
            icon: $icon,
            description: $description,
        );
    }
}
