<?php

namespace App\Controller;

use App\Repository\PricingPlanRepository;
use App\Service\HetznerService;
use App\Service\SubscriptionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private HetznerService $hetznerService,
        private SubscriptionService $subscriptionService,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/', name: 'app_home')]
    public function index(PricingPlanRepository $pricingPlanRepository): Response
    {
        $plans = $pricingPlanRepository->findAllActive();

        // Calculate minimum price from Hetzner
        $minPrice = null;
        try {
            $serverTypes = $this->hetznerService->getServerTypes();
            foreach ($serverTypes as $type) {
                $pricing = $this->subscriptionService->calculateServerCostFromData($type);
                if ($minPrice === null || $pricing['final_price_eur'] < $minPrice) {
                    $minPrice = $pricing['final_price_eur'];
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to fetch minimum price', [
                'error' => $e->getMessage(),
            ]);
            $minPrice = 6.64; // Fallback minimum (cx11)
        }

        return $this->render('home/index.html.twig', [
            'plans' => $plans,
            'minPrice' => $minPrice,
        ]);
    }
}
