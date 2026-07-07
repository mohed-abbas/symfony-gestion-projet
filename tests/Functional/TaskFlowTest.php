<?php

namespace App\Tests\Functional;

use App\Entity\BugTask;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Parcours de bout en bout : inscription → connexion automatique → création de tâche.
 * Base isolée via dama/doctrine-test-bundle (transaction rollback en fin de test).
 */
class TaskFlowTest extends WebTestCase
{
    public function testRegisterThenCreateTask(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // 1. Inscription via le formulaire (CSRF désactivé en env de test).
        $crawler = $client->request('GET', '/register');
        $this->assertResponseIsSuccessful();

        $email = 'newbie@taskflow.test';
        $form = $crawler->selectButton("S'inscrire")->form();
        $form['registration_form[email]'] = $email;
        $form['registration_form[plainPassword]'] = 'password';
        $form['registration_form[agreeTerms]']->tick();
        $client->submit($form);

        // 2. Le contrôleur connecte l'utilisateur puis redirige : session authentifiée.
        $this->assertResponseRedirects();
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertInstanceOf(User::class, $user, 'L’utilisateur doit être persisté après inscription.');

        // 3. Prérequis (organisation + projet dont l'utilisateur est LEAD) semés directement :
        //    le test cible la création de tâche, pas la chaîne complète projet/organisation.
        $project = $this->seedProject($em, $user);

        // 4. Création d'une tâche via le formulaire, en tant qu'utilisateur connecté.
        $crawler = $client->request('GET', sprintf('/projects/%d/tasks/new', $project->getId()));
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer la tâche')->form();
        $form['task[title]'] = 'Ma première tâche';
        $form['task[type]']->select('bug');
        $form['task[priority]']->select(BugTask::PRIORITY_MEDIUM);
        $form['task[status]']->select(BugTask::STATUS_TODO);
        $form['task[severity]']->select(BugTask::SEVERITY_MINOR);
        $client->submit($form);
        $this->assertResponseRedirects();

        // 5. La tâche existe en base, rattachée au projet et du bon sous-type STI.
        $task = $em->getRepository(BugTask::class)->findOneBy(['title' => 'Ma première tâche']);
        $this->assertInstanceOf(BugTask::class, $task);
        $this->assertSame($project->getId(), $task->getProject()->getId());
    }

    private function seedProject(EntityManagerInterface $em, User $lead): Project
    {
        $org = (new Organization())->setName('ACME')->setSlug('acme-'.uniqid());

        $project = (new Project())
            ->setName('Site vitrine')
            ->setProjectKey('WEB')
            ->setStatus(Project::STATUS_ACTIVE)
            ->setOrganization($org);

        $membership = (new ProjectMembership())
            ->setUser($lead)
            ->setProject($project)
            ->setInternalRole(ProjectMembership::ROLE_LEAD);
        $project->addMembership($membership);

        $em->persist($org);
        $em->persist($project);
        $em->persist($membership);
        $em->flush();

        return $project;
    }
}
