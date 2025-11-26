import React, { useState, useEffect } from 'react';
import Modal, { ModalBody, ModalFooter } from '../ui/Modal';
import { useModal } from '../../context/ModalContext';
import { useToast } from '../../context/ToastContext';

export default function ConfigureDatabaseModal({ database, projectSlug, onSuccess }) {
    const { getModalState, closeModal } = useModal();
    const toast = useToast();
    const modalState = getModalState('configure-database');

    const [formData, setFormData] = useState({
        memory_size_mb: database?.memorySizeMb || 512,
        cpu_limit: database?.cpuLimit || 1.0,
        disk_size_mb: database?.diskSizeMb || 1024
    });

    const [isSubmitting, setIsSubmitting] = useState(false);

    // Update form data when modal opens with new database data
    useEffect(() => {
        if (modalState.open && modalState.data.database) {
            const db = modalState.data.database;
            setFormData({
                memory_size_mb: db.memorySizeMb || 512,
                cpu_limit: db.cpuLimit || 1.0,
                disk_size_mb: db.diskSizeMb || 1024
            });
        }
    }, [modalState.open, modalState.data]);

    const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: name === 'cpu_limit' ? parseFloat(value) : parseInt(value)
        }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setIsSubmitting(true);

        const db = modalState.data.database;
        const slug = modalState.data.projectSlug;

        try {
            const response = await fetch(`/dashboard/projects/${slug}/databases/${db.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });

            const data = await response.json();

            if (data.success) {
                toast.success('Success', 'Database configuration updated successfully!');
                closeModal('configure-database');
                if (onSuccess) {
                    setTimeout(() => onSuccess(), 500);
                } else {
                    setTimeout(() => window.location.reload(), 1000);
                }
            } else {
                toast.error('Error', 'Failed to update database: ' + data.message);
            }
        } catch (error) {
            toast.error('Error', 'An error occurred while updating the database');
            console.error(error);
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <Modal
            open={modalState.open}
            onOpenChange={(open) => !open && closeModal('configure-database')}
            title="Configure Database"
            description="Update database resource allocation and settings"
        >
            <form onSubmit={handleSubmit}>
                <ModalBody>
                    <div>
                        <label htmlFor="memory_size_mb" className="block text-sm font-medium text-gray-300 mb-2">
                            Memory (MB)
                        </label>
                        <input
                            type="number"
                            id="memory_size_mb"
                            name="memory_size_mb"
                            value={formData.memory_size_mb}
                            onChange={handleChange}
                            min="128"
                            max="16384"
                            step="128"
                            required
                            className="w-full px-4 py-2 bg-gray-900/50 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-primary transition-colors"
                        />
                        <p className="mt-1 text-xs text-gray-500">Recommended: 512MB - 2048MB</p>
                    </div>

                    <div>
                        <label htmlFor="cpu_limit" className="block text-sm font-medium text-gray-300 mb-2">
                            CPU Limit (cores)
                        </label>
                        <input
                            type="number"
                            id="cpu_limit"
                            name="cpu_limit"
                            value={formData.cpu_limit}
                            onChange={handleChange}
                            min="0.1"
                            max="8"
                            step="0.1"
                            required
                            className="w-full px-4 py-2 bg-gray-900/50 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-primary transition-colors"
                        />
                        <p className="mt-1 text-xs text-gray-500">Recommended: 0.5 - 2.0 cores</p>
                    </div>

                    <div>
                        <label htmlFor="disk_size_mb" className="block text-sm font-medium text-gray-300 mb-2">
                            Disk Size (MB)
                        </label>
                        <input
                            type="number"
                            id="disk_size_mb"
                            name="disk_size_mb"
                            value={formData.disk_size_mb}
                            onChange={handleChange}
                            min="512"
                            max="102400"
                            step="512"
                            required
                            className="w-full px-4 py-2 bg-gray-900/50 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-primary transition-colors"
                        />
                        <p className="mt-1 text-xs text-gray-500">Recommended: 1024MB - 10240MB</p>
                    </div>
                </ModalBody>

                <ModalFooter>
                    <button
                        type="button"
                        onClick={() => closeModal('configure-database')}
                        disabled={isSubmitting}
                        className="flex-1 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={isSubmitting}
                        className="flex-1 px-4 py-2 bg-primary hover:bg-primary/90 text-white rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                    >
                        {isSubmitting ? (
                            <>
                                <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Saving...
                            </>
                        ) : (
                            'Save Changes'
                        )}
                    </button>
                </ModalFooter>
            </form>
        </Modal>
    );
}
