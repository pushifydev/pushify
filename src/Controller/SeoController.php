<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SeoController extends AbstractController
{
    #[Route('/sitemap.xml', name: 'app_sitemap', defaults: ['_format' => 'xml'])]
    public function sitemap(UrlGeneratorInterface $urlGenerator): Response
    {
        $hostname = $this->getParameter('app.hostname') ?? 'https://pushify.dev';

        $urls = [];
        $now = new \DateTime();

        // Homepage - highest priority
        $urls[] = [
            'loc' => $hostname,
            'lastmod' => $now->format('Y-m-d'),
            'changefreq' => 'daily',
            'priority' => '1.0',
        ];

        // Public pages - high priority
        $publicPages = [
            ['route' => 'app_register', 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['route' => 'app_login', 'priority' => '0.8', 'changefreq' => 'monthly'],
            // Add more public routes as needed
        ];

        foreach ($publicPages as $page) {
            try {
                $urls[] = [
                    'loc' => $hostname . $urlGenerator->generate($page['route']),
                    'lastmod' => $now->format('Y-m-d'),
                    'changefreq' => $page['changefreq'],
                    'priority' => $page['priority'],
                ];
            } catch (\Exception $e) {
                // Skip if route doesn't exist
                continue;
            }
        }

        // Generate XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($url['loc']) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . $url['lastmod'] . '</lastmod>' . "\n";
            $xml .= '    <changefreq>' . $url['changefreq'] . '</changefreq>' . "\n";
            $xml .= '    <priority>' . $url['priority'] . '</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';

        $response = new Response($xml);
        $response->headers->set('Content-Type', 'application/xml; charset=utf-8');

        // Cache for 24 hours
        $response->setSharedMaxAge(86400);
        $response->headers->addCacheControlDirective('must-revalidate', true);

        return $response;
    }

    #[Route('/.well-known/security.txt', name: 'app_security_txt')]
    public function securityTxt(): Response
    {
        $content = "Contact: mailto:security@pushify.dev\n";
        $content .= "Expires: " . (new \DateTime('+1 year'))->format('Y-m-d\TH:i:s\Z') . "\n";
        $content .= "Preferred-Languages: en\n";
        $content .= "Canonical: https://pushify.dev/.well-known/security.txt\n";

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/plain; charset=utf-8');

        return $response;
    }
}
