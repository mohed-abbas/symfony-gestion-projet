<?php

namespace App\Tests\Functional;

use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Gestion des membres (spec §2.2). Le chef de projet invite un utilisateur existant ;
 * un membre non-chef n'a pas accès à l'écran de gestion.
 */
class ProjectMemberTest extends WebTestCase
{
    public function testLeadCanInviteExistingUser(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $lead = $this->makeUser($em, 'lead');
        $target = $this->makeUser($em, 'target');
        $project = $this->makeProject($em, $lead);

        $client->loginUser($lead);
        $crawler = $client->request('GET', sprintf('/projects/%d/members', $project->getId()));
        $this->assertResponseIsSuccessful();

        // Le formulaire d'ajout porte le jeton CSRF stateful : on le soumet via le crawler.
        $form = $crawler->filter('form[action$="/members/add"]')->form();
        $form['email'] = $target->getEmail();
        $form['role'] = ProjectMembership::ROLE_CONTRIBUTOR;
        $client->submit($form);
        $this->assertResponseRedirects();

        $em->clear();
        $reloaded = $em->getRepository(Project::class)->find($project->getId());
        $emails = array_map(static fn (User $u) => $u->getEmail(), $reloaded->getMembers());
        $this->assertContains($target->getEmail(), $emails, 'L’invité doit être membre du projet.');
    }

    public function testNonLeadCannotAccessMemberManagement(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $lead = $this->makeUser($em, 'lead');
        $contributor = $this->makeUser($em, 'contrib');
        $project = $this->makeProject($em, $lead);

        // Le contributeur est membre mais pas chef → ProjectVoter::EDIT refuse.
        $membership = (new ProjectMembership())
            ->setUser($contributor)
            ->setProject($project)
            ->setInternalRole(ProjectMembership::ROLE_CONTRIBUTOR);
        $project->addMembership($membership);
        $em->persist($membership);
        $em->flush();

        $client->loginUser($contributor);
        $client->request('GET', sprintf('/projects/%d/members', $project->getId()));
        $this->assertResponseStatusCodeSame(403);
    }

    private function makeUser(EntityManagerInterface $em, string $tag): User
    {
        $user = (new User())
            ->setEmail(sprintf('%s-%s@taskflow.test', $tag, uniqid()))
            ->setRoles(['ROLE_USER']);
        $user->setPassword('x');
        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function makeProject(EntityManagerInterface $em, User $lead): Project
    {
        $org = (new Organization())->setName('ACME')->setSlug('acme-'.uniqid());
        $project = (new Project())
            ->setName('Projet test')
            ->setProjectKey('TST')
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
