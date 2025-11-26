import React, { useState, useEffect, useRef } from 'react';

// Log Line Component with syntax highlighting
const LogLine = ({ line, index }) => {
    // Parse timestamp if present
    const timestampMatch = line.match(/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z)\s+(.*)/);
    const timestamp = timestampMatch ? timestampMatch[1] : null;
    const content = timestampMatch ? timestampMatch[2] : line;

    let className = 'text-gray-300';

    // Syntax highlighting
    if (content.includes('ERROR') || content.includes('error') || content.includes('Error')) {
        className = 'text-red-400';
    } else if (content.includes('WARN') || content.includes('warn') || content.includes('Warning')) {
        className = 'text-yellow-400';
    } else if (content.includes('INFO') || content.includes('info') || content.includes('Starting')) {
        className = 'text-blue-400';
    } else if (content.includes('DEBUG') || content.includes('debug')) {
        className = 'text-purple-400';
    } else if (content.includes('GET') || content.includes('POST') || content.includes('PUT') || content.includes('DELETE')) {
        className = 'text-green-400';
    }

    return (
        <div className="leading-relaxed font-mono text-sm">
            {timestamp && (
                <span className="text-gray-600 select-none mr-3 text-xs">
                    {new Date(timestamp).toLocaleTimeString('en-US', { hour12: false })}
                </span>
            )}
            <span className={className}>{content || ' '}</span>
        </div>
    );
};

// Container Stats Component
const ContainerStats = ({ stats }) => {
    if (!stats || Object.keys(stats).length === 0) {
        return null;
    }

    return (
        <div className="flex items-center gap-4 px-4 py-2 bg-gray-900/50 border-b border-gray-700/50 text-xs">
            {stats.cpu && (
                <div className="flex items-center gap-2">
                    <span className="text-gray-500">CPU:</span>
                    <span className="text-blue-400 font-medium">{stats.cpu}</span>
                </div>
            )}
            {stats.memory && (
                <div className="flex items-center gap-2">
                    <span className="text-gray-500">Memory:</span>
                    <span className="text-purple-400 font-medium">{stats.memory}</span>
                </div>
            )}
            {stats.memoryPercent && (
                <div className="flex items-center gap-2">
                    <span className="text-gray-500">Memory %:</span>
                    <span className="text-purple-400 font-medium">{stats.memoryPercent}</span>
                </div>
            )}
        </div>
    );
};

