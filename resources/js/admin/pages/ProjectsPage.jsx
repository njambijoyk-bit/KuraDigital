import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { PlusIcon, PencilIcon, TrashIcon, FolderIcon } from '@heroicons/react/24/outline';
import api from '../../lib/api';
import PermissionGate from '../components/PermissionGate';
import useCampaignPermissions from '../hooks/useCampaignPermissions';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';

const emptyForm = { title: '', title_sw: '', description: '', description_sw: '', status: 'planned', location: '', budget: '', image_url: '', progress_percentage: 0 };
const STATUSES = ['planned', 'ongoing', 'completed'];

export default function ProjectsPage() {
    const { campaignId } = useParams();
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState(emptyForm);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const { can } = useCampaignPermissions();

    const fetch = async () => {
        setLoading(true);
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/projects`);
            setItems(data.data || []);
        } catch { /* handled */ }
        setLoading(false);
    };

    useEffect(() => { fetch(); }, [campaignId]);

    const openCreate = () => { setEditing(null); setForm(emptyForm); setError(null); setShowForm(true); };

    const openEdit = (item) => {
        setEditing(item);
        setForm({
            title: item.title || '', title_sw: item.title_sw || '',
            description: item.description || '', description_sw: item.description_sw || '',
            status: item.status || 'planned', location: item.location || '',
            budget: item.budget || '', image_url: item.image_url || '',
            progress_percentage: item.progress_percentage || 0,
        });
        setError(null); setShowForm(true);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true); setError(null);
        try {
            const payload = { ...form, progress_percentage: parseInt(form.progress_percentage) || 0 };
            if (editing) {
                await api.put(`/campaigns/${campaignId}/projects/${editing.id}`, payload);
            } else {
                await api.post(`/campaigns/${campaignId}/projects`, payload);
            }
            setShowForm(false); fetch();
        } catch (err) {
            setError(err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(', ') : err.response?.data?.message || 'Failed to save');
        }
        setSubmitting(false);
    };

    const handleDelete = async (id) => {
        if (!confirm('Delete this project?')) return;
        try { await api.delete(`/campaigns/${campaignId}/projects/${id}`); fetch(); }
        catch (err) { alert(err.response?.data?.message || 'Failed to delete'); }
    };

    const statusColors = { planned: 'bg-gray-100 text-gray-600', ongoing: 'bg-blue-50 text-blue-700', completed: 'bg-green-50 text-green-700' };

    const columns = [
        { key: 'title', label: 'Project', render: (r) => <span className="font-medium text-gray-900">{r.title}</span> },
        { key: 'status', label: 'Status', render: (r) => (
            <span className={`px-2 py-0.5 text-xs font-medium rounded-full capitalize ${statusColors[r.status] || statusColors.planned}`}>{r.status}</span>
        )},
        { key: 'progress', label: 'Progress', render: (r) => (
            <div className="flex items-center space-x-2">
                <div className="w-20 bg-gray-200 rounded-full h-1.5">
                    <div className="bg-primary-600 h-1.5 rounded-full" style={{ width: `${r.progress_percentage || 0}%` }} />
                </div>
                <span className="text-xs text-gray-500">{r.progress_percentage || 0}%</span>
            </div>
        )},
        { key: 'location', label: 'Location', render: (r) => <span className="text-gray-500 text-sm">{r.location || '—'}</span> },
        { key: 'actions', label: '', render: (r) => (
            <div className="flex items-center space-x-1">
                {can('projects.edit') && <button onClick={() => openEdit(r)} className="p-1.5 text-gray-400 hover:text-primary-600 rounded"><PencilIcon className="h-4 w-4" /></button>}
                {can('projects.delete') && <button onClick={() => handleDelete(r.id)} className="p-1.5 text-gray-400 hover:text-red-500 rounded"><TrashIcon className="h-4 w-4" /></button>}
            </div>
        )},
    ];

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <p className="text-sm text-gray-500">{items.length} project{items.length !== 1 ? 's' : ''}</p>
                <PermissionGate permission="projects.create">
                    <button onClick={openCreate} className="btn-primary !py-2 !px-4 text-sm"><PlusIcon className="h-4 w-4 mr-1" /> Add Project</button>
                </PermissionGate>
            </div>

            {items.length === 0 && !loading ? (
                <EmptyState icon={FolderIcon} title="No projects" description="Track campaign development projects" action={
                    <PermissionGate permission="projects.create">
                        <button onClick={openCreate} className="btn-primary !py-2 !px-4 text-sm"><PlusIcon className="h-4 w-4 mr-1" /> Add Project</button>
                    </PermissionGate>
                } />
            ) : (
                <DataTable columns={columns} data={items} loading={loading} />
            )}

            <Modal open={showForm} onClose={() => setShowForm(false)} title={editing ? 'Edit Project' : 'Add Project'} size="lg">
                {error && <div className="mb-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{error}</div>}
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Title (English)</label>
                            <input type="text" value={form.title} onChange={(e) => setForm((f) => ({ ...f, title: e.target.value }))} required className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Title (Swahili)</label>
                            <input type="text" value={form.title_sw} onChange={(e) => setForm((f) => ({ ...f, title_sw: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Description (English)</label>
                            <textarea value={form.description} onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))} rows={3} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Description (Swahili)</label>
                            <textarea value={form.description_sw} onChange={(e) => setForm((f) => ({ ...f, description_sw: e.target.value }))} rows={3} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>
                    </div>
                    <div className="grid grid-cols-3 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select value={form.status} onChange={(e) => setForm((f) => ({ ...f, status: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                {STATUSES.map((s) => <option key={s} value={s} className="capitalize">{s.charAt(0).toUpperCase() + s.slice(1)}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Location</label>
                            <input type="text" value={form.location} onChange={(e) => setForm((f) => ({ ...f, location: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Progress %</label>
                            <input type="number" min="0" max="100" value={form.progress_percentage} onChange={(e) => setForm((f) => ({ ...f, progress_percentage: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Budget</label>
                            <input type="text" value={form.budget} onChange={(e) => setForm((f) => ({ ...f, budget: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="KSh 5,000,000" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Image URL</label>
                            <input type="url" value={form.image_url} onChange={(e) => setForm((f) => ({ ...f, image_url: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm" />
                        </div>
                    </div>
                    <div className="flex justify-end space-x-3 pt-2">
                        <button type="button" onClick={() => setShowForm(false)} className="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                        <button type="submit" disabled={submitting} className="btn-primary !py-2 !px-5 text-sm disabled:opacity-50">{submitting ? 'Saving...' : 'Save'}</button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
