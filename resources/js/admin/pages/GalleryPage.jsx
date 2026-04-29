import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { PlusIcon, PencilIcon, TrashIcon, PhotoIcon } from '@heroicons/react/24/outline';
import api from '../../lib/api';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';

const emptyForm = { title: '', title_sw: '', image_url: '', caption: '', caption_sw: '', category: '', sort_order: 0 };

export default function GalleryPage() {
    const { campaignId } = useParams();
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState(emptyForm);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);

    const fetch = async () => {
        setLoading(true);
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/gallery`);
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
            image_url: item.image_url || '', caption: item.caption || '',
            caption_sw: item.caption_sw || '', category: item.category || '',
            sort_order: item.sort_order || 0,
        });
        setError(null); setShowForm(true);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true); setError(null);
        try {
            const payload = { ...form, sort_order: parseInt(form.sort_order) || 0 };
            if (editing) {
                await api.put(`/campaigns/${campaignId}/gallery/${editing.id}`, payload);
            } else {
                await api.post(`/campaigns/${campaignId}/gallery`, payload);
            }
            setShowForm(false); fetch();
        } catch (err) {
            setError(err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(', ') : err.response?.data?.message || 'Failed to save');
        }
        setSubmitting(false);
    };

    const handleDelete = async (id) => {
        if (!confirm('Delete this gallery item?')) return;
        try { await api.delete(`/campaigns/${campaignId}/gallery/${id}`); fetch(); }
        catch (err) { alert(err.response?.data?.message || 'Failed to delete'); }
    };

    if (loading) {
        return <div className="flex items-center justify-center py-20"><div className="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600" /></div>;
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <p className="text-sm text-gray-500">{items.length} item{items.length !== 1 ? 's' : ''}</p>
                <button onClick={openCreate} className="btn-primary !py-2 !px-4 text-sm"><PlusIcon className="h-4 w-4 mr-1" /> Add Photo</button>
            </div>

            {items.length === 0 ? (
                <EmptyState icon={PhotoIcon} title="Gallery is empty" description="Add campaign photos and images" action={
                    <button onClick={openCreate} className="btn-primary !py-2 !px-4 text-sm"><PlusIcon className="h-4 w-4 mr-1" /> Add Photo</button>
                } />
            ) : (
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    {items.map((item) => (
                        <div key={item.id} className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden group">
                            <div className="aspect-square bg-gray-100 relative">
                                {item.image_url ? (
                                    <img src={item.image_url} alt={item.title} className="w-full h-full object-cover" />
                                ) : (
                                    <div className="flex items-center justify-center h-full">
                                        <PhotoIcon className="h-12 w-12 text-gray-300" />
                                    </div>
                                )}
                                <div className="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100">
                                    <button onClick={() => openEdit(item)} className="p-2 bg-white rounded-full shadow-md mr-2"><PencilIcon className="h-4 w-4 text-gray-700" /></button>
                                    <button onClick={() => handleDelete(item.id)} className="p-2 bg-white rounded-full shadow-md"><TrashIcon className="h-4 w-4 text-red-500" /></button>
                                </div>
                            </div>
                            <div className="p-3">
                                <p className="text-sm font-medium text-gray-900 truncate">{item.title}</p>
                                {item.category && <span className="text-xs text-gray-400">{item.category}</span>}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <Modal open={showForm} onClose={() => setShowForm(false)} title={editing ? 'Edit Photo' : 'Add Photo'}>
                {error && <div className="mb-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{error}</div>}
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Image URL</label>
                        <input type="url" value={form.image_url} onChange={(e) => setForm((f) => ({ ...f, image_url: e.target.value }))} required className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                    </div>
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
                            <label className="block text-sm font-medium text-gray-700 mb-1">Caption (English)</label>
                            <input type="text" value={form.caption} onChange={(e) => setForm((f) => ({ ...f, caption: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Caption (Swahili)</label>
                            <input type="text" value={form.caption_sw} onChange={(e) => setForm((f) => ({ ...f, caption_sw: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <input type="text" value={form.category} onChange={(e) => setForm((f) => ({ ...f, category: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="rallies, community, team" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                            <input type="number" value={form.sort_order} onChange={(e) => setForm((f) => ({ ...f, sort_order: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
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
