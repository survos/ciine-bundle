<?php

declare(strict_types=1);

namespace Survos\CiineBundle\Dto;

class PlayerEvent
{
    public function __construct(
        private float $increment,
        private string $type,
        private string $text,
    ) {
    }

    public function getIncrement(): float
    {
        return $this->increment;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function isInput(): bool
    {
        return 'i' === $this->type;
    }

    public function isOutput(): bool
    {
        return 'o' === $this->type;
    }

    public function isMarker(): bool
    {
        return 'm' === $this->type;
    }

    public function endWithAppPrompt(): bool
    {
        return str_ends_with($this->getText(), '> ')
        || str_ends_with($this->getText(), '% ');
    }

    public function isReturn(): bool
    {
        return str_ends_with($this->getText(), "\r");
    }

    public function endWithShellPrompt(): bool
    {
        return str_ends_with($this->getText(), '$ ');
    }
}
