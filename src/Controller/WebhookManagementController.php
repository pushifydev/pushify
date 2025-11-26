<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Webhook;
use App\Repository\ProjectRepository;
use App\Repository\WebhookRepository;
use App\Service\WebhookService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/webhooks')]
#[IsGranted('ROLE_USER')]
class WebhookManagementController extends AbstractController
{
    public function __construct(
        private WebhookRepository $webhookRepository,
        private ProjectRepository $projectRepository,
        private WebhookService $webhookService,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'app_webhooks_manage')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $webhooks = $this->webhookRepository->findByUser($user);
        $projects = $this->projectRepository->findByOwner($user);

        return $this->render('dashboard/webhooks/index.html.twig', [
            'webhooks' => $webhooks,
            'projects' => $projects,
            'events' => Webhook::getAllEvents(),
        ]);
    }

    #[Route('/new', name: 'app_webhook_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $projects = $this->projectRepository->findByOwner($user);

        if ($request->isMethod('POST')) {
            $webhook = new Webhook();
            $webhook->setUser($user);
            $webhook->setName($request->request->get('name'));
            $webhook->setUrl($request->request->get('url'));
            $webhook->setPreset($request->request->get('preset', Webhook::PRESET_CUSTOM));
            $webhook->setEvents($request->request->all('events') ?: []);
            $webhook->setSecret($request->request->get('secret') ?: null);

            // Handle project selection
            $projectId = $request->request->get('project_id');
            if ($projectId) {
                $project = $this->projectRepository->find($projectId);
                if ($project && $project->getOwner()->getId() === $user->getId()) {
                    $webhook->setProject($project);
                }
            }

            // Validate URL
            if (!filter_var($webhook->getUrl(), FILTER_VALIDATE_URL)) {
                $this->addFlash('error', 'Invalid webhook URL');
                return $this->render('dashboard/webhooks/new.html.twig', [
                    'projects' => $projects,
                    'events' => Webhook::getAllEvents(),
                    'webhook' => $webhook,
                ]);
            }

            $this->entityManager->persist($webhook);
            $this->entityManager->flush();

            $this->addFlash('success', 'Webhook created successfully');
            return $this->redirectToRoute('app_webhooks_manage');
        }

        return $this->render('dashboard/webhooks/new.html.twig', [
            'projects' => $projects,
            'events' => Webhook::getAllEvents(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_webhook_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $webhook = $this->webhookRepository->find($id);
        if (!$webhook || $webhook->getUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Webhook not found');
        }

        $projects = $this->projectRepository->findByOwner($user);

        if ($request->isMethod('POST')) {
            $webhook->setName($request->request->get('name'));
            $webhook->setUrl($request->request->get('url'));
            $webhook->setPreset($request->request->get('preset', Webhook::PRESET_CUSTOM));
            $webhook->setEvents($request->request->all('events') ?: []);
            $webhook->setSecret($request->request->get('secret') ?: null);
            $webhook->setIsActive($request->request->getBoolean('is_active', true));

            // Handle project selection
            $projectId = $request->request->get('project_id');
            if ($projectId) {
                $project = $this->projectRepository->find($projectId);
                if ($project && $project->getOwner()->getId() === $user->getId()) {
                    $webhook->setProject($project);
                }
            } else {
                $webhook->setProject(null);
            }

            // Validate URL
            if (!filter_var($webhook->getUrl(), FILTER_VALIDATE_URL)) {
                $this->addFlash('error', 'Invalid webhook URL');
                return $this->render('dashboard/webhooks/edit.html.twig', [
                    'webhook' => $webhook,
                    'projects' => $projects,
                    'events' => Webhook::getAllEvents(),
                ]);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Webhook updated successfully');
            return $this->redirectToRoute('app_webhooks_manage');
        }

        return $this->render('dashboard/webhooks/edit.html.twig', [
            'webhook' => $webhook,
            'projects' => $projects,
            'events' => Webhook::getAllEvents(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_webhook_delete', methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $webhook = $this->webhookRepository->find($id);
        if (!$webhook || $webhook->getUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Webhook not found');
        }

        if (!$this->isCsrfTokenValid('delete-webhook-' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token');
            return $this->redirectToRoute('app_webhooks_manage');
        }

        $this->entityManager->remove($webhook);
        $this->entityManager->flush();

        $this->addFlash('success', 'Webhook deleted successfully');
        return $this->redirectToRoute('app_webhooks_manage');
    }

    #[Route('/{id}/toggle', name: 'app_webhook_toggle', methods: ['POST'])]
    public function toggle(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $webhook = $this->webhookRepository->find($id);
        if (!$webhook || $webhook->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Webhook not found'], 404);
        }

        $webhook->setIsActive(!$webhook->isActive());
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'isActive' => $webhook->isActive(),
            'statusLabel' => $webhook->getStatusLabel(),
            'statusBadgeClass' => $webhook->getStatusBadgeClass(),
        ]);
    }

    #[Route('/{id}/test', name: 'app_webhook_test', methods: ['POST'])]
    public function test(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $webhook = $this->webhookRepository->find($id);
        if (!$webhook || $webhook->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Webhook not found'], 404);
        }

        $result = $this->webhookService->test($webhook);

        return $this->json($result);
    }
}
