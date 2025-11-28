<?php

namespace App\Controller;

use App\Service\HetznerService;
use App\Service\SubscriptionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MarketingController extends AbstractController
{
    public function __construct(
        private HetznerService $hetznerService,
        private SubscriptionService $subscriptionService,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/pricing', name: 'app_pricing')]
    public function pricing(): Response
    {
        $plans = [];

        try {
            $serverTypes = $this->hetznerService->getServerTypes();

            foreach ($serverTypes as $type) {
                $pricing = $this->subscriptionService->calculateServerCostFromData($type);

                $plans[] = [
                    'name' => strtoupper($type['name']),
                    'description' => $type['cores'] . ' vCPU • ' . $type['memory'] . 'GB RAM • ' . $type['disk'] . 'GB SSD',
                    'price' => $pricing['final_price_eur'],
                    'billingCycle' => 'month',
                    'isPopular' => $type['name'] === 'cx22',
                    'badge' => $type['name'] === 'cx22' ? 'Most Popular' : null,
                    'features' => [
                        'Unlimited Servers (of this type)',
                        'Unlimited Projects',
                        'Automatic SSL Certificates',
                        'Git-based Deployments',
                        'Docker Support',
                        'Database Backups',
                        '24/7 Server Monitoring',
                        'Email Support',
                    ],
                    'server_type' => $type['name'],
                ];
            }
        } catch (\Exception $e) {
            $this->logger->warning('Failed to fetch server types for pricing', [
                'error' => $e->getMessage(),
            ]);

            // Fallback pricing
            $defaultTypes = [
                ['name' => 'cx11', 'cores' => 1, 'memory' => 2, 'disk' => 20, 'price' => 6.64],
                ['name' => 'cx22', 'cores' => 2, 'memory' => 4, 'disk' => 40, 'price' => 12.80],
                ['name' => 'cx31', 'cores' => 2, 'memory' => 8, 'disk' => 80, 'price' => 20.00],
                ['name' => 'cx41', 'cores' => 4, 'memory' => 16, 'disk' => 160, 'price' => 32.00],
                ['name' => 'cx51', 'cores' => 8, 'memory' => 32, 'disk' => 240, 'price' => 57.60],
            ];

            foreach ($defaultTypes as $type) {
                $plans[] = [
                    'name' => strtoupper($type['name']),
                    'description' => $type['cores'] . ' vCPU • ' . $type['memory'] . 'GB RAM • ' . $type['disk'] . 'GB SSD',
                    'price' => $type['price'],
                    'billingCycle' => 'month',
                    'isPopular' => $type['name'] === 'cx22',
                    'badge' => $type['name'] === 'cx22' ? 'Most Popular' : null,
                    'features' => [
                        'Unlimited Servers (of this type)',
                        'Unlimited Projects',
                        'Automatic SSL Certificates',
                        'Git-based Deployments',
                        'Docker Support',
                        'Database Backups',
                        '24/7 Server Monitoring',
                        'Email Support',
                    ],
                    'server_type' => $type['name'],
                ];
            }
        }

        return $this->render('pricing/index.html.twig', [
            'plans' => $plans,
        ]);
    }

    #[Route('/features', name: 'marketing_features')]
    public function features(): Response
    {
        return $this->render('marketing/features.html.twig');
    }

    #[Route('/faq', name: 'marketing_faq')]
    public function faq(): Response
    {
        $faqs = [
            [
                'category' => 'Subscription & Billing',
                'questions' => [
                    [
                        'question' => 'How does the subscription model work?',
                        'answer' => 'Each subscription is tied to a specific server type (e.g., CX22). You pay monthly and can create unlimited servers of that type and deploy unlimited projects on those servers.',
                    ],
                    [
                        'question' => 'Can I create multiple servers?',
                        'answer' => 'Yes! You can create as many servers as you need, but they must all be the same type as your subscription. For example, if you subscribe to CX22, you can create multiple CX22 servers.',
                    ],
                    [
                        'question' => 'Can I change my server type?',
                        'answer' => 'Yes. You can cancel your current subscription and subscribe to a different server type. Your existing servers will continue to work, but you won\'t be able to create new ones until you have an active subscription.',
                    ],
                    [
                        'question' => 'What payment methods do you accept?',
                        'answer' => 'We accept all major credit and debit cards through our secure payment processor, iyzico. All payments are processed in EUR.',
                    ],
                    [
                        'question' => 'Can I cancel anytime?',
                        'answer' => 'Absolutely! You can cancel your subscription at any time from your billing dashboard. No questions asked. We also offer a 7-day money-back guarantee.',
                    ],
                ],
            ],
            [
                'category' => 'Servers & Deployment',
                'questions' => [
                    [
                        'question' => 'How many projects can I deploy?',
                        'answer' => 'Unlimited! You can deploy as many projects as your server resources can handle. Each server can host multiple applications simultaneously.',
                    ],
                    [
                        'question' => 'Can I use my own server?',
                        'answer' => 'Yes! You can connect your existing server via SSH (manual connection) or let us create one automatically on Hetzner Cloud for you.',
                    ],
                    [
                        'question' => 'What if I need different server types?',
                        'answer' => 'If you need to run different server types simultaneously, you would need separate subscriptions for each type. However, most users find that one server type is sufficient for all their projects.',
                    ],
                    [
                        'question' => 'Do you provide SSL certificates?',
                        'answer' => 'Yes! We automatically provision and renew SSL certificates for all your domains using Let\'s Encrypt. Completely free and automatic.',
                    ],
                    [
                        'question' => 'What technologies do you support?',
                        'answer' => 'We support Docker-based deployments, which means you can deploy any application that runs in a Docker container. This includes Node.js, PHP, Python, Ruby, Go, and many more.',
                    ],
                ],
            ],
            [
                'category' => 'Technical Support',
                'questions' => [
                    [
                        'question' => 'What kind of support do you offer?',
                        'answer' => 'We offer email support for all customers. We typically respond within 24 hours on weekdays. We also have comprehensive documentation and guides.',
                    ],
                    [
                        'question' => 'Do you offer backups?',
                        'answer' => 'Yes! We provide automated database backups for all projects. You can restore your database to any previous backup point from your dashboard.',
                    ],
                    [
                        'question' => 'What is your uptime guarantee?',
                        'answer' => 'We target 99.9% uptime for our platform. Your servers run on Hetzner Cloud infrastructure, which has excellent reliability. We monitor all servers 24/7.',
                    ],
                    [
                        'question' => 'Do you have a free trial?',
                        'answer' => 'We don\'t offer a traditional free trial, but we do offer a 7-day money-back guarantee. If you\'re not satisfied within the first week, we\'ll refund your payment in full.',
                    ],
                ],
            ],
        ];

        return $this->render('marketing/faq.html.twig', [
            'faqs' => $faqs,
        ]);
    }

    #[Route('/features/databases', name: 'features_databases')]
    public function databases(): Response
    {
        return $this->render('features/databases.html.twig');
    }

    #[Route('/features/ssl', name: 'features_ssl')]
    public function ssl(): Response
    {
        return $this->render('features/ssl.html.twig');
    }

    #[Route('/features/monitoring', name: 'features_monitoring')]
    public function monitoring(): Response
    {
        return $this->render('features/monitoring.html.twig');
    }

    #[Route('/docs', name: 'docs_home')]
    public function docs(): Response
    {
        return $this->render('docs/index.html.twig');
    }

    #[Route('/docs/getting-started', name: 'docs_getting_started')]
    public function gettingStarted(): Response
    {
        return $this->render('docs/getting-started.html.twig');
    }

    #[Route('/docs/api', name: 'docs_api')]
    public function api(): Response
    {
        return $this->render('docs/api.html.twig');
    }

    #[Route('/docs/server-setup', name: 'docs_server_setup')]
    public function serverSetup(): Response
    {
        return $this->render('docs/server-setup.html.twig');
    }

    #[Route('/docs/cli', name: 'docs_cli')]
    public function cli(): Response
    {
        return $this->render('docs/cli.html.twig');
    }

    #[Route('/docs/github', name: 'docs_github')]
    public function github(): Response
    {
        return $this->render('docs/github.html.twig');
    }

    #[Route('/changelog', name: 'changelog')]
    public function changelog(): Response
    {
        return $this->render('docs/changelog.html.twig');
    }

    #[Route('/features/backups', name: 'features_backups')]
    public function backups(): Response
    {
        return $this->render('features/backups.html.twig');
    }

    #[Route('/features/preview', name: 'features_preview')]
    public function preview(): Response
    {
        return $this->render('features/preview.html.twig');
    }

    #[Route('/features/domains', name: 'features_domains')]
    public function domains(): Response
    {
        return $this->render('features/domains.html.twig');
    }

    #[Route('/features/teams', name: 'features_teams')]
    public function teams(): Response
    {
        return $this->render('features/teams.html.twig');
    }

    #[Route('/features/self-hosted', name: 'features_self_hosted')]
    public function selfHosted(): Response
    {
        return $this->render('features/self-hosted.html.twig');
    }
}
