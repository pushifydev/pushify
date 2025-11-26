<?php

namespace App\Controller;

use App\Repository\PricingPlanRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(PricingPlanRepository $pricingPlanRepository): Response
    {
        $plans = $pricingPlanRepository->findAllActive();

        return $this->render('home/index.html.twig', [
            'plans' => $plans,
        ]);
    }
}