// Main Container Logs Component
const ContainerLogs = ({ projectSlug, initialIsRunning = false }) => {
    const [logs, setLogs] = useState('');
    const [isRunning, setIsRunning] = useState(initialIsRunning);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState(null);
    const [autoScroll, setAutoScroll] = useState(true);
    const [lineLimit, setLineLimit] = useState(100);
    const [stats, setStats] = useState({});
    const containerRef = useRef(null);
    const pollingIntervalRef = useRef(null);

    const lines = logs ? logs.split('\n').filter(l => l.trim()) : [];

    // Fetch logs
    const fetchLogs = async () => {
        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/logs/api?tail=${lineLimit}`);
            const data = await response.json();

            if (data.success) {
                setLogs(data.logs || '');
                setError(null);
            } else {
                setError(data.error || 'Failed to fetch logs');
            }
            setIsLoading(false);
        } catch (err) {
            setError('Failed to connect to server');
            setIsLoading(false);
        }
    };

    // Fetch container status
    const fetchStatus = async () => {
        try {
            const response = await fetch(`/dashboard/projects/${projectSlug}/logs/status`);
            const data = await response.json();

            if (data.success) {
                setIsRunning(data.isRunning);
                setStats(data.stats || {});
            }
        } catch (err) {
            console.error('Failed to fetch status:', err);
        }
    };

    // Auto-scroll to bottom
    useEffect(() => {
        if (autoScroll && containerRef.current) {
            containerRef.current.scrollTop = containerRef.current.scrollHeight;
        }
    }, [logs, autoScroll]);

    // Initial load
    useEffect(() => {
        fetchLogs();
        fetchStatus();
    }, [projectSlug, lineLimit]);

    // Polling for live updates
    useEffect(() => {
        if (isRunning) {
            // Poll every 2 seconds for live logs
            pollingIntervalRef.current = setInterval(() => {
                fetchLogs();
            }, 2000);

            // Poll status every 5 seconds
            const statusInterval = setInterval(() => {
                fetchStatus();
            }, 5000);

            return () => {
                clearInterval(pollingIntervalRef.current);
                clearInterval(statusInterval);
            };
        } else {
            if (pollingIntervalRef.current) {
                clearInterval(pollingIntervalRef.current);
            }
        }
    }, [isRunning, projectSlug, lineLimit]);

    const handleScroll = () => {
        if (containerRef.current) {
            const { scrollTop, scrollHeight, clientHeight } = containerRef.current;
            const isAtBottom = scrollHeight - scrollTop - clientHeight < 50;
            setAutoScroll(isAtBottom);
        }
    };

    const handleRefresh = () => {
        setIsLoading(true);
        fetchLogs();
        fetchStatus();
    };

    const handleDownload = () => {
        const blob = new Blob([logs], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `container-logs-${projectSlug}-${Date.now()}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    return (
        <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl overflow-hidden">
            {/* Header */}
            <div className="flex items-center justify-between px-4 py-3 border-b border-gray-700/50">
                <div className="flex items-center gap-3">
                    <h3 className="font-semibold text-white">Container Logs</h3>
                    {isRunning && (
                        <div className="flex items-center gap-2">
                            <span className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                            <span className="text-xs text-green-400">Running</span>
                        </div>
                    )}
                    {!isRunning && !isLoading && (
                        <div className="flex items-center gap-2">
                            <span className="w-2 h-2 bg-gray-500 rounded-full"></span>
                            <span className="text-xs text-gray-500">Stopped</span>
                        </div>
                    )}
                </div>
                <div className="flex items-center gap-2">
                    <select
                        value={lineLimit}
                        onChange={(e) => setLineLimit(parseInt(e.target.value))}
                        className="bg-gray-700 border-gray-600 text-white text-xs rounded px-2 py-1"
                    >
                        <option value={50}>50 lines</option>
                        <option value={100}>100 lines</option>
                        <option value={200}>200 lines</option>
                        <option value={500}>500 lines</option>
                        <option value={1000}>1000 lines</option>
                    </select>
                    <button
                        onClick={handleRefresh}
                        className="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white text-xs rounded transition-colors"
                        disabled={isLoading}
                    >
                        {isLoading ? 'Loading...' : 'Refresh'}
                    </button>
                    <button
                        onClick={handleDownload}
                        className="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white text-xs rounded transition-colors"
                        disabled={!logs}
                    >
                        Download
                    </button>
                </div>
            </div>

            {/* Stats Bar */}
            <ContainerStats stats={stats} />

            {/* Logs Container */}
            <div
                ref={containerRef}
                onScroll={handleScroll}
                className="p-4 bg-gray-900/50 max-h-[600px] overflow-y-auto scroll-smooth"
            >
                {isLoading && lines.length === 0 ? (
                    <div className="flex items-center gap-2 text-gray-500">
                        <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Loading logs...</span>
                    </div>
                ) : error ? (
                    <div className="text-yellow-400">
                        <p className="font-medium">⚠️ {error}</p>
                        {!isRunning && (
                            <p className="text-sm text-gray-500 mt-2">
                                Container is not running. Deploy your project to see logs.
                            </p>
                        )}
                    </div>
                ) : lines.length === 0 ? (
                    <div className="text-gray-500">
                        <p>No logs available yet.</p>
                        {isRunning && (
                            <p className="text-sm text-gray-600 mt-1">
                                Container is running. Logs will appear here as they are generated.
                            </p>
                        )}
                    </div>
                ) : (
                    lines.map((line, i) => <LogLine key={i} line={line} index={i} />)
                )}
            </div>

            {/* Auto-scroll indicator */}
            {!autoScroll && lines.length > 0 && (
                <button
                    onClick={() => {
                        setAutoScroll(true);
                        if (containerRef.current) {
                            containerRef.current.scrollTop = containerRef.current.scrollHeight;
                        }
                    }}
                    className="w-full py-2 bg-gray-700/50 text-gray-400 text-xs hover:bg-gray-700 transition-colors"
                >
                    ↓ Scroll to bottom {isRunning && '(Auto-scroll disabled)'}
                </button>
            )}

            {/* Footer Info */}
            <div className="px-4 py-2 bg-gray-900/50 border-t border-gray-700/50 flex items-center justify-between text-xs text-gray-500">
                <span>{lines.length} lines</span>
                {isRunning && (
                    <span className="flex items-center gap-1">
                        <span className="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                        Auto-refreshing every 2s
                    </span>
                )}
            </div>
        </div>
    );
};

export default ContainerLogs;
