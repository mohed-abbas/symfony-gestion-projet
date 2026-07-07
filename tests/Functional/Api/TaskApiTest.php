<?php

namespace App\Tests\Functional\Api;

use App\Entity\BugTask;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * API JSON dédiée (Phase 7). Le firewall de session protège /api : un anonyme est
 * redirigé vers /login, un utilisateur membre du projet reçoit la liste en JSON.
 */
class TaskApiTest extends WebTestCase
{
    public function testAnonymousIsBlocked(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/projects/1/tasks');

        // Pas de session → l'entry point form_login redirige vers /login (302), aucune donnée.
        $this->assertResponseRedirects();
    }

    public function testMemberGetsProjectTasksAsJson(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        [$user, $project] = $this->seedUserProjectAndTask($em);

        $client->loginUser($user);
        $client->request('GET', sprintf('/api/v1/projects/%d/tasks', $project->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame('Corriger le login', $data[0]['title']);
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function seedUserProjectAndTask(EntityManagerInterface $em): array
    {
        $user = (new User())
            ->setEmail('api-user-'.uniqid().'@taskflow.test')
            ->setRoles(['ROLE_USER']);
        $user->setPassword('x'); // non utilisé : loginUser() authentifie l'objet directement

        $org = (new Organization())->setName('ACME')->setSlug('acme-'.uniqid());

        $project = (new Project())
            ->setName('API Project')
            ->setProjectKey('API')
            ->setStatus(Project::STATUS_ACTIVE)
            ->setOrganization($org);

        $membership = (new ProjectMembership())
            ->setUser($user)
            ->setProject($project)
            ->setInternalRole(ProjectMembership::ROLE_LEAD);
        $project->addMembership($membership);

        $task = (new BugTask())
            ->setTitle('Corriger le login')
            ->setStatus(Task::STATUS_TODO)
            ->setPriority(Task::PRIORITY_HIGH);
        $task->setProject($project);
        $task->setAuthor($user); // author_id NOT NULL
        $task->setSeverity(BugTask::SEVERITY_MAJOR);

        foreach ([$user, $org, $project, $membership, $task] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        return [$user, $project];
    }
}
