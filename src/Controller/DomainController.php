<?php

namespace App\Controller;

use App\Entity\Domain;
use App\Entity\User;
use App\Repository\DomainPurchaseRepository;
use App\Repository\DomainRepository;
use App\Repository\ProjectRepository;
use App\Service\DomainService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/projects/{projectId}/domains')]
#[IsGranted('ROLE_USER')]
class DomainController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectRepository $projectRepository,
        private DomainRepository $domainRepository,
        private DomainPurchaseRepository $domainPurchaseRepository,
        private DomainService $domainService
    ) {
    }

    #[Route('', name: 'app_project_domains', methods: ['GET'])]
    public function index(int $projectId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndId($user, $projectId);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        $domains = $this->domainRepository->findByProject($project);

        // Get purchased domains connected to this project
        $connectedPurchases = $this->domainPurchaseRepository->findBy([
            'project' => $project,
            'status' => 'completed',
        ]);

        // Get all user's purchased domains for the "connect" dropdown
        $allPurchases = $this->domainPurchaseRepository->findCompletedByUser($user);

        return $this->render('dashboard/domains/index.html.twig', [
            'project' => $project,
            'domains' => $domains,
            'connectedPurchases' => $connectedPurchases,
            'allPurchases' => $allPurchases,
        ]);
    }

    #[Route('/add', name: 'app_project_domain_add', methods: ['POST'])]
    public function add(Request $request, int $projectId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndId($user, $projectId);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        if (!$this->isCsrfTokenValid('add-domain', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token');
            return $this->redirectToRoute('app_project_domains', ['projectId' => $projectId]);
        }

        $domainName = trim($request->request->get('domain', ''));
        $isPrimary = $request->request->getBoolean('is_primary');

        if (empty($domainName)) {
            $this->addFlash('error', 'Domain name is required');
            return $this->redirectToRoute('app_project_domains', ['projectId' => $projectId]);
        }

        // Validate domain format
        if (!$this->isValidDomain($domainName)) {
            $this->addFlash('error', 'Invalid domain format');
            return $this->redirectToRoute('app_project_domains', ['projectId' => $projectId]);
        }

        // Get DNS provider selection (manual or pushify)
        $dnsProvider = $request->request->get('dns_provider', Domain::DNS_PROVIDER_MANUAL);

        try {
            $domain = $this->domainService->addDomain($project, $domainName, $isPrimary, $dnsProvider);

            if ($dnsProvider === Domain::DNS_PROVIDER_PUSHIFY) {
                $this->addFlash('success', "Domain {$domainName} added successfully with Pushify DNS management");
            } else {
                $this->addFlash('success', "Domain {$domainName} added successfully. Please configure your DNS records.");
            }
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_project_domains', ['projectId' => $projectId]);
    }

    #[Route('/{id}/verify', name: 'app_project_domain_verify', methods: ['POST'])]
    public function verify(Request $request, int $projectId, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndId($user, $projectId);

        if (!$project) {
            return new JsonResponse(['error' => 'Project not found'], 404);
        }

        $domain = $this->domainRepository->find($id);
        if (!$domain || $domain->getProject()->getId() !== $project->getId()) {
            return new JsonResponse(['error' => 'Domain not found'], 404);
        }

        $verified = $this->domainService->verifyDns($domain);

        return new JsonResponse([
            'success' => $verified,
            'status' => $domain->getStatus(),
            'statusLabel' => $domain->getStatusLabel(),
            'statusBadgeClass' => $domain->getStatusBadgeClass(),
            'error' => $domain->getLastError(),
        ]);
    }

    #[Route('/{id}/ssl', name: 'app_project_domain_ssl', methods: ['POST'])]
    public function issueSsl(Request $request, int $projectId, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndId($user, $projectId);

        if (!$project) {
            return new JsonResponse(['error' => 'Project not found'], 404);
        }

        $domain = $this->domainRepository->find($id);
        if (!$domain || $domain->getProject()->getId() !== $project->getId()) {
            return new JsonResponse(['error' => 'Domain not found'], 404);
        }

        if (!$domain->isDnsVerified()) {
            return new JsonResponse(['error' => 'DNS must be verified first'], 400);
        }

        $success = $this->domainService->issueSslCertificate($domain);

        return new JsonResponse([
            'success' => $success,
            'status' => $domain->getStatus(),
            'statusLabel' => $domain->getStatusLabel(),
            'statusBadgeClass' => $domain->getStatusBadgeClass(),
            'sslEnabled' => $domain->isSslEnabled(),
            'error' => $domain->getLastError(),
        ]);
    }

    #[Route('/{id}/primary', name: 'app_project_domain_primary', methods: ['POST'])]
    public function setPrimary(Request $request, int $projectId, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndId($user, $projectId);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        if (!$this->isCsrfTokenValid('set-primary-' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token');
            return $this->redirectToRoute('app_project_domains', ['projectId' => $projectId]);
        }

        $domain = $this->domainRepository->find($id);
        if (!$domain || $domain->getProject()->getId() !== $project->getId()) {
            throw $this->createNotFoundException('Domain not found');
        }

        $this->domainService->setPrimary($domain);
        $this->addFlash('success', "Domain {$domain->getDomain()} set as primary");

        return $this->redirectToRoute('app_project_domains', ['projectId' => $projectId]);
    }

    #[Route('/{id}/delete', name: 'app_project_domain_delete', methods: ['POST'])]
    public function delete(Request $request, int $projectId, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndId($user, $projectId);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        if (!$this->isCsrfTokenValid('delete-domain-' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token');
            return $this->redirectToRoute('app_project_domains', ['projectId' => $projectId]);
        }

        $domain = $this->domainRepository->find($id);
        if (!$domain || $domain->getProject()->getId() !== $project->getId()) {
            throw $this->createNotFoundException('Domain not found');
        }

        $domainName = $domain->getDomain();
        $this->domainService->removeDomain($domain);
        $this->addFlash('success', "Domain {$domainName} removed");

        return $this->redirectToRoute('app_project_domains', ['projectId' => $projectId]);
    }

    #[Route('/{id}/setup-nginx', name: 'app_project_domain_setup_nginx', methods: ['POST'])]
    public function setupNginx(Request $request, int $projectId, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $this->projectRepository->findByOwnerAndId($user, $projectId);

        if (!$project) {
            return new JsonResponse(['error' => 'Project not found'], 404);
        }

        $domain = $this->domainRepository->find($id);
        if (!$domain || $domain->getProject()->getId() !== $project->getId()) {
            return new JsonResponse(['error' => 'Domain not found'], 404);
        }

        $server = $project->getServer();
        if (!$server || !$server->isActive()) {
            return new JsonResponse(['error' => 'No active server assigned to project'], 400);
        }

        try {
            $this->domainService->setupNginxConfig($domain, $server, $domain->isSslEnabled());
            return new JsonResponse(['success' => true, 'message' => 'Nginx configuration updated']);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    private function isValidDomain(string $domain): bool
    {
        // Remove protocol if present
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');

        // Basic domain validation
        return (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain);
    }
}
