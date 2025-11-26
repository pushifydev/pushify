<?php

namespace App\Controller;

use App\Entity\DomainPurchase;
use App\Entity\User;
use App\Repository\DomainPurchaseRepository;
use App\Repository\ProjectRepository;
use App\Service\NamecheapService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/domains/marketplace')]
#[IsGranted('ROLE_USER')]
class DomainMarketplaceController extends AbstractController
{
    public function __construct(
        private NamecheapService $namecheapService,
        private DomainPurchaseRepository $domainPurchaseRepository,
        private ProjectRepository $projectRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'app_domain_marketplace')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $purchases = $this->domainPurchaseRepository->findByUser($user);
        $isConfigured = $this->namecheapService->isConfigured();

        return $this->render('dashboard/domains/marketplace/index.html.twig', [
            'purchases' => $purchases,
            'isConfigured' => $isConfigured,
        ]);
    }

    #[Route('/search', name: 'app_domain_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $keyword = $request->query->get('q', '');
        $keyword = preg_replace('/[^a-zA-Z0-9-]/', '', $keyword);

        if (strlen($keyword) < 2) {
            return $this->json(['error' => 'Search term too short'], 400);
        }

        // Demo mode when API is not configured
        if (!$this->namecheapService->isConfigured()) {
            return $this->json([
                'results' => $this->getDemoResults($keyword),
                'keyword' => $keyword,
                'demo' => true,
            ]);
        }

        try {
            $results = $this->namecheapService->getDomainSuggestions($keyword);

            // Get pricing for available domains
            foreach ($results as &$result) {
                if ($result['available']) {
                    $tld = $this->extractTld($result['domain']);
                    $pricing = $this->namecheapService->getTldPricing($tld);
                    $result['pricing'] = $pricing;
                }
            }

            return $this->json([
                'results' => $results,
                'keyword' => $keyword,
            ]);
        } catch (\Exception $e) {
            // On API error/timeout, fall back to demo mode
            return $this->json([
                'results' => $this->getDemoResults($keyword),
                'keyword' => $keyword,
                'demo' => true,
                'apiError' => $e->getMessage(),
            ]);
        }
    }

    private function getDemoResults(string $keyword): array
    {
        $tlds = ['com', 'net', 'org', 'io', 'co', 'dev', 'app'];
        $results = [];

        foreach ($tlds as $tld) {
            $domain = $keyword . '.' . $tld;
            $available = rand(0, 1) === 1; // Random availability for demo
            $results[] = [
                'domain' => $domain,
                'available' => $available,
                'premium' => false,
                'pricing' => $available ? [['price' => $this->getDemoPrice($tld), 'currency' => 'USD']] : null,
            ];
        }

        return $results;
    }

    private function getDemoPrice(string $tld): string
    {
        return match ($tld) {
            'com' => '12.99',
            'net' => '14.99',
            'org' => '13.99',
            'io' => '39.99',
            'co' => '29.99',
            'dev' => '15.99',
            'app' => '18.99',
            default => '19.99',
        };
    }

    #[Route('/check', name: 'app_domain_check', methods: ['GET'])]
    public function checkDomain(Request $request): JsonResponse
    {
        if (!$this->namecheapService->isConfigured()) {
            return $this->json(['error' => 'Domain service not configured'], 503);
        }

        $domain = $request->query->get('domain', '');
        $domain = strtolower(trim($domain));

        if (!$this->isValidDomain($domain)) {
            return $this->json(['error' => 'Invalid domain format'], 400);
        }

        try {
            $results = $this->namecheapService->checkDomainAvailability($domain);
            $result = $results[0] ?? null;

            if ($result && $result['available']) {
                $tld = $this->extractTld($domain);
                $pricing = $this->namecheapService->getTldPricing($tld);
                $result['pricing'] = $pricing;
            }

            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/pricing', name: 'app_domain_pricing', methods: ['GET'])]
    public function getPricing(): JsonResponse
    {
        if (!$this->namecheapService->isConfigured()) {
            return $this->json(['error' => 'Domain service not configured'], 503);
        }

        try {
            $pricing = $this->namecheapService->getPricing();

            // Filter to popular TLDs
            $popularTlds = ['com', 'net', 'org', 'io', 'co', 'dev', 'app', 'xyz', 'tech', 'online', 'site', 'store'];
            $filteredPricing = [];

            foreach ($popularTlds as $tld) {
                if (isset($pricing[$tld])) {
                    $filteredPricing[$tld] = $pricing[$tld];
                }
            }

            return $this->json(['pricing' => $filteredPricing]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/purchase', name: 'app_domain_purchase', methods: ['POST'])]
    public function purchase(Request $request): Response
    {
        if (!$this->namecheapService->isConfigured()) {
            $this->addFlash('error', 'Domain service not configured');
            return $this->redirectToRoute('app_domain_marketplace');
        }

        /** @var User $user */
        $user = $this->getUser();

        $domain = strtolower(trim($request->request->get('domain', '')));
        $years = (int) $request->request->get('years', 1);

        if (!$this->isValidDomain($domain)) {
            $this->addFlash('error', 'Invalid domain format');
            return $this->redirectToRoute('app_domain_marketplace');
        }

        // Check if user already owns this domain
        if ($this->domainPurchaseRepository->isDomainOwnedByUser($user, $domain)) {
            $this->addFlash('error', 'You already own this domain');
            return $this->redirectToRoute('app_domain_marketplace');
        }

        // Build registrant info from form
        $registrantInfo = [
            'firstName' => $request->request->get('firstName'),
            'lastName' => $request->request->get('lastName'),
            'address1' => $request->request->get('address1'),
            'city' => $request->request->get('city'),
            'state' => $request->request->get('state'),
            'postalCode' => $request->request->get('postalCode'),
            'country' => $request->request->get('country'),
            'phone' => $request->request->get('phone'),
            'email' => $request->request->get('email') ?: $user->getEmail(),
        ];

        // Validate registrant info
        $requiredFields = ['firstName', 'lastName', 'address1', 'city', 'state', 'postalCode', 'country', 'phone', 'email'];
        foreach ($requiredFields as $field) {
            if (empty($registrantInfo[$field])) {
                $this->addFlash('error', 'Please fill in all required fields');
                return $this->redirectToRoute('app_domain_marketplace');
            }
        }

        // Create purchase record
        $purchase = new DomainPurchase();
        $purchase->setUser($user);
        $purchase->setDomain($domain);
        $purchase->setYears($years);
        $purchase->setStatus(DomainPurchase::STATUS_PROCESSING);
        $purchase->setRegistrantInfo($registrantInfo);

        // Get pricing
        try {
            $tld = $this->extractTld($domain);
            $pricing = $this->namecheapService->getTldPricing($tld);
            $yearlyPrice = $pricing[0]['price'] ?? 0;
            $purchase->setPrice((string) ($yearlyPrice * $years));
            $purchase->setCurrency($pricing[0]['currency'] ?? 'USD');
        } catch (\Exception $e) {
            $purchase->setPrice('0');
        }

        $this->entityManager->persist($purchase);
        $this->entityManager->flush();

        // Attempt to register domain
        try {
            $result = $this->namecheapService->registerDomain(
                $domain,
                $years,
                $registrantInfo
            );

            if ($result['success']) {
                $purchase->setStatus(DomainPurchase::STATUS_COMPLETED);
                $purchase->setProviderDomainId($result['domainId'] ?? null);
                $purchase->setProviderOrderId($result['orderId'] ?? null);
                $purchase->setProviderTransactionId($result['transactionId'] ?? null);
                $purchase->setPrice((string) ($result['chargedAmount'] ?? $purchase->getPrice()));
                $purchase->setCompletedAt(new \DateTimeImmutable());
                $purchase->setExpiresAt(new \DateTimeImmutable("+{$years} years"));

                $this->addFlash('success', "Domain {$domain} registered successfully!");
            } else {
                $purchase->setStatus(DomainPurchase::STATUS_FAILED);
                $purchase->setErrorMessage($result['error'] ?? 'Registration failed');

                $this->addFlash('error', 'Domain registration failed: ' . ($result['error'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            $purchase->setStatus(DomainPurchase::STATUS_FAILED);
            $purchase->setErrorMessage($e->getMessage());

            $this->addFlash('error', 'Domain registration failed: ' . $e->getMessage());
        }

        $this->entityManager->flush();

        return $this->redirectToRoute('app_domain_marketplace');
    }

    #[Route('/my-domains', name: 'app_my_domains')]
    public function myDomains(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $domains = $this->domainPurchaseRepository->findCompletedByUser($user);
        $projects = $this->projectRepository->findByOwner($user);

        return $this->render('dashboard/domains/marketplace/my-domains.html.twig', [
            'domains' => $domains,
            'projects' => $projects,
        ]);
    }

    #[Route('/{id}/dns', name: 'app_domain_dns', methods: ['GET', 'POST'])]
    public function manageDns(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $purchase = $this->domainPurchaseRepository->find($id);

        if (!$purchase || $purchase->getUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Domain not found');
        }

        if (!$purchase->isCompleted()) {
            $this->addFlash('error', 'Cannot manage DNS for incomplete purchase');
            return $this->redirectToRoute('app_my_domains');
        }

        $records = [];
        try {
            $records = $this->namecheapService->getDnsRecords($purchase->getDomain());
        } catch (\Exception $e) {
            $this->addFlash('warning', 'Could not load DNS records: ' . $e->getMessage());
        }

        if ($request->isMethod('POST')) {
            $newRecords = [];
            $names = $request->request->all('record_name');
            $types = $request->request->all('record_type');
            $values = $request->request->all('record_value');
            $ttls = $request->request->all('record_ttl');

            for ($i = 0; $i < count($names); $i++) {
                if (!empty($values[$i])) {
                    $newRecords[] = [
                        'name' => $names[$i] ?? '@',
                        'type' => $types[$i] ?? 'A',
                        'value' => $values[$i],
                        'ttl' => (int) ($ttls[$i] ?? 1800),
                    ];
                }
            }

            try {
                $success = $this->namecheapService->setDnsRecords($purchase->getDomain(), $newRecords);
                if ($success) {
                    $this->addFlash('success', 'DNS records updated successfully');
                } else {
                    $this->addFlash('error', 'Failed to update DNS records');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating DNS: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_domain_dns', ['id' => $id]);
        }

        return $this->render('dashboard/domains/marketplace/dns.html.twig', [
            'purchase' => $purchase,
            'records' => $records,
        ]);
    }

    #[Route('/{id}/connect', name: 'app_domain_connect_project', methods: ['POST'])]
    public function connectToProject(Request $request, int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $purchase = $this->domainPurchaseRepository->find($id);

        if (!$purchase || $purchase->getUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Domain not found');
        }

        $projectId = $request->request->get('project_id');
        $project = $projectId ? $this->projectRepository->find($projectId) : null;

        if ($project && $project->getOwner()->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Project not found');
        }

        $purchase->setProject($project);
        $this->entityManager->flush();

        if ($project) {
            $this->addFlash('success', "Domain connected to project {$project->getName()}");
        } else {
            $this->addFlash('info', 'Domain disconnected from project');
        }

        return $this->redirectToRoute('app_my_domains');
    }

    private function isValidDomain(string $domain): bool
    {
        return (bool) preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.[a-z]{2,}$/i', $domain);
    }

    private function extractTld(string $domain): string
    {
        $parts = explode('.', $domain);
        return array_pop($parts);
    }
}
