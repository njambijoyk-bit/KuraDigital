import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { PlusIcon, PencilIcon, TrashIcon, NewspaperIcon } from '@heroicons/react/24/outline';
import api from '../../lib/api';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';

const emptyForm = { title: '', title_sw: '', content: '', content_sw: '', excerpt: '', excerpt_sw: '', image_url: '', status: 'draft', scheduled_at: '' };

export default function NewsPage() {
    const { campaignId } = useParams();
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState(emptyForm);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [filter, setFilter] = useState('all');

    const fetch = async () => {
        setLoading(true);
        try {
            const params = filter !== 'all' ? { status: filter } : {};
            const { data } = await api.get(`/campaigns/${campaignId}/news`, { params });
            setItems(data.data || []);
        } catch { /* handled */ }
        setLoading(false);
    };

    useEffect(() => { fetch(); }, [campaignId, filter]);

    const openCreate = () => { setEditing(null); setForm(emptyForm); setError(null); setShowForm(true); };

    const openEdit = (item) => {
        setEditing(item);
        setForm({
            title: item.title || '', title_sw: item.title_sw || '',
            content: item.content || '', content_sw: item.content_sw || '',
            excerpt: item.excerpt || '', excerpt_sw: item.excerpt_sw || '',
            image_url: item.image_url || '', status: item.status || 'draft',
            scheduled_at: item.scheduled_at ? item.scheduled_at.slice(0, 16) : '',
        });
        setError(null); setShowForm(true);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true); setError(null);
        try {
            const payload = { ...form };
            if (!payload.scheduled_at) delete payload.scheduled_at;
            if (editing) {
                await api.put(`/campaigns/${campaignId}/news/${editing.id}`, payload);
            } else {
                await api.post(`/campaigns/${campaignId}/news`, payload);
            }
            setShowForm(false); fetch();
        } catch (err) {
            setError(err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(', ') : err.response?.data?.message || 'Failed to save');
        }
        setSubmitting(false);
    };

    const handleDelete = async (id) => {
        if (!confirm('Delete this article?')) return;
        try { await api.delete(`/campaigns/${campaignId}/news/${id}`); fetch(); }
        catch (err) { alert(err.response?.data?.message || 'Failed to delete'); }
    };

    const statusColors = {
        draft: 'bg-gray-100 text-gray-600',
        published: 'bg-green-50 text-green-700',
        scheduled: 'bg-blue-50 text-blue-700',
    };

    const columns = [
        { key: 'title', label: 'Title', render: (r) => (
            <div>
                <p className="font-medium text-gray-900 truncate max-w-xs">{r.title}</p>
                {r.excerpt && <p className="text-xs text-gray-400 truncate max-w-xs mt-0.5">{r.excerpt}</p>}
            </div>
        )},
        { key: 'author', label: 'Author', render: (r) => <span className="text-gray-500">{r.author?.name || '—'}</span> },
        { key: 'status', label: 'Status', render: (r) => (
            <span className={`px-2 py-0.5 text-xs font-medium rounded-full capitalize ${statusColors[r.status] || statusColors.draft}`}>
                {r.status}
            </span>
        )},
        { key: 'created_at', label: 'Created', render: (r) => <span className="text-xs text-gray-400">{new Date(r.created_at).toLocaleDateString('en-KE')}</span> },
        { key: 'actions', label: '', render: (r) => (
            <div className="flex items-center space-x-1">
                <button onClick={() => openEdit(r)} className="p-1.5 text-gray-400 hover:text-primary-600 rounded"><PencilIcon className="h-4 w-4" /></button>
                <button onClick={() => handleDelete(r.id)} className="p-1.5 text-gray-400 hover:text-red-500 rounded"><TrashIcon className="h-4 w-4" /></button>
            </div>
        )},
    ];

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div className="flex space-x-1 bg-gray-100 rounded-lg p-0.5">
                    {['all', 'draft', 'published', 'scheduled'].map((f) => (
                        <button key={f} onClick={() => setFilter(f)} className={`px-3 py-1.5 text-sm font-medium rounded-md transition-colors capitalize ${filter === f ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'}`}>
                            {f}
                        </button>
                    ))}
                </div>
                <button onClick={openCreate} className="btn-primary !py-2 !px-4 text-sm"><PlusIcon className="h-4 w-4 mr-1" /> Write Article</button>
            </div>

            {items.length === 0 && !loading ? (
                <EmptyState icon={NewspaperIcon} title="No articles" description="Write campaign news and updates" action={
                    <button onClick={openCreate} className="btn-primary !py-2 !px-4 text-sm"><PlusIcon className="h-4 w-4 mr-1" /> Write Article</button>
                } />
            ) : (
                <DataTable columns={columns} data={items} loading={loading} />
            )}

            <Modal open={showForm} onClose={() => setShowForm(false)} title={editing ? 'Edit Article' : 'Write Article'} size="xl">
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
                            <label className="block text-sm font-medium text-gray-700 mb-1">Excerpt (English)</label>
                            <textarea value={form.excerpt} onChange={(e) => setForm((f) => ({ ...f, excerpt: e.target.value }))} rows={2} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Excerpt (Swahili)</label>
                            <textarea value={form.excerpt_sw} onChange={(e) => setForm((f) => ({ ...f, excerpt_sw: e.target.value }))} rows={2} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Content (English)</label>
                            <textarea value={form.content} onChange={(e) => setForm((f) => ({ ...f, content: e.target.value }))} rows={6} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Content (Swahili)</label>
                            <textarea value={form.content_sw} onChange={(e) => setForm((f) => ({ ...f, content_sw: e.target.value }))} rows={6} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>
                    </div>
                    <div className="grid grid-cols-3 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Image URL</label>
                            <input type="url" value={form.image_url} onChange={(e) => setForm((f) => ({ ...f, image_url: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select value={form.status} onChange={(e) => setForm((f) => ({ ...f, status: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="scheduled">Scheduled</option>
                            </select>
                        </div>
                        {form.status === 'scheduled' && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Scheduled At</label>
                                <input type="datetime-local" value={form.scheduled_at} onChange={(e) => setForm((f) => ({ ...f, scheduled_at: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                            </div>
                        )}
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
