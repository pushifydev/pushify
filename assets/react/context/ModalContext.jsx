import React, { createContext, useContext, useState, useCallback } from 'react';

const ModalContext = createContext();

export function useModal() {
    const context = useContext(ModalContext);
    if (!context) {
        throw new Error('useModal must be used within a ModalProvider');
    }
    return context;
}

export function ModalProvider({ children }) {
    const [modals, setModals] = useState({});

    const openModal = useCallback((modalId, data = {}) => {
        setModals(prev => ({
            ...prev,
            [modalId]: { open: true, data }
        }));
    }, []);

    const closeModal = useCallback((modalId) => {
        setModals(prev => ({
            ...prev,
            [modalId]: { open: false, data: {} }
        }));
    }, []);

    const getModalState = useCallback((modalId) => {
        return modals[modalId] || { open: false, data: {} };
    }, [modals]);

    const value = {
        openModal,
        closeModal,
        getModalState
    };

    return (
        <ModalContext.Provider value={value}>
            {children}
        </ModalContext.Provider>
    );
}
