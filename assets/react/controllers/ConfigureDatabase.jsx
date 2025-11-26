import React from 'react';
import { ModalProvider } from '../context/ModalContext';
import { ToastProvider } from '../context/ToastContext';
import DatabaseModalsContainer from '../components/modals/DatabaseModalsContainer';
import ToastContainer from '../components/ui/Toast';

export default function ConfigureDatabase(props) {
    return (
        <ToastProvider>
            <ModalProvider>
                <DatabaseModalsContainer
                    database={props.database}
                    projectSlug={props.projectSlug}
                />
                <ToastContainer />
            </ModalProvider>
        </ToastProvider>
    );
}
