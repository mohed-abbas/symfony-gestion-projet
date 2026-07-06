<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
class FeatureTask extends Task
{
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $businessValue = null;

    #[Groups(['task:list', 'task:read'])]
    public function getType(): string
    {
        return 'feature';
    }

    #[Groups(['task:read', 'task:write'])]
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
