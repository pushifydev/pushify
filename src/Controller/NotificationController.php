<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private NotificationService $notificationService,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'app_notifications')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $notifications = $this->notificationRepository->findByUser($user, 50);
        $unreadCount = $this->notificationRepository->countUnreadByUser($user);

        return $this->render('dashboard/notifications/index.html.twig', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    #[Route('/recent', name: 'app_notifications_recent', methods: ['GET'])]
    public function recent(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $notifications = $this->notificationRepository->findByUser($user, 10);
        $unreadCount = $this->notificationRepository->countUnreadByUser($user);

        return $this->json([
            'notifications' => array_map(fn($n) => [
                'id' => $n->getId(),
                'type' => $n->getType(),
                'title' => $n->getTitle(),
                'message' => $n->getMessage(),
                'actionUrl' => $n->getActionUrl(),
                'actionLabel' => $n->getActionLabel(),
                'isRead' => $n->isRead(),
                'timeAgo' => $n->getTimeAgo(),
                'typeBadgeClass' => $n->getTypeBadgeClass(),
            ], $notifications),
            'unreadCount' => $unreadCount,
        ]);
    }

    #[Route('/count', name: 'app_notifications_count', methods: ['GET'])]
    public function count(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'count' => $this->notificationRepository->countUnreadByUser($user),
        ]);
    }

    #[Route('/{id}/read', name: 'app_notification_read', methods: ['POST'])]
    public function markAsRead(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $notification = $this->notificationRepository->find($id);

        if (!$notification || $notification->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Notification not found'], 404);
        }

        $this->notificationService->markAsRead($notification);

        return $this->json(['success' => true]);
    }

    #[Route('/read-all', name: 'app_notifications_read_all', methods: ['POST'])]
    public function markAllAsRead(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->notificationService->markAllAsRead($user);

        return $this->json(['success' => true]);
    }

    #[Route('/settings', name: 'app_notifications_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $settings = [
                'email_enabled' => $request->request->getBoolean('email_enabled'),
                'deployment_started' => $request->request->getBoolean('deployment_started'),
                'deployment_success' => $request->request->getBoolean('deployment_success'),
                'deployment_failed' => $request->request->getBoolean('deployment_failed'),
                'server_offline' => $request->request->getBoolean('server_offline'),
                'server_online' => $request->request->getBoolean('server_online'),
                'ssl_expiring' => $request->request->getBoolean('ssl_expiring'),
            ];

            $user->setNotificationSettings($settings);
            $this->entityManager->flush();

            $this->addFlash('success', 'Notification settings updated');
            return $this->redirectToRoute('app_notifications_settings');
        }

        $settings = $user->getNotificationSettings() ?? User::getDefaultNotificationSettings();

        return $this->render('dashboard/notifications/settings.html.twig', [
            'settings' => $settings,
        ]);
    }
}
