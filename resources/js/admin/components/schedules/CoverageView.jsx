import React, { useState, useEffect, useCallback } from 'react';
import {
    ShieldCheckIcon,
    ExclamationTriangleIcon,
    MapPinIcon,
} from '@heroicons/react/24/outline';
import api from '../../../lib/api';
import StatsCard from '../StatsCard';

export default function CoverageView({ campaignId }) {
    const [coverage, setCoverage] = useState([]);
    const [summary, setSummary] = useState(null);
    const [loading, setLoading] = useState(true);
    const [dateFrom, setDateFrom] = useState(new Date().toISOString().split('T')[0]);
    const [dateTo, setDateTo] = useState(() => {
        const d = new Date();
        d.setDate(d.getDate() + 7);
        return d.toISOString().split('T')[0];
    });

    const fetchCoverage = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/agent-schedules/coverage`, {
                params: { date_from: dateFrom, date_to: dateTo },
            });
            setCoverage(data.coverage || []);
            setSummary(data.summary || null);
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, dateFrom, dateTo]);

    useEffect(() => { fetchCoverage(); }, [fetchCoverage]);

    return (
        <div className="space-y-4">
            {/* Date range filter */}
            <div className="flex items-center gap-3">
                <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">From</label>
                    <input
                        type="date"
                        value={dateFrom}
                        onChange={(e) => setDateFrom(e.target.value)}
                        className="border rounded-lg px-3 py-1.5 text-sm"
                    />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-500 mb-1">To</label>
                    <input
                        type="date"
                        value={dateTo}
                        onChange={(e) => setDateTo(e.target.value)}
                        className="border rounded-lg px-3 py-1.5 text-sm"
                    />
                </div>
            </div>

            {loading ? (
                <div className="flex justify-center py-12">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" />
                </div>
            ) : (
                <>
                    {/* Summary cards */}
                    {summary && (
                        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                            <StatsCard title="Total Wards" value={summary.total_wards} icon={MapPinIcon} color="primary" />
                            <StatsCard title="Covered" value={summary.covered_wards} icon={ShieldCheckIcon} color="green" />
                            <StatsCard title="Uncovered" value={summary.uncovered_wards} icon={ExclamationTriangleIcon} color="red" />
                            <div className="bg-white rounded-xl border p-4 flex flex-col items-center justify-center">
                                <div className="text-2xl font-bold text-gray-900">{summary.coverage_percentage}%</div>
                                <div className="text-sm text-gray-500">Coverage</div>
                                <div className="w-full bg-gray-200 rounded-full h-2 mt-2">
                                    <div
                                        className={`h-2 rounded-full ${summary.coverage_percentage >= 80 ? 'bg-green-500' : summary.coverage_percentage >= 50 ? 'bg-yellow-500' : 'bg-red-500'}`}
                                        style={{ width: `${summary.coverage_percentage}%` }}
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Coverage table */}
                    {coverage.length === 0 ? (
                        <div className="text-center py-12 text-gray-500">
                            <MapPinIcon className="h-12 w-12 mx-auto mb-3 text-gray-300" />
                            <p className="font-medium">No ward data</p>
                            <p className="text-sm">Assign agents to wards to see coverage analysis.</p>
                        </div>
                    ) : (
                        <div className="bg-white rounded-lg border overflow-hidden">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ward</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Agents Assigned</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Shifts</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {coverage.map((row) => (
                                        <tr key={row.ward} className={`${!row.has_coverage ? 'bg-red-50/50' : 'hover:bg-gray-50'}`}>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <MapPinIcon className={`h-4 w-4 ${row.has_coverage ? 'text-green-500' : 'text-red-400'}`} />
                                                    <span className="font-medium text-sm text-gray-900">{row.ward}</span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-700">{row.agent_count}</td>
                                            <td className="px-4 py-3 text-sm text-gray-700">{row.schedule_count}</td>
                                            <td className="px-4 py-3">
                                                {row.has_coverage ? (
                                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700">
                                                        <ShieldCheckIcon className="h-3 w-3" /> Covered
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700">
                                                        <ExclamationTriangleIcon className="h-3 w-3" /> No Coverage
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
