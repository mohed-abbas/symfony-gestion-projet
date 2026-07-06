<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FeatureTask extends Task
{
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $businessValue = null;

    public function getType(): string
    {
        return 'feature';
    }

    public function getBusinessValue(): ?string
    {
        return $this->businessValue;
    }

    public function setBusinessValue(?string $businessValue): static
    {
        $this->businessValue = $businessValue;

        return $this;
    }
}
