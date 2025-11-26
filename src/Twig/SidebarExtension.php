<?php

namespace App\Twig;

use App\Repository\ServerRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class SidebarExtension extends AbstractExtension
{
    public function __construct(
        private ServerRepository $serverRepository,
        private Security $security
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('server_count', [$this, 'getServerCount']),
        ];
    }

    public function getServerCount(): int
    {
        $user = $this->security->getUser();
        if (!$user) {
            return 0;
        }

        return $this->serverRepository->countByOwner($user);
    }
}
