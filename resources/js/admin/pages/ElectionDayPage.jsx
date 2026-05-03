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
    const [tab, setTab] = useState('tally-board');
    const { can } = useCampaignPermissions();

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-heading font-bold text-gray-900">Election Day</h1>
            </div>

            <div className="border-b border-gray-200">
                <nav className="flex space-x-8">
                    {[
                        { id: 'tally-board', label: 'Tally Board', perm: 'eday.view-tallies' },
                        { id: 'stations', label: 'Polling Stations', perm: 'eday.view' },
                        { id: 'tallies', label: 'Results', perm: 'eday.view-tallies' },
                        { id: 'incidents', label: 'Incidents', perm: 'eday.view' },
                        { id: 'command-centre', label: 'Command Centre', perm: 'eday.command-centre' },
                    ].filter((t) => can(t.perm)).map((t) => (
                        <button key={t.id} onClick={() => setTab(t.id)}
                            className={`py-3 px-1 border-b-2 text-sm font-medium ${tab === t.id ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                            {t.label}
                        </button>
                    ))}
                </nav>
            </div>

            {tab === 'tally-board' && <TallyBoardTab campaignId={campaignId} />}
            {tab === 'stations' && <StationsTab campaignId={campaignId} />}
            {tab === 'tallies' && <TalliesTab campaignId={campaignId} />}
            {tab === 'incidents' && <IncidentsTab campaignId={campaignId} />}
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

    useEffect(() => {
        (async () => {
            try {
                const { data: resp } = await api.get(`/campaigns/${campaignId}/election-day/tally-board`);
                setData(resp);
            } catch { /* handled */ }
            setLoading(false);
        })();
    }, [campaignId]);

    if (loading) return <div className="text-center py-10 text-gray-500">Loading...</div>;
    if (!data) return <EmptyState icon={FlagIcon} title="No tally data" description="Results will appear here once polling station agents submit tallies." />;

    const { candidates, overview } = data;

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <StatCard label="Total Stations" value={overview.total_stations} color="blue" />
                <StatCard label="Reported" value={overview.reported_stations} color="green" />
                <StatCard label="Reporting %" value={`${overview.reporting_percentage}%`} color="indigo" />
                <StatCard label="Votes Cast" value={overview.total_votes_cast?.toLocaleString()} color="purple" />
                <StatCard label="Registered" value={overview.total_registered?.toLocaleString()} color="gray" />
                <StatCard label="Turnout" value={`${overview.turnout_percentage}%`} color="emerald" />
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
        try {
            await api.post(`/campaigns/${campaignId}/election-day/tallies`, form);
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
            <div className="flex items-center justify-between">
                <select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} className="border rounded-lg px-3 py-2 text-sm">
                    <option value="">All statuses</option>
                    {TALLY_STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
                </select>
                <PermissionGate permission="eday.submit-results">
                    <button onClick={() => setShowCreate(true)} className="flex items-center space-x-1 bg-primary-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-primary-700">
                        <PlusIcon className="h-4 w-4" /><span>Submit Result</span>
                    </button>
                </PermissionGate>
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
                <PermissionGate permission="eday.report-incidents">
                    <button onClick={() => setShowCreate(true)} className="flex items-center space-x-1 bg-red-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-red-700">
                        <ExclamationTriangleIcon className="h-4 w-4" /><span>Report Incident</span>
                    </button>
                </PermissionGate>
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

    useEffect(() => {
        (async () => {
            try {
                const { data: resp } = await api.get(`/campaigns/${campaignId}/election-day/command-centre`);
                setData(resp);
            } catch { /* handled */ }
            setLoading(false);
        })();
    }, [campaignId]);

    if (loading) return <div className="text-center py-10 text-gray-500">Loading...</div>;
    if (!data) return <EmptyState icon={MapPinIcon} title="No data" description="Command Centre data will appear once stations and incidents are created." />;

    const { stations, incidents } = data;

    return (
        <div className="space-y-6">
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
