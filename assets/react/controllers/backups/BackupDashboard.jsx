import React, { useState, useEffect } from 'react';
import { Database, Download, RefreshCw, Trash2, RotateCcw, Plus, X, Clock, HardDrive, AlertCircle } from 'lucide-react';
import { useToast, ToastProvider } from '../../context/ToastContext';
import ToastContainer from '../../components/ui/Toast';

const BackupDashboard = ({ projectSlug }) => {
    const toast = useToast();
    const [backups, setBackups] = useState([]);
    const [stats, setStats] = useState({});
    const [databases, setDatabases] = useState([]);
    const [showCreateForm, setShowCreateForm] = useState(false);
    const [selectedBackup, setSelectedBackup] = useState(null);
    const [selectedDatabase, setSelectedDatabase] = useState(null);
    const [loading, setLoading] = useState(true);
    const [formData, setFormData] = useState({
        database_id: '',
        name: '',
        type: 'manual',
        method: 'dump',
        compression: 'gzip',
        retention_days: 30,
    });

    useEffect(() => {
        loadBackups();
        loadDatabases();
    }, []);

    const loadBackups = async () => {
        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/backups/list`);
            const data = await response.json();
            if (data.success) {
                setBackups(data.backups);
                setStats(data.stats);
            }
        } catch (error) {
            console.error('Failed to load backups:', error);
        } finally {
            setLoading(false);
        }
    };

    const loadDatabases = async () => {
        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/databases/list`);
            const data = await response.json();
            if (data.success) {
                setDatabases(data.databases.filter(db => db.status === 'running'));
            }
        } catch (error) {
            console.error('Failed to load databases:', error);
        }
    };

    const handleCreateBackup = async (e) => {
        e.preventDefault();

        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/backups/create`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData),
            });

            const data = await response.json();

            if (data.success) {
                toast.success('Success', 'Backup creation has been queued!');
                setShowCreateForm(false);
                resetForm();
                setTimeout(loadBackups, 2000);
            } else {
                toast.error('Error', data.message || 'Failed to create backup');
            }
        } catch (error) {
            console.error('Failed to create backup:', error);
            toast.error('Error', 'Failed to create backup');
        }
    };

    const handleRestore = async (backupId) => {
        if (!confirm('Are you sure you want to restore this backup? This will overwrite the current database.')) {
            return;
        }

        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/backups/${backupId}/restore`, {
                method: 'POST',
            });

            const data = await response.json();

            if (data.success) {
                toast.success('Success', 'Backup restored successfully!');
                loadBackups();
            } else {
                toast.error('Error', data.message || 'Failed to restore backup');
            }
        } catch (error) {
            console.error('Failed to restore backup:', error);
            toast.error('Error', 'Failed to restore backup');
        }
    };

    const handleDelete = async (backupId) => {
        if (!confirm('Are you sure you want to delete this backup? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/backups/${backupId}`, {
                method: 'DELETE',
            });

            const data = await response.json();

            if (data.success) {
                toast.success('Success', 'Backup deleted successfully!');
                loadBackups();
            } else {
                toast.error('Error', data.message || 'Failed to delete backup');
            }
        } catch (error) {
            console.error('Failed to delete backup:', error);
            toast.error('Error', 'Failed to delete backup');
        }
    };

    const handleDownload = (backupId) => {
        window.location.href = `/dashboard/projects/${projectSlug}/backups/${backupId}/download`;
    };

    const resetForm = () => {
        setFormData({
            database_id: '',
            name: '',
            type: 'manual',
            method: 'dump',
            compression: 'gzip',
            retention_days: 30,
        });
    };

    const filterBackups = () => {
        if (!selectedDatabase) return backups;
        return backups.filter(b => b.database_id === selectedDatabase);
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center h-64">
                <RefreshCw className="w-8 h-8 text-blue-500 animate-spin" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex justify-between items-center">
                <div>
                    <h2 className="text-2xl font-bold text-white">Backups</h2>
                    <p className="text-gray-400 mt-1">Manage database backups and restore points</p>
                </div>
                <div className="flex gap-3">
                    <button
                        onClick={loadBackups}
                        className="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg flex items-center gap-2 transition-colors"
                    >
                        <RefreshCw className="w-4 h-4" />
                        Refresh
                    </button>
                    <button
                        onClick={() => setShowCreateForm(true)}
                        className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center gap-2 transition-colors"
                    >
                        <Plus className="w-4 h-4" />
                        Create Backup
                    </button>
                </div>
            </div>

            {/* Stats Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div className="bg-gray-800/50 backdrop-blur border border-gray-700 rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-gray-400 text-sm">Total Backups</p>
                            <p className="text-2xl font-bold text-white mt-1">{stats.total || 0}</p>
                        </div>
                        <Database className="w-8 h-8 text-blue-400" />
                    </div>
                </div>

                <div className="bg-gray-800/50 backdrop-blur border border-gray-700 rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-gray-400 text-sm">Completed</p>
                            <p className="text-2xl font-bold text-green-400 mt-1">{stats.completed || 0}</p>
                        </div>
                        <Database className="w-8 h-8 text-green-400" />
                    </div>
                </div>

                <div className="bg-gray-800/50 backdrop-blur border border-gray-700 rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-gray-400 text-sm">In Progress</p>
                            <p className="text-2xl font-bold text-yellow-400 mt-1">{stats.in_progress || 0}</p>
                        </div>
                        <Clock className="w-8 h-8 text-yellow-400" />
                    </div>
                </div>

                <div className="bg-gray-800/50 backdrop-blur border border-gray-700 rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-gray-400 text-sm">Total Size</p>
                            <p className="text-2xl font-bold text-white mt-1">{stats.total_size_mb?.toFixed(2) || 0} MB</p>
                        </div>
                        <HardDrive className="w-8 h-8 text-purple-400" />
                    </div>
                </div>
            </div>

            {/* Filter */}
            {databases.length > 0 && (
                <div className="flex gap-2 items-center">
                    <span className="text-gray-400 text-sm">Filter by database:</span>
                    <select
                        value={selectedDatabase || ''}
                        onChange={(e) => setSelectedDatabase(e.target.value ? parseInt(e.target.value) : null)}
                        className="px-3 py-1.5 bg-gray-800 border border-gray-700 text-white rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All Databases</option>
                        {databases.map(db => (
                            <option key={db.id} value={db.id}>{db.name}</option>
                        ))}
                    </select>
                </div>
            )}

            {/* Backups Table */}
            <div className="bg-gray-800/50 backdrop-blur border border-gray-700 rounded-xl overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead className="bg-gray-900/50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Name</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Database</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Type</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Size</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Created</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-700">
                            {filterBackups().length === 0 ? (
                                <tr>
                                    <td colSpan="7" className="px-6 py-12 text-center text-gray-400">
                                        <AlertCircle className="w-12 h-12 mx-auto mb-3 text-gray-600" />
                                        <p>No backups found</p>
                                        <p className="text-sm mt-1">Create your first backup to get started</p>
                                    </td>
                                </tr>
                            ) : (
                                filterBackups().map(backup => (
                                    <tr key={backup.id} className="hover:bg-gray-700/30 transition-colors">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm font-medium text-white">{backup.name}</div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm text-gray-300">{backup.database_name}</div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className={`px-2 py-1 text-xs rounded-full ${backup.type_badge_class}`}>
                                                {backup.type_label}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <span className={`px-2 py-1 text-xs rounded-full ${backup.status_badge_class}`}>
                                                {backup.status}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                            {backup.file_size || 'N/A'}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-400">
                                            {new Date(backup.created_at).toLocaleString()}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div className="flex justify-end gap-2">
                                                {backup.status === 'completed' && (
                                                    <>
                                                        <button
                                                            onClick={() => handleRestore(backup.id)}
                                                            className="p-2 text-yellow-400 hover:bg-yellow-400/10 rounded-lg transition-colors"
                                                            title="Restore"
                                                        >
                                                            <RotateCcw className="w-4 h-4" />
                                                        </button>
                                                        <button
                                                            onClick={() => handleDownload(backup.id)}
                                                            className="p-2 text-blue-400 hover:bg-blue-400/10 rounded-lg transition-colors"
                                                            title="Download"
                                                        >
                                                            <Download className="w-4 h-4" />
                                                        </button>
                                                    </>
                                                )}
                                                <button
                                                    onClick={() => handleDelete(backup.id)}
                                                    className="p-2 text-red-400 hover:bg-red-400/10 rounded-lg transition-colors"
                                                    title="Delete"
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Create Backup Modal */}
            {showCreateForm && (
                <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
                    <div className="bg-gray-800 rounded-xl shadow-xl max-w-md w-full border border-gray-700">
                        <div className="flex items-center justify-between p-6 border-b border-gray-700">
                            <h3 className="text-xl font-bold text-white">Create Backup</h3>
                            <button
                                onClick={() => setShowCreateForm(false)}
                                className="text-gray-400 hover:text-white transition-colors"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <form onSubmit={handleCreateBackup} className="p-6 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-300 mb-2">
                                    Database *
                                </label>
                                <select
                                    required
                                    value={formData.database_id}
                                    onChange={(e) => setFormData({ ...formData, database_id: e.target.value })}
                                    className="w-full px-4 py-2 bg-gray-900 border border-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">Select a database</option>
                                    {databases.map(db => (
                                        <option key={db.id} value={db.id}>
                                            {db.name} ({db.type_label})
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-300 mb-2">
                                    Backup Name
                                </label>
                                <input
                                    type="text"
                                    value={formData.name}
                                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                    placeholder="Leave empty for auto-generated name"
                                    className="w-full px-4 py-2 bg-gray-900 border border-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-300 mb-2">
                                    Retention Days
                                </label>
                                <input
                                    type="number"
                                    min="1"
                                    max="365"
                                    value={formData.retention_days}
                                    onChange={(e) => setFormData({ ...formData, retention_days: parseInt(e.target.value) })}
                                    className="w-full px-4 py-2 bg-gray-900 border border-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p className="text-xs text-gray-500 mt-1">How long to keep this backup (1-365 days)</p>
                            </div>

                            <div className="flex gap-3 pt-4">
                                <button
                                    type="button"
                                    onClick={() => setShowCreateForm(false)}
                                    className="flex-1 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    className="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
                                >
                                    Create Backup
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

function BackupDashboardWrapper(props) {
    return (
        <ToastProvider>
            <BackupDashboard {...props} />
            <ToastContainer />
        </ToastProvider>
    );
}

export default BackupDashboardWrapper;
