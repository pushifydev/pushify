import React from 'react';
import * as ToastPrimitive from '@radix-ui/react-toast';
import { useToast } from '../../context/ToastContext';
import './toast.css';

export default function ToastContainer() {
    const { toasts, removeToast } = useToast();

    return (
        <ToastPrimitive.Provider swipeDirection="right">
            {toasts.map((toast) => (
                <ToastPrimitive.Root
                    key={toast.id}
                    className={`toast-root ${toast.type}`}
                    onOpenChange={(open) => {
                        if (!open) removeToast(toast.id);
                    }}
                    duration={toast.duration}
                    open={true}
                >
                    <div className="toast-content">
                        <div className="toast-icon">
                            {toast.type === 'success' && (
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            )}
                            {toast.type === 'error' && (
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            )}
                            {toast.type === 'warning' && (
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            )}
                            {toast.type === 'info' && (
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            )}
                        </div>
                        <div className="toast-text">
                            {toast.title && (
                                <ToastPrimitive.Title className="toast-title">
                                    {toast.title}
                                </ToastPrimitive.Title>
                            )}
                            {toast.description && (
                                <ToastPrimitive.Description className="toast-description">
                                    {toast.description}
                                </ToastPrimitive.Description>
                            )}
                        </div>
                        <ToastPrimitive.Close className="toast-close">
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </ToastPrimitive.Close>
                    </div>
                </ToastPrimitive.Root>
            ))}
            <ToastPrimitive.Viewport className="toast-viewport" />
        </ToastPrimitive.Provider>
    );
}
