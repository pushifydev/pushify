<?php

namespace App\Controller;

use App\Entity\Team;
use App\Entity\TeamInvitation;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Repository\TeamInvitationRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRepository;
use App\Service\TeamService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/teams')]
#[IsGranted('ROLE_USER')]
class TeamController extends AbstractController
{
    public function __construct(
        private TeamRepository $teamRepository,
        private TeamMemberRepository $memberRepository,
        private TeamInvitationRepository $invitationRepository,
        private TeamService $teamService,
    ) {
    }

    #[Route('', name: 'app_teams', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $teams = $this->teamRepository->findByUser($user);
        $pendingInvitations = $this->invitationRepository->findPendingByEmail($user->getEmail());

        return $this->render('dashboard/teams/index.html.twig', [
            'teams' => $teams,
            'pendingInvitations' => $pendingInvitations,
        ]);
    }

    #[Route('/new', name: 'app_team_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $description = $request->request->get('description');

            if (empty($name)) {
                $this->addFlash('error', 'Team name is required');
                return $this->redirectToRoute('app_team_new');
            }

            try {
                $team = $this->teamService->createTeam($user, $name, $description);
                $this->addFlash('success', 'Team created successfully!');
                return $this->redirectToRoute('app_team_show', ['slug' => $team->getSlug()]);
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('dashboard/teams/new.html.twig');
    }

    #[Route('/{slug}', name: 'app_team_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $this->teamRepository->findBySlugAndUser($slug, $user);

        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        $members = $this->memberRepository->findByTeam($team);
        $pendingInvitations = $this->invitationRepository->findPendingByTeam($team);
        $canManage = $this->teamService->canManageTeam($team, $user);

        return $this->render('dashboard/teams/show.html.twig', [
            'team' => $team,
            'members' => $members,
            'pendingInvitations' => $pendingInvitations,
            'canManage' => $canManage,
            'userRole' => $this->teamService->getUserRole($team, $user),
        ]);
    }

    #[Route('/{slug}/settings', name: 'app_team_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, string $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $this->teamRepository->findBySlugAndUser($slug, $user);

        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        if (!$this->teamService->canManageTeam($team, $user)) {
            throw $this->createAccessDeniedException('You cannot manage this team');
        }

        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $description = $request->request->get('description');

            if (empty($name)) {
                $this->addFlash('error', 'Team name is required');
            } else {
                $this->teamService->updateTeam($team, $name, $description);
                $this->addFlash('success', 'Team settings updated!');
            }

            return $this->redirectToRoute('app_team_settings', ['slug' => $team->getSlug()]);
        }

        return $this->render('dashboard/teams/settings.html.twig', [
            'team' => $team,
        ]);
    }

    #[Route('/{slug}/invite', name: 'app_team_invite', methods: ['POST'])]
    public function invite(Request $request, string $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $this->teamRepository->findBySlugAndUser($slug, $user);

        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        if (!$this->teamService->canManageTeam($team, $user)) {
            throw $this->createAccessDeniedException('You cannot invite members to this team');
        }

        $email = $request->request->get('email');
        $role = $request->request->get('role', TeamMember::ROLE_DEVELOPER);

        if (empty($email)) {
            $this->addFlash('error', 'Email is required');
            return $this->redirectToRoute('app_team_show', ['slug' => $slug]);
        }

        try {
            $this->teamService->inviteUser($team, $email, $role, $user);
            $this->addFlash('success', "Invitation sent to {$email}");
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_team_show', ['slug' => $slug]);
    }

    #[Route('/{slug}/member/{memberId}/role', name: 'app_team_member_role', methods: ['POST'])]
    public function updateMemberRole(Request $request, string $slug, int $memberId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $this->teamRepository->findBySlugAndUser($slug, $user);

        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        if (!$this->teamService->canManageTeam($team, $user)) {
            throw $this->createAccessDeniedException('You cannot manage this team');
        }

        $member = $this->memberRepository->find($memberId);
        if (!$member || $member->getTeam() !== $team) {
            throw $this->createNotFoundException('Member not found');
        }

        $role = $request->request->get('role');
        if (in_array($role, TeamMember::ROLES)) {
            $this->teamService->updateMemberRole($member, $role);
            $this->addFlash('success', 'Member role updated');
        }

        return $this->redirectToRoute('app_team_show', ['slug' => $slug]);
    }

    #[Route('/{slug}/member/{memberId}/remove', name: 'app_team_member_remove', methods: ['POST'])]
    public function removeMember(Request $request, string $slug, int $memberId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $this->teamRepository->findBySlugAndUser($slug, $user);

        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        if (!$this->teamService->canManageTeam($team, $user)) {
            throw $this->createAccessDeniedException('You cannot manage this team');
        }

        $member = $this->memberRepository->find($memberId);
        if (!$member || $member->getTeam() !== $team) {
            throw $this->createNotFoundException('Member not found');
        }

        if ($this->isCsrfTokenValid('remove-member-' . $memberId, $request->request->get('_token'))) {
            $this->teamService->removeMember($member);
            $this->addFlash('success', 'Member removed from team');
        }

        return $this->redirectToRoute('app_team_show', ['slug' => $slug]);
    }

    #[Route('/{slug}/invitation/{invitationId}/cancel', name: 'app_team_invitation_cancel', methods: ['POST'])]
    public function cancelInvitation(Request $request, string $slug, int $invitationId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $this->teamRepository->findBySlugAndUser($slug, $user);

        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        if (!$this->teamService->canManageTeam($team, $user)) {
            throw $this->createAccessDeniedException('You cannot manage this team');
        }

        $invitation = $this->invitationRepository->find($invitationId);
        if (!$invitation || $invitation->getTeam() !== $team) {
            throw $this->createNotFoundException('Invitation not found');
        }

        if ($this->isCsrfTokenValid('cancel-invitation-' . $invitationId, $request->request->get('_token'))) {
            $this->teamService->cancelInvitation($invitation);
            $this->addFlash('success', 'Invitation cancelled');
        }

        return $this->redirectToRoute('app_team_show', ['slug' => $slug]);
    }

    #[Route('/{slug}/leave', name: 'app_team_leave', methods: ['POST'])]
    public function leave(Request $request, string $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $this->teamRepository->findBySlugAndUser($slug, $user);

        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        if ($this->isCsrfTokenValid('leave-team-' . $team->getId(), $request->request->get('_token'))) {
            try {
                $this->teamService->leaveTeam($team, $user);
                $this->addFlash('success', 'You have left the team');
                return $this->redirectToRoute('app_teams');
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_team_show', ['slug' => $slug]);
    }

    #[Route('/{slug}/delete', name: 'app_team_delete', methods: ['POST'])]
    public function delete(Request $request, string $slug): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $this->teamRepository->findBySlugAndUser($slug, $user);

        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        if ($team->getOwner() !== $user) {
            throw $this->createAccessDeniedException('Only the team owner can delete the team');
        }

        if ($this->isCsrfTokenValid('delete-team-' . $team->getId(), $request->request->get('_token'))) {
            $this->teamService->deleteTeam($team);
            $this->addFlash('success', 'Team deleted successfully');
            return $this->redirectToRoute('app_teams');
        }

        return $this->redirectToRoute('app_team_settings', ['slug' => $slug]);
    }

    #[Route('/invite/{token}', name: 'app_team_invitation_respond', methods: ['GET', 'POST'], priority: 10)]
    public function respondToInvitation(Request $request, string $token): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $invitation = $this->invitationRepository->findByToken($token);

        if (!$invitation) {
            throw $this->createNotFoundException('Invitation not found');
        }

        if ($invitation->isExpired()) {
            $this->addFlash('error', 'This invitation has expired');
            return $this->redirectToRoute('app_teams');
        }

        if (!$invitation->isPending()) {
            $this->addFlash('info', 'This invitation has already been ' . $invitation->getStatusLabel());
            return $this->redirectToRoute('app_teams');
        }

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            try {
                if ($action === 'accept') {
                    $this->teamService->acceptInvitation($invitation, $user);
                    $this->addFlash('success', 'You have joined ' . $invitation->getTeam()->getName());
                    return $this->redirectToRoute('app_team_show', ['slug' => $invitation->getTeam()->getSlug()]);
                } elseif ($action === 'decline') {
                    $this->teamService->declineInvitation($invitation);
                    $this->addFlash('info', 'Invitation declined');
                    return $this->redirectToRoute('app_teams');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('dashboard/teams/invitation.html.twig', [
            'invitation' => $invitation,
        ]);
    }
}
