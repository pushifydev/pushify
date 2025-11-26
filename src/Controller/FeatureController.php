<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/features')]
class FeatureController extends AbstractController
{
    #[Route('/databases', name: 'app_feature_databases')]
    public function databases(): Response
    {
        return $this->render('features/databases.html.twig');
    }

    #[Route('/backups', name: 'app_feature_backups')]
    public function backups(): Response
    {
        return $this->render('features/backups.html.twig');
    }

    #[Route('/preview', name: 'app_feature_preview')]
    public function preview(): Response
    {
        return $this->render('features/preview.html.twig');
    }

    #[Route('/ssl', name: 'app_feature_ssl')]
    public function ssl(): Response
    {
        return $this->render('features/ssl.html.twig');
    }

    #[Route('/domains', name: 'app_feature_domains')]
    public function domains(): Response
    {
        return $this->render('features/domains.html.twig');
    }

    #[Route('/monitoring', name: 'app_feature_monitoring')]
    public function monitoring(): Response
    {
        return $this->render('features/monitoring.html.twig');
    }

    #[Route('/teams', name: 'app_feature_teams')]
    public function teams(): Response
    {
        return $this->render('features/teams.html.twig');
    }

    #[Route('/self-hosted', name: 'app_feature_self_hosted')]
    public function selfHosted(): Response
    {
        return $this->render('features/self-hosted.html.twig');
    }
}
