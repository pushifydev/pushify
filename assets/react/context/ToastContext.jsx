import React, { createContext, useContext, useState, useCallback } from 'react';

const ToastContext = createContext();

export function useToast() {
    const context = useContext(ToastContext);
    if (!context) {
        throw new Error('useToast must be used within a ToastProvider');
    }
    return context;
}

export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);

    const addToast = useCallback((toast) => {
        const id = Date.now() + Math.random();
        const newToast = {
            id,
            type: toast.type || 'info', // success, error, warning, info
            title: toast.title,
            description: toast.description,
            duration: toast.duration || 5000,
        };

        setToasts(prev => [...prev, newToast]);

        // Auto remove after duration
        if (newToast.duration > 0) {
            setTimeout(() => {
                removeToast(id);
            }, newToast.duration);
        }

        return id;
    }, []);

    const removeToast = useCallback((id) => {
        setToasts(prev => prev.filter(toast => toast.id !== id));
    }, []);

    const success = useCallback((title, description) => {
        return addToast({ type: 'success', title, description });
    }, [addToast]);

    const error = useCallback((title, description) => {
        return addToast({ type: 'error', title, description });
    }, [addToast]);

    const warning = useCallback((title, description) => {
        return addToast({ type: 'warning', title, description });
    }, [addToast]);

    const info = useCallback((title, description) => {
        return addToast({ type: 'info', title, description });
    }, [addToast]);

    const value = {
        toasts,
        addToast,
        removeToast,
        success,
        error,
        warning,
        info,
    };

    return (
        <ToastContext.Provider value={value}>
            {children}
        </ToastContext.Provider>
    );
}
