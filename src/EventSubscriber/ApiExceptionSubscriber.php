<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

// Rend les erreurs des routes /api/ en JSON plutôt que la page d'erreur HTML Twig.
class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function onExceptionEvent(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

        // Message générique par statut : ne pas divulguer les détails internes de l'exception.
        $messages = [
            401 => 'Authentification requise.',
            403 => 'Accès refusé.',
            404 => 'Ressource introuvable.',
            405 => 'Méthode non autorisée.',
        ];

        $event->setResponse(new JsonResponse([
            'error' => $messages[$status] ?? 'Erreur serveur.',
            'status' => $status,
        ], $status));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ExceptionEvent::class => 'onExceptionEvent',
        ];
    }
}
