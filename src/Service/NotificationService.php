<?php

namespace App\Service;

use App\Entity\Deployment;
use App\Entity\Domain;
use App\Entity\Notification;
use App\Entity\Project;
use App\Entity\Server;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        private string $appName = 'Pushify',
        private string $fromEmail = 'noreply@pushify.dev'
    ) {
    }

    /**
     * Create a notification for deployment started
     */
    public function notifyDeploymentStarted(Deployment $deployment): void
    {
        $project = $deployment->getProject();
        $user = $project->getOwner();

        if (!$this->shouldNotify($user, 'deployment_started')) {
            return;
        }

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType(Notification::TYPE_DEPLOYMENT_STARTED);
        $notification->setTitle('Deployment Started');
        $notification->setMessage(sprintf(
            'Deployment #%d for %s has started.',
            $deployment->getId(),
            $project->getName()
        ));
        $notification->setData([
            'project_id' => $project->getId(),
            'project_slug' => $project->getSlug(),
            'deployment_id' => $deployment->getId(),
            'trigger' => $deployment->getTrigger(),
        ]);
        $notification->setActionUrl($this->urlGenerator->generate('app_deployment_show', [
            'slug' => $project->getSlug(),
            'id' => $deployment->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL));
        $notification->setActionLabel('View Deployment');

        $this->save($notification);
    }

    /**
     * Create a notification for deployment success
     */
    public function notifyDeploymentSuccess(Deployment $deployment): void
    {
        $project = $deployment->getProject();
        $user = $project->getOwner();

        if (!$this->shouldNotify($user, 'deployment_success')) {
            return;
        }

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType(Notification::TYPE_DEPLOYMENT_SUCCESS);
        $notification->setTitle('Deployment Successful');
        $notification->setMessage(sprintf(
            'Deployment #%d for %s completed successfully in %ds.',
            $deployment->getId(),
            $project->getName(),
            $deployment->getTotalDuration() ?? 0
        ));
        $notification->setData([
            'project_id' => $project->getId(),
            'project_slug' => $project->getSlug(),
            'deployment_id' => $deployment->getId(),
            'deployment_url' => $deployment->getDeploymentUrl(),
            'duration' => $deployment->getTotalDuration(),
        ]);
        $notification->setActionUrl($deployment->getDeploymentUrl() ?? $this->urlGenerator->generate('app_deployment_show', [
            'slug' => $project->getSlug(),
            'id' => $deployment->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL));
        $notification->setActionLabel('View Site');

        $this->save($notification);
        $this->sendEmailNotification($notification);
    }

    /**
     * Create a notification for deployment failure
     */
    public function notifyDeploymentFailed(Deployment $deployment): void
    {
        $project = $deployment->getProject();
        $user = $project->getOwner();

        if (!$this->shouldNotify($user, 'deployment_failed')) {
            return;
        }

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType(Notification::TYPE_DEPLOYMENT_FAILED);
        $notification->setTitle('Deployment Failed');
        $notification->setMessage(sprintf(
            'Deployment #%d for %s has failed: %s',
            $deployment->getId(),
            $project->getName(),
            $deployment->getErrorMessage() ?? 'Unknown error'
        ));
        $notification->setData([
            'project_id' => $project->getId(),
            'project_slug' => $project->getSlug(),
            'deployment_id' => $deployment->getId(),
            'error' => $deployment->getErrorMessage(),
        ]);
        $notification->setActionUrl($this->urlGenerator->generate('app_deployment_show', [
            'slug' => $project->getSlug(),
            'id' => $deployment->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL));
        $notification->setActionLabel('View Logs');

        $this->save($notification);
        $this->sendEmailNotification($notification);
    }

    /**
     * Create a notification for server offline
     */
    public function notifyServerOffline(Server $server): void
    {
        $user = $server->getOwner();

        if (!$this->shouldNotify($user, 'server_offline')) {
            return;
        }

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType(Notification::TYPE_SERVER_OFFLINE);
        $notification->setTitle('Server Offline');
        $notification->setMessage(sprintf(
            'Server "%s" (%s) appears to be offline.',
            $server->getName(),
            $server->getIpAddress()
        ));
        $notification->setData([
            'server_id' => $server->getId(),
            'server_name' => $server->getName(),
            'ip_address' => $server->getIpAddress(),
        ]);
        $notification->setActionUrl($this->urlGenerator->generate('app_server_show', [
            'id' => $server->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL));
        $notification->setActionLabel('Check Server');

        $this->save($notification);
        $this->sendEmailNotification($notification);
    }

    /**
     * Create a notification for server back online
     */
    public function notifyServerOnline(Server $server): void
    {
        $user = $server->getOwner();

        if (!$this->shouldNotify($user, 'server_online')) {
            return;
        }

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType(Notification::TYPE_SERVER_ONLINE);
        $notification->setTitle('Server Online');
        $notification->setMessage(sprintf(
            'Server "%s" (%s) is back online.',
            $server->getName(),
            $server->getIpAddress()
        ));
        $notification->setData([
            'server_id' => $server->getId(),
            'server_name' => $server->getName(),
        ]);
        $notification->setActionUrl($this->urlGenerator->generate('app_server_show', [
            'id' => $server->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL));
        $notification->setActionLabel('View Server');

        $this->save($notification);
    }

    /**
     * Create a notification for SSL certificate expiring
     */
    public function notifyDomainSslExpiring(Domain $domain, int $daysRemaining): void
    {
        $user = $domain->getProject()->getOwner();

        if (!$this->shouldNotify($user, 'ssl_expiring')) {
            return;
        }

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType(Notification::TYPE_DOMAIN_SSL_EXPIRING);
        $notification->setTitle('SSL Certificate Expiring');
        $notification->setMessage(sprintf(
            'SSL certificate for %s will expire in %d days.',
            $domain->getDomain(),
            $daysRemaining
        ));
        $notification->setData([
            'domain_id' => $domain->getId(),
            'domain' => $domain->getDomain(),
            'days_remaining' => $daysRemaining,
            'project_id' => $domain->getProject()->getId(),
        ]);
        $notification->setActionUrl($this->urlGenerator->generate('app_project_domains', [
            'projectId' => $domain->getProject()->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL));
        $notification->setActionLabel('Renew SSL');

        $this->save($notification);
        $this->sendEmailNotification($notification);
    }

    /**
     * Create a generic notification
     */
    public function notify(User $user, string $type, string $title, string $message, ?string $actionUrl = null, ?string $actionLabel = null, ?array $data = null): Notification
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setData($data);
        $notification->setActionUrl($actionUrl);
        $notification->setActionLabel($actionLabel);

        $this->save($notification);

        return $notification;
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(Notification $notification): void
    {
        $user = $notification->getUser();

        // Check if user has email notifications enabled
        $settings = $user->getNotificationSettings() ?? [];
        if (!($settings['email_enabled'] ?? true)) {
            return;
        }

        try {
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($user->getEmail())
                ->subject("[{$this->appName}] " . $notification->getTitle())
                ->html($this->buildEmailHtml($notification));

            $this->mailer->send($email);

            $notification->setEmailSentAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->logger->info('Notification email sent', [
                'notification_id' => $notification->getId(),
                'user_email' => $user->getEmail(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send notification email', [
                'notification_id' => $notification->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build email HTML content
     */
    private function buildEmailHtml(Notification $notification): string
    {
        $actionButton = '';
        if ($notification->getActionUrl()) {
            $actionButton = sprintf(
                '<p style="margin-top: 20px;"><a href="%s" style="display: inline-block; padding: 12px 24px; background-color: #8B5CF6; color: white; text-decoration: none; border-radius: 6px;">%s</a></p>',
                htmlspecialchars($notification->getActionUrl()),
                htmlspecialchars($notification->getActionLabel() ?? 'View Details')
            );
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #1a1a2e; color: #e0e0e0; padding: 40px 20px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #16213e; border-radius: 12px; padding: 32px; border: 1px solid #0f3460;">
        <div style="text-align: center; margin-bottom: 24px;">
            <h1 style="color: #8B5CF6; margin: 0; font-size: 24px;">{$this->appName}</h1>
        </div>
        <h2 style="color: #ffffff; margin: 0 0 16px 0; font-size: 20px;">{$notification->getTitle()}</h2>
        <p style="color: #b0b0b0; line-height: 1.6; margin: 0;">{$notification->getMessage()}</p>
        {$actionButton}
        <hr style="border: none; border-top: 1px solid #0f3460; margin: 32px 0;">
        <p style="color: #666; font-size: 12px; margin: 0; text-align: center;">
            You received this email because you have notifications enabled for your {$this->appName} account.
        </p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Check if user should receive notification
     */
    private function shouldNotify(User $user, string $notificationType): bool
    {
        $settings = $user->getNotificationSettings() ?? [];

        // Default to enabled if not set
        return $settings[$notificationType] ?? true;
    }

    /**
     * Get unread notifications count for a user
     */
    public function getUnreadCount(User $user): int
    {
        return $this->notificationRepository->countUnreadByUser($user);
    }

    /**
     * Get recent notifications for a user
     */
    public function getRecentNotifications(User $user, int $limit = 10): array
    {
        return $this->notificationRepository->findByUser($user, $limit);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Notification $notification): void
    {
        if (!$notification->isRead()) {
            $notification->markAsRead();
            $this->entityManager->flush();
        }
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(User $user): void
    {
        $this->notificationRepository->markAllAsRead($user);
    }

    private function save(Notification $notification): void
    {
        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }
}
