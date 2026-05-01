import React, { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    MagnifyingGlassIcon,
    MapPinIcon,
    UserIcon,
    ClockIcon,
} from '@heroicons/react/24/outline';
import api from '../../lib/api';
import PermissionGate from '../components/PermissionGate';
import useCampaignPermissions from '../hooks/useCampaignPermissions';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';
import StatsCard from '../components/StatsCard';

const STATUSES = ['active', 'inactive', 'suspended'];

const emptyForm = {
    user_id: '', agent_code: '', status: 'active',
    ward: '', constituency: '', county: '',
    polling_station: '', phone: '', notes: '',
};

const statusColors = {
    active: 'bg-green-50 text-green-700',
    inactive: 'bg-gray-100 text-gray-500',
    suspended: 'bg-red-50 text-red-700',
};

export default function FieldOpsPage() {
    const { campaignId } = useParams();
    const [items, setItems] = useState([]);
    const [meta, setMeta] = useState({});
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [showEdit, setShowEdit] = useState(null);
    const [form, setForm] = useState({ ...emptyForm });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [checkIns, setCheckIns] = useState([]);
    const [showCheckIns, setShowCheckIns] = useState(false);
    const [page, setPage] = useState(1);
    const { can } = useCampaignPermissions();

    const fetchAgents = useCallback(async (pg = page) => {
        setLoading(true);
        try {
            const params = { page: pg };
            if (search.trim()) params.search = search.trim();
            if (statusFilter) params.status = statusFilter;
            const { data } = await api.get(`/campaigns/${campaignId}/field-agents`, { params });
            setItems(data.data || []);
            setMeta(data.meta || { current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, search, statusFilter, page]);

    const fetchCheckIns = useCallback(async () => {
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/check-ins`);
            setCheckIns(data.data || []);
        } catch { /* handled */ }
    }, [campaignId]);

    useEffect(() => { fetchAgents(); }, [fetchAgents]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            if (showEdit) {
                await api.put(`/campaigns/${campaignId}/field-agents/${showEdit.id}`, form);
                setShowEdit(null);
            } else {
                await api.post(`/campaigns/${campaignId}/field-agents`, form);
                setShowCreate(false);
            }
            setForm({ ...emptyForm });
            fetchAgents();
        } catch (err) {
            setError(err.response?.data?.message || 'Something went wrong.');
        }
        setSubmitting(false);
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Remove this field agent?')) return;
        try {
            await api.delete(`/campaigns/${campaignId}/field-agents/${id}`);
            fetchAgents();
        } catch { /* handled */ }
    };

    const openEdit = (agent) => {
        setForm({
            user_id: agent.user_id || '',
            agent_code: agent.agent_code || '',
            status: agent.status || 'active',
            ward: agent.ward || '',
            constituency: agent.constituency || '',
            county: agent.county || '',
            polling_station: agent.polling_station || '',
            phone: agent.phone || '',
            notes: agent.notes || '',
        });
        setShowEdit(agent);
        setError(null);
    };

    const columns = [
        { key: 'user', label: 'Agent', render: (r) => (
            <div className="flex items-center space-x-2">
                <UserIcon className="h-5 w-5 text-gray-400" />
                <div>
                    <div className="font-medium text-gray-900">{r.user?.name || '—'}</div>
                    <div className="text-xs text-gray-500">{r.agent_code || 'No code'}</div>
                </div>
            </div>
        )},
        { key: 'ward', label: 'Location', render: (r) => (
            <div className="text-sm">
                <div>{r.ward || '—'}</div>
                <div className="text-xs text-gray-500">{[r.constituency, r.county].filter(Boolean).join(', ') || '—'}</div>
            </div>
        )},
        { key: 'polling_station', label: 'Station', render: (r) => r.polling_station || '—' },
        { key: 'status', label: 'Status', render: (r) => (
            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[r.status] || statusColors.inactive}`}>
                {r.status}
            </span>
        )},
        { key: 'last_active_at', label: 'Last Active', render: (r) => (
            r.last_active_at ? new Date(r.last_active_at).toLocaleDateString() : 'Never'
        )},
        { key: 'actions', label: '', render: (r) => (
            <div className="flex items-center space-x-2">
                <PermissionGate permission="field.manage-agents">
                    <button onClick={() => openEdit(r)} className="text-gray-400 hover:text-primary-600"><PencilIcon className="h-4 w-4" /></button>
                    <button onClick={() => handleDelete(r.id)} className="text-gray-400 hover:text-red-600"><TrashIcon className="h-4 w-4" /></button>
                </PermissionGate>
            </div>
        )},
    ];

    const formFields = (
        <>
            {!showEdit && (
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">User ID</label>
                    <input type="number" value={form.user_id} onChange={(e) => setForm({ ...form, user_id: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2" required />
                </div>
            )}
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Agent Code</label>
                    <input type="text" value={form.agent_code} onChange={(e) => setForm({ ...form, agent_code: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select value={form.status} onChange={(e) => setForm({ ...form, status: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2">
                        {STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
                    </select>
                </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Polling Station</label>
                    <input type="text" value={form.polling_station} onChange={(e) => setForm({ ...form, polling_station: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2" />
                </div>
            </div>
            <div className="grid grid-cols-3 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Ward</label>
                    <input type="text" value={form.ward} onChange={(e) => setForm({ ...form, ward: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Constituency</label>
                    <input type="text" value={form.constituency} onChange={(e) => setForm({ ...form, constituency: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">County</label>
                    <input type="text" value={form.county} onChange={(e) => setForm({ ...form, county: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2" />
                </div>
            </div>
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })}
                    className="w-full border rounded-lg px-3 py-2" rows={3} />
            </div>
        </>
    );

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-heading font-bold text-gray-900">Field Operations</h1>
                <div className="flex items-center space-x-3">
                    <PermissionGate permission="field.view">
                        <button onClick={() => { fetchCheckIns(); setShowCheckIns(true); }}
                            className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            <ClockIcon className="h-4 w-4 mr-2" /> Check-ins
                        </button>
                    </PermissionGate>
                    <PermissionGate permission="field.manage-agents">
                        <button onClick={() => { setForm({ ...emptyForm }); setShowCreate(true); setError(null); }}
                            className="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                            <PlusIcon className="h-4 w-4 mr-2" /> Add Agent
                        </button>
                    </PermissionGate>
                </div>
            </div>

            <div className="flex items-center space-x-4">
                <div className="relative flex-1 max-w-md">
                    <MagnifyingGlassIcon className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <input type="text" placeholder="Search agents..." value={search}
                        onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                        className="w-full pl-10 pr-4 py-2 border rounded-lg text-sm" />
                </div>
                <select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
                    className="border rounded-lg px-3 py-2 text-sm">
                    <option value="">All Statuses</option>
                    {STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
                </select>
            </div>

            {loading ? (
                <div className="flex justify-center py-12">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" />
                </div>
            ) : items.length === 0 ? (
                <EmptyState icon={MapPinIcon} title="No field agents" description="Add field agents to start tracking field operations." />
            ) : (
                <>
                    <DataTable columns={columns} data={items} />
                    {meta.last_page > 1 && (
                        <div className="flex justify-center space-x-2">
                            {Array.from({ length: meta.last_page }, (_, i) => (
                                <button key={i + 1} onClick={() => { setPage(i + 1); fetchAgents(i + 1); }}
                                    className={`px-3 py-1 rounded text-sm ${page === i + 1 ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}`}>
                                    {i + 1}
                                </button>
                            ))}
                        </div>
                    )}
                </>
            )}

            {/* Create / Edit Modal */}
            <Modal open={showCreate || !!showEdit} onClose={() => { setShowCreate(false); setShowEdit(null); }}
                title={showEdit ? 'Edit Field Agent' : 'Add Field Agent'}>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm">{error}</div>}
                    {formFields}
                    <div className="flex justify-end space-x-3 pt-4">
                        <button type="button" onClick={() => { setShowCreate(false); setShowEdit(null); }}
                            className="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button type="submit" disabled={submitting}
                            className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 disabled:opacity-50">
                            {submitting ? 'Saving...' : showEdit ? 'Update' : 'Add Agent'}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* Check-ins Modal */}
            <Modal open={showCheckIns} onClose={() => setShowCheckIns(false)} title="Recent Check-ins">
                {checkIns.length === 0 ? (
                    <p className="text-sm text-gray-500 py-4">No check-ins recorded yet.</p>
                ) : (
                    <div className="space-y-3 max-h-96 overflow-y-auto">
                        {checkIns.map((ci) => (
                            <div key={ci.id} className="flex items-start space-x-3 p-3 bg-gray-50 rounded-lg">
                                <MapPinIcon className="h-5 w-5 text-primary-500 mt-0.5" />
                                <div className="flex-1 min-w-0">
                                    <div className="font-medium text-sm text-gray-900">{ci.user?.name || 'Unknown'}</div>
                                    <div className="text-xs text-gray-500">
                                        {ci.ward && `${ci.ward} · `}{ci.status} · {new Date(ci.created_at).toLocaleString()}
                                    </div>
                                    {ci.notes && <div className="text-xs text-gray-600 mt-1">{ci.notes}</div>}
                                    <div className="text-xs text-gray-400 mt-0.5">{ci.latitude}, {ci.longitude}</div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </Modal>
        </div>
    );
}
