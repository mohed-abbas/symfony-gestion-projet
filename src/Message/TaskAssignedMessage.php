<?php

namespace App\Message;

// Ne transporte que des ids : le message est sérialisé dans le transport Doctrine.
final class TaskAssignedMessage
{
    public function __construct(
        public readonly int $taskId,
        public readonly int $assigneeId,
    ) {
    }
}
