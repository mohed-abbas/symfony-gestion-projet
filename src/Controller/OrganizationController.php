<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Form\OrganizationType;
use App\Repository\OrganizationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

// Conteneur de projets. Création réservée aux chefs de projet (spec §2.2).
#[Route('/organizations')]
#[IsGranted('ROLE_MANAGER')]
final class OrganizationController extends AbstractController
{
    #[Route('', name: 'app_organization_index', methods: ['GET'])]
    public function index(OrganizationRepository $organizations): Response
    {
        return $this->render('organization/index.html.twig', [
            'organizations' => $organizations->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_organization_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger, OrganizationRepository $organizations): Response
    {
        $organization = new Organization();
        $form = $this->createForm(OrganizationType::class, $organization);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $organization->setSlug($this->uniqueSlug($slugger, $organizations, (string) $organization->getName()));

            $em->persist($organization);
            $em->flush();

            $this->addFlash('success', 'Organisation créée.');

            return $this->redirectToRoute('app_organization_index');
        }

        return $this->render('organization/new.html.twig', ['form' => $form]);
    }

    /** Slug dérivé du nom, suffixé si déjà pris (colonne unique). */
    private function uniqueSlug(SluggerInterface $slugger, OrganizationRepository $organizations, string $name): string
    {
        $base = strtolower($slugger->slug($name)->toString()) ?: 'org';
        $slug = $base;
        $i = 2;
        while ($organizations->findOneBy(['slug' => $slug]) !== null) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
