import React, { useState, useEffect } from "react";
import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
    ResponsiveContainer,
} from "recharts";

const MonitoringDashboard = ({ projectSlug }) => {
    const [stats, setStats] = useState(null);
    const [history, setHistory] = useState([]);
    const [loading, setLoading] = useState(true);
    const [checking, setChecking] = useState(false);
    const [selectedPeriod, setSelectedPeriod] = useState(7);

    useEffect(() => {
        fetchStats();
        fetchHistory();

        // Auto-refresh every 30 seconds
        const interval = setInterval(() => {
            fetchStats();
            fetchHistory();
        }, 30000);

        return () => clearInterval(interval);
    }, [projectSlug, selectedPeriod]);

    const fetchStats = async () => {
        try {
            const response = await fetch(
                `/dashboard/projects/${projectSlug}/monitoring/stats?days=${selectedPeriod}`
            );
            const data = await response.json();
            if (data.success) {
                setStats(data.stats);
            }
        } catch (error) {
            console.error("Failed to fetch stats:", error);
        } finally {
            setLoading(false);
        }
    };

    const fetchHistory = async () => {
        try {
            const response = await fetch(
                `/dashboard/projects/${projectSlug}/monitoring/history?limit=100`
            );
            const data = await response.json();
            if (data.success) {
                setHistory(data.history);
            }
        } catch (error) {
            console.error("Failed to fetch history:", error);
        }
    };

    const runHealthCheck = async () => {
        setChecking(true);
        try {
            const response = await fetch(
                `/dashboard/projects/${projectSlug}/monitoring/check`,
                {
                    method: "POST",
                }
            );
            const data = await response.json();
            if (data.success) {
                // Refresh data
                fetchStats();
                fetchHistory();
            }
        } catch (error) {
            console.error("Failed to run health check:", error);
        } finally {
            setChecking(false);
        }
    };

    const formatChartData = () => {
        return history.map((check) => ({
            time: new Date(check.checked_at).toLocaleTimeString("en-US", {
                hour: "2-digit",
                minute: "2-digit",
            }),
            cpu: check.cpu_usage || 0,
            memory: check.memory_usage || 0,
            responseTime: check.response_time || 0,
        }));
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center py-12">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
            </div>
        );
    }

    const latestCheck = stats?.latest_check;
    const statusColor =
        latestCheck?.status === "healthy"
            ? "green"
            : latestCheck?.status === "degraded"
            ? "yellow"
            : "red";

    return (
        <div className="space-y-6">
            {/* Header Stats */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                {/* Status */}
                <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-gray-400">Status</p>
                            <p
                                className={`text-2xl font-bold text-${statusColor}-400 capitalize mt-1`}
                            >
                                {latestCheck?.status || "Unknown"}
                            </p>
                        </div>
                        <div
                            className={`w-12 h-12 rounded-xl bg-${statusColor}-500/20 flex items-center justify-center`}
                        >
                            {latestCheck?.status === "healthy" ? (
                                <svg
                                    className={`w-6 h-6 text-${statusColor}-400`}
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            ) : (
                                <svg
                                    className={`w-6 h-6 text-${statusColor}-400`}
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                    />
                                </svg>
                            )}
                        </div>
                    </div>
                </div>

                {/* Uptime */}
                <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-gray-400">
                                Uptime ({selectedPeriod}d)
                            </p>
                            <p className="text-2xl font-bold text-white mt-1">
                                {stats?.uptime_percent?.toFixed(2)}%
                            </p>
                        </div>
                        <div className="w-12 h-12 rounded-xl bg-blue-500/20 flex items-center justify-center">
                            <svg
                                className="w-6 h-6 text-blue-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M13 10V3L4 14h7v7l9-11h-7z"
                                />
                            </svg>
                        </div>
                    </div>
                </div>

                {/* CPU Usage */}
                <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-gray-400">CPU Usage</p>
                            <p className="text-2xl font-bold text-white mt-1">
                                {latestCheck?.cpu_usage?.toFixed(1) || 0}%
                            </p>
                        </div>
                        <div className="w-12 h-12 rounded-xl bg-purple-500/20 flex items-center justify-center">
                            <svg
                                className="w-6 h-6 text-purple-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"
                                />
                            </svg>
                        </div>
                    </div>
                </div>

                {/* Memory Usage */}
                <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-gray-400">
                                Memory Usage
                            </p>
                            <p className="text-2xl font-bold text-white mt-1">
                                {latestCheck?.memory_usage?.toFixed(1) || 0}%
                            </p>
                        </div>
                        <div className="w-12 h-12 rounded-xl bg-cyan-500/20 flex items-center justify-center">
                            <svg
                                className="w-6 h-6 text-cyan-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"
                                />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            {/* Actions Bar */}
            <div className="flex items-center justify-between">
                <div className="flex gap-2">
                    {[7, 30, 90].map((days) => (
                        <button
                            key={days}
                            onClick={() => setSelectedPeriod(days)}
                            className={`px-4 py-2 rounded-lg font-medium transition-all ${
                                selectedPeriod === days
                                    ? "bg-primary text-white"
                                    : "bg-gray-700 text-gray-300 hover:bg-gray-600"
                            }`}
                        >
                            {days} days
                        </button>
                    ))}
                </div>
                <button
                    onClick={runHealthCheck}
                    disabled={checking}
                    className="px-4 py-2 bg-gray-700 hover:bg-gray-600 disabled:bg-gray-800 text-white font-medium rounded-lg transition-all inline-flex items-center gap-2"
                >
                    <svg
                        className={`w-5 h-5 ${checking ? "animate-spin" : ""}`}
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                        />
                    </svg>
                    {checking ? "Checking..." : "Run Check Now"}
                </button>
            </div>

            {/* Charts */}
            {history.length > 0 && (
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* CPU & Memory Chart */}
                    <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
                        <h3 className="text-lg font-semibold text-white mb-4">
                            CPU & Memory Usage
                        </h3>
                        <ResponsiveContainer width="100%" height={300}>
                            <LineChart data={formatChartData()}>
                                <CartesianGrid
                                    strokeDasharray="3 3"
                                    stroke="#374151"
                                />
                                <XAxis dataKey="time" stroke="#9CA3AF" />
                                <YAxis stroke="#9CA3AF" />
                                <Tooltip
                                    contentStyle={{
                                        backgroundColor: "#1F2937",
                                        border: "1px solid #374151",
                                    }}
                                    labelStyle={{ color: "#F3F4F6" }}
                                />
                                <Legend />
                                <Line
                                    type="monotone"
                                    dataKey="cpu"
                                    stroke="#8B5CF6"
                                    name="CPU %"
                                    strokeWidth={2}
                                />
                                <Line
                                    type="monotone"
                                    dataKey="memory"
                                    stroke="#06B6D4"
                                    name="Memory %"
                                    strokeWidth={2}
                                />
                            </LineChart>
                        </ResponsiveContainer>
                    </div>

                    {/* Response Time Chart */}
                    <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
                        <h3 className="text-lg font-semibold text-white mb-4">
                            Response Time
                        </h3>
                        <ResponsiveContainer width="100%" height={300}>
                            <LineChart data={formatChartData()}>
                                <CartesianGrid
                                    strokeDasharray="3 3"
                                    stroke="#374151"
                                />
                                <XAxis dataKey="time" stroke="#9CA3AF" />
                                <YAxis stroke="#9CA3AF" />
                                <Tooltip
                                    contentStyle={{
                                        backgroundColor: "#1F2937",
                                        border: "1px solid #374151",
                                    }}
                                    labelStyle={{ color: "#F3F4F6" }}
                                />
                                <Legend />
                                <Line
                                    type="monotone"
                                    dataKey="responseTime"
                                    stroke="#10B981"
                                    name="Response Time (ms)"
                                    strokeWidth={2}
                                />
                            </LineChart>
                        </ResponsiveContainer>
                    </div>
                </div>
            )}

            {/* Recent Checks Table */}
            <div className="bg-gray-800/50 border border-gray-700/50 rounded-xl p-6">
                <h3 className="text-lg font-semibold text-white mb-4">
                    Recent Health Checks
                </h3>
                <div className="overflow-x-auto">
                    <table className="w-full">
                        <thead className="bg-gray-700/50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">
                                    Time
                                </th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">
                                    CPU
                                </th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">
                                    Memory
                                </th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">
                                    Response
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-700/50">
                            {history.slice(0, 10).map((check) => (
                                <tr
                                    key={check.id}
                                    className="hover:bg-gray-700/30 transition-colors"
                                >
                                    <td className="px-4 py-3 text-sm text-gray-300">
                                        {new Date(
                                            check.checked_at
                                        ).toLocaleString()}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`px-2 py-1 rounded-full text-xs font-medium ${
                                                check.status === "healthy"
                                                    ? "bg-green-500/20 text-green-400"
                                                    : check.status ===
                                                      "degraded"
                                                    ? "bg-yellow-500/20 text-yellow-400"
                                                    : "bg-red-500/20 text-red-400"
                                            }`}
                                        >
                                            {check.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-300">
                                        {check.cpu_usage
                                            ? `${check.cpu_usage.toFixed(1)}%`
                                            : "N/A"}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-300">
                                        {check.memory_usage
                                            ? `${check.memory_usage.toFixed(
                                                  1
                                              )}%`
                                            : "N/A"}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-300">
                                        {check.response_time
                                            ? `${check.response_time}ms`
                                            : "N/A"}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
};

export default MonitoringDashboard;
