import React, { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import api from '../../lib/api';
import PermissionGate from '../components/PermissionGate';

const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];

function StatCard({ label, value, sub }) {
    return (
        <div className="bg-white rounded-xl border p-4 shadow-sm">
            <p className="text-sm text-gray-500">{label}</p>
            <p className="text-2xl font-bold mt-1">{value}</p>
            {sub && <p className="text-xs text-gray-400 mt-1">{sub}</p>}
        </div>
    );
}

function BarChart({ data, labelKey, valueKey, title }) {
    const entries = Object.entries(data || {});
    if (entries.length === 0) return null;
    const max = Math.max(...entries.map(([, v]) => Number(v || 0)), 1);
    return (
        <div className="bg-white rounded-xl border p-4 shadow-sm">
            <h3 className="text-sm font-semibold text-gray-700 mb-3">{title}</h3>
            <div className="space-y-2">
                {entries.map(([key, val], i) => (
                    <div key={key}>
                        <div className="flex justify-between text-xs text-gray-500 mb-0.5">
                            <span className="capitalize">{key}</span>
                            <span>{Number(val).toLocaleString()}</span>
                        </div>
                        <div className="w-full bg-gray-100 rounded-full h-3">
                            <div className="h-3 rounded-full" style={{ width: `${(Number(val) / max) * 100}%`, backgroundColor: COLORS[i % COLORS.length] }} />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function TimeSeriesTable({ data, title, label }) {
    const entries = Object.entries(data || {});
    if (entries.length === 0) return null;
    return (
        <div className="bg-white rounded-xl border p-4 shadow-sm">
            <h3 className="text-sm font-semibold text-gray-700 mb-3">{title}</h3>
            <div className="max-h-64 overflow-y-auto">
                <table className="w-full text-sm">
                    <thead><tr><th className="text-left text-gray-500 font-normal pb-1">Date</th><th className="text-right text-gray-500 font-normal pb-1">{label}</th></tr></thead>
                    <tbody>
                        {entries.map(([date, val]) => (
                            <tr key={date} className="border-t border-gray-100">
                                <td className="py-1">{date}</td>
                                <td className="text-right font-medium">{typeof val === 'number' ? val.toLocaleString() : val}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function AnalyticsPage() {
    const { campaignId } = useParams();
    const [tab, setTab] = useState('overview');
    const [overview, setOverview] = useState(null);
    const [voterGrowth, setVoterGrowth] = useState(null);
    const [fieldActivity, setFieldActivity] = useState(null);
    const [financeTrends, setFinanceTrends] = useState(null);
    const [geographic, setGeographic] = useState(null);
    const [loading, setLoading] = useState(true);
    const [days, setDays] = useState(30);

    const loadTab = useCallback(async () => {
        setLoading(true);
        try {
            if (tab === 'overview') {
                const { data } = await api.get(`/campaigns/${campaignId}/analytics/overview`);
                setOverview(data);
            } else if (tab === 'voters') {
                const { data } = await api.get(`/campaigns/${campaignId}/analytics/voter-growth`, { params: { days } });
                setVoterGrowth(data);
            } else if (tab === 'field') {
                const { data } = await api.get(`/campaigns/${campaignId}/analytics/field-activity`, { params: { days } });
                setFieldActivity(data);
            } else if (tab === 'finance') {
                const { data } = await api.get(`/campaigns/${campaignId}/analytics/finance-trends`, { params: { days } });
                setFinanceTrends(data);
            } else if (tab === 'geographic') {
                const { data } = await api.get(`/campaigns/${campaignId}/analytics/geographic`);
                setGeographic(data);
            }
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, tab, days]);

    useEffect(() => { loadTab(); }, [loadTab]);

    const tabs = [
        { id: 'overview', label: 'Overview' },
        { id: 'voters', label: 'Voter Growth' },
        { id: 'field', label: 'Field Activity' },
        { id: 'finance', label: 'Finance Trends' },
        { id: 'geographic', label: 'Geographic' },
    ];

    return (
        <div className="space-y-6">
            <h1 className="text-2xl font-heading font-bold text-gray-900">Analytics</h1>

            <div className="flex items-center justify-between">
                <div className="flex space-x-1 bg-gray-100 rounded-lg p-1">
                    {tabs.map((t) => (
                        <button
                            key={t.id}
                            onClick={() => setTab(t.id)}
                            className={`px-3 py-1.5 text-sm rounded-md transition ${tab === t.id ? 'bg-white shadow text-gray-900 font-medium' : 'text-gray-500 hover:text-gray-700'}`}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>
                {['voters', 'field', 'finance'].includes(tab) && (
                    <select value={days} onChange={(e) => setDays(Number(e.target.value))} className="border rounded-lg px-3 py-2 text-sm">
                        <option value={7}>Last 7 days</option>
                        <option value={30}>Last 30 days</option>
                        <option value={90}>Last 90 days</option>
                        <option value={365}>Last 365 days</option>
                    </select>
                )}
            </div>

            {loading ? (
                <div className="flex items-center justify-center py-20">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" />
                </div>
            ) : (
                <>
                    {tab === 'overview' && overview && <OverviewTab data={overview} />}
                    {tab === 'voters' && voterGrowth && <VoterGrowthTab data={voterGrowth} />}
                    {tab === 'field' && fieldActivity && <FieldActivityTab data={fieldActivity} />}
                    {tab === 'finance' && financeTrends && <FinanceTrendsTab data={financeTrends} />}
                    {tab === 'geographic' && geographic && <GeographicTab data={geographic} />}
                </>
            )}
        </div>
    );
}

function OverviewTab({ data }) {
    return (
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <StatCard label="Team Members" value={data.team_members?.toLocaleString() || '0'} />
            <StatCard label="Voters" value={data.voters?.toLocaleString() || '0'} />
            <StatCard label="Field Reports" value={data.field_reports?.toLocaleString() || '0'} />
            <StatCard label="Polling Stations" value={data.polling_stations?.toLocaleString() || '0'} />
            <StatCard label="Total Budget" value={fmt(data.total_budget)} />
            <StatCard label="Total Spent" value={fmt(data.total_spent)} />
            <StatCard label="Budget Remaining" value={fmt(data.budget_remaining)} />
            <StatCard label="Total Donations" value={fmt(data.total_donations)} />
            <StatCard label="Incidents" value={data.incidents?.toLocaleString() || '0'} />
        </div>
    );
}

function VoterGrowthTab({ data }) {
    return (
        <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
                <StatCard label="Total Voters" value={data.total?.toLocaleString() || '0'} />
                <StatCard label="Period Count" value={data.period_count?.toLocaleString() || '0'} sub="new voters in selected period" />
            </div>
            <TimeSeriesTable data={data.daily} title="Daily Voter Registrations" label="Count" />
        </div>
    );
}

function FieldActivityTab({ data }) {
    return (
        <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <TimeSeriesTable data={data.check_ins_daily} title="Daily Check-ins" label="Count" />
                <TimeSeriesTable data={data.reports_daily} title="Daily Field Reports" label="Count" />
            </div>
            {data.top_agents?.length > 0 && (
                <div className="bg-white rounded-xl border p-4 shadow-sm">
                    <h3 className="text-sm font-semibold text-gray-700 mb-3">Top Agents by Check-ins</h3>
                    <table className="w-full text-sm">
                        <thead><tr><th className="text-left text-gray-500 font-normal pb-1">Agent ID</th><th className="text-right text-gray-500 font-normal pb-1">Check-ins</th></tr></thead>
                        <tbody>
                            {data.top_agents.map((a, i) => (
                                <tr key={i} className="border-t border-gray-100">
                                    <td className="py-1">{a.agent_id}</td>
                                    <td className="text-right font-medium">{a.check_ins}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function FinanceTrendsTab({ data }) {
    return (
        <div className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <TimeSeriesTable data={data.expenses_daily} title="Daily Approved Expenses" label="Amount" />
                <TimeSeriesTable data={data.donations_daily} title="Daily Donations" label="Amount" />
            </div>
            {data.budget_utilization?.length > 0 && (
                <div className="bg-white rounded-xl border p-4 shadow-sm">
                    <h3 className="text-sm font-semibold text-gray-700 mb-3">Budget Utilization by Category</h3>
                    <div className="space-y-3">
                        {data.budget_utilization.map((b, i) => (
                            <div key={i}>
                                <div className="flex justify-between text-sm mb-0.5">
                                    <span className="capitalize text-gray-600">{b.category}</span>
                                    <span className="text-gray-500">{b.utilization}% ({fmt(b.spent)} / {fmt(b.allocated)})</span>
                                </div>
                                <div className="w-full bg-gray-100 rounded-full h-3">
                                    <div className="h-3 rounded-full" style={{
                                        width: `${Math.min(b.utilization, 100)}%`,
                                        backgroundColor: b.utilization > 90 ? '#ef4444' : b.utilization > 70 ? '#f59e0b' : '#10b981',
                                    }} />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function GeographicTab({ data }) {
    return (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <BarChart data={data.voters_by_ward} title="Voters by Ward" />
            <BarChart data={data.voters_by_constituency} title="Voters by Constituency" />
            <BarChart data={data.incidents_by_ward} title="Incidents by Ward" />
            <BarChart data={data.stations_by_constituency} title="Stations by Constituency" />
        </div>
    );
}

function fmt(val) {
    if (val == null) return '-';
    return Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
