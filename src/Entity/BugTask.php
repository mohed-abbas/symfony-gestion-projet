<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class BugTask extends Task
{
    public const SEVERITY_BLOCKER = 'blocker';
    public const SEVERITY_MAJOR = 'major';
    public const SEVERITY_MINOR = 'minor';

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $severity = self::SEVERITY_MAJOR;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $stepsToReproduce = null;

    public function getType(): string
    {
        return 'bug';
    }

    public function getSeverity(): ?string
    {
        return $this->severity;
    }

    public function setSeverity(?string $severity): static
    {
        $this->severity = $severity;

        return $this;
    }

    public function getStepsToReproduce(): ?string
    {
        return $this->stepsToReproduce;
    }

    public function setStepsToReproduce(?string $stepsToReproduce): static
    {
        $this->stepsToReproduce = $stepsToReproduce;

        return $this;
    }
}
