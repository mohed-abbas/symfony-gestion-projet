<?php

namespace App\Service;

use App\Entity\Project;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

// Génère une proposition de tâches à partir d'un projet en interrogeant l'API Groq via HttpClient.
final class AiTaskGenerator
{
    private const VALID_TYPES = ['bug', 'feature', 'story'];
    private const VALID_PRIORITIES = ['low', 'medium', 'high'];

    public function __construct(
        private readonly HttpClientInterface $groqClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(GROQ_API_KEY)%')] private readonly string $apiKey,
        #[Autowire('%env(GROQ_MODEL)%')] private readonly string $model,
        #[Autowire('%env(GROQ_API_BASE_URL)%')] private readonly string $baseUrl,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->apiKey);
    }

    /**
     * @return list<array{title: string, type: string, description: ?string, priority: string}>
     */
    public function suggest(Project $project): array
    {
        if (!$this->isConfigured()) {
            throw new AiUnavailableException("La génération par IA n'est pas configurée (clé API manquante).");
        }

        try {
            // URL absolue construite ici : évite le piège base_uri + slash initial du scoped client.
            $response = $this->groqClient->request('POST', rtrim($this->baseUrl, '/').'/chat/completions', [
                'json' => [
                    'model' => $this->model,
                    'temperature' => 0.4,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => $this->userPrompt($project)],
                    ],
                ],
            ]);

            $status = $response->getStatusCode();
            if (200 !== $status) {
                $this->logger->error('Groq a répondu {status}: {body}', ['status' => $status, 'body' => $response->getContent(false)]);

                throw new AiUnavailableException(sprintf('Le service IA a répondu une erreur (%d). Réessayez plus tard.', $status));
            }

            $payload = $response->toArray();
        } catch (HttpExceptionInterface $e) {
            // Réseau, timeout, DNS, statut via toArray()… : on ne laisse jamais fuiter une 500.
            $this->logger->error('Appel Groq échoué : {message}', ['message' => $e->getMessage()]);

            throw new AiUnavailableException("Le service IA est momentanément indisponible. Réessayez plus tard.");
        }

        $content = $payload['choices'][0]['message']['content'] ?? '';

        return $this->parseSuggestions(\is_string($content) ? $content : '');
    }

    /**
     * Extrait et normalise les suggestions du JSON renvoyé par le modèle. Public pour être testé
     * sans appel réseau (cible du test unitaire de la Phase 10).
     *
     * @return list<array{title: string, type: string, description: ?string, priority: string}>
     */
    public function parseSuggestions(string $jsonContent): array
    {
        $data = json_decode($jsonContent, true);
        if (!\is_array($data)) {
            return [];
        }
        // Le modèle renvoie un objet {"tasks": [...]} ; on tolère aussi un tableau nu.
        $items = $data['tasks'] ?? (array_is_list($data) ? $data : []);
        if (!\is_array($items)) {
            return [];
        }

        $suggestions = [];
        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $title = trim((string) ($item['title'] ?? ''));
            if ('' === $title) {
                continue;
            }

            $type = strtolower((string) ($item['type'] ?? ''));
            $priority = strtolower((string) ($item['priority'] ?? ''));
            $description = isset($item['description']) ? trim((string) $item['description']) : '';

            $suggestions[] = [
                'title' => mb_substr($title, 0, 255),
                'type' => \in_array($type, self::VALID_TYPES, true) ? $type : 'feature',
                'description' => '' !== $description ? $description : null,
                'priority' => \in_array($priority, self::VALID_PRIORITIES, true) ? $priority : 'medium',
            ];
        }

        return $suggestions;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            Tu es un chef de projet agile. À partir de la description d'un projet, propose une
            décomposition en tâches concrètes et actionnables. Réponds UNIQUEMENT en JSON, au format
            {"tasks": [{"title": string, "type": "bug"|"feature"|"story", "description": string,
            "priority": "low"|"medium"|"high"}]}. Rédige les titres et descriptions en français.
            Limite-toi à 8 tâches maximum.
            PROMPT;
    }

    private function userPrompt(Project $project): string
    {
        return sprintf(
            "Projet : %s\nDescription : %s",
            $project->getName(),
            $project->getDescription() ?: '(aucune description fournie)',
        );
    }
}
