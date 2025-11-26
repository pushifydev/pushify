<?php

namespace App\Service;

use App\Entity\Team;
use App\Entity\TeamInvitation;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Repository\TeamInvitationRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TeamService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TeamRepository $teamRepository,
        private TeamMemberRepository $memberRepository,
        private TeamInvitationRepository $invitationRepository,
        private UserRepository $userRepository,
        private EmailService $emailService,
        private LoggerInterface $logger,
        private ActivityLogService $activityLogService,
    ) {
    }

    /**
     * Create a new team
     */
    public function createTeam(User $owner, string $name, ?string $description = null): Team
    {
        $team = new Team();
        $team->setOwner($owner);
        $team->setName($name);
        $team->setDescription($description);
        $team->setSlug($team->generateSlug());

        // Ensure unique slug
        while (!$this->teamRepository->isSlugUnique($team->getSlug())) {
            $team->setSlug($team->generateSlug());
        }

        $this->entityManager->persist($team);
        $this->entityManager->flush();

        // Log activity
        $this->activityLogService->logTeamCreated($team, $owner);

        return $team;
    }

    /**
     * Update team details
     */
    public function updateTeam(Team $team, string $name, ?string $description = null): Team
    {
        $team->setName($name);
        $team->setDescription($description);
        $this->entityManager->flush();

        return $team;
    }

    /**
     * Delete a team
     */
    public function deleteTeam(Team $team): void
    {
        $this->entityManager->remove($team);
        $this->entityManager->flush();
    }

    /**
     * Invite a user to a team
     */
    public function inviteUser(Team $team, string $email, string $role, User $invitedBy): TeamInvitation
    {
        // Check if already a member
        $existingUser = $this->userRepository->findOneBy(['email' => strtolower(trim($email))]);
        if ($existingUser && ($team->hasMember($existingUser))) {
            throw new \InvalidArgumentException('User is already a member of this team');
        }

        // Check for existing pending invitation
        if ($this->invitationRepository->hasPendingInvitation($team, $email)) {
            throw new \InvalidArgumentException('There is already a pending invitation for this email');
        }

        $invitation = new TeamInvitation();
        $invitation->setTeam($team);
        $invitation->setEmail($email);
        $invitation->setRole($role);
        $invitation->setInvitedBy($invitedBy);

        if ($existingUser) {
            $invitation->setInvitedUser($existingUser);
        }

        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        // Log activity
        $this->activityLogService->logTeamInvite($team, $invitedBy, $email, $role);

        // Send invitation email
        $this->sendInvitationEmail($invitation);

        return $invitation;
    }

    /**
     * Accept an invitation
     */
    public function acceptInvitation(TeamInvitation $invitation, User $user): TeamMember
    {
        if (!$invitation->canBeAccepted()) {
            throw new \InvalidArgumentException('This invitation cannot be accepted');
        }

        // Check if already a member
        if ($invitation->getTeam()->hasMember($user)) {
            $invitation->cancel();
            $this->entityManager->flush();
            throw new \InvalidArgumentException('You are already a member of this team');
        }

        // Create team member
        $member = new TeamMember();
        $member->setTeam($invitation->getTeam());
        $member->setUser($user);
        $member->setRole($invitation->getRole());

        $invitation->accept();
        $invitation->setInvitedUser($user);

        $this->entityManager->persist($member);
        $this->entityManager->flush();

        // Log activity
        $this->activityLogService->logTeamJoin($invitation->getTeam(), $user, $invitation->getRole());

        return $member;
    }

    /**
     * Decline an invitation
     */
    public function declineInvitation(TeamInvitation $invitation): void
    {
        if (!$invitation->isPending()) {
            throw new \InvalidArgumentException('This invitation is no longer pending');
        }

        $invitation->decline();
        $this->entityManager->flush();
    }

    /**
     * Cancel an invitation
     */
    public function cancelInvitation(TeamInvitation $invitation): void
    {
        if (!$invitation->isPending()) {
            throw new \InvalidArgumentException('This invitation is no longer pending');
        }

        $invitation->cancel();
        $this->entityManager->flush();
    }

    /**
     * Update member role
     */
    public function updateMemberRole(TeamMember $member, string $role): void
    {
        $member->setRole($role);
        $this->entityManager->flush();
    }

    /**
     * Remove a member from team
     */
    public function removeMember(TeamMember $member): void
    {
        $this->entityManager->remove($member);
        $this->entityManager->flush();
    }

    /**
     * Leave a team
     */
    public function leaveTeam(Team $team, User $user): void
    {
        if ($team->getOwner() === $user) {
            throw new \InvalidArgumentException('Team owner cannot leave the team. Transfer ownership or delete the team.');
        }

        $member = $this->memberRepository->findByTeamAndUser($team, $user);
        if (!$member) {
            throw new \InvalidArgumentException('You are not a member of this team');
        }

        // Log activity before removing
        $this->activityLogService->logTeamLeave($team, $user);

        $this->entityManager->remove($member);
        $this->entityManager->flush();
    }

    /**
     * Transfer team ownership
     */
    public function transferOwnership(Team $team, User $newOwner): void
    {
        $currentOwner = $team->getOwner();

        // New owner must be a team member
        $member = $this->memberRepository->findByTeamAndUser($team, $newOwner);
        if (!$member && $team->getOwner() !== $newOwner) {
            throw new \InvalidArgumentException('New owner must be a team member');
        }

        // Remove new owner from members (they become owner)
        if ($member) {
            $this->entityManager->remove($member);
        }

        // Add current owner as admin member
        $oldOwnerMember = new TeamMember();
        $oldOwnerMember->setTeam($team);
        $oldOwnerMember->setUser($currentOwner);
        $oldOwnerMember->setRole(TeamMember::ROLE_ADMIN);
        $this->entityManager->persist($oldOwnerMember);

        // Transfer ownership
        $team->setOwner($newOwner);
        $this->entityManager->flush();
    }

    /**
     * Get user's role in team
     */
    public function getUserRole(Team $team, User $user): ?string
    {
        if ($team->getOwner() === $user) {
            return 'owner';
        }

        $member = $this->memberRepository->findByTeamAndUser($team, $user);
        return $member?->getRole();
    }

    /**
     * Check if user can manage team
     */
    public function canManageTeam(Team $team, User $user): bool
    {
        if ($team->getOwner() === $user) {
            return true;
        }

        $member = $this->memberRepository->findByTeamAndUser($team, $user);
        return $member?->canManageTeam() ?? false;
    }

    /**
     * Send invitation email via queue
     */
    private function sendInvitationEmail(TeamInvitation $invitation): void
    {
        try {
            $this->emailService->sendTeamInvitation(
                email: $invitation->getEmail(),
                teamName: $invitation->getTeam()->getName(),
                inviterName: $invitation->getInvitedBy()->getName(),
                role: $invitation->getRoleLabel(),
                token: $invitation->getToken(),
                expiresAt: $invitation->getExpiresAt()
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to queue invitation email', [
                'email' => $invitation->getEmail(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cleanup expired invitations
     */
    public function cleanupExpiredInvitations(): int
    {
        return $this->invitationRepository->markExpiredInvitations();
    }
}
