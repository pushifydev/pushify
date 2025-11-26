import React, { useState, useEffect } from 'react';
import {
    LineChart,
    Line,
    AreaChart,
    Area,
    BarChart,
    Bar,
    PieChart,
    Pie,
    Cell,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
    ResponsiveContainer
} from 'recharts';

// Stats Card Component
const StatCard = ({ title, value, subtitle, icon, color }) => {
    const colorClasses = {
        purple: 'bg-purple-500/20 text-purple-400',
        green: 'bg-green-500/20 text-green-400',
        blue: 'bg-blue-500/20 text-blue-400',
        indigo: 'bg-indigo-500/20 text-indigo-400',
    };

    return (
        <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-5">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm text-gray-400">{title}</p>
                    <p className="text-3xl font-bold text-white mt-1">{value}</p>
                </div>
                <div className={`w-12 h-12 rounded-xl ${colorClasses[color]} flex items-center justify-center`}>
                    {icon}
                </div>
            </div>
            {subtitle && <p className="text-xs text-gray-500 mt-2">{subtitle}</p>}
        </div>
    );
};

// Custom Tooltip
const CustomTooltip = ({ active, payload, label }) => {
    if (active && payload && payload.length) {
        return (
            <div className="bg-gray-800 border border-gray-700 rounded-lg p-3 shadow-lg">
                <p className="text-gray-400 text-sm mb-1">{label}</p>
                {payload.map((entry, index) => (
                    <p key={index} style={{ color: entry.color }} className="text-sm">
                        {entry.name}: {entry.value}
                    </p>
                ))}
            </div>
        );
    }
    return null;
};

// Deployment Trends Chart
const TrendsChart = ({ apiUrl }) => {
    const [data, setData] = useState([]);
    const [days, setDays] = useState(30);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        setLoading(true);
        fetch(`${apiUrl}/trends?days=${days}`)
            .then(res => res.json())
            .then(json => {
                setData(json.trends || []);
                setLoading(false);
            })
            .catch(() => setLoading(false));
    }, [days, apiUrl]);

    if (loading) {
        return <div className="h-64 flex items-center justify-center text-gray-500">Loading...</div>;
    }

    return (
        <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
            <div className="flex items-center justify-between mb-6">
                <h3 className="font-semibold text-white">Deployment Trends</h3>
                <select
                    value={days}
                    onChange={(e) => setDays(parseInt(e.target.value))}
                    className="bg-gray-700 border-gray-600 text-white text-sm rounded-lg px-3 py-1.5"
                >
                    <option value={7}>Last 7 days</option>
                    <option value={14}>Last 14 days</option>
                    <option value={30}>Last 30 days</option>
                </select>
            </div>
            <div className="h-64">
                <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={data}>
                        <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
                        <XAxis dataKey="label" stroke="#9ca3af" tick={{ fontSize: 12 }} />
                        <YAxis stroke="#9ca3af" tick={{ fontSize: 12 }} allowDecimals={false} />
                        <Tooltip content={<CustomTooltip />} />
                        <Legend />
                        <Area
                            type="monotone"
                            dataKey="successful"
                            name="Successful"
                            stroke="#22c55e"
                            fill="#22c55e"
                            fillOpacity={0.2}
                        />
                        <Area
                            type="monotone"
                            dataKey="failed"
                            name="Failed"
                            stroke="#ef4444"
                            fill="#ef4444"
                            fillOpacity={0.2}
                        />
                    </AreaChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
};

// Status Distribution Chart
const StatusChart = ({ apiUrl }) => {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetch(`${apiUrl}/by-status`)
            .then(res => res.json())
            .then(json => {
                setData(json.data || []);
                setLoading(false);
            })
            .catch(() => setLoading(false));
    }, [apiUrl]);

    if (loading || data.length === 0) {
        return (
            <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
                <h3 className="font-semibold text-white mb-6">Deployment Status</h3>
                <div className="h-64 flex items-center justify-center text-gray-500">
                    {loading ? 'Loading...' : 'No data available'}
                </div>
            </div>
        );
    }

    return (
        <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
            <h3 className="font-semibold text-white mb-6">Deployment Status</h3>
            <div className="h-64">
                <ResponsiveContainer width="100%" height="100%">
                    <PieChart>
                        <Pie
                            data={data}
                            cx="50%"
                            cy="50%"
                            innerRadius={60}
                            outerRadius={80}
                            paddingAngle={5}
                            dataKey="count"
                            nameKey="label"
                        >
                            {data.map((entry, index) => (
                                <Cell key={`cell-${index}`} fill={entry.color} />
                            ))}
                        </Pie>
                        <Tooltip content={<CustomTooltip />} />
                        <Legend />
                    </PieChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
};

