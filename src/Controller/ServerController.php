<?php

namespace App\Controller;

use App\Entity\Server;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\ServerRepository;
use App\Service\HetznerService;
use App\Service\ServerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/servers')]
#[IsGranted('ROLE_USER')]
class ServerController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ServerRepository $serverRepository,
        private ProjectRepository $projectRepository,
        private ServerService $serverService,
        private HetznerService $hetznerService
    ) {
    }

    #[Route('', name: 'app_servers', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $servers = $this->serverRepository->findByOwner($user);

        return $this->render('dashboard/servers/index.html.twig', [
            'servers' => $servers,
        ]);
    }

    #[Route('/new', name: 'app_server_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Check if user has active subscription
        if (!$user->canCreateServer()) {
            if ($request->isMethod('POST')) {
                $this->addFlash('error', 'You need an active subscription to create servers.');
                return $this->redirectToRoute('app_billing_checkout_page', [
                    'server_type' => $request->request->get('server_type', 'cx22'),
                ]);
            }

            // Redirect to checkout page with message
            $this->addFlash('warning', 'Subscribe to start creating servers. Choose your server type below.');
            return $this->redirectToRoute('app_billing_checkout_page', [
                'server_type' => 'cx22',
            ]);
        }

        if ($request->isMethod('POST')) {
            $type = $request->request->get('type', 'manual');

            if ($type === 'hetzner') {
                return $this->createHetznerServer($request, $user);
            }

            return $this->createManualServer($request, $user);
        }

        // Get Hetzner options if configured
        $hetznerOptions = [];
        $hetznerConfigured = $this->hetznerService->isConfigured();

        if ($hetznerConfigured) {
            try {
                $hetznerOptions = [
                    'serverTypes' => $this->hetznerService->getServerTypes(),
                    'locations' => $this->hetznerService->getLocations(),
                    'images' => $this->hetznerService->getImages(),
                ];
            } catch (\Exception $e) {
                $this->addFlash('error', 'Failed to load Hetzner options: ' . $e->getMessage());
            }
        }

        // Generate SSH key pair for display
        $keyPair = $this->serverService->generateKeyPair();

        return $this->render('dashboard/servers/new.html.twig', [
            'providers' => Server::getProviderChoices(),
            'hetznerConnected' => $hetznerConfigured,
            'hetznerOptions' => $hetznerOptions,
            'publicKey' => $keyPair['public'],
        ]);
    }

    private function createManualServer(Request $request, User $user): Response
    {
        $server = new Server();
        $server->setOwner($user);
        $server->setName($request->request->get('name'));
        $server->setIpAddress($request->request->get('ip_address'));
        $server->setSshPort((int) $request->request->get('ssh_port', 22));
        $server->setSshUser($request->request->get('ssh_user', 'root'));
        $server->setSshPrivateKey($request->request->get('ssh_private_key'));
        $server->setProvider($request->request->get('provider', Server::PROVIDER_CUSTOM));

        $this->entityManager->persist($server);
        $this->entityManager->flush();

        // Test connection
        $result = $this->serverService->testConnection($server);

        if ($result['success']) {
            // Check Docker
            $this->serverService->checkDocker($server);
            // Get system info
            $this->serverService->getSystemInfo($server);

            $this->addFlash('success', 'Server added and connected successfully!');
        } else {
            $this->addFlash('warning', 'Server added but connection failed: ' . $result['message']);
        }

        return $this->redirectToRoute('app_server_show', ['id' => $server->getId()]);
    }

    private function createHetznerServer(Request $request, User $user): Response
    {
        if (!$this->hetznerService->isConfigured()) {
            $this->addFlash('error', 'Hetzner API is not configured');
            return $this->redirectToRoute('app_server_new');
        }

        try {
            $server = $this->hetznerService->createServer(
                $user,
                $request->request->get('name'),
                $request->request->get('server_type', 'cx22'),
                $request->request->get('location', 'nbg1'),
                $request->request->get('image', 'ubuntu-22.04')
            );

            $this->addFlash('success', 'Hetzner server is being created. It may take a few minutes to be ready.');
            return $this->redirectToRoute('app_server_show', ['id' => $server->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to create server: ' . $e->getMessage());
            return $this->redirectToRoute('app_server_new');
        }
    }

    #[Route('/{id}', name: 'app_server_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $server = $this->serverRepository->findByOwnerAndId($user, $id);

        if (!$server) {
            throw $this->createNotFoundException('Server not found');
        }

        // Auto-sync status for pending Hetzner servers
        if ($server->getStatus() === Server::STATUS_PENDING &&
            $server->getProvider() === Server::PROVIDER_HETZNER) {
            $this->syncHetznerStatus($server);
        }

        return $this->render('dashboard/servers/show.html.twig', [
            'server' => $server,
        ]);
    }

    private function syncHetznerStatus(Server $server): void
    {
        try {
            $status = $this->hetznerService->getServerStatus($server);

            // If Hetzner says it's running, try SSH connection
            if ($status['status'] === 'running') {
                $result = $this->serverService->testConnection($server);
                if ($result['success']) {
                    $this->serverService->checkDocker($server);
                    $this->serverService->getSystemInfo($server);
                } else {
                    // If server was created recently (less than 5 minutes ago), clear the error
                    // SSH might not be ready yet - this is normal
                    $createdAt = $server->getCreatedAt();
                    $now = new \DateTime();
                    $minutesAgo = ($now->getTimestamp() - $createdAt->getTimestamp()) / 60;

                    if ($minutesAgo < 5) {
                        $server->setLastError(null);
                        $server->setStatus(Server::STATUS_PENDING);
                        $this->entityManager->flush();
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail - user can manually test
        }
    }

    #[Route('/{id}/status', name: 'app_server_status', methods: ['GET'])]
    public function status(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $server = $this->serverRepository->findByOwnerAndId($user, $id);

        if (!$server) {
            return $this->json(['error' => 'Server not found'], 404);
        }

        // Sync with Hetzner if pending
        if ($server->getStatus() === Server::STATUS_PENDING &&
            $server->getProvider() === Server::PROVIDER_HETZNER) {
            $this->syncHetznerStatus($server);
        }

        return $this->json([
            'status' => $server->getStatus(),
            'statusBadgeClass' => $server->getStatusBadgeClass(),
            'dockerInstalled' => $server->isDockerInstalled(),
            'dockerVersion' => $server->getDockerVersion(),
            'cpuCores' => $server->getCpuCores(),
            'memoryGb' => $server->getMemoryGb(),
            'diskGb' => $server->getDiskGb(),
            'lastError' => $server->getLastError(),
        ]);
    }

    #[Route('/{id}/test', name: 'app_server_test', methods: ['POST'])]
    public function testConnection(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $server = $this->serverRepository->findByOwnerAndId($user, $id);

        if (!$server) {
            return $this->json(['error' => 'Server not found'], 404);
        }

        $result = $this->serverService->testConnection($server);

        if ($result['success']) {
            $this->serverService->checkDocker($server);
            $this->serverService->getSystemInfo($server);
        }

        return $this->json($result);
    }

    #[Route('/{id}/docker/check', name: 'app_server_docker_check', methods: ['GET'])]
    public function checkDocker(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $server = $this->serverRepository->findByOwnerAndId($user, $id);

        if (!$server) {
            return $this->json(['error' => 'Server not found'], 404);
        }

        $result = $this->serverService->checkDocker($server);
        return $this->json($result);
    }

    #[Route('/{id}/docker/install', name: 'app_server_docker_install', methods: ['POST'])]
    public function installDocker(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $server = $this->serverRepository->findByOwnerAndId($user, $id);

        if (!$server) {
            return $this->json(['error' => 'Server not found'], 404);
        }

        $result = $this->serverService->installDocker($server);
        return $this->json($result);
    }

    #[Route('/{id}/delete', name: 'app_server_delete', methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $server = $this->serverRepository->findByOwnerAndId($user, $id);

        if (!$server) {
            throw $this->createNotFoundException('Server not found');
        }

        if (!$this->isCsrfTokenValid('delete-server-' . $server->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token');
            return $this->redirectToRoute('app_servers');
        }

        // Remove server reference from all projects using this server
        $projects = $this->projectRepository->findBy(['server' => $server]);
        foreach ($projects as $project) {
            $project->setServer(null);
            $project->setContainerPort(null);
            $project->setContainerId(null);
        }
        $this->entityManager->flush();

        // If Hetzner server, also delete from Hetzner
        if ($server->getProvider() === Server::PROVIDER_HETZNER && $server->getProviderId()) {
            try {
                $this->hetznerService->deleteServer($server);
                $this->addFlash('success', 'Server deleted from Hetzner and Pushify');
                return $this->redirectToRoute('app_servers');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Failed to delete from Hetzner: ' . $e->getMessage());
            }
        }

        $this->entityManager->remove($server);
        $this->entityManager->flush();

        $this->addFlash('success', 'Server deleted successfully');
        return $this->redirectToRoute('app_servers');
    }

    #[Route('/{id}/power/{action}', name: 'app_server_power', methods: ['POST'])]
    public function power(Request $request, int $id, string $action): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $server = $this->serverRepository->findByOwnerAndId($user, $id);

        if (!$server) {
            return $this->json(['error' => 'Server not found'], 404);
        }

        if ($server->getProvider() !== Server::PROVIDER_HETZNER) {
            return $this->json(['error' => 'Power actions only available for Hetzner servers'], 400);
        }

        try {
            $this->hetznerService->powerAction($server, $action);
            return $this->json(['success' => true, 'message' => ucfirst($action) . ' command sent']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
