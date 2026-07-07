<?php

namespace App\Message;

// Ne transporte que des ids : le message est sérialisé dans le transport Doctrine.
final class ProjectMemberAddedMessage
{
    public function __construct(
        public readonly int $projectId,
        public readonly int $memberId,
    ) {
    }
}