// Trigger Distribution Chart
const TriggerChart = ({ apiUrl }) => {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);

    const colors = ['#8b5cf6', '#3b82f6', '#f59e0b', '#06b6d4'];

    useEffect(() => {
        fetch(`${apiUrl}/by-trigger`)
            .then(res => res.json())
            .then(json => {
                const chartData = (json.data || []).map((item, idx) => ({
                    ...item,
                    fill: colors[idx % colors.length]
                }));
                setData(chartData);
                setLoading(false);
            })
            .catch(() => setLoading(false));
    }, [apiUrl]);

    if (loading || data.length === 0) {
        return (
            <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
                <h3 className="font-semibold text-white mb-6">Deployments by Trigger</h3>
                <div className="h-64 flex items-center justify-center text-gray-500">
                    {loading ? 'Loading...' : 'No data available'}
                </div>
            </div>
        );
    }

    return (
        <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
            <h3 className="font-semibold text-white mb-6">Deployments by Trigger</h3>
            <div className="h-64">
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data}>
                        <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
                        <XAxis dataKey="label" stroke="#9ca3af" tick={{ fontSize: 12 }} />
                        <YAxis stroke="#9ca3af" tick={{ fontSize: 12 }} allowDecimals={false} />
                        <Tooltip content={<CustomTooltip />} />
                        <Bar dataKey="count" name="Deployments" radius={[4, 4, 0, 0]}>
                            {data.map((entry, index) => (
                                <Cell key={`cell-${index}`} fill={entry.fill} />
                            ))}
                        </Bar>
                    </BarChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
};

// Build Time Trends Chart
const BuildTimeChart = ({ apiUrl }) => {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetch(`${apiUrl}/build-times?days=30`)
            .then(res => res.json())
            .then(json => {
                setData(json.data || []);
                setLoading(false);
            })
            .catch(() => setLoading(false));
    }, [apiUrl]);

    if (loading || data.length === 0) {
        return (
            <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
                <h3 className="font-semibold text-white mb-6">Build Time Trends</h3>
                <div className="h-64 flex items-center justify-center text-gray-500">
                    {loading ? 'Loading...' : 'No data available'}
                </div>
            </div>
        );
    }

    return (
        <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
            <h3 className="font-semibold text-white mb-6">Build Time Trends</h3>
            <div className="h-64">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={data}>
                        <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
                        <XAxis dataKey="label" stroke="#9ca3af" tick={{ fontSize: 12 }} />
                        <YAxis stroke="#9ca3af" tick={{ fontSize: 12 }} unit="s" />
                        <Tooltip content={<CustomTooltip />} />
                        <Legend />
                        <Line
                            type="monotone"
                            dataKey="avgBuild"
                            name="Build Time"
                            stroke="#8b5cf6"
                            strokeWidth={2}
                            dot={{ fill: '#8b5cf6', strokeWidth: 2 }}
                        />
                        <Line
                            type="monotone"
                            dataKey="avgDeploy"
                            name="Deploy Time"
                            stroke="#06b6d4"
                            strokeWidth={2}
                            dot={{ fill: '#06b6d4', strokeWidth: 2 }}
                        />
                    </LineChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
};

