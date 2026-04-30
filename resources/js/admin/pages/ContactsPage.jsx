import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { ArrowDownTrayIcon, EnvelopeIcon, EyeIcon, TrashIcon, ChatBubbleLeftIcon } from '@heroicons/react/24/outline';
import api from '../../lib/api';
import PermissionGate from '../components/PermissionGate';
import useCampaignPermissions from '../hooks/useCampaignPermissions';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';

export default function ContactsPage() {
    const { campaignId } = useParams();
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [filter, setFilter] = useState('active');
    const [showDetail, setShowDetail] = useState(null);
    const [response, setResponse] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const { can } = useCampaignPermissions();

    const fetch = async () => {
        setLoading(true);
        try {
            const params = filter === 'archived' ? { include_archived: 1 } : {};
            const { data } = await api.get(`/campaigns/${campaignId}/contacts`, { params });
            let results = data.data || [];
            if (filter === 'archived') {
                results = results.filter((m) => m.is_archived);
            }
            setItems(results);
        } catch { /* handled */ }
        setLoading(false);
    };

    useEffect(() => { fetch(); }, [campaignId, filter]);

    const openDetail = async (item) => {
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/contacts/${item.id}`);
            const contact = data.message || data.data;
            setShowDetail(contact);
            setResponse(contact?.response || '');
        } catch {
            setShowDetail(item);
            setResponse(item.response || '');
        }
    };

    const handleRespond = async () => {
        if (!response.trim()) return;
        setSubmitting(true);
        try {
            await api.put(`/campaigns/${campaignId}/contacts/${showDetail.id}`, { response });
            setShowDetail(null);
            fetch();
        } catch (err) {
            alert(err.response?.data?.message || 'Failed to respond');
        }
        setSubmitting(false);
    };

    const handleArchive = async (id) => {
        try {
            await api.put(`/campaigns/${campaignId}/contacts/${id}`, { is_archived: true });
            fetch();
        } catch (err) {
            alert(err.response?.data?.message || 'Failed to archive');
        }
    };

    const handleDelete = async (id) => {
        if (!confirm('Delete this message?')) return;
        try { await api.delete(`/campaigns/${campaignId}/contacts/${id}`); fetch(); }
        catch (err) { alert(err.response?.data?.message || 'Failed to delete'); }
    };

    const handleExport = () => {
        const token = localStorage.getItem('kura_token');
        window.open(`/api/v1/campaigns/${campaignId}/contacts/export?token=${token}`, '_blank');
    };

    const columns = [
        { key: 'name', label: 'From', render: (r) => (
            <div>
                <p className="font-medium text-gray-900">{r.name}</p>
                <p className="text-xs text-gray-400">{r.email}</p>
            </div>
        )},
        { key: 'subject', label: 'Subject', render: (r) => (
            <div className="max-w-xs">
                <p className="text-sm text-gray-700 truncate">{r.subject || '(No subject)'}</p>
                <p className="text-xs text-gray-400 truncate">{r.message?.substring(0, 60)}</p>
            </div>
        )},
        { key: 'is_read', label: 'Status', render: (r) => (
            <div className="flex flex-col gap-0.5">
                <span className={`px-2 py-0.5 text-xs font-medium rounded-full w-fit ${r.is_read ? 'bg-gray-100 text-gray-500' : 'bg-blue-50 text-blue-700'}`}>
                    {r.is_read ? 'Read' : 'Unread'}
                </span>
                {r.response && <span className="px-2 py-0.5 text-xs font-medium rounded-full bg-green-50 text-green-700 w-fit">Responded</span>}
            </div>
        )},
        { key: 'created_at', label: 'Received', render: (r) => <span className="text-xs text-gray-400">{new Date(r.created_at).toLocaleDateString('en-KE', { day: 'numeric', month: 'short' })}</span> },
        { key: 'actions', label: '', render: (r) => (
            <div className="flex items-center space-x-1">
                <button onClick={() => openDetail(r)} className="p-1.5 text-gray-400 hover:text-primary-600 rounded" title="View"><EyeIcon className="h-4 w-4" /></button>
                {can('contacts.respond') && !r.is_archived && <button onClick={() => handleArchive(r.id)} className="p-1.5 text-gray-400 hover:text-yellow-600 rounded" title="Archive"><EnvelopeIcon className="h-4 w-4" /></button>}
                {can('contacts.delete') && <button onClick={() => handleDelete(r.id)} className="p-1.5 text-gray-400 hover:text-red-500 rounded" title="Delete"><TrashIcon className="h-4 w-4" /></button>}
            </div>
        )},
    ];

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between flex-wrap gap-3">
                <div className="flex space-x-1 bg-gray-100 rounded-lg p-0.5">
                    {['active', 'archived'].map((f) => (
                        <button key={f} onClick={() => setFilter(f)} className={`px-3 py-1.5 text-sm font-medium rounded-md transition-colors capitalize ${filter === f ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'}`}>
                            {f}
                        </button>
                    ))}
                </div>
                <PermissionGate permission="contacts.export">
                    <button onClick={handleExport} className="inline-flex items-center space-x-1.5 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 shadow-sm">
                        <ArrowDownTrayIcon className="h-4 w-4" />
                        <span>Export CSV</span>
                    </button>
                </PermissionGate>
            </div>

            {items.length === 0 && !loading ? (
                <EmptyState icon={EnvelopeIcon} title={filter === 'archived' ? 'No archived messages' : 'No messages'} description="Contact messages from your campaign site will appear here" />
            ) : (
                <DataTable columns={columns} data={items} loading={loading} />
            )}

            {/* Detail / Respond Modal */}
            <Modal open={!!showDetail} onClose={() => setShowDetail(null)} title="Message Details" size="lg">
                {showDetail && (
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <p className="text-xs text-gray-400 mb-0.5">From</p>
                                <p className="text-sm font-medium text-gray-900">{showDetail.name}</p>
                                <p className="text-xs text-gray-500">{showDetail.email}</p>
                                {showDetail.phone && <p className="text-xs text-gray-500">{showDetail.phone}</p>}
                            </div>
                            <div>
                                <p className="text-xs text-gray-400 mb-0.5">Received</p>
                                <p className="text-sm text-gray-700">{new Date(showDetail.created_at).toLocaleString('en-KE')}</p>
                            </div>
                        </div>

                        {showDetail.subject && (
                            <div>
                                <p className="text-xs text-gray-400 mb-0.5">Subject</p>
                                <p className="text-sm font-medium text-gray-900">{showDetail.subject}</p>
                            </div>
                        )}

                        <div>
                            <p className="text-xs text-gray-400 mb-0.5">Message</p>
                            <div className="bg-gray-50 rounded-lg p-4 text-sm text-gray-700 whitespace-pre-wrap">{showDetail.message}</div>
                        </div>

                        {showDetail.response && (
                            <div>
                                <p className="text-xs text-gray-400 mb-0.5">Your Response</p>
                                <div className="bg-primary-50 rounded-lg p-4 text-sm text-gray-700 whitespace-pre-wrap">{showDetail.response}</div>
                            </div>
                        )}

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                <ChatBubbleLeftIcon className="h-4 w-4 inline mr-1" />
                                {showDetail.response ? 'Update Response' : 'Write Response'}
                            </label>
                            <textarea
                                value={response}
                                onChange={(e) => setResponse(e.target.value)}
                                rows={3}
                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="Type your response..."
                            />
                        </div>

                        <div className="flex justify-end space-x-3">
                            <button onClick={() => setShowDetail(null)} className="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Close</button>
                            <button onClick={handleRespond} disabled={submitting || !response.trim()} className="btn-primary !py-2 !px-5 text-sm disabled:opacity-50">
                                {submitting ? 'Sending...' : 'Send Response'}
                            </button>
                        </div>
                    </div>
                )}
            </Modal>
        </div>
    );
}
