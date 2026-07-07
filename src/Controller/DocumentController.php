<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Task;
use App\Entity\User;
use App\Form\DocumentType;
use App\Security\Voter\TaskVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class DocumentController extends AbstractController
{
    public function __construct(
        #[Autowire('%app.documents_dir%')] private readonly string $documentsDir,
    ) {
    }

    #[Route('/tasks/{id}/documents', name: 'app_document_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::VIEW, subject: 'task')]
    public function new(Request $request, Task $task, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(DocumentType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $file */
            $file = $form->get('file')->getData();
            if ($file) {
                /** @var User $user */
                $user = $this->getUser();
                $document = (new Document())
                    ->setTask($task)
                    ->setOwner($user)
                    ->setFilename($file->getClientOriginalName())
                    ->setMimeType($file->getMimeType())
                    ->setSize($file->getSize());

                // Flush first to get the id, then store the bytes under that id (no extra column needed).
                $em->persist($document);
                $em->flush();
                $file->move($this->documentsDir, (string) $document->getId());

                $this->addFlash('success', 'Pièce jointe ajoutée.');
            }
        }

        return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
    }

    #[Route('/documents/{id}/download', name: 'app_document_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(Document $document): Response
    {
        // Reuse the task's VIEW rule: only project members (or admin) can download.
        $this->denyAccessUnlessGranted(TaskVoter::VIEW, $document->getTask());

        $path = $this->documentsDir.'/'.$document->getId();
        if (!is_file($path)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $document->getFilename());
        if ($document->getMimeType()) {
            $response->headers->set('Content-Type', $document->getMimeType());
        }

        return $response;
    }

    #[Route('/documents/{id}/delete', name: 'app_document_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Document $document, EntityManagerInterface $em): Response
    {
        $task = $document->getTask();
        /** @var User $user */
        $user = $this->getUser();
        // Uploader, project lead or admin may remove an attachment.
        $isOwner = $document->getOwner()?->getId() === $user->getId();
        if (!$isOwner && !$this->isGranted(TaskVoter::DELETE, $task)) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete_document_'.$document->getId(), $request->request->getString('_token'))) {
            $path = $this->documentsDir.'/'.$document->getId();
            $em->remove($document);
            $em->flush();
            if (is_file($path)) {
                @unlink($path);
            }
            $this->addFlash('success', 'Pièce jointe supprimée.');
        }

        return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
    }
}
