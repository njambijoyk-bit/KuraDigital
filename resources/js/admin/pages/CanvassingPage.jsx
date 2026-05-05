import React, { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    MagnifyingGlassIcon,
    MapPinIcon,
    ClipboardDocumentCheckIcon,
    UserGroupIcon,
    ChatBubbleLeftRightIcon,
    FunnelIcon,
} from '@heroicons/react/24/outline';
import api from '../../lib/api';
import PermissionGate from '../components/PermissionGate';
import useCampaignPermissions from '../hooks/useCampaignPermissions';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';
import StatsCard from '../components/StatsCard';

const TABS = ['assignments', 'interactions', 'check-ins'];

const ASSIGNMENT_STATUSES = ['pending', 'in_progress', 'completed', 'cancelled'];
const PRIORITIES = ['low', 'medium', 'high', 'urgent'];
const INTERACTION_TYPES = ['door_knock', 'phone_call', 'rally', 'community_meeting', 'market_visit', 'church_visit', 'other'];
const OUTCOMES = ['contacted', 'not_home', 'refused', 'supportive', 'undecided', 'hostile', 'moved', 'other'];
const CHECK_IN_TYPES = ['start_shift', 'end_shift', 'break', 'location_update', 'incident'];

const statusColors = {
    pending: 'bg-yellow-50 text-yellow-700',
    in_progress: 'bg-blue-50 text-blue-700',
    completed: 'bg-green-50 text-green-700',
    cancelled: 'bg-gray-100 text-gray-500',
};

const priorityColors = {
    low: 'bg-gray-100 text-gray-600',
    medium: 'bg-blue-50 text-blue-600',
    high: 'bg-orange-50 text-orange-600',
    urgent: 'bg-red-50 text-red-700',
};

const outcomeColors = {
    contacted: 'bg-blue-50 text-blue-600',
    not_home: 'bg-gray-100 text-gray-500',
    refused: 'bg-red-50 text-red-600',
    supportive: 'bg-green-50 text-green-700',
    undecided: 'bg-yellow-50 text-yellow-700',
    hostile: 'bg-red-100 text-red-800',
    moved: 'bg-gray-100 text-gray-400',
    other: 'bg-gray-50 text-gray-500',
};

const emptyAssignmentForm = {
    assigned_to: '', title: '', description: '', ward: '', constituency: '', county: '',
    priority: 'medium', due_date: '', target_voters: '', status: 'pending', completion_notes: '',
};

const emptyInteractionForm = {
    voter_id: '', assignment_id: '', interaction_type: 'door_knock', outcome: 'contacted',
    notes: '', latitude: '', longitude: '', ward: '', constituency: '', county: '', duration_minutes: '',
};

const emptyCheckInForm = {
    assignment_id: '', latitude: '', longitude: '', location_name: '', ward: '', constituency: '',
    county: '', check_in_type: 'location_update', notes: '',
};

