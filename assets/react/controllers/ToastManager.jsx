import React, { useEffect } from 'react';
import { ToastProvider, useToast } from '../context/ToastContext';
import ToastContainer from '../components/ui/Toast';

function ToastManager() {
    const toast = useToast();

    // Expose toast functions to window
    useEffect(() => {
        window.toast = {
            success: (title, description) => toast.success(title, description),
            error: (title, description) => toast.error(title, description),
            warning: (title, description) => toast.warning(title, description),
            info: (title, description) => toast.info(title, description),
        };

        return () => {
            delete window.toast;
        };
    }, [toast]);

    return <ToastContainer />;
}

export default function ToastManagerWrapper() {
    return (
        <ToastProvider>
            <ToastManager />
        </ToastProvider>
    );
}
