<?php

namespace App\Controller\Api\V1;

use App\Entity\BugTask;
use App\Entity\FeatureTask;
use App\Entity\Project;
use App\Entity\StoryTask;
use App\Entity\Task;
use App\Message\TaskAssignedMessage;
use App\Repository\TaskRepository;
use App\Security\Voter\ProjectVoter;
use App\Security\Voter\TaskVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// API JSON dédiée (Serializer + groupes de normalisation). Session-based auth (firewall principal).
#[Route('/api/v1')]
#[IsGranted('ROLE_USER')]
final class TaskApiController extends AbstractController
{
    // Discriminateur STI → classe concrète à instancier lors d'un POST.
    private const TYPE_MAP = [
        'bug' => BugTask::class,
        'feature' => FeatureTask::class,
        'story' => StoryTask::class,
    ];

    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/projects/{id}/tasks', name: 'api_v1_project_tasks', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ProjectVoter::VIEW, subject: 'project')]
    public function listByProject(Project $project, TaskRepository $tasks): JsonResponse
    {
        $data = $tasks->findBy(['project' => $project], ['createdAt' => 'DESC']);

        return $this->serialized($data, 'task:list');
    }

    #[Route('/tasks/{id}', name: 'api_v1_task_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(TaskVoter::VIEW, subject: 'task')]
    public function show(Task $task): JsonResponse
    {
        return $this->serialized($task, 'task:read');
    }

    #[Route('/projects/{id}/tasks', name: 'api_v1_task_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ProjectVoter::VIEW, subject: 'project')]
    public function create(Request $request, Project $project, ValidatorInterface $validator, MessageBusInterface $bus): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->error('Corps JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        $class = self::TYPE_MAP[$payload['type'] ?? ''] ?? null;
        if (null === $class) {
            return $this->error('Champ "type" requis : bug, feature ou story.', Response::HTTP_BAD_REQUEST);
        }

        try {
            /** @var Task $task */
            $task = $this->serializer->deserialize($request->getContent(), $class, 'json', [
                'groups' => ['task:write'],
                AbstractNormalizer::IGNORED_ATTRIBUTES => ['type'],
            ]);
        } catch (NotEncodableValueException) {
            return $this->error('Corps JSON invalide.', Response::HTTP_BAD_REQUEST);
        }

        $task->setProject($project)->setAuthor($this->getUser());

        $violations = $validator->validate($task);
        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = ['field' => $violation->getPropertyPath(), 'message' => $violation->getMessage()];
            }

            return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($task);
        $this->em->flush();

        if (null !== $task->getAssignee()) {
            $bus->dispatch(new TaskAssignedMessage($task->getId(), $task->getAssignee()->getId()));
        }

        return $this->serialized($task, 'task:read', Response::HTTP_CREATED);
    }

    private function serialized(mixed $data, string $group, int $status = Response::HTTP_OK): JsonResponse
    {
        $json = $this->serializer->serialize($data, 'json', ['groups' => [$group]]);

        return new JsonResponse($json, $status, [], true);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return $this->json(['error' => $message], $status);
    }
}
