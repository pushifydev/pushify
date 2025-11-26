<?php

namespace App\Message;

/**
 * Message for sending emails asynchronously via RabbitMQ
 */
class SendEmailMessage
{
    public const TYPE_TEAM_INVITATION = 'team_invitation';
    public const TYPE_DEPLOYMENT_SUCCESS = 'deployment_success';
    public const TYPE_DEPLOYMENT_FAILED = 'deployment_failed';
    public const TYPE_WELCOME = 'welcome';
    public const TYPE_PASSWORD_RESET = 'password_reset';
    public const TYPE_GENERIC = 'generic';

    public function __construct(
        private string $to,
        private string $subject,
        private string $type,
        private array $data = [],
        private ?string $htmlBody = null,
        private ?string $textBody = null,
    ) {
    }

    public function getTo(): string
    {
        return $this->to;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getHtmlBody(): ?string
    {
        return $this->htmlBody;
    }

    public function getTextBody(): ?string
    {
        return $this->textBody;
    }

    /**
     * Create a team invitation email message
     */
    public static function teamInvitation(
        string $email,
        string $teamName,
        string $inviterName,
        string $role,
        string $invitationUrl,
        string $expiresAt
    ): self {
        return new self(
            to: $email,
            subject: "You've been invited to join {$teamName} on Pushify",
            type: self::TYPE_TEAM_INVITATION,
            data: [
                'team_name' => $teamName,
                'inviter_name' => $inviterName,
                'role' => $role,
                'invitation_url' => $invitationUrl,
                'expires_at' => $expiresAt,
            ]
        );
    }

    /**
     * Create a deployment success email message
     */
    public static function deploymentSuccess(
        string $email,
        string $projectName,
        string $deploymentUrl,
        string $commitHash,
        string $branch
    ): self {
        return new self(
            to: $email,
            subject: "Deployment successful: {$projectName}",
            type: self::TYPE_DEPLOYMENT_SUCCESS,
            data: [
                'project_name' => $projectName,
                'deployment_url' => $deploymentUrl,
                'commit_hash' => $commitHash,
                'branch' => $branch,
            ]
        );
    }

    /**
     * Create a deployment failed email message
     */
    public static function deploymentFailed(
        string $email,
        string $projectName,
        string $errorMessage,
        string $commitHash,
        string $branch
    ): self {
        return new self(
            to: $email,
            subject: "Deployment failed: {$projectName}",
            type: self::TYPE_DEPLOYMENT_FAILED,
            data: [
                'project_name' => $projectName,
                'error_message' => $errorMessage,
                'commit_hash' => $commitHash,
                'branch' => $branch,
            ]
        );
    }

    /**
     * Create a generic email message with custom HTML body
     */
    public static function generic(string $email, string $subject, string $htmlBody, ?string $textBody = null): self
    {
        return new self(
            to: $email,
            subject: $subject,
            type: self::TYPE_GENERIC,
            data: [],
            htmlBody: $htmlBody,
            textBody: $textBody
        );
    }
}
