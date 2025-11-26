import { Controller } from '@hotwired/stimulus';
import { createRoot } from 'react-dom/client';
import React from 'react';
import { ModalProvider } from '../react/context/ModalContext';
import DatabaseModalsContainer from '../react/components/modals/DatabaseModalsContainer';

export default class extends Controller {
    static values = {
        database: Object,
        projectSlug: String
    };

    connect() {
        console.log('DatabaseModals controller connected!', this.databaseValue);

        const container = document.createElement('div');
        container.id = 'database-modals-root';
        document.body.appendChild(container);

        this.root = createRoot(container);
        this.root.render(
            React.createElement(ModalProvider, null,
                React.createElement(DatabaseModalsContainer, {
                    database: this.databaseValue,
                    projectSlug: this.projectSlugValue,
                    controller: this
                })
            )
        );
    }

    disconnect() {
        if (this.root) {
            this.root.unmount();
        }
        const container = document.getElementById('database-modals-root');
        if (container) {
            container.remove();
        }
    }

    openConfigure() {
        // This will be called from the template button
        if (window.openConfigureModal) {
            window.openConfigureModal(this.databaseValue, this.projectSlugValue);
        }
    }
}
