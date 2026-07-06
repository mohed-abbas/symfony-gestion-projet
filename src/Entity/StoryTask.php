<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
class StoryTask extends Task
{
    #[ORM\Column(nullable: true)]
    private ?int $storyPoints = null;

    #[Groups(['task:list', 'task:read'])]
    public function getType(): string
    {
        return 'story';
    }

    #[Groups(['task:read', 'task:write'])]
    public function getStoryPoints(): ?int
    {
        return $this->storyPoints;
    }

    public function setStoryPoints(?int $storyPoints): static
    {
        $this->storyPoints = $storyPoints;

        return $this;
    }
}
