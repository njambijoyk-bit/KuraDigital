import React, { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import axios from 'axios';
import useSiteStore from '../stores/useSiteStore';

function formatNumber(n) {
    return Number(n || 0).toLocaleString('en-KE');
}

function StatCard({ label, value, color }) {
    const colors = {
        blue: 'bg-blue-50 text-blue-900',
        green: 'bg-green-50 text-green-900',
        indigo: 'bg-indigo-50 text-indigo-900',
        purple: 'bg-purple-50 text-purple-900',
        gray: 'bg-gray-50 text-gray-900',
        emerald: 'bg-emerald-50 text-emerald-900',
    };
    return (
        <div className={`rounded-xl p-4 ${colors[color] || 'bg-gray-50 text-gray-900'}`}>
            <p className="text-xs font-medium opacity-75">{label}</p>
            <p className="text-xl font-bold mt-1">{value}</p>
        </div>
    );
}

export default function ResultsPage() {
    const { slug } = useParams();
    const { site } = useSiteStore();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [lastRefresh, setLastRefresh] = useState(null);
    const [autoRefresh, setAutoRefresh] = useState(true);

    const fetchResults = useCallback(async () => {
        if (!site?.id) return;
        try {
            const { data: resp } = await axios.get(`/api/sites/${site.id}/election-results`);
            setData(resp.data);
            setLastRefresh(new Date());
        } catch { /* handled */ }
        setLoading(false);
    }, [site?.id]);

    useEffect(() => {
        fetchResults();
    }, [fetchResults]);

    useEffect(() => {
        if (!autoRefresh) return;
        const interval = setInterval(fetchResults, 30000);
        return () => clearInterval(interval);
    }, [autoRefresh, fetchResults]);

    const primaryColor = site?.primary_color || '#16a34a';

    if (loading) {
        return (
            <div className="min-h-[60vh] flex items-center justify-center">
                <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600" />
            </div>
        );
    }

    const overview = data?.overview;
    const candidates = data?.candidates || [];
    const maxVotes = candidates[0]?.total_votes || 1;

    return (
        <div>
            {/* Hero */}
            <section className="py-12" style={{ background: `linear-gradient(135deg, ${primaryColor}ee, ${site?.secondary_color || '#0f172a'}ee)` }}>
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-white">
                    <h1 className="text-3xl md:text-4xl font-heading font-bold mb-2">Election Results</h1>
                    <p className="text-lg opacity-90">{site?.constituency || site?.candidate_name || 'Live Results'}</p>
                    <div className="mt-4 flex items-center justify-center gap-4 text-sm opacity-75">
                        {lastRefresh && (
                            <span>Updated: {lastRefresh.toLocaleTimeString()}</span>
                        )}
                        <label className="flex items-center gap-1.5 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={autoRefresh}
                                onChange={(e) => setAutoRefresh(e.target.checked)}
                                className="rounded border-white/30"
                            />
                            Auto-refresh (30s)
                        </label>
                    </div>
                </div>
            </section>

            <section className="py-10 bg-gray-50">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                    {/* Overview stats */}
                    {overview && (
                        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                            <StatCard label="Total Stations" value={formatNumber(overview.total_stations)} color="blue" />
                            <StatCard label="Reported" value={formatNumber(overview.reported_stations)} color="green" />
                            <StatCard label="Reporting %" value={`${overview.reporting_percentage}%`} color="indigo" />
                            <StatCard label="Votes Cast" value={formatNumber(overview.total_votes_cast)} color="purple" />
                            <StatCard label="Registered" value={formatNumber(overview.total_registered)} color="gray" />
                            <StatCard label="Turnout" value={`${overview.turnout_percentage}%`} color="emerald" />
                        </div>
                    )}

                    {/* Candidate results */}
                    {candidates.length > 0 ? (
                        <div className="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                            <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                                <h3 className="text-lg font-semibold text-gray-900">Candidate Results</h3>
                                <button
                                    onClick={fetchResults}
                                    className="text-sm text-primary-600 hover:text-primary-700 font-medium"
                                >
                                    Refresh
                                </button>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Candidate</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Party</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Votes</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Stations</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Progress</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200">
                                        {candidates.map((c, i) => {
                                            const pct = Math.round((c.total_votes / maxVotes) * 100);
                                            const totalVotes = candidates.reduce((s, x) => s + x.total_votes, 0);
                                            const votePct = totalVotes > 0 ? ((c.total_votes / totalVotes) * 100).toFixed(1) : 0;
                                            return (
                                                <tr key={c.candidate_name} className={i === 0 ? 'bg-green-50' : ''}>
                                                    <td className="px-6 py-4 text-sm font-bold">{i + 1}</td>
                                                    <td className="px-6 py-4">
                                                        <p className="text-sm font-semibold">{c.candidate_name}</p>
                                                        {c.verified_count > 0 && (
                                                            <span className="text-xs text-green-600">{c.verified_count} verified</span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 text-sm text-gray-500">{c.party || '-'}</td>
                                                    <td className="px-6 py-4 text-sm text-right">
                                                        <span className="font-bold">{formatNumber(c.total_votes)}</span>
                                                        <span className="text-gray-400 ml-1">({votePct}%)</span>
                                                    </td>
                                                    <td className="px-6 py-4 text-sm text-right text-gray-500">{c.stations_reported}</td>
                                                    <td className="px-6 py-4 w-48">
                                                        <div className="w-full bg-gray-200 rounded-full h-3">
                                                            <div
                                                                className={`h-3 rounded-full transition-all duration-500 ${i === 0 ? 'bg-green-500' : 'bg-blue-400'}`}
                                                                style={{ width: `${pct}%` }}
                                                            />
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ) : (
                        <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center">
                            <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg className="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <h3 className="text-lg font-semibold text-gray-900 mb-1">No Results Yet</h3>
                            <p className="text-gray-500">Results will appear here once polling station agents begin submitting tallies.</p>
                        </div>
                    )}

                    {/* Disclaimer */}
                    <p className="text-xs text-gray-400 text-center">
                        Results shown are provisional and subject to official verification by the electoral commission.
                        {data?.last_updated && ` Last data received: ${new Date(data.last_updated).toLocaleString()}.`}
                    </p>
                </div>
            </section>
        </div>
    );
}
