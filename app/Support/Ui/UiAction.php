<?php

declare(strict_types=1);

namespace App\Support\Ui;

final readonly class UiAction
{
    public function __construct(
        public string $name,
        public string $label,
        public bool $visible,
        public bool $enabled,
        public ?string $disabledReason = null,
        public ?string $confirmation = null,
    ) {}

    public static function available(
        string $name,
        string $label,
        ?string $confirmation = null,
    ): self {
        return new self(
            name: $name,
            label: $label,
            visible: true,
            enabled: true,
            confirmation: $confirmation,
        );
    }

    public static function unavailable(
        string $name,
        string $label,
        ?string $reason = null,
    ): self {
        return new self(
            name: $name,
            label: $label,
            visible: false,
            enabled: false,
            disabledReason: $reason,
        );
    }

    public static function disabled(
        string $name,
        string $label,
        string $reason,
        ?string $confirmation = null,
    ): self {
        return new self(
            name: $name,
            label: $label,
            visible: true,
            enabled: false,
            disabledReason: $reason,
            confirmation: $confirmation,
        );
    }
}
