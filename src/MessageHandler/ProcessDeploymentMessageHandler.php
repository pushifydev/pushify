<?php

namespace App\MessageHandler;

use App\Entity\Deployment;
use App\Message\ProcessDeploymentMessage;
use App\Service\DeploymentService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessDeploymentMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DeploymentService $deploymentService,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(ProcessDeploymentMessage $message): void
    {
        $deployment = $this->entityManager->getRepository(Deployment::class)->find($message->getDeploymentId());

        if (!$deployment) {
            $this->logger->warning('Deployment not found', ['id' => $message->getDeploymentId()]);
            return;
        }

        if (!$deployment->isQueued()) {
            $this->logger->info('Deployment already processed', ['id' => $deployment->getId()]);
            return;
        }

        $this->logger->info('Processing deployment', [
            'deployment_id' => $deployment->getId(),
            'project' => $deployment->getProject()->getName(),
        ]);

        $this->deploymentService->processDeployment($deployment);
    }
}
