<?php

namespace App\Controller;

use App\Entity\PreviewDeployment;
use App\Repository\PreviewDeploymentRepository;
use App\Repository\ProjectRepository;
use App\Service\PreviewDeploymentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/previews')]
#[IsGranted('ROLE_USER')]
class PreviewDeploymentController extends AbstractController
{
    public function __construct(
        private PreviewDeploymentRepository $previewRepository,
        private ProjectRepository $projectRepository,
        private PreviewDeploymentService $previewService,
    ) {
    }

    #[Route('', name: 'app_previews_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $previews = $this->previewRepository->findByUser($user);

        // Group by project
        $projectPreviews = [];
        foreach ($previews as $preview) {
            $projectId = $preview->getProject()?->getId();
            if ($projectId) {
                $projectPreviews[$projectId][] = $preview;
            }
        }

        // Get projects for dropdown
        $projects = $this->projectRepository->findBy(['owner' => $user]);

        return $this->render('dashboard/previews/index.html.twig', [
            'previews' => $previews,
            'projectPreviews' => $projectPreviews,
            'projects' => $projects,
        ]);
    }

    #[Route('/project/{slug}', name: 'app_previews_project', methods: ['GET'])]
    public function byProject(string $slug): Response
    {
        $project = $this->projectRepository->findOneBy(['slug' => $slug, 'owner' => $this->getUser()]);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        $previews = $this->previewRepository->findByProject($project);
        $stats = $this->previewRepository->getProjectStats($project);

        return $this->render('dashboard/previews/project.html.twig', [
            'project' => $project,
            'previews' => $previews,
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}', name: 'app_preview_show', methods: ['GET'])]
    public function show(PreviewDeployment $preview): Response
    {
        // Check ownership
        if ($preview->getProject()?->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('dashboard/previews/show.html.twig', [
            'preview' => $preview,
        ]);
    }

    #[Route('/{id}/destroy', name: 'app_preview_destroy', methods: ['POST'])]
    public function destroy(Request $request, PreviewDeployment $preview): Response
    {
        // Check ownership
        if ($preview->getProject()?->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('destroy-preview-' . $preview->getId(), $request->request->get('_token'))) {
            $this->previewService->destroy($preview);
            $this->addFlash('success', 'Preview deployment destroyed successfully');
        }

        return $this->redirectToRoute('app_previews_index');
    }

    #[Route('/{id}/rebuild', name: 'app_preview_rebuild', methods: ['POST'])]
    public function rebuild(Request $request, PreviewDeployment $preview): Response
    {
        // Check ownership
        if ($preview->getProject()?->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('rebuild-preview-' . $preview->getId(), $request->request->get('_token'))) {
            // Reset status to pending for rebuild
            $preview->setStatus(PreviewDeployment::STATUS_PENDING);
            $preview->setErrorMessage(null);
            $this->previewRepository->save($preview, true);

            // TODO: Queue the rebuild
            $this->addFlash('success', 'Preview deployment queued for rebuild');
        }

        return $this->redirectToRoute('app_preview_show', ['id' => $preview->getId()]);
    }

    #[Route('/{id}/logs', name: 'app_preview_logs', methods: ['GET'])]
    public function logs(PreviewDeployment $preview): JsonResponse
    {
        // Check ownership
        if ($preview->getProject()?->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->json([
            'status' => $preview->getStatus(),
            'buildLog' => $preview->getBuildLog(),
            'errorMessage' => $preview->getErrorMessage(),
        ]);
    }
}
