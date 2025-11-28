<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/billing')]
#[IsGranted('ROLE_USER')]
class BillingController extends AbstractController
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private SubscriptionRepository $subscriptionRepository,
        private LoggerInterface $logger
    ) {
    }

    #[Route('', name: 'app_billing', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $subscription = $this->subscriptionRepository->findActiveByUser($user);
        $subscriptions = $this->subscriptionRepository->findByUser($user);

        return $this->render('dashboard/billing/index.html.twig', [
            'subscription' => $subscription,
            'subscriptions' => $subscriptions,
        ]);
    }

    #[Route('/checkout', name: 'app_billing_checkout_page', methods: ['GET'])]
    public function checkoutPage(Request $request): Response
    {
        $serverType = $request->query->get('server_type', 'cx22');
        $pricing = $this->subscriptionService->calculateServerCost($serverType);

        return $this->render('dashboard/billing/checkout.html.twig', [
            'server_type' => $serverType,
            'pricing' => $pricing,
        ]);
    }

    #[Route('/checkout', name: 'app_billing_checkout', methods: ['POST'])]
    public function checkout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Check if user already has an active subscription
        if ($user->hasActiveSubscription()) {
            return $this->json([
                'success' => false,
                'error' => 'You already have an active subscription',
            ], 400);
        }

        $data = json_decode($request->getContent(), true);

        // Validate card details
        if (empty($data['card_number']) || empty($data['holder_name']) ||
            empty($data['expire_month']) || empty($data['expire_year']) ||
            empty($data['cvc'])) {
            return $this->json([
                'success' => false,
                'error' => 'All card details are required',
            ], 400);
        }

        $serverType = $data['server_type'] ?? 'cx22';
        $pricing = $this->subscriptionService->calculateServerCost($serverType);

        $cardDetails = [
            'card_number' => $data['card_number'],
            'holder_name' => $data['holder_name'],
            'expire_month' => $data['expire_month'],
            'expire_year' => $data['expire_year'],
            'cvc' => $data['cvc'],
        ];

        $result = $this->subscriptionService->createPaymentWithCard(
            $user,
            $cardDetails,
            $pricing['final_price_eur'],
            $serverType
        );

        if ($result['success']) {
            return $this->json([
                'success' => true,
                'subscription_id' => $result['subscription']->getId(),
                'message' => 'Payment successful! You can now create servers.',
            ]);
        }

        return $this->json([
            'success' => false,
            'error' => $result['error'] ?? 'Payment failed',
        ], 400);
    }

    #[Route('/pricing/{serverType}', name: 'app_billing_pricing', methods: ['GET'])]
    public function getPricing(string $serverType): JsonResponse
    {
        $pricing = $this->subscriptionService->calculateServerCost($serverType);
        return $this->json($pricing);
    }

    #[Route('/subscription/cancel', name: 'app_billing_cancel', methods: ['POST'])]
    public function cancel(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $subscription = $this->subscriptionRepository->findActiveByUser($user);

        if (!$subscription) {
            return $this->json([
                'success' => false,
                'error' => 'No active subscription found',
            ], 404);
        }

        $result = $this->subscriptionService->cancelSubscription($subscription);

        if ($result) {
            return $this->json([
                'success' => true,
                'message' => 'Subscription cancelled successfully',
            ]);
        }

        return $this->json([
            'success' => false,
            'error' => 'Failed to cancel subscription',
        ], 400);
    }

    #[Route('/webhook', name: 'app_billing_webhook', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        // iyzico webhook handler
        // Verify webhook signature and process payment notifications

        $payload = json_decode($request->getContent(), true);

        // Log webhook for debugging
        $this->logger->info('iyzico webhook received', ['payload' => $payload]);

        // Process webhook based on event type
        // - payment.success
        // - payment.failed
        // - subscription.renewed
        // - subscription.cancelled

        return $this->json(['status' => 'received']);
    }
}
