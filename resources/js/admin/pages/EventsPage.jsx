import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { PlusIcon, PencilIcon, TrashIcon, CalendarDaysIcon } from '@heroicons/react/24/outline';
import api from '../../lib/api';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';

const emptyForm = { title: '', title_sw: '', description: '', description_sw: '', location: '', event_date: '', event_time: '', is_published: true };

export default function EventsPage() {
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
            const { data } = await api.get(`/campaigns/${campaignId}/events`);
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
            location: item.location || '', event_date: item.event_date?.split('T')[0] || '',
            event_time: item.event_time || '', is_published: item.is_published ?? true,
        });
        setError(null); setShowForm(true);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true); setError(null);
        try {
            const payload = { ...form, is_published: form.is_published ? 1 : 0 };
            if (editing) {
                await api.put(`/campaigns/${campaignId}/events/${editing.id}`, payload);
            } else {
                await api.post(`/campaigns/${campaignId}/events`, payload);
            }
            setShowForm(false); fetch();
        } catch (err) {
            setError(err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(', ') : err.response?.data?.message || 'Failed to save');
        }
        setSubmitting(false);
    };

    const handleDelete = async (id) => {
        if (!confirm('Delete this event?')) return;
        try { await api.delete(`/campaigns/${campaignId}/events/${id}`); fetch(); }
        catch (err) { alert(err.response?.data?.message || 'Failed to delete'); }
    };

    const columns = [
        { key: 'title', label: 'Event', render: (r) => <span className="font-medium text-gray-900">{r.title}</span> },
        { key: 'location', label: 'Location', render: (r) => <span className="text-gray-500">{r.location || '—'}</span> },
        { key: 'event_date', label: 'Date', render: (r) => r.event_date ? new Date(r.event_date).toLocaleDateString('en-KE', { day: 'numeric', month: 'short', year: 'numeric' }) : '—' },
        { key: 'is_published', label: 'Status', render: (r) => (
            <span className={`px-2 py-0.5 text-xs font-medium rounded-full ${r.is_published ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                {r.is_published ? 'Published' : 'Draft'}
            </span>
        )},
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
                <p className="text-sm text-gray-500">{items.length} event{items.length !== 1 ? 's' : ''}</p>
                <button onClick={openCreate} className="btn-primary !py-2 !px-4 text-sm"><PlusIcon className="h-4 w-4 mr-1" /> Add Event</button>
            </div>

            {items.length === 0 && !loading ? (
                <EmptyState icon={CalendarDaysIcon} title="No events" description="Schedule rallies, town halls, and campaign events" action={
                    <button onClick={openCreate} className="btn-primary !py-2 !px-4 text-sm"><PlusIcon className="h-4 w-4 mr-1" /> Add Event</button>
                } />
            ) : (
                <DataTable columns={columns} data={items} loading={loading} />
            )}

            <Modal open={showForm} onClose={() => setShowForm(false)} title={editing ? 'Edit Event' : 'Add Event'} size="lg">
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
                            <label className="block text-sm font-medium text-gray-700 mb-1">Location</label>
                            <input type="text" value={form.location} onChange={(e) => setForm((f) => ({ ...f, location: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="Uhuru Gardens, Nairobi" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Date</label>
                            <input type="date" value={form.event_date} onChange={(e) => setForm((f) => ({ ...f, event_date: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Time</label>
                            <input type="time" value={form.event_time} onChange={(e) => setForm((f) => ({ ...f, event_time: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>
                    </div>
                    <div className="flex items-center space-x-2">
                        <input type="checkbox" id="event_published" checked={form.is_published} onChange={(e) => setForm((f) => ({ ...f, is_published: e.target.checked }))} className="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                        <label htmlFor="event_published" className="text-sm text-gray-700">Published</label>
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
