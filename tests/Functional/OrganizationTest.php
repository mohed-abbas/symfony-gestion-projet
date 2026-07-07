<?php

namespace App\Tests\Functional;

use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Création d'organisation et garde de rôle (spec §2.2 : réservé aux chefs de projet).
 */
class OrganizationTest extends WebTestCase
{
    public function testManagerCanCreateOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $client->loginUser($this->makeUser($em, ['ROLE_MANAGER']));

        $crawler = $client->request('GET', '/organizations/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton("Créer l'organisation")->form();
        $form['organization[name]'] = 'QA Corp';
        $client->submit($form);
        $this->assertResponseRedirects('/organizations');

        $org = $em->getRepository(Organization::class)->findOneBy(['name' => 'QA Corp']);
        $this->assertInstanceOf(Organization::class, $org);
        $this->assertNotEmpty($org->getSlug(), 'Le slug doit être généré à partir du nom.');
    }

    public function testPlainMemberCannotCreateProjectOrOrganization(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $client->loginUser($this->makeUser($em, ['ROLE_USER']));

        $client->request('GET', '/projects/new');
        $this->assertResponseStatusCodeSame(403);

        $client->request('GET', '/organizations/new');
        $this->assertResponseStatusCodeSame(403);
    }

    /** @param string[] $roles */
    private function makeUser(EntityManagerInterface $em, array $roles): User
    {
        $user = (new User())
            ->setEmail('org-'.uniqid().'@taskflow.test')
            ->setRoles($roles);
        $user->setPassword('x');
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
