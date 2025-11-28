<?php

namespace App\Service;

use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Iyzipay\Model\Address;
use Iyzipay\Model\Buyer;
use Iyzipay\Model\CheckoutFormInitialize;
use Iyzipay\Model\Locale;
use Iyzipay\Model\PaymentCard;
use Iyzipay\Model\PaymentGroup;
use Iyzipay\Options;
use Iyzipay\Request\CreateCheckoutFormInitializeRequest;
use Iyzipay\Request\CreatePaymentRequest;
use Psr\Log\LoggerInterface;

class SubscriptionService
{
    private Options $iyzicoOptions;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private HetznerService $hetznerService,
        private string $iyzicoApiKey,
        private string $iyzicoSecretKey,
        private string $iyzicoBaseUrl
    ) {
        $this->iyzicoOptions = new Options();
        $this->iyzicoOptions->setApiKey($this->iyzicoApiKey);
        $this->iyzicoOptions->setSecretKey($this->iyzicoSecretKey);
        $this->iyzicoOptions->setBaseUrl($this->iyzicoBaseUrl);
    }

    /**
     * Calculate server monthly cost with markup
     */
    public function calculateServerCost(string $serverType): array
    {
        try {
            // Get real-time pricing from Hetzner API
            $serverTypes = $this->hetznerService->getServerTypes();

            $foundType = null;
            foreach ($serverTypes as $type) {
                if ($type['name'] === $serverType) {
                    $foundType = $type;
                    break;
                }
            }

            if (!$foundType) {
                // Fallback to default if type not found
                throw new \RuntimeException("Server type not found: {$serverType}");
            }

            // Get monthly price from Hetzner
            // prices[0] is location-based pricing, we'll use 'monthly' price
            $monthlyPrice = null;
            foreach ($foundType['prices'] as $priceData) {
                if (isset($priceData['price_monthly']['gross'])) {
                    $monthlyPrice = (float) $priceData['price_monthly']['gross'];
                    break;
                }
            }

            if (!$monthlyPrice) {
                throw new \RuntimeException("Could not find monthly price for {$serverType}");
            }

            $costEur = $monthlyPrice;

        } catch (\Exception $e) {
            // Fallback to hard-coded prices if API fails
            $this->logger->warning('Using fallback pricing', [
                'server_type' => $serverType,
                'error' => $e->getMessage(),
            ]);

            $hetznerCosts = [
                'cx11' => 4.15,
                'cx21' => 6.40,
                'cx22' => 8.00,
                'cx31' => 12.50,
                'cx41' => 20.00,
                'cx51' => 36.00,
            ];

            $costEur = $hetznerCosts[$serverType] ?? $hetznerCosts['cx22'];
        }

        // Add 60% markup
        $markup = 0.60;
        $finalPrice = $costEur * (1 + $markup);

        return [
            'server_type' => $serverType,
            'cost_eur' => round($costEur, 2),
            'markup_percentage' => $markup * 100,
            'final_price_eur' => round($finalPrice, 2),
            'final_price_usd' => round($finalPrice * 1.10, 2), // EUR to USD approximate
        ];
    }

    /**
     * Create a payment with card details
     */
    public function createPaymentWithCard(
        User $user,
        array $cardDetails,
        float $amount,
        string $serverType
    ): array {
        try {
            $request = new CreatePaymentRequest();
            $request->setLocale(Locale::EN);
            $request->setConversationId($this->generateConversationId());
            $request->setPrice($amount);
            $request->setPaidPrice($amount);
            $request->setCurrency(\Iyzipay\Model\Currency::EUR);
            $request->setInstallment(1);
            $request->setPaymentChannel(\Iyzipay\Model\PaymentChannel::WEB);
            $request->setPaymentGroup(PaymentGroup::SUBSCRIPTION);

            // Payment card
            $paymentCard = new PaymentCard();
            $paymentCard->setCardHolderName($cardDetails['holder_name']);
            $paymentCard->setCardNumber($cardDetails['card_number']);
            $paymentCard->setExpireMonth($cardDetails['expire_month']);
            $paymentCard->setExpireYear($cardDetails['expire_year']);
            $paymentCard->setCvc($cardDetails['cvc']);
            $paymentCard->setRegisterCard(1); // Save card for subscription
            $request->setPaymentCard($paymentCard);

            // Buyer information
            $buyer = new Buyer();
            $buyer->setId($user->getId());
            $buyer->setName(explode(' ', $user->getName())[0] ?? 'User');
            $buyer->setSurname(explode(' ', $user->getName())[1] ?? 'Name');
            $buyer->setEmail($user->getEmail());
            $buyer->setIdentityNumber('11111111111'); // TR ID required, use dummy for now
            $buyer->setRegistrationAddress('Istanbul, Turkey');
            $buyer->setCity('Istanbul');
            $buyer->setCountry('Turkey');
            $buyer->setZipCode('34000');
            $buyer->setIp($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            $request->setBuyer($buyer);

            // Billing address
            $billingAddress = new Address();
            $billingAddress->setContactName($user->getName());
            $billingAddress->setCity('Istanbul');
            $billingAddress->setCountry('Turkey');
            $billingAddress->setAddress('Istanbul, Turkey');
            $billingAddress->setZipCode('34000');
            $request->setBillingAddress($billingAddress);
            $request->setShippingAddress($billingAddress);

            // Basket items
            $basketItems = [];
            $basketItem = new \Iyzipay\Model\BasketItem();
            $basketItem->setId('SERVER-' . uniqid());
            $basketItem->setName("Pushify Server - {$serverType}");
            $basketItem->setCategory1('Server Hosting');
            $basketItem->setItemType(\Iyzipay\Model\BasketItemType::VIRTUAL);
            $basketItem->setPrice($amount);
            $basketItems[] = $basketItem;
            $request->setBasketItems($basketItems);

            // Make payment request
            $payment = \Iyzipay\Model\Payment::create($request, $this->iyzicoOptions);

            if ($payment->getStatus() === 'success') {
                // Create subscription
                $subscription = $this->createSubscriptionFromPayment($user, $payment, $amount, $serverType);

                $this->logger->info('Payment successful', [
                    'user_id' => $user->getId(),
                    'payment_id' => $payment->getPaymentId(),
                    'amount' => $amount,
                ]);

                return [
                    'success' => true,
                    'subscription' => $subscription,
                    'payment_id' => $payment->getPaymentId(),
                    'card_token' => $payment->getCardToken(),
                ];
            }

            $this->logger->error('Payment failed', [
                'user_id' => $user->getId(),
                'error' => $payment->getErrorMessage(),
            ]);

            return [
                'success' => false,
                'error' => $payment->getErrorMessage() ?? 'Payment failed',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Payment exception', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create subscription from successful payment
     */
    private function createSubscriptionFromPayment(
        User $user,
        $payment,
        float $amount,
        string $serverType
    ): Subscription {
        $subscription = new Subscription();
        $subscription->setUser($user);
        $subscription->setIyzicoSubscriptionReferenceCode($payment->getPaymentId());
        $subscription->setIyzicoCustomerReferenceCode($payment->getCardUserKey());
        $subscription->setStatus(Subscription::STATUS_ACTIVE);
        $subscription->setAmount((string) $amount);
        $subscription->setCurrency('EUR');
        $subscription->setBillingCycle('monthly');

        $now = new \DateTime();
        $subscription->setCurrentPeriodStart($now);
        $subscription->setCurrentPeriodEnd(new \DateTime('+1 month'));

        $subscription->setMetadata([
            'server_type' => $serverType,
            'payment_id' => $payment->getPaymentId(),
            'card_token' => $payment->getCardToken(),
            'card_last_four' => substr($payment->getCardNumber() ?? '', -4),
        ]);

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        return $subscription;
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(Subscription $subscription): bool
    {
        try {
            $subscription->setStatus(Subscription::STATUS_CANCELLED);
            $subscription->setCancelledAt(new \DateTime());

            $this->entityManager->flush();

            $this->logger->info('Subscription cancelled', [
                'subscription_id' => $subscription->getId(),
                'user_id' => $subscription->getUser()->getId(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to cancel subscription', [
                'subscription_id' => $subscription->getId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check and renew expiring subscriptions
     */
    public function renewSubscription(Subscription $subscription): array
    {
        if (!$subscription->isActive()) {
            return [
                'success' => false,
                'error' => 'Subscription is not active',
            ];
        }

        $metadata = $subscription->getMetadata();
        $cardToken = $metadata['card_token'] ?? null;

        if (!$cardToken) {
            return [
                'success' => false,
                'error' => 'No saved card found',
            ];
        }

        // Charge the saved card
        // iyzico will handle this automatically with card token
        // For now, just extend the period

        $subscription->setCurrentPeriodStart(new \DateTime());
        $subscription->setCurrentPeriodEnd(new \DateTime('+1 month'));
        $subscription->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        $this->logger->info('Subscription renewed', [
            'subscription_id' => $subscription->getId(),
        ]);

        return [
            'success' => true,
            'subscription' => $subscription,
        ];
    }

    /**
     * Generate unique conversation ID for iyzico
     */
    private function generateConversationId(): string
    {
        return 'PUSHIFY-' . uniqid() . '-' . time();
    }

    /**
     * Get subscription statistics
     */
    public function getStatistics(): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        $activeCount = $qb->select('COUNT(s.id)')
            ->from(Subscription::class, 's')
            ->where('s.status = :status')
            ->setParameter('status', Subscription::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        $totalRevenue = $qb->select('SUM(s.amount)')
            ->from(Subscription::class, 's')
            ->where('s.status = :status')
            ->setParameter('status', Subscription::STATUS_ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'active_subscriptions' => (int) $activeCount,
            'monthly_revenue' => (float) ($totalRevenue ?? 0),
        ];
    }
}
