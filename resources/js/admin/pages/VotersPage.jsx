import React, { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    MagnifyingGlassIcon,
    ArrowDownTrayIcon,
    ArrowUpTrayIcon,
    TagIcon,
    UserIcon,
    FunnelIcon,
} from '@heroicons/react/24/outline';
import api from '../../lib/api';
import PermissionGate from '../components/PermissionGate';
import useCampaignPermissions from '../hooks/useCampaignPermissions';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';
import StatsCard from '../components/StatsCard';

const SUPPORTER_STATUSES = ['supporter', 'leaning', 'undecided', 'opposition', 'unknown'];
const SOURCES = ['walk_in', 'field_agent', 'import', 'event', 'referral', 'online', 'other'];

const emptyForm = {
    name: '', phone: '', national_id: '', email: '',
    supporter_status: 'unknown', source: 'other',
    county: '', constituency: '', ward: '', polling_station: '',
    tags: '', notes: '', gender: '', date_of_birth: '',
};

const statusColors = {
    supporter: 'bg-green-50 text-green-700',
    leaning: 'bg-lime-50 text-lime-700',
    undecided: 'bg-yellow-50 text-yellow-700',
    opposition: 'bg-red-50 text-red-700',
    unknown: 'bg-gray-100 text-gray-500',
};

