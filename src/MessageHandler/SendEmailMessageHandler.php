<?php

namespace App\MessageHandler;

use App\Message\SendEmailMessage;
use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Handler for processing email messages from RabbitMQ queue
 */
class SendEmailMessageHandler implements ConsumerInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $fromEmail = 'noreply@pushify.app',
        private string $fromName = 'Pushify',
    ) {
    }

    /**
     * Handle message from RabbitMQ
     */
    public function execute(AMQPMessage $msg): int
    {
        try {
            $message = unserialize($msg->getBody());

            if (!$message instanceof SendEmailMessage) {
                $this->logger->error('Invalid message type received');
                return self::MSG_REJECT;
            }

            $this->processEmail($message);
            return self::MSG_ACK;
        } catch (\Exception $e) {
            $this->logger->error('Failed to process email message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'class' => get_class($e),
            ]);
            // Don't requeue to prevent infinite loop - reject permanently
            return self::MSG_REJECT;
        }
    }

    /**
     * Process and send the email
     */
    private function processEmail(SendEmailMessage $message): void
    {
        try {
            $email = (new Email())
                ->from("{$this->fromName} <{$this->fromEmail}>")
                ->to($message->getTo())
                ->subject($message->getSubject());

            // Get HTML body based on message type
            $htmlBody = $this->renderEmailBody($message);
            $email->html($htmlBody);

            // Add text body if available
            if ($message->getTextBody()) {
                $email->text($message->getTextBody());
            }

            $this->mailer->send($email);

            $this->logger->info('Email sent successfully', [
                'to' => $message->getTo(),
                'subject' => $message->getSubject(),
                'type' => $message->getType(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send email', [
                'to' => $message->getTo(),
                'subject' => $message->getSubject(),
                'type' => $message->getType(),
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to let messenger handle retry logic
        }
    }

    /**
     * Render email body based on message type
     */
    private function renderEmailBody(SendEmailMessage $message): string
    {
        // If custom HTML body is provided, use it
        if ($message->getHtmlBody()) {
            return $message->getHtmlBody();
        }

        $data = $message->getData();

        return match ($message->getType()) {
            SendEmailMessage::TYPE_TEAM_INVITATION => $this->renderTeamInvitation($data),
            SendEmailMessage::TYPE_DEPLOYMENT_SUCCESS => $this->renderDeploymentSuccess($data),
            SendEmailMessage::TYPE_DEPLOYMENT_FAILED => $this->renderDeploymentFailed($data),
            default => $this->renderGenericEmail($data),
        };
    }

    private function renderTeamInvitation(array $data): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; padding: 20px 0; }
        .logo { font-size: 24px; font-weight: bold; color: #6366f1; }
        .content { background: #f9fafb; border-radius: 8px; padding: 30px; margin: 20px 0; }
        .button { display: inline-block; padding: 12px 24px; background: #6366f1; color: white !important; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .footer { text-align: center; color: #6b7280; font-size: 14px; margin-top: 30px; }
        .info { background: #fff; border: 1px solid #e5e7eb; border-radius: 6px; padding: 15px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Pushify</div>
        </div>
        <div class="content">
            <h2 style="margin-top: 0;">Team Invitation</h2>
            <p><strong>{$data['inviter_name']}</strong> has invited you to join <strong>{$data['team_name']}</strong> as a <strong>{$data['role']}</strong>.</p>

            <div class="info">
                <p style="margin: 0;"><strong>Team:</strong> {$data['team_name']}</p>
                <p style="margin: 5px 0 0;"><strong>Role:</strong> {$data['role']}</p>
            </div>

            <p style="text-align: center; margin: 30px 0;">
                <a href="{$data['invitation_url']}" class="button">Accept Invitation</a>
            </p>

            <p style="font-size: 14px; color: #6b7280;">This invitation will expire on {$data['expires_at']}.</p>
        </div>
        <div class="footer">
            <p>If you didn't expect this invitation, you can ignore this email.</p>
            <p>&copy; Pushify - Deploy with confidence</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function renderDeploymentSuccess(array $data): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; padding: 20px 0; }
        .logo { font-size: 24px; font-weight: bold; color: #6366f1; }
        .content { background: #f0fdf4; border: 1px solid #22c55e; border-radius: 8px; padding: 30px; margin: 20px 0; }
        .button { display: inline-block; padding: 12px 24px; background: #22c55e; color: white !important; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .footer { text-align: center; color: #6b7280; font-size: 14px; margin-top: 30px; }
        .success-icon { font-size: 48px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Pushify</div>
        </div>
        <div class="content">
            <div class="success-icon">✓</div>
            <h2 style="text-align: center; color: #22c55e;">Deployment Successful!</h2>
            <p><strong>{$data['project_name']}</strong> has been deployed successfully.</p>

            <p><strong>Branch:</strong> {$data['branch']}<br>
            <strong>Commit:</strong> {$data['commit_hash']}</p>

            <p style="text-align: center; margin: 30px 0;">
                <a href="{$data['deployment_url']}" class="button">View Deployment</a>
            </p>
        </div>
        <div class="footer">
            <p>&copy; Pushify - Deploy with confidence</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function renderDeploymentFailed(array $data): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; padding: 20px 0; }
        .logo { font-size: 24px; font-weight: bold; color: #6366f1; }
        .content { background: #fef2f2; border: 1px solid #ef4444; border-radius: 8px; padding: 30px; margin: 20px 0; }
        .footer { text-align: center; color: #6b7280; font-size: 14px; margin-top: 30px; }
        .error-icon { font-size: 48px; text-align: center; }
        .error-box { background: #fff; border: 1px solid #fca5a5; border-radius: 6px; padding: 15px; margin: 15px 0; font-family: monospace; font-size: 13px; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Pushify</div>
        </div>
        <div class="content">
            <div class="error-icon">✗</div>
            <h2 style="text-align: center; color: #ef4444;">Deployment Failed</h2>
            <p><strong>{$data['project_name']}</strong> deployment has failed.</p>

            <p><strong>Branch:</strong> {$data['branch']}<br>
            <strong>Commit:</strong> {$data['commit_hash']}</p>

            <div class="error-box">{$data['error_message']}</div>
        </div>
        <div class="footer">
            <p>&copy; Pushify - Deploy with confidence</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function renderGenericEmail(array $data): string
    {
        $content = $data['content'] ?? '';
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; padding: 20px 0; }
        .logo { font-size: 24px; font-weight: bold; color: #6366f1; }
        .content { background: #f9fafb; border-radius: 8px; padding: 30px; margin: 20px 0; }
        .footer { text-align: center; color: #6b7280; font-size: 14px; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Pushify</div>
        </div>
        <div class="content">
            {$content}
        </div>
        <div class="footer">
            <p>&copy; Pushify - Deploy with confidence</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
