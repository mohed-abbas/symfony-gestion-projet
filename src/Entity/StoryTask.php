<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class StoryTask extends Task
{
    #[ORM\Column(nullable: true)]
    private ?int $storyPoints = null;

    public function getType(): string
    {
        return 'story';
    }

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
