<?php

namespace App\Message;

// Ne transporte que l'id du commentaire ; le handler recharge tâche/auteur/destinataire.
final class TaskCommentedMessage
{
    public function __construct(
        public readonly int $commentId,
    ) {
    }
}
