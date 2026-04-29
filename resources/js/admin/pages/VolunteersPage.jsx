import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { ArrowDownTrayIcon, HandRaisedIcon, PencilIcon, TrashIcon, MagnifyingGlassIcon } from '@heroicons/react/24/outline';
import api from '../../lib/api';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';

const ENGAGEMENT_STATUSES = ['new', 'contacted', 'active', 'inactive'];

export default function VolunteersPage() {
    const { campaignId } = useParams();
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [showEdit, setShowEdit] = useState(null);
    const [editForm, setEditForm] = useState({ engagement_status: 'new', tags: '', notes: '' });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);

    const fetch = async () => {
        setLoading(true);
        try {
            const params = {};
            if (search.trim()) params.search = search.trim();
            const { data } = await api.get(`/campaigns/${campaignId}/volunteers`, { params });
            setItems(data.data || []);
        } catch { /* handled */ }
        setLoading(false);
    };

    useEffect(() => { fetch(); }, [campaignId]);

    const handleSearch = (e) => {
        e.preventDefault();
        fetch();
    };

    const openEdit = (item) => {
        setEditForm({
            engagement_status: item.engagement_status || 'new',
            tags: Array.isArray(item.tags) ? item.tags.join(', ') : (item.tags || ''),
            notes: item.notes || '',
        });
        setShowEdit(item);
        setError(null);
    };

    const handleUpdate = async (e) => {
        e.preventDefault();
        setSubmitting(true); setError(null);
        try {
            const payload = {
                engagement_status: editForm.engagement_status,
                notes: editForm.notes,
            };
            if (editForm.tags.trim()) {
                payload.tags = editForm.tags.split(',').map((t) => t.trim()).filter(Boolean);
            } else {
                payload.tags = [];
            }
            await api.put(`/campaigns/${campaignId}/volunteers/${showEdit.id}`, payload);
            setShowEdit(null);
            fetch();
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to update');
        }
        setSubmitting(false);
    };

    const handleDelete = async (id) => {
        if (!confirm('Remove this volunteer?')) return;
        try { await api.delete(`/campaigns/${campaignId}/volunteers/${id}`); fetch(); }
        catch (err) { alert(err.response?.data?.message || 'Failed to delete'); }
    };

    const handleExport = () => {
        const token = localStorage.getItem('kura_token');
        window.open(`/api/v1/campaigns/${campaignId}/volunteers/export?token=${token}`, '_blank');
    };

    const engagementColors = {
        new: 'bg-blue-50 text-blue-700',
        contacted: 'bg-yellow-50 text-yellow-700',
        active: 'bg-green-50 text-green-700',
        inactive: 'bg-gray-100 text-gray-500',
    };

    const columns = [
        { key: 'name', label: 'Name', render: (r) => <span className="font-medium text-gray-900">{r.name}</span> },
        { key: 'email', label: 'Email', render: (r) => <span className="text-sm text-gray-500">{r.email || '—'}</span> },
        { key: 'phone', label: 'Phone', render: (r) => <span className="text-sm text-gray-500">{r.phone || '—'}</span> },
        { key: 'ward', label: 'Ward', render: (r) => <span className="text-sm text-gray-500">{r.ward || '—'}</span> },
        { key: 'engagement_status', label: 'Status', render: (r) => (
            <span className={`px-2 py-0.5 text-xs font-medium rounded-full capitalize ${engagementColors[r.engagement_status] || engagementColors.new}`}>
                {r.engagement_status || 'new'}
            </span>
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
                <button onClick={() => openEdit(r)} className="p-1.5 text-gray-400 hover:text-primary-600 rounded"><PencilIcon className="h-4 w-4" /></button>
                <button onClick={() => handleDelete(r.id)} className="p-1.5 text-gray-400 hover:text-red-500 rounded"><TrashIcon className="h-4 w-4" /></button>
            </div>
        )},
    ];

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between flex-wrap gap-3">
                <form onSubmit={handleSearch} className="flex items-center space-x-2">
                    <div className="relative">
                        <MagnifyingGlassIcon className="h-4 w-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            type="text" value={search} onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by name, email, ward..."
                            className="pl-9 pr-3 py-2 rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm w-72"
                        />
                    </div>
                    <button type="submit" className="btn-outline !py-2 !px-3 text-sm">Search</button>
                </form>
                <button onClick={handleExport} className="inline-flex items-center space-x-1.5 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 shadow-sm">
                    <ArrowDownTrayIcon className="h-4 w-4" />
                    <span>Export CSV</span>
                </button>
            </div>

            {items.length === 0 && !loading ? (
                <EmptyState icon={HandRaisedIcon} title="No volunteers" description="Volunteers will appear here when they sign up through your campaign site" />
            ) : (
                <DataTable columns={columns} data={items} loading={loading} />
            )}

            <Modal open={!!showEdit} onClose={() => setShowEdit(null)} title={`Update: ${showEdit?.name}`}>
                {error && <div className="mb-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{error}</div>}
                <form onSubmit={handleUpdate} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Engagement Status</label>
                        <select value={editForm.engagement_status} onChange={(e) => setEditForm((f) => ({ ...f, engagement_status: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            {ENGAGEMENT_STATUSES.map((s) => <option key={s} value={s} className="capitalize">{s.charAt(0).toUpperCase() + s.slice(1)}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Tags (comma-separated)</label>
                        <input type="text" value={editForm.tags} onChange={(e) => setEditForm((f) => ({ ...f, tags: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="youth, transport, social-media" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea value={editForm.notes} onChange={(e) => setEditForm((f) => ({ ...f, notes: e.target.value }))} rows={3} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                    </div>
                    <div className="flex justify-end space-x-3 pt-2">
                        <button type="button" onClick={() => setShowEdit(null)} className="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                        <button type="submit" disabled={submitting} className="btn-primary !py-2 !px-5 text-sm disabled:opacity-50">{submitting ? 'Saving...' : 'Save'}</button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
