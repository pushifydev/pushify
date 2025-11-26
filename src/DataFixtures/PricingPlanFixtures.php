<?php

namespace App\DataFixtures;

use App\Entity\PricingPlan;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class PricingPlanFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $plans = [
            [
                'name' => 'Hobby',
                'description' => 'Perfect for side projects',
                'price' => '0.00',
                'billingCycle' => 'month',
                'isPopular' => false,
                'isActive' => true,
                'sortOrder' => 1,
                'badge' => null,
                'features' => [
                    '1 Server',
                    '3 Projects',
                    'Auto SSL Certificates',
                    'Git Push Deployments',
                    'Community Support',
                ],
            ],
            [
                'name' => 'Pro',
                'description' => 'For growing teams',
                'price' => '29.00',
                'billingCycle' => 'month',
                'isPopular' => true,
                'isActive' => true,
                'sortOrder' => 2,
                'badge' => 'Most Popular',
                'features' => [
                    '5 Servers',
                    'Unlimited Projects',
                    'Database Backups',
                    'Team Collaboration (5 members)',
                    'Priority Support',
                    'Custom Domains',
                    'Environment Variables',
                ],
            ],
            [
                'name' => 'Enterprise',
                'description' => 'For large organizations',
                'price' => '99.00',
                'billingCycle' => 'month',
                'isPopular' => false,
                'isActive' => true,
                'sortOrder' => 3,
                'badge' => null,
                'features' => [
                    'Unlimited Servers',
                    'Unlimited Projects',
                    'Advanced Analytics',
                    'SSO / SAML Authentication',
                    '24/7 Dedicated Support',
                    'Custom SLA',
                    'Audit Logs',
                    'Role-based Access Control',
                ],
            ],
        ];

        foreach ($plans as $planData) {
            $plan = new PricingPlan();
            $plan->setName($planData['name']);
            $plan->setDescription($planData['description']);
            $plan->setPrice($planData['price']);
            $plan->setBillingCycle($planData['billingCycle']);
            $plan->setIsPopular($planData['isPopular']);
            $plan->setIsActive($planData['isActive']);
            $plan->setSortOrder($planData['sortOrder']);
            $plan->setBadge($planData['badge']);
            $plan->setFeatures($planData['features']);

            $manager->persist($plan);
        }

        $manager->flush();
    }
}
