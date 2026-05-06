import React, { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    MagnifyingGlassIcon,
    CheckIcon,
    ExclamationTriangleIcon,
    FlagIcon,
    MapPinIcon,
    ArrowUpIcon,
    ArrowDownTrayIcon,
    UserGroupIcon,
    ChartBarIcon,
    ShieldExclamationIcon,
} from '@heroicons/react/24/outline';
import api from '../../lib/api';
import PermissionGate from '../components/PermissionGate';
import useCampaignPermissions from '../hooks/useCampaignPermissions';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';

const STATION_STATUSES = ['pending', 'open', 'closed', 'disputed'];
const INCIDENT_CATEGORIES = ['violence', 'irregularity', 'voter_intimidation', 'equipment_failure', 'procedural', 'other'];
const SEVERITY_LEVELS = ['low', 'medium', 'high', 'critical'];
const TALLY_STATUSES = ['provisional', 'verified', 'disputed'];
const INCIDENT_STATUSES = ['reported', 'acknowledged', 'investigating', 'resolved', 'escalated'];

const statusColors = {
    pending: 'bg-yellow-50 text-yellow-800',
    open: 'bg-green-50 text-green-800',
    closed: 'bg-gray-100 text-gray-800',
    disputed: 'bg-red-50 text-red-800',
    provisional: 'bg-yellow-50 text-yellow-800',
    verified: 'bg-green-50 text-green-800',
    reported: 'bg-orange-50 text-orange-800',
    acknowledged: 'bg-blue-50 text-blue-800',
    investigating: 'bg-purple-50 text-purple-800',
    resolved: 'bg-green-50 text-green-800',
    escalated: 'bg-red-50 text-red-800',
};

const severityColors = {
    low: 'bg-gray-100 text-gray-700',
    medium: 'bg-yellow-50 text-yellow-700',
    high: 'bg-orange-50 text-orange-700',
    critical: 'bg-red-100 text-red-800',
};

