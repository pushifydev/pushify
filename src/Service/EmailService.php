<?php

namespace App\Service;

use App\Message\SendEmailMessage;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for sending emails via RabbitMQ queue
 */
class EmailService
{
    public function __construct(
        private ProducerInterface $emailProducer,
        private LoggerInterface $logger,
        private string $appUrl = 'http://localhost',
    ) {
    }

    /**
     * Queue an email message for async sending
     */
    public function queue(SendEmailMessage $message): void
    {
        try {
            $this->emailProducer->publish(serialize($message));

            $this->logger->info('Email queued', [
                'to' => $message->getTo(),
                'subject' => $message->getSubject(),
                'type' => $message->getType(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to queue email', [
                'to' => $message->getTo(),
                'subject' => $message->getSubject(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send team invitation email
     */
    public function sendTeamInvitation(
        string $email,
        string $teamName,
        string $inviterName,
        string $role,
        string $token,
        \DateTimeImmutable $expiresAt
    ): void {
        $invitationUrl = rtrim($this->appUrl, '/') . '/dashboard/teams/invite/' . $token;

        $message = SendEmailMessage::teamInvitation(
            email: $email,
            teamName: $teamName,
            inviterName: $inviterName,
            role: $role,
            invitationUrl: $invitationUrl,
            expiresAt: $expiresAt->format('F d, Y')
        );

        $this->queue($message);
    }

    /**
     * Send deployment success email
     */
    public function sendDeploymentSuccess(
        string $email,
        string $projectName,
        string $deploymentUrl,
        string $commitHash,
        string $branch
    ): void {
        $message = SendEmailMessage::deploymentSuccess(
            email: $email,
            projectName: $projectName,
            deploymentUrl: $deploymentUrl,
            commitHash: substr($commitHash, 0, 7),
            branch: $branch
        );

        $this->queue($message);
    }

    /**
     * Send deployment failed email
     */
    public function sendDeploymentFailed(
        string $email,
        string $projectName,
        string $errorMessage,
        string $commitHash,
        string $branch
    ): void {
        $message = SendEmailMessage::deploymentFailed(
            email: $email,
            projectName: $projectName,
            errorMessage: $errorMessage,
            commitHash: substr($commitHash, 0, 7),
            branch: $branch
        );

        $this->queue($message);
    }

    /**
     * Send a generic email with custom content
     */
    public function sendGeneric(string $email, string $subject, string $htmlBody, ?string $textBody = null): void
    {
        $message = SendEmailMessage::generic($email, $subject, $htmlBody, $textBody);
        $this->queue($message);
    }
}
