import React, { useState, useEffect } from 'react';

const EnvironmentVariables = ({ projectSlug }) => {
    const [variables, setVariables] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showAddModal, setShowAddModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [showImportModal, setShowImportModal] = useState(false);
    const [editingVar, setEditingVar] = useState(null);
    const [formData, setFormData] = useState({ key: '', value: '', isSecret: false });
    const [importData, setImportData] = useState({ content: '', overwrite: false });
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);

    useEffect(() => {
        fetchVariables();
    }, [projectSlug]);

    const fetchVariables = async () => {
        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/environment/api`);
            const data = await response.json();

            if (data.success) {
                setVariables(data.variables);
            } else {
                setError(data.error || 'Failed to load variables');
            }
        } catch (err) {
            setError('Failed to load environment variables');
        } finally {
            setLoading(false);
        }
    };

    const handleAdd = async (e) => {
        e.preventDefault();
        setError(null);

        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/environment/create`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });

            const data = await response.json();

            if (data.success) {
                setSuccess('Environment variable created successfully');
                setShowAddModal(false);
                setFormData({ key: '', value: '', isSecret: false });
                fetchVariables();
                setTimeout(() => setSuccess(null), 3000);
            } else {
                setError(data.error || 'Failed to create variable');
            }
        } catch (err) {
            setError('Failed to create environment variable');
        }
    };

    const handleEdit = async (e) => {
        e.preventDefault();
        setError(null);

        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/environment/${editingVar.id}/update`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    value: formData.value,
                    isSecret: formData.isSecret
                })
            });

            const data = await response.json();

            if (data.success) {
                setSuccess('Environment variable updated successfully');
                setShowEditModal(false);
                setEditingVar(null);
                setFormData({ key: '', value: '', isSecret: false });
                fetchVariables();
                setTimeout(() => setSuccess(null), 3000);
            } else {
                setError(data.error || 'Failed to update variable');
            }
        } catch (err) {
            setError('Failed to update environment variable');
        }
    };

    const handleDelete = async (id) => {
        if (!confirm('Are you sure you want to delete this environment variable?')) {
            return;
        }

        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/environment/${id}/delete`, {
                method: 'POST'
            });

            const data = await response.json();

            if (data.success) {
                setSuccess('Environment variable deleted successfully');
                fetchVariables();
                setTimeout(() => setSuccess(null), 3000);
            } else {
                setError(data.error || 'Failed to delete variable');
            }
        } catch (err) {
            setError('Failed to delete environment variable');
        }
    };

    const handleImport = async (e) => {
        e.preventDefault();
        setError(null);

        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/environment/import`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(importData)
            });

            const data = await response.json();

            if (data.success) {
                setSuccess(`Imported ${data.imported} variable(s)${data.errors.length > 0 ? ' with some errors' : ''}`);
                if (data.errors.length > 0) {
                    setError(data.errors.join('\n'));
                }
                setShowImportModal(false);
                setImportData({ content: '', overwrite: false });
                fetchVariables();
                setTimeout(() => {
                    setSuccess(null);
                    setError(null);
                }, 5000);
            } else {
                setError(data.error || 'Failed to import variables');
            }
        } catch (err) {
            setError('Failed to import environment variables');
        }
    };

    const openEditModal = (variable) => {
        setEditingVar(variable);
        setFormData({
            key: variable.key,
            value: variable.actualValue,
            isSecret: variable.isSecret
        });
        setShowEditModal(true);
    };

    const handleExport = () => {
        window.location.href = `/dashboard/projects/${projectSlug}/environment/export`;
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center py-12">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Success/Error Messages */}
            {success && (
                <div className="bg-green-500/20 border border-green-500/50 text-green-400 px-4 py-3 rounded-lg">
                    {success}
                </div>
            )}

            {error && (
                <div className="bg-red-500/20 border border-red-500/50 text-red-400 px-4 py-3 rounded-lg whitespace-pre-line">
                    {error}
                </div>
            )}

            {/* Header Actions */}
            <div className="flex justify-between items-center">
                <div>
                    <h2 className="text-xl font-semibold text-white">Environment Variables</h2>
                    <p className="text-sm text-gray-400 mt-1">
                        {variables.length} variable{variables.length !== 1 ? 's' : ''}
                    </p>
                </div>
                <div className="flex gap-3">
                    <button
                        onClick={() => setShowImportModal(true)}
                        className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg transition-all inline-flex items-center gap-2"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        Import
                    </button>
                    {variables.length > 0 && (
                        <button
                            onClick={handleExport}
                            className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg transition-all inline-flex items-center gap-2"
                        >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                            </svg>
                            Export
                        </button>
                    )}
                    <button
                        onClick={() => setShowAddModal(true)}
                        className="px-4 py-2 bg-primary hover:bg-primary/90 text-white font-semibold rounded-lg transition-all inline-flex items-center gap-2"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                        Add Variable
                    </button>
                </div>
            </div>

            {/* Variables Table */}
            {variables.length === 0 ? (
                <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-12 text-center">
                    <svg className="w-16 h-16 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 className="text-lg font-semibold text-white mb-2">No environment variables</h3>
                    <p className="text-gray-400 mb-6">Add your first environment variable to get started</p>
                    <button
                        onClick={() => setShowAddModal(true)}
                        className="px-4 py-2 bg-primary hover:bg-primary/90 text-white font-semibold rounded-lg transition-all inline-flex items-center gap-2"
                    >
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                        </svg>
                        Add Variable
                    </button>
                </div>
            ) : (
                <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="bg-gray-700/50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Key</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Value</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Type</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Updated</th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-700/50">
                                {variables.map((variable) => (
                                    <tr key={variable.id} className="hover:bg-gray-700/30 transition-colors">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className="text-white font-mono text-sm">{variable.key}</span>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className="text-gray-300 font-mono text-sm">
                                                {variable.value}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            {variable.isSecret ? (
                                                <span className="px-2 py-1 rounded-full text-xs font-medium bg-red-500/20 text-red-400">
                                                    Secret
                                                </span>
                                            ) : (
                                                <span className="px-2 py-1 rounded-full text-xs font-medium bg-blue-500/20 text-blue-400">
                                                    Public
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                                            {variable.updatedAt || variable.createdAt}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button
                                                onClick={() => openEditModal(variable)}
                                                className="text-primary hover:text-primary/80 mr-3"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                onClick={() => handleDelete(variable.id)}
                                                className="text-red-400 hover:text-red-300"
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {/* Add Modal */}
            {showAddModal && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50" onClick={() => setShowAddModal(false)}>
                    <div className="bg-gray-800 rounded-xl p-6 w-full max-w-md border border-gray-700" onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-xl font-semibold text-white mb-4">Add Environment Variable</h3>
                        <form onSubmit={handleAdd} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-300 mb-2">Key</label>
                                <input
                                    type="text"
                                    value={formData.key}
                                    onChange={(e) => setFormData({ ...formData, key: e.target.value.toUpperCase() })}
                                    className="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary font-mono"
                                    placeholder="API_KEY"
                                    required
                                />
                                <p className="text-xs text-gray-400 mt-1">Uppercase letters, numbers, and underscores only</p>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-300 mb-2">Value</label>
                                <textarea
                                    value={formData.value}
                                    onChange={(e) => setFormData({ ...formData, value: e.target.value })}
                                    className="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary font-mono"
                                    placeholder="your-secret-value"
                                    rows="3"
                                    required
                                />
                            </div>
                            <div className="flex items-center">
                                <input
                                    type="checkbox"
                                    id="isSecret"
                                    checked={formData.isSecret}
                                    onChange={(e) => setFormData({ ...formData, isSecret: e.target.checked })}
                                    className="w-4 h-4 text-primary bg-gray-700 border-gray-600 rounded focus:ring-primary"
                                />
                                <label htmlFor="isSecret" className="ml-2 text-sm text-gray-300">
                                    Mark as secret (value will be masked in UI)
                                </label>
                            </div>
                            <div className="flex justify-end gap-3 pt-4">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setShowAddModal(false);
                                        setFormData({ key: '', value: '', isSecret: false });
                                    }}
                                    className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg transition-all"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 bg-primary hover:bg-primary/90 text-white font-semibold rounded-lg transition-all"
                                >
                                    Add Variable
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Edit Modal */}
            {showEditModal && editingVar && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50" onClick={() => setShowEditModal(false)}>
                    <div className="bg-gray-800 rounded-xl p-6 w-full max-w-md border border-gray-700" onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-xl font-semibold text-white mb-4">Edit Environment Variable</h3>
                        <form onSubmit={handleEdit} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-300 mb-2">Key</label>
                                <input
                                    type="text"
                                    value={formData.key}
                                    className="w-full px-4 py-2 bg-gray-700/50 border border-gray-600 rounded-lg text-gray-400 font-mono"
                                    disabled
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-300 mb-2">Value</label>
                                <textarea
                                    value={formData.value}
                                    onChange={(e) => setFormData({ ...formData, value: e.target.value })}
                                    className="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary font-mono"
                                    rows="3"
                                    required
                                />
                            </div>
                            <div className="flex items-center">
                                <input
                                    type="checkbox"
                                    id="isSecretEdit"
                                    checked={formData.isSecret}
                                    onChange={(e) => setFormData({ ...formData, isSecret: e.target.checked })}
                                    className="w-4 h-4 text-primary bg-gray-700 border-gray-600 rounded focus:ring-primary"
                                />
                                <label htmlFor="isSecretEdit" className="ml-2 text-sm text-gray-300">
                                    Mark as secret (value will be masked in UI)
                                </label>
                            </div>
                            <div className="flex justify-end gap-3 pt-4">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setShowEditModal(false);
                                        setEditingVar(null);
                                        setFormData({ key: '', value: '', isSecret: false });
                                    }}
                                    className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg transition-all"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 bg-primary hover:bg-primary/90 text-white font-semibold rounded-lg transition-all"
                                >
                                    Update Variable
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Import Modal */}
            {showImportModal && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50" onClick={() => setShowImportModal(false)}>
                    <div className="bg-gray-800 rounded-xl p-6 w-full max-w-2xl border border-gray-700" onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-xl font-semibold text-white mb-4">Import Environment Variables</h3>
                        <form onSubmit={handleImport} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-300 mb-2">
                                    .env File Content
                                </label>
                                <textarea
                                    value={importData.content}
                                    onChange={(e) => setImportData({ ...importData, content: e.target.value })}
                                    className="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary font-mono text-sm"
                                    placeholder="KEY1=value1&#10;KEY2=value2&#10;# Comments are ignored"
                                    rows="10"
                                    required
                                />
                                <p className="text-xs text-gray-400 mt-1">Paste your .env file content here</p>
                            </div>
                            <div className="flex items-center">
                                <input
                                    type="checkbox"
                                    id="overwrite"
                                    checked={importData.overwrite}
                                    onChange={(e) => setImportData({ ...importData, overwrite: e.target.checked })}
                                    className="w-4 h-4 text-primary bg-gray-700 border-gray-600 rounded focus:ring-primary"
                                />
                                <label htmlFor="overwrite" className="ml-2 text-sm text-gray-300">
                                    Overwrite existing variables
                                </label>
                            </div>
                            <div className="flex justify-end gap-3 pt-4">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setShowImportModal(false);
                                        setImportData({ content: '', overwrite: false });
                                    }}
                                    className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg transition-all"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 bg-primary hover:bg-primary/90 text-white font-semibold rounded-lg transition-all"
                                >
                                    Import Variables
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default EnvironmentVariables;