export default function ElectionDayPage() {
    const { campaignId } = useParams();
    const [tab, setTab] = useState('summary');
    const { can } = useCampaignPermissions();

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-heading font-bold text-gray-900">Election Day</h1>
            </div>

            <div className="border-b border-gray-200">
                <nav className="flex space-x-8">
                    {[
                        { id: 'summary', label: 'Summary', perm: 'eday.view-tallies' },
                        { id: 'tally-board', label: 'Tally Board', perm: 'eday.view-tallies' },
                        { id: 'stations', label: 'Polling Stations', perm: 'eday.view' },
                        { id: 'station-map', label: 'Station Map', perm: 'eday.view' },
                        { id: 'tallies', label: 'Results', perm: 'eday.view-tallies' },
                        { id: 'forms', label: 'Form 34A/B', perm: 'eday.view-tallies' },
                        { id: 'incidents', label: 'Incidents', perm: 'eday.view' },
                        { id: 'agents', label: 'Agent Deployment', perm: 'eday.command-centre' },
                        { id: 'command-centre', label: 'Command Centre', perm: 'eday.command-centre' },
                    ].filter((t) => can(t.perm)).map((t) => (
                        <button key={t.id} onClick={() => setTab(t.id)}
                            className={`py-3 px-1 border-b-2 text-sm font-medium whitespace-nowrap ${tab === t.id ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                            {t.label}
                        </button>
                    ))}
                </nav>
            </div>

            {tab === 'summary' && <SummaryTab campaignId={campaignId} />}
            {tab === 'tally-board' && <TallyBoardTab campaignId={campaignId} />}
            {tab === 'stations' && <StationsTab campaignId={campaignId} />}
            {tab === 'station-map' && <StationMapTab campaignId={campaignId} />}
            {tab === 'tallies' && <TalliesTab campaignId={campaignId} />}
            {tab === 'forms' && <FormsTab campaignId={campaignId} />}
            {tab === 'incidents' && <IncidentsTab campaignId={campaignId} />}
            {tab === 'agents' && <AgentDeploymentTab campaignId={campaignId} />}
            {tab === 'command-centre' && <CommandCentreTab campaignId={campaignId} />}
        </div>
    );
}

// =====================================================================
// Tally Board
// =====================================================================

function TallyBoardTab({ campaignId }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [autoRefresh, setAutoRefresh] = useState(true);

    const fetchData = useCallback(async () => {
        try {
            const { data: resp } = await api.get(`/campaigns/${campaignId}/election-day/tally-board`);
            setData(resp);
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId]);

    useEffect(() => { fetchData(); }, [fetchData]);

    useEffect(() => {
        if (!autoRefresh) return;
        const interval = setInterval(fetchData, 30000);
        return () => clearInterval(interval);
    }, [autoRefresh, fetchData]);

    if (loading) return <div className="text-center py-10 text-gray-500">Loading...</div>;
    if (!data) return <EmptyState icon={FlagIcon} title="No tally data" description="Results will appear here once polling station agents submit tallies." />;

    const { candidates, overview } = data;

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <span className="text-sm text-gray-500">Live data</span>
                <div className="flex items-center gap-3">
                    <a href={`/api/v1/campaigns/${campaignId}/election-day/tallies/export/csv`} className="flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900 border rounded-lg px-3 py-1.5">
                        <ArrowDownTrayIcon className="h-4 w-4" /> Export CSV
                    </a>
                    <label className="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" checked={autoRefresh} onChange={(e) => setAutoRefresh(e.target.checked)} className="rounded border-gray-300 text-primary-600" />
                        Auto-refresh (30s)
                    </label>
                    <button onClick={fetchData} className="text-sm text-primary-600 hover:text-primary-700 font-medium">Refresh now</button>
                </div>
            </div>

            {overview.turnout_percentage > 100 && (
                <div className="bg-red-50 border border-red-200 rounded-lg p-4 flex items-start gap-3">
                    <ShieldExclamationIcon className="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
                    <div>
                        <p className="text-sm font-semibold text-red-800">Anomaly Detected: Turnout exceeds 100%</p>
                        <p className="text-sm text-red-700">Overall turnout is {overview.turnout_percentage}% which exceeds the registered voter count. Review individual station tallies for accuracy.</p>
                    </div>
                </div>
            )}

            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <StatCard label="Total Stations" value={overview.total_stations} color="blue" />
                <StatCard label="Reported" value={overview.reported_stations} color="green" />
                <StatCard label="Reporting %" value={`${overview.reporting_percentage}%`} color="indigo" />
                <StatCard label="Votes Cast" value={overview.total_votes_cast?.toLocaleString()} color="purple" />
                <StatCard label="Registered" value={overview.total_registered?.toLocaleString()} color="gray" />
                <StatCard label="Turnout" value={`${overview.turnout_percentage}%`} color={overview.turnout_percentage > 100 ? 'red' : 'emerald'} />
            </div>

            {candidates && candidates.length > 0 && (
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div className="p-6 border-b border-gray-200">
                        <h3 className="text-lg font-semibold">Candidate Results</h3>
                    </div>
                    <table className="w-full">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Candidate</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Party</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Votes</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Stations</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Verified</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bar</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {candidates.map((c, i) => {
                                const maxVotes = candidates[0]?.total_votes || 1;
                                const pct = Math.round((c.total_votes / maxVotes) * 100);
                                return (
                                    <tr key={c.candidate_name} className={i === 0 ? 'bg-green-50' : ''}>
                                        <td className="px-6 py-4 text-sm font-bold">{i + 1}</td>
                                        <td className="px-6 py-4 text-sm font-medium">{c.candidate_name}</td>
                                        <td className="px-6 py-4 text-sm text-gray-500">{c.party || '-'}</td>
                                        <td className="px-6 py-4 text-sm text-right font-bold">{c.total_votes.toLocaleString()}</td>
                                        <td className="px-6 py-4 text-sm text-right">{c.stations_reported}</td>
                                        <td className="px-6 py-4 text-sm text-right text-green-600">{c.verified_count}</td>
                                        <td className="px-6 py-4 w-48">
                                            <div className="w-full bg-gray-200 rounded-full h-3">
                                                <div className={`h-3 rounded-full ${i === 0 ? 'bg-green-500' : 'bg-blue-400'}`} style={{ width: `${pct}%` }} />
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function StatCard({ label, value, color }) {
    const colorClasses = {
        blue: 'bg-blue-50 text-blue-900',
        green: 'bg-green-50 text-green-900',
        indigo: 'bg-indigo-50 text-indigo-900',
        purple: 'bg-purple-50 text-purple-900',
        gray: 'bg-gray-50 text-gray-900',
        emerald: 'bg-emerald-50 text-emerald-900',
        red: 'bg-red-50 text-red-900',
        orange: 'bg-orange-50 text-orange-900',
    };
    return (
        <div className={`rounded-xl p-4 ${colorClasses[color] || 'bg-gray-50 text-gray-900'}`}>
            <p className="text-xs font-medium opacity-75">{label}</p>
            <p className="text-xl font-bold mt-1">{value}</p>
        </div>
    );
}

// =====================================================================
// Polling Stations Tab
// =====================================================================

function StationsTab({ campaignId }) {
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [showEdit, setShowEdit] = useState(null);
    const [form, setForm] = useState({ name: '', code: '', ward: '', constituency: '', county: '', registered_voters: '', notes: '' });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [page, setPage] = useState(1);
    const [meta, setMeta] = useState({});
    const { can } = useCampaignPermissions();

    const fetch = useCallback(async (pg = page) => {
        setLoading(true);
        try {
            const params = { page: pg };
            if (search.trim()) params.search = search.trim();
            if (statusFilter) params.status = statusFilter;
            const { data } = await api.get(`/campaigns/${campaignId}/election-day/stations`, { params });
            setItems(data.data || []);
            setMeta(data.meta || { current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, search, statusFilter, page]);

    useEffect(() => { fetch(); }, [fetch]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            const payload = { ...form };
            if (showEdit) {
                await api.put(`/campaigns/${campaignId}/election-day/stations/${showEdit.id}`, payload);
                setShowEdit(null);
            } else {
                await api.post(`/campaigns/${campaignId}/election-day/stations`, payload);
                setShowCreate(false);
            }
            setForm({ name: '', code: '', ward: '', constituency: '', county: '', registered_voters: '', notes: '' });
            fetch();
        } catch (err) {
            setError(err.response?.data?.message || 'Something went wrong.');
        }
        setSubmitting(false);
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Delete this polling station?')) return;
        try {
            await api.delete(`/campaigns/${campaignId}/election-day/stations/${id}`);
            fetch();
        } catch { /* handled */ }
    };

    const columns = [
        { key: 'name', label: 'Name' },
        { key: 'code', label: 'Code' },
        { key: 'ward', label: 'Ward' },
        { key: 'constituency', label: 'Constituency' },
        { key: 'registered_voters', label: 'Registered', render: (row) => row.registered_voters?.toLocaleString() },
        { key: 'status', label: 'Status', render: (row) => <span className={`px-2 py-0.5 rounded text-xs font-medium ${statusColors[row.status] || ''}`}>{row.status}</span> },
        { key: 'assigned_agent', label: 'Agent', render: (row) => row.assigned_agent?.name || '-' },
    ];

    const actions = (row) => (
        <div className="flex space-x-1">
            {can('eday.command-centre') && (
                <>
                    <button onClick={() => { setShowEdit(row); setForm({ name: row.name, code: row.code || '', ward: row.ward || '', constituency: row.constituency || '', county: row.county || '', registered_voters: row.registered_voters || '', notes: row.notes || '' }); }} className="p-1 text-blue-600 hover:bg-blue-50 rounded"><PencilIcon className="h-4 w-4" /></button>
                    <button onClick={() => handleDelete(row.id)} className="p-1 text-red-600 hover:bg-red-50 rounded"><TrashIcon className="h-4 w-4" /></button>
                </>
            )}
        </div>
    );

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                    <div className="relative">
                        <MagnifyingGlassIcon className="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input type="text" placeholder="Search stations..." value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} className="pl-9 pr-3 py-2 border rounded-lg text-sm w-64" />
                    </div>
                    <select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} className="border rounded-lg px-3 py-2 text-sm">
                        <option value="">All statuses</option>
                        {STATION_STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
                    </select>
                </div>
                <PermissionGate permission="eday.command-centre">
                    <button onClick={() => { setShowCreate(true); setForm({ name: '', code: '', ward: '', constituency: '', county: '', registered_voters: '', notes: '' }); }} className="flex items-center space-x-1 bg-primary-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-primary-700">
                        <PlusIcon className="h-4 w-4" /><span>Add Station</span>
                    </button>
                </PermissionGate>
            </div>

            <DataTable columns={columns} data={items} loading={loading} actions={actions} meta={meta} onPageChange={(pg) => { setPage(pg); fetch(pg); }} />

            <Modal isOpen={showCreate || !!showEdit} onClose={() => { setShowCreate(false); setShowEdit(null); }} title={showEdit ? 'Edit Station' : 'Add Polling Station'}>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && <div className="text-red-600 text-sm">{error}</div>}
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                            <input type="text" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="w-full border rounded-lg px-3 py-2" required />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Code</label>
                            <input type="text" value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                    </div>
                    <div className="grid grid-cols-3 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Ward</label>
                            <input type="text" value={form.ward} onChange={(e) => setForm({ ...form, ward: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Constituency</label>
                            <input type="text" value={form.constituency} onChange={(e) => setForm({ ...form, constituency: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">County</label>
                            <input type="text" value={form.county} onChange={(e) => setForm({ ...form, county: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Registered Voters</label>
                        <input type="number" min="0" value={form.registered_voters} onChange={(e) => setForm({ ...form, registered_voters: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} rows={2} className="w-full border rounded-lg px-3 py-2" />
                    </div>
                    <div className="flex justify-end space-x-2">
                        <button type="button" onClick={() => { setShowCreate(false); setShowEdit(null); }} className="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button type="submit" disabled={submitting} className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 disabled:opacity-50">{submitting ? 'Saving...' : 'Save'}</button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}

// =====================================================================
// Tallies Tab
// =====================================================================

function TalliesTab({ campaignId }) {
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [statusFilter, setStatusFilter] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [form, setForm] = useState({ polling_station_id: '', candidate_name: '', party: '', votes: '', rejected_votes: '', total_votes_cast: '', notes: '' });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [warnings, setWarnings] = useState([]);
    const [page, setPage] = useState(1);
    const [meta, setMeta] = useState({});
    const { can } = useCampaignPermissions();

    const fetch = useCallback(async (pg = page) => {
        setLoading(true);
        try {
            const params = { page: pg };
            if (statusFilter) params.status = statusFilter;
            const { data } = await api.get(`/campaigns/${campaignId}/election-day/tallies`, { params });
            setItems(data.data || []);
            setMeta(data.meta || { current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, statusFilter, page]);

    useEffect(() => { fetch(); }, [fetch]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        setWarnings([]);
        try {
            const { data: resp } = await api.post(`/campaigns/${campaignId}/election-day/tallies`, form);
            if (resp.warnings && resp.warnings.length > 0) {
                setWarnings(resp.warnings);
            }
            setShowCreate(false);
            setForm({ polling_station_id: '', candidate_name: '', party: '', votes: '', rejected_votes: '', total_votes_cast: '', notes: '' });
            fetch();
        } catch (err) {
            setError(err.response?.data?.message || 'Something went wrong.');
        }
        setSubmitting(false);
    };

    const handleVerify = async (id) => {
        if (!window.confirm('Verify this tally result?')) return;
        try {
            await api.post(`/campaigns/${campaignId}/election-day/tallies/${id}/verify`);
            fetch();
        } catch { /* handled */ }
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Delete this tally result?')) return;
        try {
            await api.delete(`/campaigns/${campaignId}/election-day/tallies/${id}`);
            fetch();
        } catch { /* handled */ }
    };

    const columns = [
        { key: 'polling_station', label: 'Station', render: (row) => row.polling_station?.name || '-' },
        { key: 'candidate_name', label: 'Candidate' },
        { key: 'party', label: 'Party' },
        { key: 'votes', label: 'Votes', render: (row) => row.votes?.toLocaleString() },
        { key: 'total_votes_cast', label: 'Total Cast', render: (row) => row.total_votes_cast?.toLocaleString() },
        { key: 'status', label: 'Status', render: (row) => <span className={`px-2 py-0.5 rounded text-xs font-medium ${statusColors[row.status] || ''}`}>{row.status}</span> },
        { key: 'submitter', label: 'Submitted By', render: (row) => row.submitter?.name || '' },
    ];

    const actions = (row) => (
        <div className="flex space-x-1">
            {row.status === 'provisional' && can('eday.command-centre') && (
                <button onClick={() => handleVerify(row.id)} className="p-1 text-green-600 hover:bg-green-50 rounded" title="Verify"><CheckIcon className="h-4 w-4" /></button>
            )}
            {row.status !== 'verified' && can('eday.command-centre') && (
                <button onClick={() => handleDelete(row.id)} className="p-1 text-red-600 hover:bg-red-50 rounded"><TrashIcon className="h-4 w-4" /></button>
            )}
        </div>
    );

    return (
        <div className="space-y-4">
            {warnings.length > 0 && (
                <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <div className="flex items-start gap-2">
                        <ShieldExclamationIcon className="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" />
                        <div>
                            <p className="text-sm font-semibold text-amber-800">Anomaly Warnings</p>
                            <ul className="mt-1 space-y-1">
                                {warnings.map((w, i) => (
                                    <li key={i} className="text-sm text-amber-700">{w.message}</li>
                                ))}
                            </ul>
                            <button onClick={() => setWarnings([])} className="mt-2 text-xs text-amber-600 hover:text-amber-800 underline">Dismiss</button>
                        </div>
                    </div>
                </div>
            )}

            <div className="flex items-center justify-between">
                <select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} className="border rounded-lg px-3 py-2 text-sm">
                    <option value="">All statuses</option>
                    {TALLY_STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
                </select>
                <div className="flex items-center gap-2">
                    <a href={`/api/v1/campaigns/${campaignId}/election-day/tallies/export/csv`} className="flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900 border rounded-lg px-3 py-2">
                        <ArrowDownTrayIcon className="h-4 w-4" /> CSV
                    </a>
                    <PermissionGate permission="eday.submit-results">
                        <button onClick={() => setShowCreate(true)} className="flex items-center space-x-1 bg-primary-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-primary-700">
                            <PlusIcon className="h-4 w-4" /><span>Submit Result</span>
                        </button>
                    </PermissionGate>
                </div>
            </div>

            <DataTable columns={columns} data={items} loading={loading} actions={actions} meta={meta} onPageChange={(pg) => { setPage(pg); fetch(pg); }} />

            <Modal isOpen={showCreate} onClose={() => setShowCreate(false)} title="Submit Tally Result">
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && <div className="text-red-600 text-sm">{error}</div>}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Polling Station ID *</label>
                        <input type="number" value={form.polling_station_id} onChange={(e) => setForm({ ...form, polling_station_id: e.target.value })} className="w-full border rounded-lg px-3 py-2" required />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Candidate Name *</label>
                            <input type="text" value={form.candidate_name} onChange={(e) => setForm({ ...form, candidate_name: e.target.value })} className="w-full border rounded-lg px-3 py-2" required />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Party</label>
                            <input type="text" value={form.party} onChange={(e) => setForm({ ...form, party: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                    </div>
                    <div className="grid grid-cols-3 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Votes *</label>
                            <input type="number" min="0" value={form.votes} onChange={(e) => setForm({ ...form, votes: e.target.value })} className="w-full border rounded-lg px-3 py-2" required />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Rejected Votes</label>
                            <input type="number" min="0" value={form.rejected_votes} onChange={(e) => setForm({ ...form, rejected_votes: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Total Votes Cast</label>
                            <input type="number" min="0" value={form.total_votes_cast} onChange={(e) => setForm({ ...form, total_votes_cast: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} rows={2} className="w-full border rounded-lg px-3 py-2" />
                    </div>
                    <div className="flex justify-end space-x-2">
                        <button type="button" onClick={() => setShowCreate(false)} className="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button type="submit" disabled={submitting} className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 disabled:opacity-50">{submitting ? 'Submitting...' : 'Submit'}</button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}

// =====================================================================
// Incidents Tab
// =====================================================================

function IncidentsTab({ campaignId }) {
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [severityFilter, setSeverityFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [showResolve, setShowResolve] = useState(null);
    const [resolveNotes, setResolveNotes] = useState('');
    const [form, setForm] = useState({ title: '', description: '', category: 'other', severity: 'medium', ward: '', constituency: '', county: '' });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [page, setPage] = useState(1);
    const [meta, setMeta] = useState({});
    const { can } = useCampaignPermissions();

    const fetch = useCallback(async (pg = page) => {
        setLoading(true);
        try {
            const params = { page: pg };
            if (search.trim()) params.search = search.trim();
            if (severityFilter) params.severity = severityFilter;
            if (statusFilter) params.status = statusFilter;
            const { data } = await api.get(`/campaigns/${campaignId}/election-day/incidents`, { params });
            setItems(data.data || []);
            setMeta(data.meta || { current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, search, severityFilter, statusFilter, page]);

    useEffect(() => { fetch(); }, [fetch]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            await api.post(`/campaigns/${campaignId}/election-day/incidents`, form);
            setShowCreate(false);
            setForm({ title: '', description: '', category: 'other', severity: 'medium', ward: '', constituency: '', county: '' });
            fetch();
        } catch (err) {
            setError(err.response?.data?.message || 'Something went wrong.');
        }
        setSubmitting(false);
    };

    const handleResolve = async () => {
        if (!resolveNotes.trim()) return;
        try {
            await api.post(`/campaigns/${campaignId}/election-day/incidents/${showResolve}/resolve`, { resolution_notes: resolveNotes });
            setShowResolve(null);
            setResolveNotes('');
            fetch();
        } catch { /* handled */ }
    };

    const handleEscalate = async (id) => {
        if (!window.confirm('Escalate this incident to critical?')) return;
        try {
            await api.post(`/campaigns/${campaignId}/election-day/incidents/${id}/escalate`);
            fetch();
        } catch { /* handled */ }
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Delete this incident?')) return;
        try {
            await api.delete(`/campaigns/${campaignId}/election-day/incidents/${id}`);
            fetch();
        } catch { /* handled */ }
    };

    const columns = [
        { key: 'title', label: 'Title' },
        { key: 'category', label: 'Category', render: (row) => <span className="capitalize">{row.category?.replace('_', ' ')}</span> },
        { key: 'severity', label: 'Severity', render: (row) => <span className={`px-2 py-0.5 rounded text-xs font-medium ${severityColors[row.severity] || ''}`}>{row.severity}</span> },
        { key: 'status', label: 'Status', render: (row) => <span className={`px-2 py-0.5 rounded text-xs font-medium ${statusColors[row.status] || ''}`}>{row.status}</span> },
        { key: 'polling_station', label: 'Station', render: (row) => row.polling_station?.name || '-' },
        { key: 'reporter', label: 'Reported By', render: (row) => row.reporter?.name || '' },
        { key: 'created_at', label: 'Time', render: (row) => row.created_at ? new Date(row.created_at).toLocaleTimeString() : '' },
    ];

    const actions = (row) => (
        <div className="flex space-x-1">
            {row.status !== 'resolved' && can('eday.command-centre') && (
                <>
                    <button onClick={() => { setShowResolve(row.id); setResolveNotes(''); }} className="p-1 text-green-600 hover:bg-green-50 rounded" title="Resolve"><CheckIcon className="h-4 w-4" /></button>
                    <button onClick={() => handleEscalate(row.id)} className="p-1 text-red-600 hover:bg-red-50 rounded" title="Escalate"><ArrowUpIcon className="h-4 w-4" /></button>
                </>
            )}
            {can('eday.command-centre') && (
                <button onClick={() => handleDelete(row.id)} className="p-1 text-red-600 hover:bg-red-50 rounded"><TrashIcon className="h-4 w-4" /></button>
            )}
        </div>
    );

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                    <div className="relative">
                        <MagnifyingGlassIcon className="h-4 w-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input type="text" placeholder="Search incidents..." value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }} className="pl-9 pr-3 py-2 border rounded-lg text-sm w-64" />
                    </div>
                    <select value={severityFilter} onChange={(e) => { setSeverityFilter(e.target.value); setPage(1); }} className="border rounded-lg px-3 py-2 text-sm">
                        <option value="">All severities</option>
                        {SEVERITY_LEVELS.map((s) => <option key={s} value={s}>{s}</option>)}
                    </select>
                    <select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} className="border rounded-lg px-3 py-2 text-sm">
                        <option value="">All statuses</option>
                        {INCIDENT_STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
                    </select>
                </div>
                <div className="flex items-center gap-2">
                    <a href={`/api/v1/campaigns/${campaignId}/election-day/incidents/export/csv`} className="flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900 border rounded-lg px-3 py-2">
                        <ArrowDownTrayIcon className="h-4 w-4" /> CSV
                    </a>
                    <PermissionGate permission="eday.report-incidents">
                        <button onClick={() => setShowCreate(true)} className="flex items-center space-x-1 bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700">
                            <ExclamationTriangleIcon className="h-4 w-4" /><span>Report Incident</span>
                        </button>
                    </PermissionGate>
                </div>
            </div>

            <DataTable columns={columns} data={items} loading={loading} actions={actions} meta={meta} onPageChange={(pg) => { setPage(pg); fetch(pg); }} />

            <Modal isOpen={showCreate} onClose={() => setShowCreate(false)} title="Report Incident">
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && <div className="text-red-600 text-sm">{error}</div>}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                        <input type="text" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} className="w-full border rounded-lg px-3 py-2" required />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                        <textarea value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} rows={3} className="w-full border rounded-lg px-3 py-2" required />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                            <select value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} className="w-full border rounded-lg px-3 py-2">
                                {INCIDENT_CATEGORIES.map((c) => <option key={c} value={c}>{c.replace('_', ' ')}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Severity</label>
                            <select value={form.severity} onChange={(e) => setForm({ ...form, severity: e.target.value })} className="w-full border rounded-lg px-3 py-2">
                                {SEVERITY_LEVELS.map((s) => <option key={s} value={s}>{s}</option>)}
                            </select>
                        </div>
                    </div>
                    <div className="grid grid-cols-3 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Ward</label>
                            <input type="text" value={form.ward} onChange={(e) => setForm({ ...form, ward: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Constituency</label>
                            <input type="text" value={form.constituency} onChange={(e) => setForm({ ...form, constituency: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">County</label>
                            <input type="text" value={form.county} onChange={(e) => setForm({ ...form, county: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                    </div>
                    <div className="flex justify-end space-x-2">
                        <button type="button" onClick={() => setShowCreate(false)} className="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button type="submit" disabled={submitting} className="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 disabled:opacity-50">{submitting ? 'Reporting...' : 'Report'}</button>
                    </div>
                </form>
            </Modal>

            <Modal isOpen={!!showResolve} onClose={() => setShowResolve(null)} title="Resolve Incident">
                <div className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Resolution Notes *</label>
                        <textarea value={resolveNotes} onChange={(e) => setResolveNotes(e.target.value)} rows={3} className="w-full border rounded-lg px-3 py-2" required />
                    </div>
                    <div className="flex justify-end space-x-2">
                        <button onClick={() => setShowResolve(null)} className="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button onClick={handleResolve} disabled={!resolveNotes.trim()} className="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 disabled:opacity-50">Resolve</button>
                    </div>
                </div>
            </Modal>
        </div>
    );
}

// =====================================================================
// Command Centre Tab
// =====================================================================

function CommandCentreTab({ campaignId }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [autoRefresh, setAutoRefresh] = useState(true);

    const fetchData = useCallback(async () => {
        try {
            const { data: resp } = await api.get(`/campaigns/${campaignId}/election-day/command-centre`);
            setData(resp);
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId]);

    useEffect(() => { fetchData(); }, [fetchData]);

    useEffect(() => {
        if (!autoRefresh) return;
        const interval = setInterval(fetchData, 30000);
        return () => clearInterval(interval);
    }, [autoRefresh, fetchData]);

    if (loading) return <div className="text-center py-10 text-gray-500">Loading...</div>;
    if (!data) return <EmptyState icon={MapPinIcon} title="No data" description="Command Centre data will appear once stations and incidents are created." />;

    const { stations, incidents } = data;

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <span className="text-sm text-gray-500">Live command centre</span>
                <div className="flex items-center gap-3">
                    <label className="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" checked={autoRefresh} onChange={(e) => setAutoRefresh(e.target.checked)} className="rounded border-gray-300 text-primary-600" />
                        Auto-refresh (30s)
                    </label>
                    <button onClick={fetchData} className="text-sm text-primary-600 hover:text-primary-700 font-medium">Refresh now</button>
                </div>
            </div>
            <div className="grid grid-cols-2 md:grid-cols-5 gap-4">
                <StatCard label="Total Stations" value={stations.total} color="blue" />
                <StatCard label="Open" value={stations.open} color="green" />
                <StatCard label="Closed" value={stations.closed} color="gray" />
                <StatCard label="Pending" value={stations.pending} color="orange" />
                <StatCard label="Disputed" value={stations.disputed} color="red" />
            </div>

            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <StatCard label="Total Incidents" value={incidents.total} color="orange" />
                <StatCard label="Unresolved" value={incidents.unresolved} color="red" />
                <StatCard label="Critical" value={incidents.by_severity?.critical || 0} color="red" />
                <StatCard label="High" value={incidents.by_severity?.high || 0} color="orange" />
            </div>

            {incidents.by_category && Object.keys(incidents.by_category).length > 0 && (
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 className="text-lg font-semibold mb-4">Incidents by Category</h3>
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                        {Object.entries(incidents.by_category).map(([cat, count]) => (
                            <div key={cat} className="flex justify-between items-center px-4 py-3 bg-gray-50 rounded-lg">
                                <span className="text-sm capitalize">{cat.replace('_', ' ')}</span>
                                <span className="text-sm font-bold">{count}</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {incidents.recent && incidents.recent.length > 0 && (
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h3 className="text-lg font-semibold mb-4">Recent Incidents</h3>
                    <div className="space-y-3">
                        {incidents.recent.map((inc) => (
                            <div key={inc.id} className="flex items-start space-x-3 p-3 rounded-lg bg-gray-50">
                                <span className={`px-2 py-0.5 rounded text-xs font-medium ${severityColors[inc.severity] || ''}`}>{inc.severity}</span>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium truncate">{inc.title}</p>
                                    <p className="text-xs text-gray-500">{inc.polling_station?.name || inc.ward || 'Unknown location'} - {inc.reporter?.name}</p>
                                </div>
                                <span className={`px-2 py-0.5 rounded text-xs font-medium ${statusColors[inc.status] || ''}`}>{inc.status}</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

// =====================================================================
// Form 34A/B/C Tab
// =====================================================================

const FORM_TYPES = ['34A', '34B', '34C'];
const FORM_STATUSES = ['pending', 'verified', 'disputed', 'rejected'];

function FormsTab({ campaignId }) {
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [meta, setMeta] = useState({});
    const [page, setPage] = useState(1);
    const [typeFilter, setTypeFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [showUpload, setShowUpload] = useState(false);
    const [showCompare, setShowCompare] = useState(null);
    const [form, setForm] = useState({ polling_station_id: '', form_type: '34A', notes: '' });
    const [file, setFile] = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [ocrResult, setOcrResult] = useState(null);
    const [stations, setStations] = useState([]);
    const { can } = useCampaignPermissions();

    const fetch = useCallback(async (pg = 1) => {
        try {
            const params = new URLSearchParams({ page: pg });
            if (typeFilter) params.append('form_type', typeFilter);
            if (statusFilter) params.append('status', statusFilter);
            const { data } = await api.get(`/campaigns/${campaignId}/election-day/forms?${params}`);
            setItems(data.data);
            setMeta(data);
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, typeFilter, statusFilter]);

    useEffect(() => { fetch(); }, [fetch]);

    useEffect(() => {
        (async () => {
            try {
                const { data } = await api.get(`/campaigns/${campaignId}/election-day/stations?per_page=200`);
                setStations(data.data || []);
            } catch { /* handled */ }
        })();
    }, [campaignId]);

    const handleUpload = async (e) => {
        e.preventDefault();
        if (!file || !form.polling_station_id) return;
        setSubmitting(true);
        setError(null);
        setOcrResult(null);
        try {
            const fd = new FormData();
            fd.append('image', file);
            fd.append('polling_station_id', form.polling_station_id);
            fd.append('form_type', form.form_type);
            if (form.notes) fd.append('notes', form.notes);
            const { data: resp } = await api.post(`/campaigns/${campaignId}/election-day/forms`, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
            if (resp.ocr) {
                setOcrResult(resp.ocr);
            }
            setShowUpload(false);
            setFile(null);
            setForm({ polling_station_id: '', form_type: '34A', notes: '' });
            fetch();
        } catch (err) {
            setError(err.response?.data?.message || 'Upload failed');
        }
        setSubmitting(false);
    };

    const handleVerify = async (id) => {
        try {
            await api.post(`/campaigns/${campaignId}/election-day/forms/${id}/verify`);
            fetch(page);
        } catch { /* handled */ }
    };

    const handleDelete = async (id) => {
        if (!confirm('Delete this form?')) return;
        try {
            await api.delete(`/campaigns/${campaignId}/election-day/forms/${id}`);
            fetch(page);
        } catch { /* handled */ }
    };

    const handleCompare = async (id) => {
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/election-day/forms/${id}/compare`);
            setShowCompare(data);
        } catch { /* handled */ }
    };

    const columns = [
        { key: 'id', label: 'ID', render: (r) => `#${r.id}` },
        { key: 'form_type', label: 'Type', render: (r) => <span className="font-mono font-bold">{r.form_type}</span> },
        { key: 'station', label: 'Station', render: (r) => r.polling_station?.name || '-' },
        { key: 'status', label: 'Status', render: (r) => <span className={`px-2 py-0.5 rounded text-xs font-medium ${statusColors[r.status] || ''}`}>{r.status}</span> },
        { key: 'uploader', label: 'Uploaded By', render: (r) => r.uploader?.name || '-' },
        { key: 'created_at', label: 'Date', render: (r) => new Date(r.created_at).toLocaleDateString() },
    ];

    const actions = (row) => (
        <div className="flex items-center gap-1">
            <button onClick={() => handleCompare(row.id)} className="p-1 text-blue-600 hover:bg-blue-50 rounded" title="Compare">
                <MagnifyingGlassIcon className="w-4 h-4" />
            </button>
            {can('eday.command-centre') && row.status === 'pending' && (
                <button onClick={() => handleVerify(row.id)} className="p-1 text-green-600 hover:bg-green-50 rounded" title="Verify">
                    <CheckIcon className="w-4 h-4" />
                </button>
            )}
            {can('eday.command-centre') && (
                <button onClick={() => handleDelete(row.id)} className="p-1 text-red-600 hover:bg-red-50 rounded" title="Delete">
                    <TrashIcon className="w-4 h-4" />
                </button>
            )}
        </div>
    );

    return (
        <div className="space-y-4">
            {ocrResult && (
                <div className={`rounded-lg p-4 flex items-start gap-3 ${ocrResult.success ? 'bg-green-50 border border-green-200' : 'bg-amber-50 border border-amber-200'}`}>
                    <ChartBarIcon className={`h-5 w-5 flex-shrink-0 mt-0.5 ${ocrResult.success ? 'text-green-600' : 'text-amber-600'}`} />
                    <div>
                        <p className={`text-sm font-semibold ${ocrResult.success ? 'text-green-800' : 'text-amber-800'}`}>
                            {ocrResult.success
                                ? ocrResult.auto_parsed ? 'OCR: Results auto-extracted from form image' : 'OCR: Text extracted (manual review recommended)'
                                : `OCR: ${ocrResult.error || 'Could not process image'}`
                            }
                        </p>
                        {ocrResult.success && ocrResult.auto_parsed && (
                            <p className="text-sm text-green-700 mt-1">Candidate names and votes were automatically parsed. Verify accuracy before confirming.</p>
                        )}
                        <button onClick={() => setOcrResult(null)} className="mt-2 text-xs underline opacity-70 hover:opacity-100">Dismiss</button>
                    </div>
                </div>
            )}

            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <select value={typeFilter} onChange={(e) => { setTypeFilter(e.target.value); setPage(1); }} className="border rounded-lg px-3 py-2 text-sm">
                        <option value="">All Types</option>
                        {FORM_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
                    </select>
                    <select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} className="border rounded-lg px-3 py-2 text-sm">
                        <option value="">All Statuses</option>
                        {FORM_STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
                    </select>
                </div>
                <PermissionGate permission="eday.submit-results">
                    <button onClick={() => setShowUpload(true)} className="btn-primary text-sm flex items-center gap-1">
                        <PlusIcon className="w-4 h-4" /> Upload Form
                    </button>
                </PermissionGate>
            </div>

            <DataTable columns={columns} data={items} loading={loading} actions={actions} meta={meta} onPageChange={(pg) => { setPage(pg); fetch(pg); }} />

            <Modal isOpen={showUpload} onClose={() => setShowUpload(false)} title="Upload Result Form">
                <form onSubmit={handleUpload} className="space-y-4">
                    {error && <div className="text-red-600 text-sm">{error}</div>}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Polling Station *</label>
                        <select value={form.polling_station_id} onChange={(e) => setForm({ ...form, polling_station_id: e.target.value })} className="w-full border rounded-lg px-3 py-2" required>
                            <option value="">Select station...</option>
                            {stations.map((s) => <option key={s.id} value={s.id}>{s.name} ({s.code})</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Form Type *</label>
                        <select value={form.form_type} onChange={(e) => setForm({ ...form, form_type: e.target.value })} className="w-full border rounded-lg px-3 py-2">
                            {FORM_TYPES.map((t) => <option key={t} value={t}>Form {t}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Photo of Form *</label>
                        <input type="file" accept="image/*" onChange={(e) => setFile(e.target.files[0])} className="w-full border rounded-lg px-3 py-2" required />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} rows={2} className="w-full border rounded-lg px-3 py-2" />
                    </div>
                    <div className="flex justify-end space-x-2">
                        <button type="button" onClick={() => setShowUpload(false)} className="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button type="submit" disabled={submitting} className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 disabled:opacity-50">{submitting ? 'Uploading...' : 'Upload'}</button>
                    </div>
                </form>
            </Modal>

            <Modal isOpen={!!showCompare} onClose={() => setShowCompare(null)} title="Compare: Form vs Agent Tallies">
                {showCompare && (
                    <div className="space-y-4">
                        <div className="bg-gray-50 rounded-lg p-4">
                            <h4 className="font-medium text-sm mb-2">Form {showCompare.form.form_type} — {showCompare.form.polling_station?.name}</h4>
                            {showCompare.form.image_path && (
                                <img src={`/storage/${showCompare.form.image_path}`} alt="Form" className="max-h-64 rounded border" />
                            )}
                        </div>

                        {showCompare.agent_tallies.length > 0 ? (
                            <div>
                                <h4 className="font-medium text-sm mb-2">Agent-Submitted Results</h4>
                                <table className="w-full text-sm">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-3 py-2 text-left">Candidate</th>
                                            <th className="px-3 py-2 text-left">Party</th>
                                            <th className="px-3 py-2 text-right">Votes</th>
                                            <th className="px-3 py-2 text-left">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {showCompare.agent_tallies.map((t, i) => (
                                            <tr key={i}>
                                                <td className="px-3 py-2">{t.candidate_name}</td>
                                                <td className="px-3 py-2 text-gray-500">{t.party || '-'}</td>
                                                <td className="px-3 py-2 text-right font-bold">{t.votes.toLocaleString()}</td>
                                                <td className="px-3 py-2"><span className={`px-2 py-0.5 rounded text-xs ${statusColors[t.status] || ''}`}>{t.status}</span></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">No agent tallies submitted for this station yet.</p>
                        )}

                        {showCompare.discrepancies.length > 0 && (
                            <div className="bg-red-50 rounded-lg p-4">
                                <h4 className="font-medium text-sm text-red-800 mb-2">Discrepancies Found</h4>
                                <ul className="space-y-1 text-sm text-red-700">
                                    {showCompare.discrepancies.map((d, i) => (
                                        <li key={i}>
                                            {d.type === 'vote_mismatch' && `${d.candidate}: Form shows ${d.form_votes}, agent shows ${d.agent_votes} (diff: ${d.difference})`}
                                            {d.type === 'missing_in_form' && `${d.candidate}: Present in agent tallies (${d.agent_votes} votes) but not in form`}
                                            {d.type === 'missing_in_agent' && `${d.candidate}: Present in form (${d.form_votes} votes) but no agent tally`}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <div className="flex justify-end">
                            <button onClick={() => setShowCompare(null)} className="px-4 py-2 border rounded-lg text-sm">Close</button>
                        </div>
                    </div>
                )}
            </Modal>
        </div>
    );
}

// =====================================================================
// Station Map Tab (Leaflet)
// =====================================================================

function StationMapTab({ campaignId }) {
    const [stations, setStations] = useState([]);
    const [loading, setLoading] = useState(true);
    const [mapReady, setMapReady] = useState(false);
    const [L, setL] = useState(null);
    const [RL, setRL] = useState(null);

    useEffect(() => {
        (async () => {
            try {
                const { data } = await api.get(`/campaigns/${campaignId}/election-day/stations?per_page=500`);
                setStations(data.data || []);
            } catch { /* handled */ }
            setLoading(false);
        })();
    }, [campaignId]);

    useEffect(() => {
        Promise.all([
            import('leaflet'),
            import('react-leaflet'),
        ]).then(([leaflet, reactLeaflet]) => {
            setL(leaflet.default || leaflet);
            setRL(reactLeaflet);
            setMapReady(true);
        }).catch(() => setMapReady(false));
    }, []);

    if (loading) return <div className="text-center py-10 text-gray-500">Loading stations...</div>;

    const geoStations = stations.filter((s) => s.latitude && s.longitude);
    if (!geoStations.length) {
        return <EmptyState icon={MapPinIcon} title="No station coordinates" description="Add latitude and longitude to polling stations to see them on the map." />;
    }

    if (!mapReady || !RL) {
        return <div className="text-center py-10 text-gray-500">Loading map...</div>;
    }

    const { MapContainer, TileLayer, CircleMarker, Popup } = RL;

    const center = [
        geoStations.reduce((s, st) => s + parseFloat(st.latitude), 0) / geoStations.length,
        geoStations.reduce((s, st) => s + parseFloat(st.longitude), 0) / geoStations.length,
    ];

    const markerColors = {
        pending: '#f59e0b',
        open: '#22c55e',
        closed: '#6b7280',
        disputed: '#ef4444',
    };

    return (
        <div className="space-y-4">
            <div className="flex items-center gap-4 text-sm">
                {Object.entries(markerColors).map(([status, color]) => (
                    <span key={status} className="flex items-center gap-1.5">
                        <span className="w-3 h-3 rounded-full" style={{ backgroundColor: color }} />
                        <span className="capitalize">{status}</span>
                    </span>
                ))}
            </div>

            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" style={{ height: 500 }}>
                <MapContainer center={center} zoom={13} style={{ height: '100%', width: '100%' }}>
                    <TileLayer
                        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                    />
                    {geoStations.map((st) => (
                        <CircleMarker
                            key={st.id}
                            center={[parseFloat(st.latitude), parseFloat(st.longitude)]}
                            radius={8}
                            pathOptions={{ fillColor: markerColors[st.status] || '#6b7280', color: '#fff', weight: 2, fillOpacity: 0.9 }}
                        >
                            <Popup>
                                <div className="text-sm">
                                    <p className="font-bold">{st.name}</p>
                                    <p className="text-gray-500">{st.code} &middot; {st.ward}</p>
                                    <p>Status: <span className="font-medium capitalize">{st.status}</span></p>
                                    <p>Registered: {st.registered_voters?.toLocaleString()}</p>
                                </div>
                            </Popup>
                        </CircleMarker>
                    ))}
                </MapContainer>
            </div>

            <p className="text-xs text-gray-500">{geoStations.length} stations with coordinates shown on map.</p>
        </div>
    );
}

// =====================================================================
// Summary / Readiness Tab
// =====================================================================

function SummaryTab({ campaignId }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [autoRefresh, setAutoRefresh] = useState(true);

    const fetchData = useCallback(async () => {
        try {
            const { data: resp } = await api.get(`/campaigns/${campaignId}/election-day/summary`);
            setData(resp);
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId]);

    useEffect(() => { fetchData(); }, [fetchData]);

    useEffect(() => {
        if (!autoRefresh) return;
        const interval = setInterval(fetchData, 30000);
        return () => clearInterval(interval);
    }, [autoRefresh, fetchData]);

    if (loading) return <div className="text-center py-10 text-gray-500">Loading...</div>;
    if (!data) return <EmptyState icon={ChartBarIcon} title="No data yet" description="Summary will appear once election day data is available." />;

    const { reporting, turnout, tallies, stations_by_status, incidents, agents, turnout_by_ward } = data;

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold">Election Day Readiness & Summary</h2>
                <div className="flex items-center gap-3">
                    <label className="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                        <input type="checkbox" checked={autoRefresh} onChange={(e) => setAutoRefresh(e.target.checked)} className="rounded border-gray-300 text-primary-600" />
                        Auto-refresh (30s)
                    </label>
                    <button onClick={fetchData} className="text-sm text-primary-600 hover:text-primary-700 font-medium">Refresh now</button>
                </div>
            </div>

            {turnout.turnout_percentage > 100 && (
                <div className="bg-red-50 border border-red-200 rounded-lg p-4 flex items-start gap-3">
                    <ShieldExclamationIcon className="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
                    <div>
                        <p className="text-sm font-semibold text-red-800">Anomaly: Turnout exceeds 100%</p>
                        <p className="text-sm text-red-700">Overall turnout is {turnout.turnout_percentage}%. Check station-level tallies for errors.</p>
                    </div>
                </div>
            )}

            {incidents.critical > 0 && (
                <div className="bg-red-50 border border-red-200 rounded-lg p-4 flex items-start gap-3">
                    <ExclamationTriangleIcon className="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
                    <div>
                        <p className="text-sm font-semibold text-red-800">{incidents.critical} Critical Incident{incidents.critical > 1 ? 's' : ''} Unresolved</p>
                        <p className="text-sm text-red-700">Immediate attention required. Check the Incidents tab.</p>
                    </div>
                </div>
            )}

            {agents.stations_without_agent > 0 && (
                <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-start gap-3">
                    <UserGroupIcon className="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" />
                    <div>
                        <p className="text-sm font-semibold text-amber-800">{agents.stations_without_agent} Station{agents.stations_without_agent > 1 ? 's' : ''} Without Agents</p>
                        <p className="text-sm text-amber-700">Assign agents to cover all polling stations.</p>
                    </div>
                </div>
            )}

            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <StatCard label="Stations Reporting" value={`${reporting.reported}/${reporting.total_stations}`} color="blue" />
                <StatCard label="Reporting %" value={`${reporting.reporting_percentage}%`} color="indigo" />
                <StatCard label="Turnout" value={`${turnout.turnout_percentage}%`} color={turnout.turnout_percentage > 100 ? 'red' : 'emerald'} />
                <StatCard label="Votes Cast" value={turnout.total_votes_cast?.toLocaleString()} color="purple" />
            </div>

            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <StatCard label="Verified Tallies" value={tallies.verified} color="green" />
                <StatCard label="Provisional" value={tallies.provisional} color="orange" />
                <StatCard label="Disputed" value={tallies.disputed} color="red" />
                <StatCard label="Unresolved Incidents" value={incidents.unresolved} color="red" />
                <StatCard label="Active Agents" value={`${agents.active}/${agents.total}`} color="blue" />
                <StatCard label="Unmanned Stations" value={agents.stations_without_agent} color={agents.stations_without_agent > 0 ? 'orange' : 'green'} />
            </div>

            {turnout_by_ward && turnout_by_ward.length > 0 && (
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div className="p-6 border-b border-gray-200">
                        <h3 className="text-lg font-semibold">Turnout by Ward</h3>
                    </div>
                    <table className="w-full">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ward</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Stations</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Reported</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Registered</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Votes Cast</th>
                                <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Turnout</th>
                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bar</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {turnout_by_ward.map((w, i) => (
                                <tr key={i} className={w.turnout > 100 ? 'bg-red-50' : ''}>
                                    <td className="px-6 py-4 text-sm font-medium">{w.ward || 'Unknown'}</td>
                                    <td className="px-6 py-4 text-sm text-right">{w.stations}</td>
                                    <td className="px-6 py-4 text-sm text-right">{w.reported}</td>
                                    <td className="px-6 py-4 text-sm text-right">{w.registered?.toLocaleString()}</td>
                                    <td className="px-6 py-4 text-sm text-right">{w.votes_cast?.toLocaleString()}</td>
                                    <td className="px-6 py-4 text-sm text-right font-bold">{w.turnout}%</td>
                                    <td className="px-6 py-4 w-36">
                                        <div className="w-full bg-gray-200 rounded-full h-2.5">
                                            <div className={`h-2.5 rounded-full ${w.turnout > 100 ? 'bg-red-500' : w.turnout > 70 ? 'bg-green-500' : 'bg-blue-400'}`} style={{ width: `${Math.min(w.turnout, 100)}%` }} />
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

// =====================================================================
// Agent Deployment Tab
// =====================================================================

function AgentDeploymentTab({ campaignId }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [viewMode, setViewMode] = useState('all');

    const fetchData = useCallback(async () => {
        try {
            const { data: resp } = await api.get(`/campaigns/${campaignId}/election-day/agent-deployment`);
            setData(resp);
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId]);

    useEffect(() => { fetchData(); }, [fetchData]);

    if (loading) return <div className="text-center py-10 text-gray-500">Loading...</div>;
    if (!data) return <EmptyState icon={UserGroupIcon} title="No agents" description="No field agents found for this campaign." />;

    const { overview, deployed, undeployed, unmanned_stations } = data;

    const filteredAgents = viewMode === 'deployed' ? deployed : viewMode === 'undeployed' ? undeployed : [...deployed, ...undeployed];

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold">Agent Deployment Status</h2>
                <button onClick={fetchData} className="text-sm text-primary-600 hover:text-primary-700 font-medium">Refresh</button>
            </div>

            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                <StatCard label="Total Agents" value={overview.total_agents} color="blue" />
                <StatCard label="Checked In" value={overview.checked_in} color="green" />
                <StatCard label="Not Checked In" value={overview.not_checked_in} color={overview.not_checked_in > 0 ? 'orange' : 'green'} />
                <StatCard label="Unmanned Stations" value={overview.unmanned_stations} color={overview.unmanned_stations > 0 ? 'red' : 'green'} />
            </div>

            <div className="flex items-center gap-2">
                {['all', 'deployed', 'undeployed'].map((mode) => (
                    <button key={mode} onClick={() => setViewMode(mode)}
                        className={`px-3 py-1.5 rounded-lg text-sm font-medium capitalize ${viewMode === mode ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}`}>
                        {mode} ({mode === 'all' ? deployed.length + undeployed.length : mode === 'deployed' ? deployed.length : undeployed.length})
                    </button>
                ))}
            </div>

            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table className="w-full">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Agent</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ward</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Station</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Checked In</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Scheduled</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {filteredAgents.map((agent) => (
                            <tr key={agent.id} className={!agent.checked_in_today && agent.scheduled_today ? 'bg-red-50' : ''}>
                                <td className="px-6 py-4 text-sm font-medium">{agent.name}</td>
                                <td className="px-6 py-4 text-sm text-gray-500 font-mono">{agent.agent_code || '-'}</td>
                                <td className="px-6 py-4 text-sm text-gray-500">{agent.phone || '-'}</td>
                                <td className="px-6 py-4 text-sm text-gray-500">{agent.ward || '-'}</td>
                                <td className="px-6 py-4 text-sm text-gray-500">{agent.assigned_station || '-'}</td>
                                <td className="px-6 py-4 text-sm">
                                    {agent.checked_in_today
                                        ? <span className="px-2 py-0.5 bg-green-50 text-green-800 rounded text-xs font-medium">Yes</span>
                                        : <span className="px-2 py-0.5 bg-red-50 text-red-800 rounded text-xs font-medium">No</span>
                                    }
                                </td>
                                <td className="px-6 py-4 text-sm">
                                    {agent.scheduled_today
                                        ? <span className="px-2 py-0.5 bg-blue-50 text-blue-800 rounded text-xs font-medium">Yes</span>
                                        : <span className="px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs font-medium">No</span>
                                    }
                                </td>
                            </tr>
                        ))}
                        {filteredAgents.length === 0 && (
                            <tr><td colSpan={7} className="px-6 py-10 text-center text-sm text-gray-500">No agents in this category.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>

            {unmanned_stations.length > 0 && (
                <div className="bg-white rounded-xl shadow-sm border border-red-200 overflow-hidden">
                    <div className="p-6 border-b border-red-200 bg-red-50">
                        <h3 className="text-lg font-semibold text-red-800">Unmanned Stations ({unmanned_stations.length})</h3>
                        <p className="text-sm text-red-600">These stations have no assigned agent.</p>
                    </div>
                    <div className="divide-y divide-gray-200">
                        {unmanned_stations.map((station) => (
                            <div key={station.id} className="px-6 py-3 flex justify-between items-center">
                                <div>
                                    <span className="text-sm font-medium">{station.name}</span>
                                    <span className="text-sm text-gray-500 ml-2">{station.code}</span>
                                </div>
                                <span className="text-sm text-gray-500">{station.ward}</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
