<?php

namespace App\Controller;

use App\Entity\Deployment;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\DeploymentRepository;
use App\Repository\ProjectRepository;
use App\Service\DeploymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard')]
#[IsGranted('ROLE_USER')]
class DeploymentController extends AbstractController
{
    public function __construct(
        private DeploymentRepository $deploymentRepository,
        private ProjectRepository $projectRepository,
        private DeploymentService $deploymentService
    ) {
    }

    #[Route('/deployments', name: 'app_deployments')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get all projects for user
        $projects = $this->projectRepository->findByOwner($user);

        // Get recent deployments across all projects
        $deployments = [];
        foreach ($projects as $project) {
            $projectDeployments = $this->deploymentRepository->findByProject($project, 5);
            $deployments = array_merge($deployments, $projectDeployments);
        }

        // Sort by created date
        usort($deployments, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
        $deployments = array_slice($deployments, 0, 20);

        return $this->render('dashboard/deployments/index.html.twig', [
            'deployments' => $deployments,
        ]);
    }

    #[Route('/projects/{slug}/deploy', name: 'app_project_deploy', methods: ['POST'])]
    public function deploy(Request $request, string $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndSlug($user, $slug);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        if (!$this->isCsrfTokenValid('deploy-' . $project->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token');
            return $this->redirectToRoute('app_project_show', ['slug' => $slug]);
        }

        $deployment = $this->deploymentService->createDeployment(
            $project,
            $user,
            Deployment::TRIGGER_MANUAL
        );

        $this->addFlash('success', 'Deployment started!');

        return $this->redirectToRoute('app_deployment_show', [
            'slug' => $slug,
            'id' => $deployment->getId(),
        ]);
    }

    #[Route('/projects/{slug}/deployments', name: 'app_project_deployments')]
    public function projectDeployments(string $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndSlug($user, $slug);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        $deployments = $this->deploymentRepository->findByProject($project, 50);

        return $this->render('dashboard/deployments/project.html.twig', [
            'project' => $project,
            'deployments' => $deployments,
        ]);
    }

    #[Route('/projects/{slug}/deployments/{id}', name: 'app_deployment_show')]
    public function show(string $slug, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndSlug($user, $slug);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        $deployment = $this->deploymentRepository->find($id);

        if (!$deployment || $deployment->getProject()->getId() !== $project->getId()) {
            throw $this->createNotFoundException('Deployment not found');
        }

        return $this->render('dashboard/deployments/show.html.twig', [
            'project' => $project,
            'deployment' => $deployment,
        ]);
    }

    #[Route('/projects/{slug}/deployments/{id}/logs', name: 'app_deployment_logs')]
    public function logs(string $slug, int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndSlug($user, $slug);

        if (!$project) {
            return $this->json(['error' => 'Project not found'], 404);
        }

        // Clear entity manager to get fresh data from database
        $entityManager->clear();

        $deployment = $this->deploymentRepository->find($id);

        if (!$deployment || $deployment->getProject()->getId() !== $project->getId()) {
            return $this->json(['error' => 'Deployment not found'], 404);
        }

        return $this->json([
            'status' => $deployment->getStatus(),
            'buildLogs' => $deployment->getBuildLogs(),
            'deployLogs' => $deployment->getDeployLogs(),
            'errorMessage' => $deployment->getErrorMessage(),
            'isFinished' => $deployment->isFinished(),
            'buildDuration' => $deployment->getBuildDuration(),
            'deployDuration' => $deployment->getDeployDuration(),
            'deploymentUrl' => $deployment->getDeploymentUrl(),
        ]);
    }

    #[Route('/projects/{slug}/deployments/{id}/cancel', name: 'app_deployment_cancel', methods: ['POST'])]
    public function cancel(Request $request, string $slug, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndSlug($user, $slug);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        $deployment = $this->deploymentRepository->find($id);

        if (!$deployment || $deployment->getProject()->getId() !== $project->getId()) {
            throw $this->createNotFoundException('Deployment not found');
        }

        if ($deployment->isRunning()) {
            $deployment->setStatus(Deployment::STATUS_CANCELLED);
            // Note: Actually cancelling the process would require more complex handling
        }

        $this->addFlash('info', 'Deployment cancelled');

        return $this->redirectToRoute('app_project_deployments', ['slug' => $slug]);
    }

    #[Route('/projects/{slug}/deployments/{id}/rollback', name: 'app_deployment_rollback', methods: ['POST'])]
    public function rollback(Request $request, string $slug, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndSlug($user, $slug);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        if (!$this->isCsrfTokenValid('rollback-' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token');
            return $this->redirectToRoute('app_project_deployments', ['slug' => $slug]);
        }

        $targetDeployment = $this->deploymentRepository->find($id);

        if (!$targetDeployment || $targetDeployment->getProject()->getId() !== $project->getId()) {
            throw $this->createNotFoundException('Deployment not found');
        }

        if (!$targetDeployment->isSuccess()) {
            $this->addFlash('error', 'Can only rollback to successful deployments');
            return $this->redirectToRoute('app_project_deployments', ['slug' => $slug]);
        }

        if (!$targetDeployment->getDockerImage() || !$targetDeployment->getDockerTag()) {
            $this->addFlash('error', 'Deployment does not have Docker image information for rollback');
            return $this->redirectToRoute('app_project_deployments', ['slug' => $slug]);
        }

        // Create rollback deployment
        $deployment = $this->deploymentService->createRollbackDeployment(
            $project,
            $user,
            $targetDeployment
        );

        $this->addFlash('success', 'Rollback started to deployment #' . $targetDeployment->getId());

        return $this->redirectToRoute('app_deployment_show', [
            'slug' => $slug,
            'id' => $deployment->getId(),
        ]);
    }

    #[Route('/projects/{slug}/deployments/{id}/redeploy', name: 'app_deployment_redeploy', methods: ['POST'])]
    public function redeploy(Request $request, string $slug, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndSlug($user, $slug);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        if (!$this->isCsrfTokenValid('redeploy-' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token');
            return $this->redirectToRoute('app_project_deployments', ['slug' => $slug]);
        }

        $targetDeployment = $this->deploymentRepository->find($id);

        if (!$targetDeployment || $targetDeployment->getProject()->getId() !== $project->getId()) {
            throw $this->createNotFoundException('Deployment not found');
        }

        // Create redeploy with same commit
        $deployment = $this->deploymentService->createDeployment(
            $project,
            $user,
            Deployment::TRIGGER_REDEPLOY,
            $targetDeployment->getCommitHash(),
            $targetDeployment->getCommitMessage()
        );

        $this->addFlash('success', 'Redeploy started!');

        return $this->redirectToRoute('app_deployment_show', [
            'slug' => $slug,
            'id' => $deployment->getId(),
        ]);
    }
}