// Project Stats Table
const ProjectStatsTable = ({ projects }) => {
    if (!projects || projects.length === 0) {
        return (
            <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl overflow-hidden">
                <div className="px-6 py-4 border-b border-gray-700/50">
                    <h3 className="font-semibold text-white">Project Statistics</h3>
                </div>
                <div className="text-center py-12 text-gray-400">
                    <p>No projects yet</p>
                </div>
            </div>
        );
    }

    const getSuccessRateColor = (rate) => {
        if (rate >= 90) return 'text-green-400';
        if (rate >= 70) return 'text-yellow-400';
        return 'text-red-400';
    };

    const getStatusBadge = (status) => {
        const classes = {
            deployed: 'bg-green-500/20 text-green-400',
            failed: 'bg-red-500/20 text-red-400',
            default: 'bg-gray-500/20 text-gray-400'
        };
        return classes[status] || classes.default;
    };

    return (
        <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl overflow-hidden">
            <div className="px-6 py-4 border-b border-gray-700/50">
                <h3 className="font-semibold text-white">Project Statistics</h3>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full">
                    <thead className="bg-gray-900/50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Project</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-400 uppercase tracking-wider">Deployments</th>
                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-400 uppercase tracking-wider">Success Rate</th>
                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-400 uppercase tracking-wider">Avg Time</th>
                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Last Deploy</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-700/50">
                        {projects.map((project) => (
                            <tr key={project.id} className="hover:bg-gray-700/20">
                                <td className="px-6 py-4">
                                    <a href={`/dashboard/projects/${project.slug}`} className="text-white hover:text-purple-400 font-medium">
                                        {project.name}
                                    </a>
                                    <p className="text-xs text-gray-500 mt-0.5">{project.framework}</p>
                                </td>
                                <td className="px-6 py-4">
                                    <span className={`px-2 py-1 text-xs rounded-full ${getStatusBadge(project.status)}`}>
                                        {project.status?.charAt(0).toUpperCase() + project.status?.slice(1)}
                                    </span>
                                </td>
                                <td className="px-6 py-4 text-center">
                                    <span className="text-white font-medium">{project.totalDeployments}</span>
                                    <span className="text-xs text-gray-500 ml-1">({project.successful}/{project.failed})</span>
                                </td>
                                <td className="px-6 py-4 text-center">
                                    <span className={`${getSuccessRateColor(project.successRate)} font-medium`}>
                                        {project.successRate}%
                                    </span>
                                </td>
                                <td className="px-6 py-4 text-center text-gray-400">
                                    {project.avgBuildTime ? `${project.avgBuildTime}s` : '-'}
                                </td>
                                <td className="px-6 py-4 text-right text-gray-400 text-sm">
                                    {project.lastDeployedAt
                                        ? new Date(project.lastDeployedAt).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
                                        : 'Never'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

// Icons
const LightningIcon = () => (
    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
    </svg>
);

const CheckCircleIcon = () => (
    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
);

const ClockIcon = () => (
    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
);

const FolderIcon = () => (
    <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
    </svg>
);

// Main Dashboard Component
const AnalyticsDashboard = ({ stats, projectStats, apiUrl }) => {
    return (
        <div className="space-y-6">
            {/* Stats Cards */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <StatCard
                    title="Total Deployments"
                    value={stats.totalDeployments}
                    subtitle={`${stats.deploymentsThisWeek} this week`}
                    icon={<LightningIcon />}
                    color="purple"
                />
                <StatCard
                    title="Success Rate"
                    value={`${stats.successRate}%`}
                    subtitle={`${stats.successfulDeployments} successful / ${stats.failedDeployments} failed`}
                    icon={<CheckCircleIcon />}
                    color="green"
                />
                <StatCard
                    title="Avg Build Time"
                    value={stats.avgBuildTime ? `${stats.avgBuildTime}s` : '-'}
                    subtitle={stats.avgTotalTime ? `${stats.avgTotalTime}s total` : null}
                    icon={<ClockIcon />}
                    color="blue"
                />
                <StatCard
                    title="Active Projects"
                    value={stats.activeProjects}
                    subtitle={`${stats.totalProjects} total projects`}
                    icon={<FolderIcon />}
                    color="indigo"
                />
            </div>

            {/* Charts Row 1 */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <TrendsChart apiUrl={apiUrl} />
                <StatusChart apiUrl={apiUrl} />
            </div>

            {/* Charts Row 2 */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <TriggerChart apiUrl={apiUrl} />
                <BuildTimeChart apiUrl={apiUrl} />
            </div>

            {/* Project Stats Table */}
            <ProjectStatsTable projects={projectStats} />
        </div>
    );
};

export default AnalyticsDashboard;
