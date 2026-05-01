import React, { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    MagnifyingGlassIcon,
    LightBulbIcon,
    ChartBarIcon,
    ChartPieIcon,
} from '@heroicons/react/24/outline';
import api from '../../lib/api';
import PermissionGate from '../components/PermissionGate';
import useCampaignPermissions from '../hooks/useCampaignPermissions';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';

const CATEGORIES = ['general', 'swot', 'talking-point', 'risk', 'opportunity'];
const CLEARANCES = ['public', 'internal', 'confidential', 'top_secret'];
const PRIORITIES = ['critical', 'high', 'medium', 'low'];

const priorityColors = {
    critical: 'bg-red-100 text-red-800',
    high: 'bg-orange-100 text-orange-800',
    medium: 'bg-yellow-50 text-yellow-800',
    low: 'bg-green-50 text-green-800',
};

const categoryColors = {
    general: 'bg-gray-100 text-gray-700',
    swot: 'bg-blue-50 text-blue-700',
    'talking-point': 'bg-purple-50 text-purple-700',
    risk: 'bg-red-50 text-red-700',
    opportunity: 'bg-green-50 text-green-700',
};

export default function StrategyPage() {
    const { campaignId } = useParams();
    const [tab, setTab] = useState('notes');
    const { can } = useCampaignPermissions();

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-heading font-bold text-gray-900">Strategy</h1>
            </div>

            <div className="border-b border-gray-200">
                <nav className="flex space-x-8">
                    {[
                        { id: 'notes', label: 'Notes', perm: 'strategy.view' },
                        { id: 'targets', label: 'Ward Targets', perm: 'strategy.view-electoral-math' },
                        { id: 'polls', label: 'Polls', perm: 'strategy.view-polls' },
                    ].filter((t) => can(t.perm)).map((t) => (
                        <button key={t.id} onClick={() => setTab(t.id)}
                            className={`py-3 px-1 border-b-2 text-sm font-medium ${tab === t.id ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                            {t.label}
                        </button>
                    ))}
                </nav>
            </div>

            {tab === 'notes' && <NotesTab campaignId={campaignId} />}
            {tab === 'targets' && <WardTargetsTab campaignId={campaignId} />}
            {tab === 'polls' && <PollsTab campaignId={campaignId} />}
        </div>
    );
}

function NotesTab({ campaignId }) {
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [showEdit, setShowEdit] = useState(null);
    const [form, setForm] = useState({ title: '', content: '', category: 'general', clearance_level: 'internal', ward: '', constituency: '', county: '' });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [page, setPage] = useState(1);
    const [meta, setMeta] = useState({});

    const fetch = useCallback(async (pg = page) => {
        setLoading(true);
        try {
            const params = { page: pg };
            if (search.trim()) params.search = search.trim();
            if (categoryFilter) params.category = categoryFilter;
            const { data } = await api.get(`/campaigns/${campaignId}/strategy/notes`, { params });
            setItems(data.data || []);
            setMeta(data.meta || { current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, search, categoryFilter, page]);

    useEffect(() => { fetch(); }, [fetch]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            if (showEdit) {
                await api.put(`/campaigns/${campaignId}/strategy/notes/${showEdit.id}`, form);
                setShowEdit(null);
            } else {
                await api.post(`/campaigns/${campaignId}/strategy/notes`, form);
                setShowCreate(false);
            }
            setForm({ title: '', content: '', category: 'general', clearance_level: 'internal', ward: '', constituency: '', county: '' });
            fetch();
        } catch (err) {
            setError(err.response?.data?.message || 'Something went wrong.');
        }
        setSubmitting(false);
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Delete this note?')) return;
        try { await api.delete(`/campaigns/${campaignId}/strategy/notes/${id}`); fetch(); } catch { /* handled */ }
    };

    const columns = [
        { key: 'title', label: 'Title', render: (r) => <div className="font-medium text-gray-900">{r.title}</div> },
        { key: 'category', label: 'Category', render: (r) => (
            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${categoryColors[r.category] || categoryColors.general}`}>{r.category}</span>
        )},
        { key: 'clearance_level', label: 'Clearance', render: (r) => <span className="text-xs text-gray-500">{r.clearance_level}</span> },
        { key: 'creator', label: 'Author', render: (r) => r.creator?.name || '—' },
        { key: 'updated_at', label: 'Updated', render: (r) => new Date(r.updated_at).toLocaleDateString() },
        { key: 'actions', label: '', render: (r) => (
            <div className="flex items-center space-x-2">
                <PermissionGate permission="strategy.edit">
                    <button onClick={() => { setForm({ title: r.title, content: r.content || '', category: r.category, clearance_level: r.clearance_level, ward: r.ward || '', constituency: r.constituency || '', county: r.county || '' }); setShowEdit(r); setError(null); }} className="text-gray-400 hover:text-primary-600"><PencilIcon className="h-4 w-4" /></button>
                    <button onClick={() => handleDelete(r.id)} className="text-gray-400 hover:text-red-600"><TrashIcon className="h-4 w-4" /></button>
                </PermissionGate>
            </div>
        )},
    ];

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div className="flex items-center space-x-4 flex-1">
                    <div className="relative max-w-md flex-1">
                        <MagnifyingGlassIcon className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input type="text" placeholder="Search notes..." value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                            className="w-full pl-10 pr-4 py-2 border rounded-lg text-sm" />
                    </div>
                    <select value={categoryFilter} onChange={(e) => { setCategoryFilter(e.target.value); setPage(1); }} className="border rounded-lg px-3 py-2 text-sm">
                        <option value="">All Categories</option>
                        {CATEGORIES.map((c) => <option key={c} value={c}>{c}</option>)}
                    </select>
                </div>
                <PermissionGate permission="strategy.edit">
                    <button onClick={() => { setForm({ title: '', content: '', category: 'general', clearance_level: 'internal', ward: '', constituency: '', county: '' }); setShowCreate(true); setError(null); }}
                        className="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                        <PlusIcon className="h-4 w-4 mr-2" /> New Note
                    </button>
                </PermissionGate>
            </div>

            {loading ? (
                <div className="flex justify-center py-12"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" /></div>
            ) : items.length === 0 ? (
                <EmptyState icon={LightBulbIcon} title="No strategy notes" description="Create strategy notes to document your campaign plan." />
            ) : (
                <DataTable columns={columns} data={items} />
            )}

            <Modal open={showCreate || !!showEdit} onClose={() => { setShowCreate(false); setShowEdit(null); }} title={showEdit ? 'Edit Note' : 'New Strategy Note'}>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm">{error}</div>}
                    <div><label className="block text-sm font-medium text-gray-700 mb-1">Title</label>
                        <input type="text" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} className="w-full border rounded-lg px-3 py-2" required /></div>
                    <div><label className="block text-sm font-medium text-gray-700 mb-1">Content</label>
                        <textarea value={form.content} onChange={(e) => setForm({ ...form, content: e.target.value })} className="w-full border rounded-lg px-3 py-2" rows={6} /></div>
                    <div className="grid grid-cols-2 gap-4">
                        <div><label className="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} className="w-full border rounded-lg px-3 py-2">
                                {CATEGORIES.map((c) => <option key={c} value={c}>{c}</option>)}</select></div>
                        <div><label className="block text-sm font-medium text-gray-700 mb-1">Clearance</label>
                            <select value={form.clearance_level} onChange={(e) => setForm({ ...form, clearance_level: e.target.value })} className="w-full border rounded-lg px-3 py-2">
                                {CLEARANCES.map((c) => <option key={c} value={c}>{c}</option>)}</select></div>
                    </div>
                    <div className="flex justify-end space-x-3 pt-4">
                        <button type="button" onClick={() => { setShowCreate(false); setShowEdit(null); }} className="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button type="submit" disabled={submitting} className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 disabled:opacity-50">
                            {submitting ? 'Saving...' : showEdit ? 'Update' : 'Create'}</button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}

