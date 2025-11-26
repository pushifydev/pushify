<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GitHubController extends AbstractController
{
    #[Route('/connect/github', name: 'connect_github_start')]
    public function connect(ClientRegistry $clientRegistry): RedirectResponse
    {
        // Redirect to GitHub for authorization
        // 'repo' scope gives access to private repos
        return $clientRegistry
            ->getClient('github')
            ->redirect(['user:email', 'read:user', 'repo'], []);
    }

    #[Route('/connect/github/check', name: 'connect_github_check')]
    public function connectCheck(): Response
    {
        // This route is handled by GitHubAuthenticator
        // If we reach here, something went wrong
        return $this->redirectToRoute('app_login');
    }
}