export default function VotersPage() {
    const { campaignId } = useParams();
    const [items, setItems] = useState([]);
    const [meta, setMeta] = useState({});
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [sourceFilter, setSourceFilter] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [showEdit, setShowEdit] = useState(null);
    const [showImport, setShowImport] = useState(false);
    const [showBulkTag, setShowBulkTag] = useState(false);
    const [form, setForm] = useState({ ...emptyForm });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [stats, setStats] = useState(null);
    const [selectedIds, setSelectedIds] = useState([]);
    const [page, setPage] = useState(1);
    const { can } = useCampaignPermissions();

    const fetchVoters = useCallback(async (pg = page) => {
        setLoading(true);
        try {
            const params = { page: pg };
            if (search.trim()) params.search = search.trim();
            if (statusFilter) params.supporter_status = statusFilter;
            if (sourceFilter) params.source = sourceFilter;
            const { data } = await api.get(`/campaigns/${campaignId}/voters`, { params });
            setItems(data.data || []);
            setMeta(data.meta || { current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, search, statusFilter, sourceFilter, page]);

    const fetchStats = useCallback(async () => {
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/voters/stats`);
            setStats(data);
        } catch { /* handled */ }
    }, [campaignId]);

    useEffect(() => { fetchVoters(1); fetchStats(); }, [campaignId]);
    useEffect(() => { fetchVoters(page); }, [page]);

    const handleSearch = (e) => { e.preventDefault(); setPage(1); fetchVoters(1); };
    const applyFilters = () => { setPage(1); fetchVoters(1); };

    const openCreate = () => { setForm({ ...emptyForm }); setShowCreate(true); setError(null); };

    const openEdit = (item) => {
        setForm({
            name: item.name || '', phone: item.phone || '', national_id: item.national_id || '',
            email: item.email || '', supporter_status: item.supporter_status || 'unknown',
            source: item.source || 'other', county: item.county || '', constituency: item.constituency || '',
            ward: item.ward || '', polling_station: item.polling_station || '',
            tags: Array.isArray(item.tags) ? item.tags.join(', ') : '', notes: item.notes || '',
            gender: item.gender || '', date_of_birth: item.date_of_birth || '',
        });
        setShowEdit(item);
        setError(null);
    };

    const handleCreate = async (e) => {
        e.preventDefault();
        setSubmitting(true); setError(null);
        try {
            const payload = { ...form };
            payload.tags = form.tags ? form.tags.split(',').map((t) => t.trim()).filter(Boolean) : [];
            if (!payload.date_of_birth) delete payload.date_of_birth;
            await api.post(`/campaigns/${campaignId}/voters`, payload);
            setShowCreate(false); fetchVoters(1); fetchStats();
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to create voter');
        }
        setSubmitting(false);
    };

    const handleUpdate = async (e) => {
        e.preventDefault();
        setSubmitting(true); setError(null);
        try {
            const payload = { ...form };
            payload.tags = form.tags ? form.tags.split(',').map((t) => t.trim()).filter(Boolean) : [];
            if (!payload.date_of_birth) delete payload.date_of_birth;
            await api.put(`/campaigns/${campaignId}/voters/${showEdit.id}`, payload);
            setShowEdit(null); fetchVoters(); fetchStats();
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to update voter');
        }
        setSubmitting(false);
    };

    const handleDelete = async (id) => {
        if (!confirm('Remove this voter record?')) return;
        try { await api.delete(`/campaigns/${campaignId}/voters/${id}`); fetchVoters(); fetchStats(); }
        catch (err) { alert(err.response?.data?.message || 'Failed to delete'); }
    };

    const handleExport = () => {
        const token = localStorage.getItem('kura_token');
        const params = new URLSearchParams();
        if (token) params.set('token', token);
        if (statusFilter) params.set('supporter_status', statusFilter);
        window.open(`/api/v1/campaigns/${campaignId}/voters/export?${params}`, '_blank');
    };

    const [importFile, setImportFile] = useState(null);
    const [importResult, setImportResult] = useState(null);

    const handleImport = async (e) => {
        e.preventDefault();
        if (!importFile) return;
        setSubmitting(true); setError(null); setImportResult(null);
        try {
            const fd = new FormData();
            fd.append('file', importFile);
            const { data } = await api.post(`/campaigns/${campaignId}/voters/import`, fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setImportResult(data);
            fetchVoters(1); fetchStats();
        } catch (err) {
            setError(err.response?.data?.message || 'Import failed');
        }
        setSubmitting(false);
    };

    const [bulkTagForm, setBulkTagForm] = useState({ tags: '', action: 'add' });

    const handleBulkTag = async (e) => {
        e.preventDefault();
        if (selectedIds.length === 0) return;
        setSubmitting(true); setError(null);
        try {
            const tags = bulkTagForm.tags.split(',').map((t) => t.trim()).filter(Boolean);
            await api.post(`/campaigns/${campaignId}/voters/bulk-tag`, {
                voter_ids: selectedIds, tags, action: bulkTagForm.action,
            });
            setShowBulkTag(false); setSelectedIds([]); fetchVoters();
        } catch (err) {
            setError(err.response?.data?.message || 'Bulk tag failed');
        }
        setSubmitting(false);
    };

    const handleBulkStatus = async (status) => {
        if (selectedIds.length === 0) return;
        if (!confirm(`Update ${selectedIds.length} voter(s) to "${status}"?`)) return;
        try {
            await api.post(`/campaigns/${campaignId}/voters/bulk-status`, {
                voter_ids: selectedIds, supporter_status: status,
            });
            setSelectedIds([]); fetchVoters(); fetchStats();
        } catch (err) { alert(err.response?.data?.message || 'Bulk status update failed'); }
    };

    const toggleSelect = (id) => {
        setSelectedIds((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]
        );
    };

    const toggleSelectAll = () => {
        if (selectedIds.length === items.length) {
            setSelectedIds([]);
        } else {
            setSelectedIds(items.map((i) => i.id));
        }
    };

    const columns = [
        { key: 'select', label: (
            <input type="checkbox" checked={items.length > 0 && selectedIds.length === items.length}
                onChange={toggleSelectAll} className="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
        ), render: (r) => (
            <input type="checkbox" checked={selectedIds.includes(r.id)} onChange={() => toggleSelect(r.id)}
                className="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
        )},
        { key: 'name', label: 'Name', render: (r) => <span className="font-medium text-gray-900">{r.name}</span> },
        { key: 'phone', label: 'Phone', render: (r) => <span className="text-sm text-gray-500">{r.phone || '—'}</span> },
        { key: 'ward', label: 'Ward', render: (r) => <span className="text-sm text-gray-500">{r.ward || '—'}</span> },
        { key: 'supporter_status', label: 'Status', render: (r) => (
            <span className={`px-2 py-0.5 text-xs font-medium rounded-full capitalize ${statusColors[r.supporter_status] || statusColors.unknown}`}>
                {(r.supporter_status || 'unknown').replace('_', ' ')}
            </span>
        )},
        { key: 'source', label: 'Source', render: (r) => (
            <span className="text-xs text-gray-500 capitalize">{(r.source || '').replace('_', ' ')}</span>
        )},
        { key: 'tags', label: 'Tags', render: (r) => {
            const tags = Array.isArray(r.tags) ? r.tags : [];
            return tags.length > 0 ? (
                <div className="flex flex-wrap gap-1">
                    {tags.slice(0, 3).map((t) => <span key={t} className="px-1.5 py-0.5 bg-gray-100 text-gray-600 text-xs rounded">{t}</span>)}
                    {tags.length > 3 && <span className="text-xs text-gray-400">+{tags.length - 3}</span>}
                </div>
            ) : <span className="text-xs text-gray-300">—</span>;
        }},
        { key: 'actions', label: '', render: (r) => (
            <div className="flex items-center space-x-1">
                {can('voters.edit') && <button onClick={() => openEdit(r)} className="p-1.5 text-gray-400 hover:text-primary-600 rounded"><PencilIcon className="h-4 w-4" /></button>}
                {can('voters.delete') && <button onClick={() => handleDelete(r.id)} className="p-1.5 text-gray-400 hover:text-red-500 rounded"><TrashIcon className="h-4 w-4" /></button>}
            </div>
        )},
    ];

    const VoterForm = ({ onSubmit, submitLabel }) => (
        <form onSubmit={onSubmit} className="space-y-4">
            {error && <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{error}</div>}
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                    <input type="text" required value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="text" value={form.phone} onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
                        placeholder="+254..." className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" value={form.email} onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">National ID</label>
                    <input type="text" value={form.national_id} onChange={(e) => setForm((f) => ({ ...f, national_id: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Supporter Status</label>
                    <select value={form.supporter_status} onChange={(e) => setForm((f) => ({ ...f, supporter_status: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        {SUPPORTER_STATUSES.map((s) => <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Source</label>
                    <select value={form.source} onChange={(e) => setForm((f) => ({ ...f, source: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        {SOURCES.map((s) => <option key={s} value={s}>{s.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">County</label>
                    <input type="text" value={form.county} onChange={(e) => setForm((f) => ({ ...f, county: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Constituency</label>
                    <input type="text" value={form.constituency} onChange={(e) => setForm((f) => ({ ...f, constituency: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Ward</label>
                    <input type="text" value={form.ward} onChange={(e) => setForm((f) => ({ ...f, ward: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Polling Station</label>
                    <input type="text" value={form.polling_station} onChange={(e) => setForm((f) => ({ ...f, polling_station: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                    <select value={form.gender} onChange={(e) => setForm((f) => ({ ...f, gender: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">—</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                    <input type="date" value={form.date_of_birth} onChange={(e) => setForm((f) => ({ ...f, date_of_birth: e.target.value }))}
                        className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                </div>
            </div>
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Tags (comma-separated)</label>
                <input type="text" value={form.tags} onChange={(e) => setForm((f) => ({ ...f, tags: e.target.value }))}
                    placeholder="youth, women, business-owner" className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
            </div>
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea value={form.notes} onChange={(e) => setForm((f) => ({ ...f, notes: e.target.value }))} rows={3}
                    className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
            </div>
            <div className="flex justify-end space-x-3 pt-2">
                <button type="button" onClick={() => { setShowCreate(false); setShowEdit(null); }}
                    className="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button type="submit" disabled={submitting}
                    className="btn-primary !py-2 !px-5 text-sm disabled:opacity-50">{submitting ? 'Saving...' : submitLabel}</button>
            </div>
        </form>
    );

    return (
        <div className="space-y-6">
            {/* Stats cards */}
            {stats && (
                <div className="grid grid-cols-2 lg:grid-cols-5 gap-4">
                    <StatsCard title="Total Voters" value={stats.total} icon={UserIcon} color="primary" />
                    <StatsCard title="Supporters" value={stats.by_status?.supporter || 0} icon={UserIcon} color="green" />
                    <StatsCard title="Leaning" value={stats.by_status?.leaning || 0} icon={UserIcon} color="lime" />
                    <StatsCard title="Undecided" value={stats.by_status?.undecided || 0} icon={UserIcon} color="yellow" />
                    <StatsCard title="Added (7 days)" value={stats.added_last_7_days || 0} icon={UserIcon} color="blue" />
                </div>
            )}

            {/* Toolbar */}
            <div className="flex items-center justify-between flex-wrap gap-3">
                <div className="flex items-center gap-2 flex-wrap">
                    <form onSubmit={handleSearch} className="flex items-center space-x-2">
                        <div className="relative">
                            <MagnifyingGlassIcon className="h-4 w-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                            <input type="text" value={search} onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search by name, phone, email..."
                                className="pl-9 pr-3 py-2 rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm w-64" />
                        </div>
                        <button type="submit" className="btn-outline !py-2 !px-3 text-sm">Search</button>
                    </form>

                    <select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); }}
                        className="rounded-lg border-gray-300 shadow-sm text-sm py-2 focus:border-primary-500 focus:ring-primary-500">
                        <option value="">All statuses</option>
                        {SUPPORTER_STATUSES.map((s) => <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>)}
                    </select>

                    <select value={sourceFilter} onChange={(e) => { setSourceFilter(e.target.value); }}
                        className="rounded-lg border-gray-300 shadow-sm text-sm py-2 focus:border-primary-500 focus:ring-primary-500">
                        <option value="">All sources</option>
                        {SOURCES.map((s) => <option key={s} value={s}>{s.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</option>)}
                    </select>

                    <button onClick={applyFilters} className="btn-outline !py-2 !px-3 text-sm inline-flex items-center space-x-1">
                        <FunnelIcon className="h-4 w-4" /><span>Filter</span>
                    </button>
                </div>

                <div className="flex items-center gap-2">
                    <PermissionGate permission="voters.create">
                        <button onClick={openCreate} className="btn-primary !py-2 !px-3 text-sm inline-flex items-center space-x-1">
                            <PlusIcon className="h-4 w-4" /><span>Add Voter</span>
                        </button>
                    </PermissionGate>
                    <PermissionGate permission="voters.import">
                        <button onClick={() => { setShowImport(true); setError(null); setImportResult(null); setImportFile(null); }}
                            className="btn-outline !py-2 !px-3 text-sm inline-flex items-center space-x-1">
                            <ArrowUpTrayIcon className="h-4 w-4" /><span>Import</span>
                        </button>
                    </PermissionGate>
                    <PermissionGate permission="voters.export">
                        <button onClick={handleExport} className="btn-outline !py-2 !px-3 text-sm inline-flex items-center space-x-1">
                            <ArrowDownTrayIcon className="h-4 w-4" /><span>Export</span>
                        </button>
                    </PermissionGate>
                </div>
            </div>

            {/* Bulk actions bar */}
            {selectedIds.length > 0 && (
                <div className="bg-primary-50 border border-primary-200 rounded-lg px-4 py-3 flex items-center justify-between">
                    <span className="text-sm font-medium text-primary-700">{selectedIds.length} voter(s) selected</span>
                    <div className="flex items-center gap-2">
                        <PermissionGate permission="voters.edit">
                            <button onClick={() => { setShowBulkTag(true); setBulkTagForm({ tags: '', action: 'add' }); setError(null); }}
                                className="btn-outline !py-1.5 !px-3 text-xs inline-flex items-center space-x-1">
                                <TagIcon className="h-3.5 w-3.5" /><span>Bulk Tag</span>
                            </button>
                            <select onChange={(e) => { if (e.target.value) handleBulkStatus(e.target.value); e.target.value = ''; }}
                                className="rounded-lg border-gray-300 shadow-sm text-xs py-1.5 focus:border-primary-500 focus:ring-primary-500">
                                <option value="">Set Status...</option>
                                {SUPPORTER_STATUSES.map((s) => <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>)}
                            </select>
                        </PermissionGate>
                        <button onClick={() => setSelectedIds([])} className="text-xs text-gray-500 hover:text-gray-700">Clear</button>
                    </div>
                </div>
            )}

            {/* Table */}
            {items.length === 0 && !loading ? (
                <EmptyState icon={UserIcon} title="No voters" description="Add voters manually, import from CSV, or they'll appear when registered by field agents." />
            ) : (
                <DataTable columns={columns} data={items} loading={loading} />
            )}

            {/* Pagination */}
            {(meta.last_page || 0) > 1 && (
                <div className="flex items-center justify-between">
                    <span className="text-sm text-gray-500">
                        Page {meta.current_page} of {meta.last_page} ({meta.total} total)
                    </span>
                    <div className="flex items-center gap-2">
                        <button onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page <= 1}
                            className="btn-outline !py-1.5 !px-3 text-sm disabled:opacity-50">Prev</button>
                        <button onClick={() => setPage((p) => Math.min(meta.last_page, p + 1))} disabled={page >= meta.last_page}
                            className="btn-outline !py-1.5 !px-3 text-sm disabled:opacity-50">Next</button>
                    </div>
                </div>
            )}

            {/* Create modal */}
            <Modal open={showCreate} onClose={() => setShowCreate(false)} title="Add Voter">
                <VoterForm onSubmit={handleCreate} submitLabel="Add Voter" />
            </Modal>

            {/* Edit modal */}
            <Modal open={!!showEdit} onClose={() => setShowEdit(null)} title={`Edit: ${showEdit?.name}`}>
                <VoterForm onSubmit={handleUpdate} submitLabel="Save Changes" />
            </Modal>

            {/* Import modal */}
            <Modal open={showImport} onClose={() => setShowImport(false)} title="Import Voters from CSV">
                {error && <div className="mb-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{error}</div>}
                {importResult && (
                    <div className="mb-3 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
                        <p className="font-medium">{importResult.imported} voter(s) imported.</p>
                        {importResult.total_errors > 0 && (
                            <details className="mt-2">
                                <summary className="cursor-pointer text-red-600">{importResult.total_errors} error(s)</summary>
                                <ul className="mt-1 list-disc list-inside text-xs text-red-600">
                                    {importResult.errors.map((err, i) => <li key={i}>{err}</li>)}
                                </ul>
                            </details>
                        )}
                    </div>
                )}
                <form onSubmit={handleImport} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">CSV File</label>
                        <input type="file" accept=".csv,.txt" onChange={(e) => setImportFile(e.target.files[0])}
                            className="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100" />
                        <p className="mt-1 text-xs text-gray-500">Required column: name. Optional: phone, email, national_id, supporter_status, source, county, constituency, ward, polling_station, gender, notes.</p>
                    </div>
                    <div className="flex justify-end space-x-3 pt-2">
                        <button type="button" onClick={() => setShowImport(false)}
                            className="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                        <button type="submit" disabled={submitting || !importFile}
                            className="btn-primary !py-2 !px-5 text-sm disabled:opacity-50">{submitting ? 'Importing...' : 'Import'}</button>
                    </div>
                </form>
            </Modal>

            {/* Bulk tag modal */}
            <Modal open={showBulkTag} onClose={() => setShowBulkTag(false)} title={`Bulk Tag (${selectedIds.length} voters)`}>
                {error && <div className="mb-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{error}</div>}
                <form onSubmit={handleBulkTag} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Tags (comma-separated)</label>
                        <input type="text" value={bulkTagForm.tags} onChange={(e) => setBulkTagForm((f) => ({ ...f, tags: e.target.value }))}
                            placeholder="youth, women, priority" className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Action</label>
                        <select value={bulkTagForm.action} onChange={(e) => setBulkTagForm((f) => ({ ...f, action: e.target.value }))}
                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="add">Add tags</option>
                            <option value="remove">Remove tags</option>
                        </select>
                    </div>
                    <div className="flex justify-end space-x-3 pt-2">
                        <button type="button" onClick={() => setShowBulkTag(false)}
                            className="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                        <button type="submit" disabled={submitting || !bulkTagForm.tags.trim()}
                            className="btn-primary !py-2 !px-5 text-sm disabled:opacity-50">{submitting ? 'Applying...' : 'Apply'}</button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