export default function CanvassingPage() {
    const { campaignId } = useParams();
    const [tab, setTab] = useState('assignments');
    const [items, setItems] = useState([]);
    const [meta, setMeta] = useState({});
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [page, setPage] = useState(1);
    const [stats, setStats] = useState(null);
    const [showCreate, setShowCreate] = useState(false);
    const [showEdit, setShowEdit] = useState(null);
    const [form, setForm] = useState({});
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [members, setMembers] = useState([]);
    const { can } = useCampaignPermissions();

    const fetchMembers = useCallback(async () => {
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/members`);
            setMembers(data.data || []);
        } catch { /* handled */ }
    }, [campaignId]);

    const fetchStats = useCallback(async () => {
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/field/stats`);
            setStats(data);
        } catch { /* handled */ }
    }, [campaignId]);

    const fetchItems = useCallback(async (pg = page) => {
        setLoading(true);
        try {
            const params = { page: pg };
            let url = '';
            if (tab === 'assignments') {
                url = `/campaigns/${campaignId}/field/assignments`;
                if (search.trim()) params.search = search.trim();
                if (statusFilter) params.status = statusFilter;
            } else if (tab === 'interactions') {
                url = `/campaigns/${campaignId}/field/interactions`;
                if (statusFilter) params.outcome = statusFilter;
            } else {
                url = `/campaigns/${campaignId}/field/check-ins`;
                if (statusFilter) params.check_in_type = statusFilter;
            }
            const { data } = await api.get(url, { params });
            setItems(data.data || []);
            setMeta(data.meta || { current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, tab, search, statusFilter, page]);

    useEffect(() => { fetchMembers(); fetchStats(); }, [campaignId]);
    useEffect(() => { setPage(1); setStatusFilter(''); setSearch(''); }, [tab]);
    useEffect(() => { fetchItems(page); }, [tab, page, campaignId]);

    const applyFilters = () => { setPage(1); fetchItems(1); };

    const openCreateAssignment = () => { setForm({ ...emptyAssignmentForm }); setShowCreate(true); setError(null); };
    const openEditAssignment = (item) => {
        setForm({
            assigned_to: item.assigned_to || '', title: item.title || '', description: item.description || '',
            ward: item.ward || '', constituency: item.constituency || '', county: item.county || '',
            priority: item.priority || 'medium', due_date: item.due_date ? item.due_date.slice(0, 10) : '',
            target_voters: item.target_voters || '', status: item.status || 'pending',
            completion_notes: item.completion_notes || '',
        });
        setShowEdit(item); setError(null);
    };

    const handleCreateAssignment = async (e) => {
        e.preventDefault(); setSubmitting(true); setError(null);
        try {
            const payload = { ...form };
            if (!payload.due_date) delete payload.due_date;
            if (!payload.target_voters) delete payload.target_voters;
            else payload.target_voters = parseInt(payload.target_voters, 10);
            await api.post(`/campaigns/${campaignId}/field/assignments`, payload);
            setShowCreate(false); fetchItems(1); fetchStats();
        } catch (err) { setError(err.response?.data?.message || 'Failed to create'); }
        setSubmitting(false);
    };

    const handleUpdateAssignment = async (e) => {
        e.preventDefault(); setSubmitting(true); setError(null);
        try {
            const payload = { ...form };
            if (!payload.due_date) delete payload.due_date;
            if (payload.target_voters) payload.target_voters = parseInt(payload.target_voters, 10);
            else delete payload.target_voters;
            await api.put(`/campaigns/${campaignId}/field/assignments/${showEdit.id}`, payload);
            setShowEdit(null); fetchItems(); fetchStats();
        } catch (err) { setError(err.response?.data?.message || 'Failed to update'); }
        setSubmitting(false);
    };

    const handleDeleteAssignment = async (id) => {
        if (!confirm('Delete this assignment?')) return;
        try { await api.delete(`/campaigns/${campaignId}/field/assignments/${id}`); fetchItems(); fetchStats(); }
        catch (err) { alert(err.response?.data?.message || 'Failed to delete'); }
    };

    const openCreateInteraction = () => { setForm({ ...emptyInteractionForm }); setShowCreate(true); setError(null); };

    const handleCreateInteraction = async (e) => {
        e.preventDefault(); setSubmitting(true); setError(null);
        try {
            const payload = { ...form };
            payload.voter_id = parseInt(payload.voter_id, 10);
            if (payload.assignment_id) payload.assignment_id = parseInt(payload.assignment_id, 10);
            else delete payload.assignment_id;
            if (payload.duration_minutes) payload.duration_minutes = parseInt(payload.duration_minutes, 10);
            else delete payload.duration_minutes;
            if (!payload.latitude) delete payload.latitude;
            if (!payload.longitude) delete payload.longitude;
            await api.post(`/campaigns/${campaignId}/field/interactions`, payload);
            setShowCreate(false); fetchItems(1); fetchStats();
        } catch (err) { setError(err.response?.data?.message || 'Failed to record'); }
        setSubmitting(false);
    };

    const openCreateCheckIn = () => { setForm({ ...emptyCheckInForm }); setShowCreate(true); setError(null); };

    const handleCreateCheckIn = async (e) => {
        e.preventDefault(); setSubmitting(true); setError(null);
        try {
            const payload = { ...form };
            if (payload.assignment_id) payload.assignment_id = parseInt(payload.assignment_id, 10);
            else delete payload.assignment_id;
            if (!payload.latitude) delete payload.latitude;
            if (!payload.longitude) delete payload.longitude;
            await api.post(`/campaigns/${campaignId}/field/check-ins`, payload);
            setShowCreate(false); fetchItems(1); fetchStats();
        } catch (err) { setError(err.response?.data?.message || 'Failed to check in'); }
        setSubmitting(false);
    };

    const assignmentColumns = [
        { key: 'title', label: 'Assignment', render: (r) => <span className="font-medium text-gray-900">{r.title}</span> },
        { key: 'assignee', label: 'Assigned To', render: (r) => <span className="text-sm text-gray-500">{r.assignee?.name || '—'}</span> },
        { key: 'ward', label: 'Ward', render: (r) => <span className="text-sm text-gray-500">{r.ward || '—'}</span> },
        { key: 'priority', label: 'Priority', render: (r) => (
            <span className={`px-2 py-0.5 text-xs font-medium rounded-full capitalize ${priorityColors[r.priority] || ''}`}>{r.priority}</span>
        )},
        { key: 'status', label: 'Status', render: (r) => (
            <span className={`px-2 py-0.5 text-xs font-medium rounded-full capitalize ${statusColors[r.status] || ''}`}>{(r.status || '').replace('_', ' ')}</span>
        )},
        { key: 'interactions_count', label: 'Interactions', render: (r) => <span className="text-sm text-gray-500">{r.interactions_count ?? 0}</span> },
        { key: 'due_date', label: 'Due', render: (r) => <span className="text-sm text-gray-500">{r.due_date ? r.due_date.slice(0, 10) : '—'}</span> },
        { key: 'actions', label: '', render: (r) => (
            <div className="flex items-center space-x-1">
                {can('field.manage-agents') && <button onClick={() => openEditAssignment(r)} className="p-1.5 text-gray-400 hover:text-primary-600 rounded"><PencilIcon className="h-4 w-4" /></button>}
                {can('field.manage-agents') && <button onClick={() => handleDeleteAssignment(r.id)} className="p-1.5 text-gray-400 hover:text-red-500 rounded"><TrashIcon className="h-4 w-4" /></button>}
            </div>
        )},
    ];

    const interactionColumns = [
        { key: 'voter', label: 'Voter', render: (r) => <span className="font-medium text-gray-900">{r.voter?.name || `#${r.voter_id}`}</span> },
        { key: 'agent', label: 'Agent', render: (r) => <span className="text-sm text-gray-500">{r.agent?.name || '—'}</span> },
        { key: 'interaction_type', label: 'Type', render: (r) => <span className="text-xs text-gray-600 capitalize">{(r.interaction_type || '').replace('_', ' ')}</span> },
        { key: 'outcome', label: 'Outcome', render: (r) => (
            <span className={`px-2 py-0.5 text-xs font-medium rounded-full capitalize ${outcomeColors[r.outcome] || ''}`}>{(r.outcome || '').replace('_', ' ')}</span>
        )},
        { key: 'ward', label: 'Ward', render: (r) => <span className="text-sm text-gray-500">{r.ward || '—'}</span> },
        { key: 'duration', label: 'Duration', render: (r) => <span className="text-sm text-gray-500">{r.duration_minutes ? `${r.duration_minutes}m` : '—'}</span> },
        { key: 'created_at', label: 'When', render: (r) => <span className="text-xs text-gray-400">{r.created_at ? new Date(r.created_at).toLocaleString() : '—'}</span> },
    ];

    const checkInColumns = [
        { key: 'user', label: 'Agent', render: (r) => <span className="font-medium text-gray-900">{r.user?.name || '—'}</span> },
        { key: 'check_in_type', label: 'Type', render: (r) => (
            <span className="px-2 py-0.5 text-xs font-medium rounded-full capitalize bg-gray-100 text-gray-600">{(r.check_in_type || '').replace('_', ' ')}</span>
        )},
        { key: 'location_name', label: 'Location', render: (r) => <span className="text-sm text-gray-500">{r.location_name || '—'}</span> },
        { key: 'ward', label: 'Ward', render: (r) => <span className="text-sm text-gray-500">{r.ward || '—'}</span> },
        { key: 'coords', label: 'Coords', render: (r) => r.latitude && r.longitude ? (
            <span className="text-xs text-gray-400">{Number(r.latitude).toFixed(4)}, {Number(r.longitude).toFixed(4)}</span>
        ) : <span className="text-xs text-gray-300">—</span> },
        { key: 'notes', label: 'Notes', render: (r) => <span className="text-sm text-gray-500 truncate max-w-[150px] inline-block">{r.notes || '—'}</span> },
        { key: 'created_at', label: 'When', render: (r) => <span className="text-xs text-gray-400">{r.created_at ? new Date(r.created_at).toLocaleString() : '—'}</span> },
    ];

    const columns = tab === 'assignments' ? assignmentColumns : tab === 'interactions' ? interactionColumns : checkInColumns;

    const AssignmentForm = ({ onSubmit, submitLabel, isEdit }) => (
        <form onSubmit={onSubmit} className="space-y-4">
            {error && <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{error}</div>}
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                    <input type="text" required value={form.title} onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Assign To *</label>
                    <select required value={form.assigned_to} onChange={(e) => setForm((f) => ({ ...f, assigned_to: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Select agent...</option>
                        {members.map((m) => <option key={m.user_id || m.id} value={m.user_id || m.id}>{m.user?.name || m.name}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                    <select value={form.priority} onChange={(e) => setForm((f) => ({ ...f, priority: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        {PRIORITIES.map((p) => <option key={p} value={p}>{p.charAt(0).toUpperCase() + p.slice(1)}</option>)}
                    </select>
                </div>
                {isEdit && (
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select value={form.status} onChange={(e) => setForm((f) => ({ ...f, status: e.target.value }))}
                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            {ASSIGNMENT_STATUSES.map((s) => <option key={s} value={s}>{s.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</option>)}
                        </select>
                    </div>
                )}
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Ward</label>
                    <input type="text" value={form.ward} onChange={(e) => setForm((f) => ({ ...f, ward: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Constituency</label>
                    <input type="text" value={form.constituency} onChange={(e) => setForm((f) => ({ ...f, constituency: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">County</label>
                    <input type="text" value={form.county} onChange={(e) => setForm((f) => ({ ...f, county: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                    <input type="date" value={form.due_date} onChange={(e) => setForm((f) => ({ ...f, due_date: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Target Voters</label>
                    <input type="number" min="1" value={form.target_voters} onChange={(e) => setForm((f) => ({ ...f, target_voters: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
            </div>
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea value={form.description} onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))} rows={3}
                    className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
            </div>
            {isEdit && (
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Completion Notes</label>
                    <textarea value={form.completion_notes} onChange={(e) => setForm((f) => ({ ...f, completion_notes: e.target.value }))} rows={2}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
            )}
            <div className="flex justify-end space-x-3 pt-2">
                <button type="button" onClick={() => { setShowCreate(false); setShowEdit(null); }}
                    className="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button type="submit" disabled={submitting}
                    className="btn-primary !py-2 !px-5 text-sm disabled:opacity-50">{submitting ? 'Saving...' : submitLabel}</button>
            </div>
        </form>
    );

    const InteractionForm = () => (
        <form onSubmit={handleCreateInteraction} className="space-y-4">
            {error && <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{error}</div>}
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Voter ID *</label>
                    <input type="number" required min="1" value={form.voter_id} onChange={(e) => setForm((f) => ({ ...f, voter_id: e.target.value }))}
                        placeholder="Enter voter ID" className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Type *</label>
                    <select required value={form.interaction_type} onChange={(e) => setForm((f) => ({ ...f, interaction_type: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        {INTERACTION_TYPES.map((t) => <option key={t} value={t}>{t.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Outcome *</label>
                    <select required value={form.outcome} onChange={(e) => setForm((f) => ({ ...f, outcome: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        {OUTCOMES.map((o) => <option key={o} value={o}>{o.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Duration (min)</label>
                    <input type="number" min="0" value={form.duration_minutes} onChange={(e) => setForm((f) => ({ ...f, duration_minutes: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Assignment ID</label>
                    <input type="number" min="1" value={form.assignment_id} onChange={(e) => setForm((f) => ({ ...f, assignment_id: e.target.value }))}
                        placeholder="Optional" className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Ward</label>
                    <input type="text" value={form.ward} onChange={(e) => setForm((f) => ({ ...f, ward: e.target.value }))}
                        placeholder="Auto-filled from voter" className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
            </div>
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea value={form.notes} onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))} rows={3}
                    className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
            </div>
            <div className="flex justify-end space-x-3 pt-2">
                <button type="button" onClick={() => setShowCreate(false)}
                    className="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button type="submit" disabled={submitting}
                    className="btn-primary !py-2 !px-5 text-sm disabled:opacity-50">{submitting ? 'Recording...' : 'Record Interaction'}</button>
            </div>
        </form>
    );

    const CheckInForm = () => (
        <form onSubmit={handleCreateCheckIn} className="space-y-4">
            {error && <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{error}</div>}
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select value={form.check_in_type} onChange={(e) => setForm((f) => ({ ...f, check_in_type: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        {CHECK_IN_TYPES.map((t) => <option key={t} value={t}>{t.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Location Name</label>
                    <input type="text" value={form.location_name} onChange={(e) => setForm((f) => ({ ...f, location_name: e.target.value }))}
                        placeholder="e.g. Kangemi Market" className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Latitude</label>
                    <input type="number" step="any" value={form.latitude} onChange={(e) => setForm((f) => ({ ...f, latitude: e.target.value }))}
                        placeholder="-1.2345" className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Longitude</label>
                    <input type="number" step="any" value={form.longitude} onChange={(e) => setForm((f) => ({ ...f, longitude: e.target.value }))}
                        placeholder="36.7890" className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Ward</label>
                    <input type="text" value={form.ward} onChange={(e) => setForm((f) => ({ ...f, ward: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Assignment ID</label>
                    <input type="number" min="1" value={form.assignment_id} onChange={(e) => setForm((f) => ({ ...f, assignment_id: e.target.value }))}
                        placeholder="Optional" className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
            </div>
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea value={form.notes} onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))} rows={3}
                    className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
            </div>
            <div className="flex justify-end space-x-3 pt-2">
                <button type="button" onClick={() => setShowCreate(false)}
                    className="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button type="submit" disabled={submitting}
                    className="btn-primary !py-2 !px-5 text-sm disabled:opacity-50">{submitting ? 'Checking in...' : 'Check In'}</button>
            </div>
        </form>
    );

    const openCreate = () => {
        if (tab === 'assignments') openCreateAssignment();
        else if (tab === 'interactions') openCreateInteraction();
        else openCreateCheckIn();
    };

    const createPermission = tab === 'assignments' ? 'field.manage-agents' : 'field.submit-checkin';
    const createLabel = tab === 'assignments' ? 'New Assignment' : tab === 'interactions' ? 'Record Interaction' : 'Check In';
    const emptyTitle = tab === 'assignments' ? 'No assignments' : tab === 'interactions' ? 'No interactions' : 'No check-ins';
    const emptyDesc = tab === 'assignments'
        ? 'Create canvassing assignments and assign agents to wards.'
        : tab === 'interactions'
            ? 'Door-to-door interactions will appear here when agents record them.'
            : 'Agent location check-ins will appear here during field operations.';

    return (
        <div className="space-y-6">
            {stats && (
                <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
                    <StatsCard title="Assignments" value={stats.assignments?.total || 0} icon={ClipboardDocumentCheckIcon} color="primary" />
                    <StatsCard title="Active" value={stats.assignments?.by_status?.in_progress || 0} icon={ClipboardDocumentCheckIcon} color="blue" />
                    <StatsCard title="Interactions (7d)" value={stats.interactions?.this_week || 0} icon={ChatBubbleLeftRightIcon} color="green" />
                    <StatsCard title="Today" value={stats.interactions?.today || 0} icon={ChatBubbleLeftRightIcon} color="lime" />
                    <StatsCard title="Agents Active" value={stats.check_ins?.active_agents_today || 0} icon={MapPinIcon} color="yellow" />
                </div>
            )}

            <div className="border-b border-gray-200">
                <nav className="-mb-px flex space-x-6">
                    {TABS.map((t) => (
                        <button key={t} onClick={() => setTab(t)}
                            className={`pb-3 px-1 text-sm font-medium border-b-2 capitalize ${tab === t ? 'border-primary-500 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'}`}>
                            {t.replace('-', ' ')}
                        </button>
                    ))}
                </nav>
            </div>

            <div className="flex items-center justify-between flex-wrap gap-3">
                <div className="flex items-center gap-2 flex-wrap">
                    {tab === 'assignments' && (
                        <form onSubmit={(e) => { e.preventDefault(); applyFilters(); }} className="flex items-center space-x-2">
                            <div className="relative">
                                <MagnifyingGlassIcon className="h-4 w-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <input type="text" value={search} onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search assignments..."
                                    className="pl-9 pr-3 py-2 rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm w-56" />
                            </div>
                            <button type="submit" className="btn-outline !py-2 !px-3 text-sm">Search</button>
                        </form>
                    )}
                    {tab === 'assignments' && (
                        <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}
                            className="rounded-lg border-gray-300 shadow-sm text-sm py-2 focus:border-primary-500 focus:ring-primary-500">
                            <option value="">All statuses</option>
                            {ASSIGNMENT_STATUSES.map((s) => <option key={s} value={s}>{s.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</option>)}
                        </select>
                    )}
                    {tab === 'interactions' && (
                        <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}
                            className="rounded-lg border-gray-300 shadow-sm text-sm py-2 focus:border-primary-500 focus:ring-primary-500">
                            <option value="">All outcomes</option>
                            {OUTCOMES.map((o) => <option key={o} value={o}>{o.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</option>)}
                        </select>
                    )}
                    {tab === 'check-ins' && (
                        <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}
                            className="rounded-lg border-gray-300 shadow-sm text-sm py-2 focus:border-primary-500 focus:ring-primary-500">
                            <option value="">All types</option>
                            {CHECK_IN_TYPES.map((t) => <option key={t} value={t}>{t.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</option>)}
                        </select>
                    )}
                    <button onClick={applyFilters} className="btn-outline !py-2 !px-3 text-sm inline-flex items-center space-x-1">
                        <FunnelIcon className="h-4 w-4" /><span>Filter</span>
                    </button>
                </div>
                <PermissionGate permission={createPermission}>
                    <button onClick={openCreate} className="btn-primary !py-2 !px-3 text-sm inline-flex items-center space-x-1">
                        <PlusIcon className="h-4 w-4" /><span>{createLabel}</span>
                    </button>
                </PermissionGate>
            </div>

            {items.length === 0 && !loading ? (
                <EmptyState icon={ClipboardDocumentCheckIcon} title={emptyTitle} description={emptyDesc} />
            ) : (
                <DataTable columns={columns} data={items} loading={loading} />
            )}

            {(meta.last_page || 0) > 1 && (
                <div className="flex items-center justify-between">
                    <span className="text-sm text-gray-500">Page {meta.current_page} of {meta.last_page} ({meta.total} total)</span>
                    <div className="flex items-center gap-2">
                        <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page <= 1}
                            className="btn-outline !py-1.5 !px-3 text-sm disabled:opacity-50">Prev</button>
                        <button onClick={() => setPage((p) => Math.min(meta.last_page, p + 1))} disabled={page >= meta.last_page}
                            className="btn-outline !py-1.5 !px-3 text-sm disabled:opacity-50">Next</button>
                    </div>
                </div>
            )}

            <Modal open={showCreate && tab === 'assignments'} onClose={() => setShowCreate(false)} title="New Canvassing Assignment">
                <AssignmentForm onSubmit={handleCreateAssignment} submitLabel="Create Assignment" isEdit={false} />
            </Modal>
            <Modal open={!!showEdit} onClose={() => setShowEdit(null)} title={`Edit: ${showEdit?.title}`}>
                <AssignmentForm onSubmit={handleUpdateAssignment} submitLabel="Save Changes" isEdit={true} />
            </Modal>
            <Modal open={showCreate && tab === 'interactions'} onClose={() => setShowCreate(false)} title="Record Voter Interaction">
                <InteractionForm />
            </Modal>
            <Modal open={showCreate && tab === 'check-ins'} onClose={() => setShowCreate(false)} title="Agent Check-In">
                <CheckInForm />
            </Modal>
        </div>
    );
}
