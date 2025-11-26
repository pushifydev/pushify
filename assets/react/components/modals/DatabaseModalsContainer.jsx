import React, { useEffect } from 'react';
import { useModal } from '../../context/ModalContext';
import { useToast } from '../../context/ToastContext';
import ConfigureDatabaseModal from './ConfigureDatabaseModal';

export default function DatabaseModalsContainer({ database, projectSlug }) {
    const { openModal } = useModal();
    const toast = useToast();

    // Expose functions to window for easy access from vanilla JS
    useEffect(() => {
        window.openConfigureModal = (db, slug) => {
            openModal('configure-database', { database: db, projectSlug: slug });
        };

        // Expose toast functions
        window.toast = {
            success: (title, description) => toast.success(title, description),
            error: (title, description) => toast.error(title, description),
            warning: (title, description) => toast.warning(title, description),
            info: (title, description) => toast.info(title, description),
        };

        return () => {
            delete window.openConfigureModal;
            delete window.toast;
        };
    }, [openModal, toast]);

    return <ConfigureDatabaseModal database={database} projectSlug={projectSlug} />;
}
