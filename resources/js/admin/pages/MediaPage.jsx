import React, { useState, useEffect, useRef } from 'react';
import { useParams } from 'react-router-dom';
import { PlusIcon, TrashIcon, PencilIcon, FilmIcon, DocumentIcon, PhotoIcon } from '@heroicons/react/24/outline';
import api from '../../lib/api';
import PermissionGate from '../components/PermissionGate';
import useCampaignPermissions from '../hooks/useCampaignPermissions';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';

export default function MediaPage() {
    const { campaignId } = useParams();
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [uploading, setUploading] = useState(false);
    const [showEdit, setShowEdit] = useState(null);
    const [editForm, setEditForm] = useState({ alt_text: '', tags: '' });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const fileRef = useRef(null);
    const { can } = useCampaignPermissions();

    const fetch = async () => {
        setLoading(true);
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/media`);
            setItems(data.data || []);
        } catch { /* handled */ }
        setLoading(false);
    };

    useEffect(() => { fetch(); }, [campaignId]);

    const handleUpload = async (e) => {
        const files = e.target.files;
        if (!files?.length) return;
        setUploading(true);
        try {
            for (const file of files) {
                const formData = new FormData();
                formData.append('file', file);
                await api.post(`/campaigns/${campaignId}/media`, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
            }
            fetch();
        } catch (err) {
            alert(err.response?.data?.message || 'Upload failed');
        }
        setUploading(false);
        if (fileRef.current) fileRef.current.value = '';
    };

    const openEdit = (item) => {
        setEditForm({
            alt_text: item.alt_text || '',
            tags: Array.isArray(item.tags) ? item.tags.join(', ') : (item.tags || ''),
        });
        setShowEdit(item);
        setError(null);
    };

    const handleUpdate = async (e) => {
        e.preventDefault();
        setSubmitting(true); setError(null);
        try {
            const payload = {
                alt_text: editForm.alt_text,
                tags: editForm.tags ? editForm.tags.split(',').map((t) => t.trim()).filter(Boolean) : [],
            };
            await api.put(`/campaigns/${campaignId}/media/${showEdit.id}`, payload);
            setShowEdit(null);
            fetch();
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to update');
        }
        setSubmitting(false);
    };

    const handleDelete = async (id) => {
        if (!confirm('Delete this media file?')) return;
        try { await api.delete(`/campaigns/${campaignId}/media/${id}`); fetch(); }
        catch (err) { alert(err.response?.data?.message || 'Failed to delete'); }
    };

    const getIcon = (mimeType) => {
        if (mimeType?.startsWith('image/')) return PhotoIcon;
        if (mimeType?.startsWith('video/')) return FilmIcon;
        return DocumentIcon;
    };

    if (loading) {
        return <div className="flex items-center justify-center py-20"><div className="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600" /></div>;
    }

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <p className="text-sm text-gray-500">{items.length} file{items.length !== 1 ? 's' : ''}</p>
                <PermissionGate permission="media.upload">
                    <div>
                        <input ref={fileRef} type="file" multiple onChange={handleUpload} className="hidden" id="media-upload" />
                        <label htmlFor="media-upload" className={`btn-primary !py-2 !px-4 text-sm cursor-pointer ${uploading ? 'opacity-50 pointer-events-none' : ''}`}>
                            <PlusIcon className="h-4 w-4 mr-1" />
                            {uploading ? 'Uploading...' : 'Upload Files'}
                        </label>
                    </div>
                </PermissionGate>
            </div>

            {items.length === 0 ? (
                <EmptyState icon={FilmIcon} title="Media library is empty" description="Upload images, videos, and documents for your campaign" action={
                    <PermissionGate permission="media.upload">
                        <label htmlFor="media-upload" className="btn-primary !py-2 !px-4 text-sm cursor-pointer"><PlusIcon className="h-4 w-4 mr-1" /> Upload Files</label>
                    </PermissionGate>
                } />
            ) : (
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    {items.map((item) => {
                        const Icon = getIcon(item.mime_type);
                        const isImage = item.mime_type?.startsWith('image/');
                        return (
                            <div key={item.id} className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden group">
                                <div className="aspect-square bg-gray-100 relative">
                                    {isImage && item.url ? (
                                        <img src={item.url} alt={item.alt_text || item.file_name} className="w-full h-full object-cover" />
                                    ) : (
                                        <div className="flex items-center justify-center h-full">
                                            <Icon className="h-10 w-10 text-gray-300" />
                                        </div>
                                    )}
                                    <div className="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100">
                                        {can('media.edit') && <button onClick={() => openEdit(item)} className="p-2 bg-white rounded-full shadow-md mr-2"><PencilIcon className="h-4 w-4 text-gray-700" /></button>}
                                        {can('media.delete') && <button onClick={() => handleDelete(item.id)} className="p-2 bg-white rounded-full shadow-md"><TrashIcon className="h-4 w-4 text-red-500" /></button>}
                                    </div>
                                </div>
                                <div className="p-2">
                                    <p className="text-xs font-medium text-gray-700 truncate">{item.file_name}</p>
                                    <p className="text-xs text-gray-400">{item.mime_type?.split('/')[1]?.toUpperCase()}</p>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            <Modal open={!!showEdit} onClose={() => setShowEdit(null)} title="Edit Media">
                {error && <div className="mb-3 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{error}</div>}
                <form onSubmit={handleUpdate} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Alt Text</label>
                        <input type="text" value={editForm.alt_text} onChange={(e) => setEditForm((f) => ({ ...f, alt_text: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Tags (comma-separated)</label>
                        <input type="text" value={editForm.tags} onChange={(e) => setEditForm((f) => ({ ...f, tags: e.target.value }))} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="rally, team, banner" />
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
