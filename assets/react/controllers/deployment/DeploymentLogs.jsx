import React, { useState, useEffect, useRef } from 'react';

// Log Line Component with syntax highlighting
const LogLine = ({ line, index }) => {
    let className = 'text-gray-300';

    if (line.startsWith('✓') || line.toLowerCase().includes('success') || line.toLowerCase().includes('done')) {
        className = 'text-green-400';
    } else if (line.startsWith('❌') || line.includes('ERROR') || line.includes('error') || line.includes('failed')) {
        className = 'text-red-400 font-medium';
    } else if (/^[📦🔨📝📤🚀⚠️]/.test(line)) {
        className = 'text-blue-400 font-medium';
    } else if (line.includes('Step') || line.includes('---') || line.startsWith('#')) {
        className = 'text-yellow-400';
    } else if (line.startsWith('FROM') || line.startsWith('RUN') || line.startsWith('COPY') || line.startsWith('WORKDIR') || line.startsWith('ENV')) {
        className = 'text-purple-400';
    }

    return (
        <div className={`${className} leading-relaxed`}>
            <span className="text-gray-600 select-none mr-3 text-xs">{String(index + 1).padStart(3, ' ')}</span>
            {line || ' '}
        </div>
    );
};

// Log Panel Component
const LogPanel = ({ title, logs, isActive, duration }) => {
    const containerRef = useRef(null);
    const [autoScroll, setAutoScroll] = useState(true);

    const lines = logs ? logs.split('\n').filter(l => l.trim()) : [];

    useEffect(() => {
        if (autoScroll && containerRef.current) {
            containerRef.current.scrollTop = containerRef.current.scrollHeight;
        }
    }, [logs, autoScroll]);

    const handleScroll = () => {
        if (containerRef.current) {
            const { scrollTop, scrollHeight, clientHeight } = containerRef.current;
            const isAtBottom = scrollHeight - scrollTop - clientHeight < 50;
            setAutoScroll(isAtBottom);
        }
    };

    return (
        <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl overflow-hidden">
            <div className="flex items-center justify-between px-4 py-3 border-b border-gray-700/50">
                <div className="flex items-center gap-3">
                    <h3 className="font-semibold text-white">{title}</h3>
                    {isActive && (
                        <div className="flex items-center gap-2">
                            <span className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                            <span className="text-xs text-green-400">Live</span>
                        </div>
                    )}
                </div>
                <div className="flex items-center gap-3">
                    {duration && <span className="text-xs text-gray-500">{duration}s</span>}
                    <span className="text-xs text-gray-500">{lines.length} lines</span>
                </div>
            </div>
            <div
                ref={containerRef}
                onScroll={handleScroll}
                className="p-4 bg-gray-900/50 font-mono text-sm max-h-[400px] overflow-y-auto scroll-smooth"
            >
                {lines.length === 0 ? (
                    <div className="flex items-center gap-2 text-gray-500">
                        {isActive ? (
                            <>
                                <svg className="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Waiting for output...</span>
                            </>
                        ) : (
                            <span>No logs available</span>
                        )}
                    </div>
                ) : (
                    lines.map((line, i) => <LogLine key={i} line={line} index={i} />)
                )}
            </div>
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
                    ↓ Scroll to bottom
                </button>
            )}
        </div>
    );
};

// Main Component
const DeploymentLogs = ({
    deploymentId,
    projectSlug,
    initialStatus,
    initialBuildLogs,
    initialDeployLogs,
    initialError,
    isRunning: isRunningInitial
}) => {
    const [status, setStatus] = useState(initialStatus);
    const [buildLogs, setBuildLogs] = useState(initialBuildLogs || '');
    const [deployLogs, setDeployLogs] = useState(initialDeployLogs || '');
    const [errorMessage, setErrorMessage] = useState(initialError || '');
    const [buildDuration, setBuildDuration] = useState(null);
    const [deployDuration, setDeployDuration] = useState(null);
    const [isRunning, setIsRunning] = useState(isRunningInitial);

    useEffect(() => {
        if (!isRunning) return;

        let timeoutId;
        let isMounted = true;

        const fetchLogs = async () => {
            try {
                const response = await fetch(`/dashboard/projects/${projectSlug}/deployments/${deploymentId}/logs`);
                const data = await response.json();

                if (!isMounted) return;

                setStatus(data.status);
                setBuildLogs(data.buildLogs || '');
                setDeployLogs(data.deployLogs || '');
                setErrorMessage(data.errorMessage || '');
                setBuildDuration(data.buildDuration);
                setDeployDuration(data.deployDuration);

                // Update status badge in the page
                const statusBadge = document.getElementById('status-badge');
                if (statusBadge) {
                    statusBadge.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
                }

                if (data.isFinished) {
                    setIsRunning(false);
                    // Reload after short delay to show final state
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    // Continue polling - every 500ms
                    timeoutId = setTimeout(fetchLogs, 500);
                }
            } catch (error) {
                console.error('Error fetching logs:', error);
                if (isMounted) {
                    timeoutId = setTimeout(fetchLogs, 2000);
                }
            }
        };

        // Start fetching immediately
        fetchLogs();

        return () => {
            isMounted = false;
            if (timeoutId) clearTimeout(timeoutId);
        };
    }, [isRunning, deploymentId, projectSlug]);

    const isBuildActive = isRunning && ['queued', 'building'].includes(status);
    const isDeployActive = isRunning && status === 'deploying';

    return (
        <div className="space-y-4">
            <LogPanel
                title="Build Logs"
                logs={buildLogs}
                isActive={isBuildActive}
                duration={buildDuration}
            />

            <LogPanel
                title="Deploy Logs"
                logs={deployLogs}
                isActive={isDeployActive}
                duration={deployDuration}
            />

            {errorMessage && (
                <div className="bg-red-500/10 border border-red-500/30 rounded-xl p-4">
                    <h3 className="font-semibold text-red-400 mb-2">Error</h3>
                    <pre className="text-red-300 text-sm whitespace-pre-wrap">{errorMessage}</pre>
                </div>
            )}
        </div>
    );
};

export default DeploymentLogs;
