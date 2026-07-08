<?php

declare(strict_types=1);

namespace Survos\CiineBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Base class for a hosted asciinema cast (recording bytes + intrinsic metadata).
 *
 * Shipped by ciine-bundle as a mapped superclass. Each consuming app defines a
 * concrete `App\Entity\Cast extends CastBase` (see survos_ciine.cast_class) and
 * adds its own concerns there — marking/workflow, uploader, votes, bookmarks.
 */
#[ORM\MappedSuperclass]
abstract class CastBase implements \Stringable
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        private readonly ?string $code = null,
    ) {
    }

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $title = null;

    #[ORM\Column(nullable: true)]
    public ?string $author = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $asciiCast = null;

    #[ORM\Column(nullable: true)]
    public ?int $fileSize = null;

    #[ORM\Column(nullable: true)]
    public ?int $asciinemaId = null;

    #[ORM\Column(nullable: true)]
    public ?int $markerCount = null;

    #[ORM\Column(nullable: true)]
    public ?int $lineCount = null;

    #[ORM\Column(nullable: true)]
    public int $inputCount = 0;

    #[ORM\Column(nullable: true)]
    public ?float $totalTime = null;

    public ?string $castUrl { get => $this->asciinemaId ? 'https://asciinema.org/a/' . $this->asciinemaId : null; }
    public ?string $downloadUrl { get => $this->asciinemaId ? 'https://asciinema.org/a/' . $this->asciinemaId . '.cast' : null; }

    public function getCode(): ?string
    {
        return $this->code;
    }

    /** @return array<int, string> */
    public function getLines(): array
    {
        return explode("\n", (string) $this->asciiCast);
    }

    /** @return array<string, mixed>|null */
    public function getHeader(): ?array
    {
        return json_decode($this->getLines()[0] ?? '', true);
    }

    public function getAsciiCast(): ?string
    {
        return $this->asciiCast;
    }

    public function setAsciiCast(?string $asciiCast): static
    {
        $this->asciiCast = $asciiCast;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function setFileSize(?int $fileSize): static
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    public function setLineCount(?int $lineCount): static
    {
        $this->lineCount = $lineCount;

        return $this;
    }

    public function setMarkerCount(?int $markerCount): static
    {
        $this->markerCount = $markerCount;

        return $this;
    }

    public function setTotalTime(?float $totalTime): static
    {
        $this->totalTime = $totalTime;

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->code;
    }
}