function WardTargetsTab({ campaignId }) {
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showCreate, setShowCreate] = useState(false);
    const [showEdit, setShowEdit] = useState(null);
    const [form, setForm] = useState({ ward: '', constituency: '', county: '', registered_voters: 0, target_votes: 0, projected_turnout: 0, priority: 'medium', notes: '' });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);

    const fetch = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/strategy/ward-targets`);
            setItems(data.data || []);
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId]);

    useEffect(() => { fetch(); }, [fetch]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            if (showEdit) {
                await api.put(`/campaigns/${campaignId}/strategy/ward-targets/${showEdit.id}`, form);
                setShowEdit(null);
            } else {
                await api.post(`/campaigns/${campaignId}/strategy/ward-targets`, form);
                setShowCreate(false);
            }
            setForm({ ward: '', constituency: '', county: '', registered_voters: 0, target_votes: 0, projected_turnout: 0, priority: 'medium', notes: '' });
            fetch();
        } catch (err) {
            setError(err.response?.data?.message || 'Something went wrong.');
        }
        setSubmitting(false);
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Delete this target?')) return;
        try { await api.delete(`/campaigns/${campaignId}/strategy/ward-targets/${id}`); fetch(); } catch { /* handled */ }
    };

    const columns = [
        { key: 'ward', label: 'Ward', render: (r) => <div className="font-medium text-gray-900">{r.ward}</div> },
        { key: 'constituency', label: 'Constituency', render: (r) => r.constituency || '—' },
        { key: 'registered_voters', label: 'Registered', render: (r) => r.registered_voters?.toLocaleString() },
        { key: 'target_votes', label: 'Target', render: (r) => r.target_votes?.toLocaleString() },
        { key: 'projected_turnout', label: 'Turnout', render: (r) => r.projected_turnout?.toLocaleString() },
        { key: 'priority', label: 'Priority', render: (r) => (
            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${priorityColors[r.priority] || priorityColors.medium}`}>{r.priority}</span>
        )},
        { key: 'actions', label: '', render: (r) => (
            <div className="flex items-center space-x-2">
                <PermissionGate permission="strategy.edit-ward-targets">
                    <button onClick={() => { setForm({ ward: r.ward, constituency: r.constituency || '', county: r.county || '', registered_voters: r.registered_voters, target_votes: r.target_votes, projected_turnout: r.projected_turnout, priority: r.priority, notes: r.notes || '' }); setShowEdit(r); setError(null); }} className="text-gray-400 hover:text-primary-600"><PencilIcon className="h-4 w-4" /></button>
                    <button onClick={() => handleDelete(r.id)} className="text-gray-400 hover:text-red-600"><TrashIcon className="h-4 w-4" /></button>
                </PermissionGate>
            </div>
        )},
    ];

    return (
        <div className="space-y-4">
            <div className="flex justify-end">
                <PermissionGate permission="strategy.edit-ward-targets">
                    <button onClick={() => { setForm({ ward: '', constituency: '', county: '', registered_voters: 0, target_votes: 0, projected_turnout: 0, priority: 'medium', notes: '' }); setShowCreate(true); setError(null); }}
                        className="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                        <PlusIcon className="h-4 w-4 mr-2" /> Add Ward Target
                    </button>
                </PermissionGate>
            </div>

            {loading ? (
                <div className="flex justify-center py-12"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" /></div>
            ) : items.length === 0 ? (
                <EmptyState icon={ChartBarIcon} title="No ward targets" description="Set ward-level voter targets for your campaign strategy." />
            ) : (
                <DataTable columns={columns} data={items} />
            )}

            <Modal open={showCreate || !!showEdit} onClose={() => { setShowCreate(false); setShowEdit(null); }} title={showEdit ? 'Edit Ward Target' : 'Add Ward Target'}>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm">{error}</div>}
                    {!showEdit && (
                        <div className="grid grid-cols-3 gap-4">
                            <div><label className="block text-sm font-medium text-gray-700 mb-1">Ward</label>
                                <input type="text" value={form.ward} onChange={(e) => setForm({ ...form, ward: e.target.value })} className="w-full border rounded-lg px-3 py-2" required /></div>
                            <div><label className="block text-sm font-medium text-gray-700 mb-1">Constituency</label>
                                <input type="text" value={form.constituency} onChange={(e) => setForm({ ...form, constituency: e.target.value })} className="w-full border rounded-lg px-3 py-2" /></div>
                            <div><label className="block text-sm font-medium text-gray-700 mb-1">County</label>
                                <input type="text" value={form.county} onChange={(e) => setForm({ ...form, county: e.target.value })} className="w-full border rounded-lg px-3 py-2" /></div>
                        </div>
                    )}
                    <div className="grid grid-cols-3 gap-4">
                        <div><label className="block text-sm font-medium text-gray-700 mb-1">Registered Voters</label>
                            <input type="number" value={form.registered_voters} onChange={(e) => setForm({ ...form, registered_voters: parseInt(e.target.value) || 0 })} className="w-full border rounded-lg px-3 py-2" min={0} /></div>
                        <div><label className="block text-sm font-medium text-gray-700 mb-1">Target Votes</label>
                            <input type="number" value={form.target_votes} onChange={(e) => setForm({ ...form, target_votes: parseInt(e.target.value) || 0 })} className="w-full border rounded-lg px-3 py-2" min={0} /></div>
                        <div><label className="block text-sm font-medium text-gray-700 mb-1">Projected Turnout</label>
                            <input type="number" value={form.projected_turnout} onChange={(e) => setForm({ ...form, projected_turnout: parseInt(e.target.value) || 0 })} className="w-full border rounded-lg px-3 py-2" min={0} /></div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div><label className="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                            <select value={form.priority} onChange={(e) => setForm({ ...form, priority: e.target.value })} className="w-full border rounded-lg px-3 py-2">
                                {PRIORITIES.map((p) => <option key={p} value={p}>{p}</option>)}</select></div>
                    </div>
                    <div><label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} className="w-full border rounded-lg px-3 py-2" rows={3} /></div>
                    <div className="flex justify-end space-x-3 pt-4">
                        <button type="button" onClick={() => { setShowCreate(false); setShowEdit(null); }} className="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button type="submit" disabled={submitting} className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 disabled:opacity-50">
                            {submitting ? 'Saving...' : showEdit ? 'Update' : 'Create'}</button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}

