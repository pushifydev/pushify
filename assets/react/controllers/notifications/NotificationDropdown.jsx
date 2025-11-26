import React, { useState, useEffect, useCallback } from 'react';
import * as Popover from '@radix-ui/react-popover';
import {
    BellIcon,
    CheckIcon,
    Cross2Icon,
    RocketIcon,
    ExclamationTriangleIcon,
    CheckCircledIcon,
    CrossCircledIcon,
    GearIcon
} from '@radix-ui/react-icons';

export default function NotificationDropdown({
    fetchUrl = '/dashboard/notifications/recent',
    countUrl = '/dashboard/notifications/count',
    markAllReadUrl = '/dashboard/notifications/read-all',
    viewAllUrl = '/dashboard/notifications',
    settingsUrl = '/dashboard/notifications/settings'
}) {
    const [open, setOpen] = useState(false);
    const [notifications, setNotifications] = useState([]);
    const [unreadCount, setUnreadCount] = useState(0);
    const [loading, setLoading] = useState(false);

    const fetchNotifications = useCallback(async () => {
        setLoading(true);
        try {
            const response = await fetch(fetchUrl);
            const data = await response.json();
            setNotifications(data.notifications || []);
            setUnreadCount(data.unreadCount || 0);
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
        } finally {
            setLoading(false);
        }
    }, [fetchUrl]);

    const fetchCount = useCallback(async () => {
        try {
            const response = await fetch(countUrl);
            const data = await response.json();
            setUnreadCount(data.count || 0);
        } catch (error) {
            console.error('Failed to fetch notification count:', error);
        }
    }, [countUrl]);

    const markAllAsRead = async () => {
        try {
            await fetch(markAllReadUrl, { method: 'POST' });
            setUnreadCount(0);
            setNotifications(prev => prev.map(n => ({ ...n, isRead: true })));
        } catch (error) {
            console.error('Failed to mark all as read:', error);
        }
    };

    // Fetch notifications when dropdown opens
    useEffect(() => {
        if (open) {
            fetchNotifications();
        }
    }, [open, fetchNotifications]);

    // Poll for count every 30 seconds
    useEffect(() => {
        fetchCount();
        const interval = setInterval(fetchCount, 30000);
        return () => clearInterval(interval);
    }, [fetchCount]);

    const getNotificationIcon = (type) => {
        switch (type) {
            case 'deployment_success':
                return <CheckCircledIcon className="w-4 h-4" />;
            case 'deployment_failed':
                return <CrossCircledIcon className="w-4 h-4" />;
            case 'deployment_started':
                return <RocketIcon className="w-4 h-4" />;
            case 'server_offline':
                return <ExclamationTriangleIcon className="w-4 h-4" />;
            default:
                return <BellIcon className="w-4 h-4" />;
        }
    };

    const getTypeColors = (type) => {
        switch (type) {
            case 'deployment_success':
            case 'server_online':
                return 'bg-green-500/20 text-green-400';
            case 'deployment_failed':
            case 'server_offline':
                return 'bg-red-500/20 text-red-400';
            case 'deployment_started':
                return 'bg-blue-500/20 text-blue-400';
            case 'domain_ssl_expiring':
                return 'bg-yellow-500/20 text-yellow-400';
            default:
                return 'bg-gray-500/20 text-gray-400';
        }
    };

    return (
        <Popover.Root open={open} onOpenChange={setOpen}>
            <Popover.Trigger asChild>
                <button
                    className="p-2 text-gray-400 hover:text-white hover:bg-gray-800 rounded-lg transition-colors relative"
                    aria-label="Notifications"
                >
                    <BellIcon className="w-5 h-5" />
                    {unreadCount > 0 && (
                        <span className="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center">
                            {unreadCount > 9 ? '9+' : unreadCount}
                        </span>
                    )}
                </button>
            </Popover.Trigger>

            <Popover.Portal>
                <Popover.Content
                    className="w-96 bg-gray-800 border border-gray-700 rounded-xl shadow-xl overflow-hidden z-50 animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95"
                    sideOffset={8}
                    align="end"
                >
                    {/* Header */}
                    <div className="px-4 py-3 border-b border-gray-700 flex items-center justify-between">
                        <h3 className="font-semibold text-white">Notifications</h3>
                        <div className="flex items-center gap-2">
                            <a
                                href={settingsUrl}
                                className="p-1.5 text-gray-400 hover:text-white hover:bg-gray-700 rounded-lg transition-colors"
                                title="Settings"
                            >
                                <GearIcon className="w-4 h-4" />
                            </a>
                            <a
                                href={viewAllUrl}
                                className="text-sm text-primary hover:text-primary/80"
                            >
                                View All
                            </a>
                        </div>
                    </div>

                    {/* Notification List */}
                    <div className="max-h-96 overflow-y-auto">
                        {loading ? (
                            <div className="p-6 text-center">
                                <div className="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin mx-auto"></div>
                                <p className="text-sm text-gray-400 mt-2">Loading...</p>
                            </div>
                        ) : notifications.length === 0 ? (
                            <div className="p-6 text-center text-gray-400">
                                <BellIcon className="w-8 h-8 mx-auto mb-2 text-gray-600" />
                                <p className="text-sm">No notifications</p>
                            </div>
                        ) : (
                            notifications.map((notification) => (
                                <a
                                    key={notification.id}
                                    href={notification.actionUrl || '#'}
                                    className={`block px-4 py-3 hover:bg-gray-700/50 transition-colors border-b border-gray-700/50 last:border-0 ${
                                        !notification.isRead ? 'bg-primary/5' : ''
                                    }`}
                                >
                                    <div className="flex items-start gap-3">
                                        <div className={`w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 ${getTypeColors(notification.type)}`}>
                                            {getNotificationIcon(notification.type)}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-white truncate">
                                                {notification.title}
                                            </p>
                                            <p className="text-xs text-gray-400 truncate">
                                                {notification.message}
                                            </p>
                                            <p className="text-xs text-gray-500 mt-1">
                                                {notification.timeAgo}
                                            </p>
                                        </div>
                                        {!notification.isRead && (
                                            <div className="w-2 h-2 bg-primary rounded-full flex-shrink-0 mt-2"></div>
                                        )}
                                    </div>
                                </a>
                            ))
                        )}
                    </div>

                    {/* Footer */}
                    {unreadCount > 0 && (
                        <div className="px-4 py-3 border-t border-gray-700">
                            <button
                                onClick={markAllAsRead}
                                className="w-full text-center text-sm text-gray-400 hover:text-white transition-colors flex items-center justify-center gap-2"
                            >
                                <CheckIcon className="w-4 h-4" />
                                Mark all as read
                            </button>
                        </div>
                    )}

                    <Popover.Arrow className="fill-gray-700" />
                </Popover.Content>
            </Popover.Portal>
        </Popover.Root>
    );
}
