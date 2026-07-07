<?php

namespace App\Service;

// Levée quand l'appel à l'IA échoue (non configurée, réseau, statut HTTP, réponse illisible).
// Porte un message affichable tel quel à l'utilisateur.
final class AiUnavailableException extends \RuntimeException
{
}