function PollsTab({ campaignId }) {
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showCreate, setShowCreate] = useState(false);
    const [form, setForm] = useState({ title: '', pollster: '', poll_date: '', sample_size: '', margin_of_error: '', clearance_level: 'internal', results: [{ candidate: '', percentage: '', party: '' }], notes: '' });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);

    const fetch = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/strategy/polls`);
            setItems(data.data || []);
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId]);

    useEffect(() => { fetch(); }, [fetch]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            await api.post(`/campaigns/${campaignId}/strategy/polls`, {
                ...form,
                sample_size: form.sample_size ? parseInt(form.sample_size) : null,
                margin_of_error: form.margin_of_error ? parseFloat(form.margin_of_error) : null,
                results: form.results.filter((r) => r.candidate).map((r) => ({ ...r, percentage: parseFloat(r.percentage) || 0 })),
            });
            setShowCreate(false);
            setForm({ title: '', pollster: '', poll_date: '', sample_size: '', margin_of_error: '', clearance_level: 'internal', results: [{ candidate: '', percentage: '', party: '' }], notes: '' });
            fetch();
        } catch (err) {
            setError(err.response?.data?.message || 'Something went wrong.');
        }
        setSubmitting(false);
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Delete this poll?')) return;
        try { await api.delete(`/campaigns/${campaignId}/strategy/polls/${id}`); fetch(); } catch { /* handled */ }
    };

    const columns = [
        { key: 'title', label: 'Poll', render: (r) => <div className="font-medium text-gray-900">{r.title}</div> },
        { key: 'pollster', label: 'Pollster', render: (r) => r.pollster || '—' },
        { key: 'poll_date', label: 'Date', render: (r) => new Date(r.poll_date).toLocaleDateString() },
        { key: 'sample_size', label: 'Sample', render: (r) => r.sample_size?.toLocaleString() || '—' },
        { key: 'results', label: 'Top Result', render: (r) => {
            const top = (r.results || []).sort((a, b) => b.percentage - a.percentage)[0];
            return top ? `${top.candidate}: ${top.percentage}%` : '—';
        }},
        { key: 'actions', label: '', render: (r) => (
            <PermissionGate permission="strategy.edit">
                <button onClick={() => handleDelete(r.id)} className="text-gray-400 hover:text-red-600"><TrashIcon className="h-4 w-4" /></button>
            </PermissionGate>
        )},
    ];

    return (
        <div className="space-y-4">
            <div className="flex justify-end">
                <PermissionGate permission="strategy.edit">
                    <button onClick={() => { setShowCreate(true); setError(null); }}
                        className="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                        <PlusIcon className="h-4 w-4 mr-2" /> Add Poll
                    </button>
                </PermissionGate>
            </div>

            {loading ? (
                <div className="flex justify-center py-12"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" /></div>
            ) : items.length === 0 ? (
                <EmptyState icon={ChartPieIcon} title="No polls" description="Add polling data to track campaign performance." />
            ) : (
                <DataTable columns={columns} data={items} />
            )}

            <Modal open={showCreate} onClose={() => setShowCreate(false)} title="Add Poll Data">
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm">{error}</div>}
                    <div><label className="block text-sm font-medium text-gray-700 mb-1">Title</label>
                        <input type="text" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} className="w-full border rounded-lg px-3 py-2" required /></div>
                    <div className="grid grid-cols-2 gap-4">
                        <div><label className="block text-sm font-medium text-gray-700 mb-1">Pollster</label>
                            <input type="text" value={form.pollster} onChange={(e) => setForm({ ...form, pollster: e.target.value })} className="w-full border rounded-lg px-3 py-2" /></div>
                        <div><label className="block text-sm font-medium text-gray-700 mb-1">Date</label>
                            <input type="date" value={form.poll_date} onChange={(e) => setForm({ ...form, poll_date: e.target.value })} className="w-full border rounded-lg px-3 py-2" required /></div>
                    </div>
                    <div className="grid grid-cols-3 gap-4">
                        <div><label className="block text-sm font-medium text-gray-700 mb-1">Sample Size</label>
                            <input type="number" value={form.sample_size} onChange={(e) => setForm({ ...form, sample_size: e.target.value })} className="w-full border rounded-lg px-3 py-2" min={1} /></div>
                        <div><label className="block text-sm font-medium text-gray-700 mb-1">Margin of Error (%)</label>
                            <input type="number" step="0.1" value={form.margin_of_error} onChange={(e) => setForm({ ...form, margin_of_error: e.target.value })} className="w-full border rounded-lg px-3 py-2" /></div>
                        <div><label className="block text-sm font-medium text-gray-700 mb-1">Clearance</label>
                            <select value={form.clearance_level} onChange={(e) => setForm({ ...form, clearance_level: e.target.value })} className="w-full border rounded-lg px-3 py-2">
                                {CLEARANCES.map((c) => <option key={c} value={c}>{c}</option>)}</select></div>
                    </div>
                    <div className="border-t pt-4">
                        <div className="flex items-center justify-between mb-3">
                            <label className="block text-sm font-medium text-gray-700">Results</label>
                            <button type="button" onClick={() => setForm({ ...form, results: [...form.results, { candidate: '', percentage: '', party: '' }] })}
                                className="text-sm text-primary-600 hover:text-primary-700 font-medium">+ Add Candidate</button>
                        </div>
                        {form.results.map((r, idx) => (
                            <div key={idx} className="grid grid-cols-4 gap-2 mb-2">
                                <input type="text" value={r.candidate} onChange={(e) => { const u = [...form.results]; u[idx].candidate = e.target.value; setForm({ ...form, results: u }); }}
                                    placeholder="Candidate" className="border rounded px-2 py-1.5 text-sm col-span-2" required />
                                <input type="number" step="0.1" value={r.percentage} onChange={(e) => { const u = [...form.results]; u[idx].percentage = e.target.value; setForm({ ...form, results: u }); }}
                                    placeholder="%" className="border rounded px-2 py-1.5 text-sm" required />
                                {form.results.length > 1 && (
                                    <button type="button" onClick={() => setForm({ ...form, results: form.results.filter((_, i) => i !== idx) })}
                                        className="text-xs text-red-500 hover:text-red-700">Remove</button>
                                )}
                            </div>
                        ))}
                    </div>
                    <div className="flex justify-end space-x-3 pt-4">
                        <button type="button" onClick={() => setShowCreate(false)} className="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button type="submit" disabled={submitting} className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 disabled:opacity-50">
                            {submitting ? 'Saving...' : 'Add Poll'}</button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
