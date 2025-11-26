import React from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import './modal.css';

export default function Modal({
    open,
    onOpenChange,
    title,
    description,
    children,
    maxWidth = 'max-w-md'
}) {
    return (
        <Dialog.Root open={open} onOpenChange={onOpenChange}>
            <Dialog.Portal>
                <Dialog.Overlay className="modal-overlay fixed inset-0 bg-black/50 z-50" />
                <Dialog.Content
                    className={`modal-content fixed left-[50%] top-[50%] z-50 translate-x-[-50%] translate-y-[-50%]
                        bg-gray-800 border border-gray-700 rounded-xl p-6 ${maxWidth} w-full mx-4 shadow-xl`}
                >
                    <div className="flex items-center justify-between mb-6">
                        {title && (
                            <Dialog.Title className="text-xl font-semibold text-white">
                                {title}
                            </Dialog.Title>
                        )}
                        <Dialog.Close className="text-gray-400 hover:text-white transition-colors ml-auto">
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </Dialog.Close>
                    </div>

                    {description && (
                        <Dialog.Description className="text-sm text-gray-400 mb-4">
                            {description}
                        </Dialog.Description>
                    )}

                    {children}
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}

// Convenience components for common use cases
export function ModalHeader({ children }) {
    return <div className="mb-4">{children}</div>;
}

export function ModalBody({ children }) {
    return <div className="space-y-4">{children}</div>;
}

export function ModalFooter({ children }) {
    return <div className="flex gap-3 pt-4 mt-6 border-t border-gray-700">{children}</div>;
}
