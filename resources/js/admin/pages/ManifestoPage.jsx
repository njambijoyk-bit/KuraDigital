import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { PlusIcon, PencilIcon, TrashIcon, DocumentTextIcon } from '@heroicons/react/24/outline';
import api from '../../lib/api';
import PermissionGate from '../components/PermissionGate';
import useCampaignPermissions from '../hooks/useCampaignPermissions';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';

const emptyForm = { title: '', title_sw: '', description: '', description_sw: '', icon: '', sort_order: 0, promises: '' };

export default function ManifestoPage() {
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
            const { data } = await api.get(`/campaigns/${campaignId}/manifesto`);
            setItems(data.pillars || data.data || []);
        } catch { /* handled */ }
        setLoading(false);
    };

    useEffect(() => { fetch(); }, [campaignId]);

    const openCreate = () => {
        setEditing(null);
        setForm(emptyForm);
        setError(null);
        setShowForm(true);
    };

    const openEdit = (item) => {
        setEditing(item);
        setForm({
            title: item.title || '',
            title_sw: item.title_sw || '',
            description: item.description || '',
            description_sw: item.description_sw || '',
            icon: item.icon || '',
            sort_order: item.sort_order || 0,
            promises: Array.isArray(item.promises) ? item.promises.join('\n') : (item.promises || ''),
        });
        setError(null);
        setShowForm(true);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            const payload = {
                ...form,
                sort_order: parseInt(form.sort_order) || 0,
                promises: form.promises ? form.promises.split('\n').map((p) => p.trim()).filter(Boolean) : [],
            };
            if (editing) {
                await api.put(`/campaigns/${campaignId}/manifesto/${editing.id}`, payload);
            } else {
                await api.post(`/campaigns/${campaignId}/manifesto`, payload);
            }
            setShowForm(false);
            fetch();
        } catch (err) {
            setError(err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(', ') : err.response?.data?.message || 'Failed to save');
        }
        setSubmitting(false);
    };

    const handleDelete = async (id) => {
        if (!confirm('Delete this manifesto pillar?')) return;
        try {
            await api.delete(`/campaigns/${campaignId}/manifesto/${id}`);
            fetch();
        } catch (err) {
            alert(err.response?.data?.message || 'Failed to delete');
        }
    };

    const columns = [
        { key: 'sort_order', label: '#', render: (r) => <span className="text-gray-400 font-mono text-xs">{r.sort_order}</span> },
        { key: 'title', label: 'Title', render: (r) => <span className="font-medium text-gray-900">{r.title}</span> },
        { key: 'icon', label: 'Icon', render: (r) => <span className="text-lg">{r.icon}</span> },
        { key: 'promises', label: 'Promises', render: (r) => <span className="text-xs text-gray-500">{Array.isArray(r.promises) ? r.promises.length : 0} promises</span> },
        {
            key: 'actions', label: '',
            render: (r) => (
                <div className="flex items-center space-x-1">
                    {can('manifesto.edit') && <button onClick={() => openEdit(r)} className="p-1.5 text-gray-400 hover:text-primary-600 rounded"><PencilIcon className="h-4 w-4" /></button>}
                    {can('manifesto.delete') && <button onClick={() => handleDelete(r.id)} className="p-1.5 text-gray-400 hover:text-red-500 rounded"><TrashIcon className="h-4 w-4" /></button>}
                </div>
            ),
        },
    ];

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <p className="text-sm text-gray-500">{items.length} manifesto pillar{items.length !== 1 ? 's' : ''}</p>
                <PermissionGate permission="manifesto.create">
                    <button onClick={openCreate} className="btn-primary !py-2 !px-4 text-sm">
                        <PlusIcon className="h-4 w-4 mr-1" /> Add Pillar
                    </button>
                </PermissionGate>
            </div>

            {items.length === 0 && !loading ? (
                <EmptyState icon={DocumentTextIcon} title="No manifesto pillars" description="Add your campaign's key policy areas" action={
                    <PermissionGate permission="manifesto.create">
                        <button onClick={openCreate} className="btn-primary !py-2 !px-4 text-sm"><PlusIcon className="h-4 w-4 mr-1" /> Add Pillar</button>
                    </PermissionGate>
                } />
            ) : (
                <DataTable columns={columns} data={items} loading={loading} />
            )}

            <Modal open={showForm} onClose={() => setShowForm(false)} title={editing ? 'Edit Pillar' : 'Add Manifesto Pillar'} size="lg">
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
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Icon (emoji)</label>
                            <input type="text" value={form.icon} onChange={(e) => setForm((f) => ({ ...f, icon: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="🏥" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                            <input type="number" value={form.sort_order} onChange={(e) => setForm((f) => ({ ...f, sort_order: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Promises (one per line)</label>
                        <textarea value={form.promises} onChange={(e) => setForm((f) => ({ ...f, promises: e.target.value }))} rows={4} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="Build 5 new health centers&#10;Hire 200 community health workers&#10;Free maternal care" />
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
