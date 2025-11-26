import React, { useState } from 'react';

const EnvironmentVariables = ({ projectSlug, initialVariables = {} }) => {
    const [variables, setVariables] = useState(
        Object.entries(initialVariables).map(([key, value]) => ({ key, value, isNew: false }))
    );
    const [newKey, setNewKey] = useState('');
    const [newValue, setNewValue] = useState('');
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState(null);
    const [showValues, setShowValues] = useState({});

    const addVariable = () => {
        if (!newKey.trim()) return;

        // Check for duplicate
        if (variables.some(v => v.key === newKey.trim())) {
            setMessage({ type: 'error', text: 'Variable already exists' });
            return;
        }

        setVariables([...variables, { key: newKey.trim(), value: newValue, isNew: true }]);
        setNewKey('');
        setNewValue('');
        setMessage(null);
    };

    const removeVariable = (index) => {
        setVariables(variables.filter((_, i) => i !== index));
    };

    const updateVariable = (index, field, value) => {
        const updated = [...variables];
        updated[index] = { ...updated[index], [field]: value };
        setVariables(updated);
    };

    const toggleShowValue = (key) => {
        setShowValues(prev => ({ ...prev, [key]: !prev[key] }));
    };

    const saveVariables = async () => {
        setSaving(true);
        setMessage(null);

        try {
            // Convert array back to object
            const envObject = {};
            variables.forEach(v => {
                if (v.key.trim()) {
                    envObject[v.key.trim()] = v.value;
                }
            });

            const response = await fetch(`/dashboard/projects/${projectSlug}/env`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ variables: envObject })
            });

            const data = await response.json();

            if (data.success) {
                setMessage({ type: 'success', text: 'Environment variables saved successfully' });
                // Mark all as not new
                setVariables(variables.map(v => ({ ...v, isNew: false })));
            } else {
                setMessage({ type: 'error', text: data.error || 'Failed to save' });
            }
        } catch (error) {
            setMessage({ type: 'error', text: 'Failed to save environment variables' });
        } finally {
            setSaving(false);
        }
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addVariable();
        }
    };

    return (
        <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h3 className="text-lg font-semibold text-white">Environment Variables</h3>
                    <p className="text-sm text-gray-400 mt-1">
                        Encrypted and available during build and runtime
                    </p>
                </div>
                <span className="text-xs text-gray-500">{variables.length} variables</span>
            </div>

            {/* Message */}
            {message && (
                <div className={`mb-4 p-3 rounded-lg text-sm ${
                    message.type === 'success'
                        ? 'bg-green-500/20 text-green-400 border border-green-500/30'
                        : 'bg-red-500/20 text-red-400 border border-red-500/30'
                }`}>
                    {message.text}
                </div>
            )}

            {/* Variables List */}
            <div className="space-y-3 mb-4">
                {variables.map((variable, index) => (
                    <div key={index} className="flex items-center gap-3 group">
                        <input
                            type="text"
                            value={variable.key}
                            onChange={(e) => updateVariable(index, 'key', e.target.value.toUpperCase().replace(/[^A-Z0-9_]/g, ''))}
                            placeholder="KEY"
                            className="flex-1 px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white font-mono text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                        />
                        <div className="flex-1 relative">
                            <input
                                type={showValues[variable.key] ? 'text' : 'password'}
                                value={variable.value}
                                onChange={(e) => updateVariable(index, 'value', e.target.value)}
                                placeholder="value"
                                className="w-full px-3 py-2 pr-10 bg-gray-900 border border-gray-700 rounded-lg text-white font-mono text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                            />
                            <button
                                type="button"
                                onClick={() => toggleShowValue(variable.key)}
                                className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300"
                            >
                                {showValues[variable.key] ? (
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                    </svg>
                                ) : (
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                )}
                            </button>
                        </div>
                        <button
                            type="button"
                            onClick={() => removeVariable(index)}
                            className="p-2 text-gray-500 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors opacity-0 group-hover:opacity-100"
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                ))}

                {variables.length === 0 && (
                    <div className="text-center py-8 text-gray-500">
                        <svg className="w-12 h-12 mx-auto mb-3 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        <p>No environment variables configured</p>
                    </div>
                )}
            </div>

            {/* Add New Variable */}
            <div className="flex items-center gap-3 pt-4 border-t border-gray-700">
                <input
                    type="text"
                    value={newKey}
                    onChange={(e) => setNewKey(e.target.value.toUpperCase().replace(/[^A-Z0-9_]/g, ''))}
                    onKeyDown={handleKeyDown}
                    placeholder="NEW_KEY"
                    className="flex-1 px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white font-mono text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                />
                <input
                    type="text"
                    value={newValue}
                    onChange={(e) => setNewValue(e.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder="value"
                    className="flex-1 px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg text-white font-mono text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"
                />
                <button
                    type="button"
                    onClick={addVariable}
                    disabled={!newKey.trim()}
                    className="px-4 py-2 bg-gray-700 hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed text-white font-medium rounded-lg transition-colors"
                >
                    Add
                </button>
            </div>

            {/* Save Button */}
            <div className="mt-6 pt-6 border-t border-gray-700 flex items-center justify-between">
                <p className="text-xs text-gray-500">
                    Changes require a new deployment to take effect
                </p>
                <button
                    type="button"
                    onClick={saveVariables}
                    disabled={saving}
                    className="px-6 py-3 bg-primary hover:bg-primary/90 disabled:opacity-50 text-white font-semibold rounded-lg transition-all flex items-center gap-2"
                >
                    {saving ? (
                        <>
                            <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Saving...
                        </>
                    ) : (
                        'Save Environment Variables'
                    )}
                </button>
            </div>
        </div>
    );
};

export default EnvironmentVariables;
