<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\Deployment;
use App\Entity\Domain;
use App\Entity\Project;
use App\Entity\Server;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Entity\Webhook;
use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ActivityLogService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityLogRepository $activityLogRepository,
        private RequestStack $requestStack
    ) {
    }

    /**
     * Log an activity
     */
    public function log(
        string $action,
        string $description,
        ?User $user = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $entityName = null,
        ?Project $project = null,
        ?Team $team = null,
        ?array $metadata = null
    ): ActivityLog {
        $activity = new ActivityLog();
        $activity->setAction($action);
        $activity->setDescription($description);
        $activity->setUser($user);
        $activity->setEntityType($entityType);
        $activity->setEntityId($entityId);
        $activity->setEntityName($entityName);
        $activity->setProject($project);
        $activity->setTeam($team);
        $activity->setMetadata($metadata);

        // Capture request info
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $activity->setIpAddress($request->getClientIp());
            $activity->setUserAgent(substr($request->headers->get('User-Agent', ''), 0, 500));
        }

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }

    // ==========================================
    // Project Activities
    // ==========================================

    public function logProjectCreated(Project $project, User $user): ActivityLog
    {
        return $this->log(
            ActivityLog::ACTION_CREATE,
            "Created project \"{$project->getName()}\"",
            $user,
            ActivityLog::ENTITY_PROJECT,
            $project->getId(),
            $project->getName(),
            $project,
            $project->getTeam(),
            ['repository' => $project->getRepositoryName(), 'framework' => $project->getFramework()]
        );
    }

    public function logProjectUpdated(Project $project, User $user, array $changes = []): ActivityLog
    {
        return $this->log(
            ActivityLog::ACTION_UPDATE,
            "Updated project \"{$project->getName()}\"",
            $user,
            ActivityLog::ENTITY_PROJECT,
            $project->getId(),
            $project->getName(),
            $project,
            $project->getTeam(),
            ['changes' => $changes]
        );
    }

    public function logProjectDeleted(string $projectName, User $user, ?Team $team = null): ActivityLog
    {
        return $this->log(
            ActivityLog::ACTION_DELETE,
            "Deleted project \"{$projectName}\"",
            $user,
            ActivityLog::ENTITY_PROJECT,
            null,
            $projectName,
            null,
            $team
        );
    }

    public function logProjectSettings(Project $project, User $user, string $setting): ActivityLog
    {
        return $this->log(
            ActivityLog::ACTION_SETTINGS,
            "Changed {$setting} settings for \"{$project->getName()}\"",
            $user,
            ActivityLog::ENTITY_PROJECT,
            $project->getId(),
            $project->getName(),
            $project,
            $project->getTeam(),
            ['setting' => $setting]
        );
    }

    // ==========================================
    // Deployment Activities
    // ==========================================

    public function logDeploymentStarted(Deployment $deployment, User $user): ActivityLog
    {
        $project = $deployment->getProject();
        $trigger = $deployment->getTriggerLabel();

        return $this->log(
            ActivityLog::ACTION_DEPLOY,
            "Started deployment #{$deployment->getId()} for \"{$project->getName()}\" ({$trigger})",
            $user,
            ActivityLog::ENTITY_DEPLOYMENT,
            $deployment->getId(),
            "#{$deployment->getId()}",
            $project,
            $project->getTeam(),
            [
                'trigger' => $deployment->getTrigger(),
                'branch' => $deployment->getBranch(),
                'commit' => $deployment->getShortCommitHash(),
            ]
        );
    }

    public function logDeploymentSuccess(Deployment $deployment): ActivityLog
    {
        $project = $deployment->getProject();
        $user = $deployment->getTriggeredBy();

        return $this->log(
            ActivityLog::ACTION_DEPLOY,
            "Deployment #{$deployment->getId()} succeeded for \"{$project->getName()}\"",
            $user,
            ActivityLog::ENTITY_DEPLOYMENT,
            $deployment->getId(),
            "#{$deployment->getId()}",
            $project,
            $project->getTeam(),
            [
                'status' => 'success',
                'duration' => $deployment->getTotalDuration(),
                'url' => $deployment->getDeploymentUrl(),
            ]
        );
    }

    public function logDeploymentFailed(Deployment $deployment): ActivityLog
    {
        $project = $deployment->getProject();
        $user = $deployment->getTriggeredBy();

        return $this->log(
            ActivityLog::ACTION_DEPLOY,
            "Deployment #{$deployment->getId()} failed for \"{$project->getName()}\"",
            $user,
            ActivityLog::ENTITY_DEPLOYMENT,
            $deployment->getId(),
            "#{$deployment->getId()}",
            $project,
            $project->getTeam(),
            [
                'status' => 'failed',
                'error' => substr($deployment->getErrorMessage() ?? '', 0, 200),
            ]
        );
    }

    public function logRollback(Deployment $deployment, User $user): ActivityLog
    {
        $project = $deployment->getProject();
        $rollbackFrom = $deployment->getRollbackFrom();

        return $this->log(
            ActivityLog::ACTION_ROLLBACK,
            "Rolled back \"{$project->getName()}\" to deployment #{$deployment->getCommitMessage()}",
            $user,
            ActivityLog::ENTITY_DEPLOYMENT,
            $deployment->getId(),
            "#{$deployment->getId()}",
            $project,
            $project->getTeam(),
            [
                'rollback_from' => $rollbackFrom?->getId(),
                'target_commit' => $deployment->getShortCommitHash(),
            ]
        );
    }

    // ==========================================
    // Server Activities
    // ==========================================

    public function logServerProvisioned(Server $server, User $user): ActivityLog
    {
        return $this->log(
            ActivityLog::ACTION_SERVER_PROVISION,
            "Provisioned server \"{$server->getName()}\"",
            $user,
            ActivityLog::ENTITY_SERVER,
            $server->getId(),
            $server->getName(),
            null,
            null,
            [
                'provider' => $server->getProvider(),
                'region' => $server->getRegion(),
                'ip' => $server->getIpAddress(),
            ]
        );
    }

    public function logServerDeleted(string $serverName, User $user): ActivityLog
    {
        return $this->log(
            ActivityLog::ACTION_SERVER_DELETE,
            "Deleted server \"{$serverName}\"",
            $user,
            ActivityLog::ENTITY_SERVER,
            null,
            $serverName
        );
    }

    // ==========================================
    // Domain Activities
    // ==========================================

    public function logDomainAdded(Domain $domain, User $user): ActivityLog
    {
        $project = $domain->getProject();

        return $this->log(
            ActivityLog::ACTION_DOMAIN_ADD,
            "Added domain \"{$domain->getDomain()}\" to \"{$project?->getName()}\"",
            $user,
            ActivityLog::ENTITY_DOMAIN,
            $domain->getId(),
            $domain->getDomain(),
            $project,
            $project?->getTeam()
        );
    }

    public function logDomainVerified(Domain $domain, User $user): ActivityLog
    {
        $project = $domain->getProject();

        return $this->log(
            ActivityLog::ACTION_DOMAIN_VERIFY,
            "Verified domain \"{$domain->getDomain()}\"",
            $user,
            ActivityLog::ENTITY_DOMAIN,
            $domain->getId(),
            $domain->getDomain(),
            $project,
            $project?->getTeam()
        );
    }

    public function logSSLIssued(Domain $domain, User $user): ActivityLog
    {
        $project = $domain->getProject();

        return $this->log(
            ActivityLog::ACTION_SSL_ISSUE,
            "Issued SSL certificate for \"{$domain->getDomain()}\"",
            $user,
            ActivityLog::ENTITY_DOMAIN,
            $domain->getId(),
            $domain->getDomain(),
            $project,
            $project?->getTeam()
        );
    }

    // ==========================================
    // Team Activities
    // ==========================================

    public function logTeamCreated(Team $team, User $user): ActivityLog
    {
        return $this->log(
            ActivityLog::ACTION_CREATE,
            "Created team \"{$team->getName()}\"",
            $user,
            ActivityLog::ENTITY_TEAM,
            $team->getId(),
            $team->getName(),
            null,
            $team
        );
    }

    public function logTeamInvite(Team $team, User $inviter, string $inviteeEmail, string $role): ActivityLog
    {
        return $this->log(
            ActivityLog::ACTION_INVITE,
            "Invited {$inviteeEmail} to team \"{$team->getName()}\" as {$role}",
            $inviter,
            ActivityLog::ENTITY_TEAM,
            $team->getId(),
            $team->getName(),
            null,
            $team,
            ['invitee_email' => $inviteeEmail, 'role' => $role]
        );
    }

    public function logTeamJoin(Team $team, User $user, string $role): ActivityLog
    {
        return $this->log(
            ActivityLog::ACTION_JOIN,
            "{$user->getEmail()} joined team \"{$team->getName()}\" as {$role}",
            $user,
            ActivityLog::ENTITY_TEAM,
            $team->getId(),
            $team->getName(),
            null,
            $team,
            ['role' => $role]
        );
    }

    public function logTeamLeave(Team $team, User $user): ActivityLog
    {
        return $this->log(
            ActivityLog::ACTION_LEAVE,
            "{$user->getEmail()} left team \"{$team->getName()}\"",
            $user,
            ActivityLog::ENTITY_TEAM,
            $team->getId(),
            $team->getName(),
            null,
            $team
        );
    }

    public function logTeamMemberRoleChanged(Team $team, User $admin, User $member, string $newRole): ActivityLog
    {
        return $this->log(
            ActivityLog::ACTION_UPDATE,
            "Changed {$member->getEmail()}'s role to {$newRole} in team \"{$team->getName()}\"",
            $admin,
            ActivityLog::ENTITY_TEAM_MEMBER,
            null,
            $member->getEmail(),
            null,
            $team,
            ['new_role' => $newRole]
        );
    }

    // ==========================================
    // Webhook Activities
    // ==========================================

    public function logWebhookCreated(Webhook $webhook, User $user): ActivityLog
    {
        return $this->log(
            ActivityLog::ACTION_CREATE,
            "Created webhook \"{$webhook->getName()}\"",
            $user,
            ActivityLog::ENTITY_WEBHOOK,
            $webhook->getId(),
            $webhook->getName(),
            $webhook->getProject(),
            null,
            ['preset' => $webhook->getPreset(), 'events' => $webhook->getEvents()]
        );
    }

    public function logWebhookTriggered(Webhook $webhook, string $event, bool $success): ActivityLog
    {
        $status = $success ? 'successfully' : 'with errors';

        return $this->log(
            ActivityLog::ACTION_WEBHOOK_TRIGGER,
            "Webhook \"{$webhook->getName()}\" triggered {$status} for {$event}",
            $webhook->getUser(),
            ActivityLog::ENTITY_WEBHOOK,
            $webhook->getId(),
            $webhook->getName(),
            $webhook->getProject(),
            null,
            ['event' => $event, 'success' => $success]
        );
    }

    // ==========================================
    // User Activities
    // ==========================================

    public function logUserLogin(User $user): ActivityLog
    {
        return $this->log(
            ActivityLog::ACTION_LOGIN,
            "{$user->getEmail()} logged in",
            $user,
            ActivityLog::ENTITY_USER,
            $user->getId(),
            $user->getEmail()
        );
    }

    public function logUserLogout(User $user): ActivityLog
    {
        return $this->log(
            ActivityLog::ACTION_LOGOUT,
            "{$user->getEmail()} logged out",
            $user,
            ActivityLog::ENTITY_USER,
            $user->getId(),
            $user->getEmail()
        );
    }

    // ==========================================
    // Query Methods
    // ==========================================

    public function getRecentForUser(User $user, int $limit = 50): array
    {
        return $this->activityLogRepository->findVisibleToUser($user, $limit);
    }

    public function getForProject(Project $project, int $limit = 50): array
    {
        return $this->activityLogRepository->findByProject($project, $limit);
    }

    public function getForTeam(Team $team, int $limit = 50): array
    {
        return $this->activityLogRepository->findByTeam($team, $limit);
    }

    public function getTimeline(User $user, int $days = 7): array
    {
        return $this->activityLogRepository->getTimelineForUser($user, $days);
    }
}
