import React, { useState, useEffect } from 'react';
import { useToast, ToastProvider } from '../../context/ToastContext';
import ToastContainer from '../../components/ui/Toast';

const DatabaseDashboard = ({ projectSlug }) => {
    const toast = useToast();
    const [databases, setDatabases] = useState([]);
    const [stats, setStats] = useState(null);
    const [types, setTypes] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showCreateForm, setShowCreateForm] = useState(false);
    const [selectedDatabase, setSelectedDatabase] = useState(null);
    const [showDetails, setShowDetails] = useState(false);

    const [formData, setFormData] = useState({
        name: '',
        type: '',
        version: '',
        username: '',
        password: '',
        database_name: '',
        memory_size_mb: 512,
        cpu_limit: 1.0,
    });

    useEffect(() => {
        loadDatabases();
        loadTypes();
    }, [projectSlug]);

    const loadDatabases = async () => {
        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/databases/list`);
            const data = await response.json();
            if (data.success) {
                setDatabases(data.databases);
                setStats(data.stats);
            }
        } catch (error) {
            console.error('Failed to load databases:', error);
        } finally {
            setLoading(false);
        }
    };

    const loadTypes = async () => {
        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/databases/types`);
            const data = await response.json();
            if (data.success) {
                setTypes(data.types);
            }
        } catch (error) {
            console.error('Failed to load database types:', error);
        }
    };

    const handleCreateDatabase = async (e) => {
        e.preventDefault();

        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/databases/create`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData),
            });

            const data = await response.json();

            if (data.success) {
                setShowCreateForm(false);
                resetForm();
                loadDatabases();
                toast.success('Success', 'Database creation has been queued!');
            } else {
                toast.error('Error', data.message || 'Failed to create database');
            }
        } catch (error) {
            console.error('Failed to create database:', error);
            toast.error('Error', 'Failed to create database');
        }
    };

    const handleStart = async (id) => {
        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/databases/${id}/start`, {
                method: 'POST',
            });
            const data = await response.json();
            if (data.success) {
                toast.success('Success', 'Database started successfully!');
                loadDatabases();
            } else {
                toast.error('Error', data.message);
            }
        } catch (error) {
            console.error('Failed to start database:', error);
            toast.error('Error', 'Failed to start database');
        }
    };

    const handleStop = async (id) => {
        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/databases/${id}/stop`, {
                method: 'POST',
            });
            const data = await response.json();
            if (data.success) {
                toast.success('Success', 'Database stopped successfully!');
                loadDatabases();
            } else {
                toast.error('Error', data.message);
            }
        } catch (error) {
            console.error('Failed to stop database:', error);
            toast.error('Error', 'Failed to stop database');
        }
    };

    const handleRestart = async (id) => {
        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/databases/${id}/restart`, {
                method: 'POST',
            });
            const data = await response.json();
            if (data.success) {
                toast.success('Success', 'Database restarted successfully!');
                loadDatabases();
            } else {
                toast.error('Error', data.message);
            }
        } catch (error) {
            console.error('Failed to restart database:', error);
            toast.error('Error', 'Failed to restart database');
        }
    };

    const handleDelete = async (id, name) => {
        if (!confirm(`Are you sure you want to delete database "${name}"? This action cannot be undone.`)) {
            return;
        }

        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/databases/${id}`, {
                method: 'DELETE',
            });
            const data = await response.json();
            if (data.success) {
                toast.success('Success', 'Database deleted successfully!');
                loadDatabases();
            } else {
                toast.error('Error', data.message);
            }
        } catch (error) {
            console.error('Failed to delete database:', error);
            toast.error('Error', 'Failed to delete database');
        }
    };

    const showDatabaseDetails = async (id) => {
        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/databases/${id}`);
            const data = await response.json();
            if (data.success) {
                setSelectedDatabase(data.database);
                setShowDetails(true);
            }
        } catch (error) {
            console.error('Failed to load database details:', error);
        }
    };

    const resetForm = () => {
        setFormData({
            name: '',
            type: '',
            version: '',
            username: '',
            password: '',
            database_name: '',
            memory_size_mb: 512,
            cpu_limit: 1.0,
        });
    };

    const generateRandomPassword = () => {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let password = '';
        for (let i = 0; i < 16; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        setFormData({ ...formData, password });
    };

    const copyToClipboard = (text) => {
        navigator.clipboard.writeText(text);
        toast.success('Copied', 'Copied to clipboard!');
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
            {/* Stats */}
            {stats && (
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
                        <p className="text-sm text-gray-400">Total Databases</p>
                        <p className="text-2xl font-bold text-white mt-1">{stats.total}</p>
                    </div>
                    <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
                        <p className="text-sm text-gray-400">Running</p>
                        <p className="text-2xl font-bold text-green-400 mt-1">{stats.running}</p>
                    </div>
                    <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
                        <p className="text-sm text-gray-400">Stopped</p>
                        <p className="text-2xl font-bold text-gray-400 mt-1">{stats.stopped}</p>
                    </div>
                    <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
                        <p className="text-sm text-gray-400">Errors</p>
                        <p className="text-2xl font-bold text-red-400 mt-1">{stats.error}</p>
                    </div>
                </div>
            )}

            {/* Header */}
            <div className="flex items-center justify-between">
                <h2 className="text-xl font-semibold text-white">Databases</h2>
                <button
                    onClick={() => setShowCreateForm(true)}
                    className="px-4 py-2 bg-primary hover:bg-primary-dark text-white font-medium rounded-lg transition-all inline-flex items-center gap-2"
                >
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                    </svg>
                    Create Database
                </button>
            </div>

            {/* Databases List */}
            {databases.length === 0 ? (
                <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-12 text-center">
                    <svg className="w-16 h-16 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                    </svg>
                    <p className="text-gray-400 text-lg mb-4">No databases yet</p>
                    <button
                        onClick={() => setShowCreateForm(true)}
                        className="px-4 py-2 bg-primary hover:bg-primary-dark text-white font-medium rounded-lg transition-all"
                    >
                        Create Your First Database
                    </button>
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-4">
                    {databases.map((db) => (
                        <div key={db.id} className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
                            <div className="flex items-start justify-between">
                                <div className="flex-1">
                                    <div className="flex items-center gap-3 mb-2">
                                        <span className="text-2xl">{db.type_icon}</span>
                                        <h3 className="text-lg font-semibold text-white">{db.name}</h3>
                                        <span className={`px-2 py-1 rounded-full text-xs font-medium ${db.status_badge_class}`}>
                                            {db.status}
                                        </span>
                                    </div>
                                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4 text-sm">
                                        <div>
                                            <p className="text-gray-400">Type</p>
                                            <p className="text-white">{db.type_label} {db.version}</p>
                                        </div>
                                        <div>
                                            <p className="text-gray-400">Port</p>
                                            <p className="text-white">{db.port}</p>
                                        </div>
                                        <div>
                                            <p className="text-gray-400">Memory</p>
                                            <p className="text-white">{db.memory_size_mb} MB</p>
                                        </div>
                                        <div>
                                            <p className="text-gray-400">Uptime</p>
                                            <p className="text-white">{db.uptime || 'N/A'}</p>
                                        </div>
                                    </div>
                                    {db.error_message && (
                                        <div className="mt-3 bg-red-500/10 border border-red-500/50 text-red-400 px-3 py-2 rounded text-sm">
                                            {db.error_message}
                                        </div>
                                    )}
                                </div>
                                <div className="flex items-center gap-2 ml-4">
                                    {db.status === 'running' && (
                                        <>
                                            <button
                                                onClick={() => handleStop(db.id)}
                                                className="p-2 bg-yellow-500/20 hover:bg-yellow-500/30 text-yellow-400 rounded-lg transition-all"
                                                title="Stop"
                                            >
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z" />
                                                </svg>
                                            </button>
                                            <button
                                                onClick={() => handleRestart(db.id)}
                                                className="p-2 bg-blue-500/20 hover:bg-blue-500/30 text-blue-400 rounded-lg transition-all"
                                                title="Restart"
                                            >
                                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                            </button>
                                        </>
                                    )}
                                    {db.status === 'stopped' && (
                                        <button
                                            onClick={() => handleStart(db.id)}
                                            className="p-2 bg-green-500/20 hover:bg-green-500/30 text-green-400 rounded-lg transition-all"
                                            title="Start"
                                        >
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>
                                    )}
                                    <button
                                        onClick={() => showDatabaseDetails(db.id)}
                                        className="p-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-all"
                                        title="Details"
                                    >
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                    <button
                                        onClick={() => handleDelete(db.id, db.name)}
                                        className="p-2 bg-red-500/20 hover:bg-red-500/30 text-red-400 rounded-lg transition-all"
                                        title="Delete"
                                    >
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Create Database Modal */}
            {showCreateForm && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                    <div className="bg-gray-800 border border-gray-700 rounded-xl p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                        <div className="flex items-center justify-between mb-6">
                            <h3 className="text-xl font-semibold text-white">Create New Database</h3>
                            <button
                                onClick={() => {
                                    setShowCreateForm(false);
                                    resetForm();
                                }}
                                className="text-gray-400 hover:text-white"
                            >
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form onSubmit={handleCreateDatabase} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-300 mb-2">Database Name</label>
                                <input
                                    type="text"
                                    required
                                    value={formData.name}
                                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                    className="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-primary"
                                    placeholder="my-database"
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-300 mb-2">Database Type</label>
                                    <select
                                        required
                                        value={formData.type}
                                        onChange={(e) => {
                                            const selectedType = types.find(t => t.type === e.target.value);
                                            setFormData({
                                                ...formData,
                                                type: e.target.value,
                                                version: selectedType?.versions[0] || ''
                                            });
                                        }}
                                        className="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-primary"
                                    >
                                        <option value="">Select type</option>
                                        {types.map((type) => (
                                            <option key={type.type} value={type.type}>{type.label}</option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-300 mb-2">Version</label>
                                    <select
                                        required
                                        value={formData.version}
                                        onChange={(e) => setFormData({ ...formData, version: e.target.value })}
                                        className="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-primary"
                                    >
                                        <option value="">Select version</option>
                                        {formData.type && types.find(t => t.type === formData.type)?.versions.map((version) => (
                                            <option key={version} value={version}>{version}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-300 mb-2">Username</label>
                                    <input
                                        type="text"
                                        value={formData.username}
                                        onChange={(e) => setFormData({ ...formData, username: e.target.value })}
                                        className="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-primary"
                                        placeholder="Auto-generated if empty"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-300 mb-2">Database Name</label>
                                    <input
                                        type="text"
                                        value={formData.database_name}
                                        onChange={(e) => setFormData({ ...formData, database_name: e.target.value })}
                                        className="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-primary"
                                        placeholder="Same as name if empty"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-300 mb-2">Password</label>
                                <div className="flex gap-2">
                                    <input
                                        type="text"
                                        value={formData.password}
                                        onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                                        className="flex-1 px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-primary"
                                        placeholder="Auto-generated if empty"
                                    />
                                    <button
                                        type="button"
                                        onClick={generateRandomPassword}
                                        className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-all"
                                    >
                                        Generate
                                    </button>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-300 mb-2">Memory (MB)</label>
                                    <input
                                        type="number"
                                        value={formData.memory_size_mb}
                                        onChange={(e) => setFormData({ ...formData, memory_size_mb: parseInt(e.target.value) })}
                                        className="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-primary"
                                        min="128"
                                        max="8192"
                                    />
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-300 mb-2">CPU Limit</label>
                                    <input
                                        type="number"
                                        step="0.1"
                                        value={formData.cpu_limit}
                                        onChange={(e) => setFormData({ ...formData, cpu_limit: parseFloat(e.target.value) })}
                                        className="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-white focus:outline-none focus:border-primary"
                                        min="0.1"
                                        max="8"
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end gap-3 mt-6">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setShowCreateForm(false);
                                        resetForm();
                                    }}
                                    className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg transition-all"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="px-4 py-2 bg-primary hover:bg-primary-dark text-white font-medium rounded-lg transition-all"
                                >
                                    Create Database
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Database Details Modal */}
            {showDetails && selectedDatabase && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                    <div className="bg-gray-800 border border-gray-700 rounded-xl p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                        <div className="flex items-center justify-between mb-6">
                            <h3 className="text-xl font-semibold text-white">Database Details</h3>
                            <button
                                onClick={() => {
                                    setShowDetails(false);
                                    setSelectedDatabase(null);
                                }}
                                className="text-gray-400 hover:text-white"
                            >
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-400 mb-1">Name</label>
                                <p className="text-white">{selectedDatabase.name}</p>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-400 mb-1">Type</label>
                                    <p className="text-white">{selectedDatabase.type_label}</p>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-400 mb-1">Version</label>
                                    <p className="text-white">{selectedDatabase.version}</p>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-400 mb-1">Port</label>
                                    <p className="text-white">{selectedDatabase.port}</p>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-400 mb-1">Status</label>
                                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${selectedDatabase.status_badge_class}`}>
                                        {selectedDatabase.status}
                                    </span>
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-400 mb-1">Username</label>
                                <div className="flex items-center gap-2">
                                    <code className="flex-1 px-3 py-2 bg-gray-900 rounded text-white text-sm">
                                        {selectedDatabase.username}
                                    </code>
                                    <button
                                        onClick={() => copyToClipboard(selectedDatabase.username)}
                                        className="p-2 bg-gray-700 hover:bg-gray-600 rounded transition-all"
                                        title="Copy"
                                    >
                                        <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-400 mb-1">Password</label>
                                <div className="flex items-center gap-2">
                                    <code className="flex-1 px-3 py-2 bg-gray-900 rounded text-white text-sm">
                                        {selectedDatabase.password}
                                    </code>
                                    <button
                                        onClick={() => copyToClipboard(selectedDatabase.password)}
                                        className="p-2 bg-gray-700 hover:bg-gray-600 rounded transition-all"
                                        title="Copy"
                                    >
                                        <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-400 mb-1">Connection String</label>
                                <div className="flex items-center gap-2">
                                    <code className="flex-1 px-3 py-2 bg-gray-900 rounded text-white text-sm overflow-x-auto">
                                        {selectedDatabase.connection_string}
                                    </code>
                                    <button
                                        onClick={() => copyToClipboard(selectedDatabase.connection_string)}
                                        className="p-2 bg-gray-700 hover:bg-gray-600 rounded transition-all"
                                        title="Copy"
                                    >
                                        <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-400 mb-1">Memory</label>
                                    <p className="text-white">{selectedDatabase.memory_size_mb} MB</p>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-400 mb-1">CPU Limit</label>
                                    <p className="text-white">{selectedDatabase.cpu_limit}</p>
                                </div>
                            </div>

                            {selectedDatabase.container_name && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-400 mb-1">Container Name</label>
                                    <p className="text-white text-sm">{selectedDatabase.container_name}</p>
                                </div>
                            )}

                            <div className="flex justify-end mt-6">
                                <button
                                    onClick={() => {
                                        setShowDetails(false);
                                        setSelectedDatabase(null);
                                    }}
                                    className="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white font-medium rounded-lg transition-all"
                                >
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

function DatabaseDashboardWrapper(props) {
    return (
        <ToastProvider>
            <DatabaseDashboard {...props} />
            <ToastContainer />
        </ToastProvider>
    );
}

export default DatabaseDashboardWrapper;
