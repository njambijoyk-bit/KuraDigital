import React, { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    MagnifyingGlassIcon,
    EnvelopeIcon,
    ChatBubbleLeftIcon,
    DevicePhoneMobileIcon,
    CheckCircleIcon,
    PaperAirplaneIcon,
} from '@heroicons/react/24/outline';
import api from '../../lib/api';
import PermissionGate from '../components/PermissionGate';
import useCampaignPermissions from '../hooks/useCampaignPermissions';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';

const CHANNELS = ['sms', 'whatsapp', 'email'];

const channelIcons = {
    sms: DevicePhoneMobileIcon,
    whatsapp: ChatBubbleLeftIcon,
    email: EnvelopeIcon,
};

const statusColors = {
    draft: 'bg-gray-100 text-gray-500',
    approved: 'bg-green-50 text-green-700',
    archived: 'bg-gray-200 text-gray-600',
    sending: 'bg-blue-50 text-blue-700',
    sent: 'bg-green-100 text-green-800',
    failed: 'bg-red-50 text-red-700',
};

export default function MessagingPage() {
    const { campaignId } = useParams();
    const [tab, setTab] = useState('templates');
    const { can } = useCampaignPermissions();

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-heading font-bold text-gray-900">Messaging</h1>
            </div>

            <div className="border-b border-gray-200">
                <nav className="flex space-x-8">
                    <button onClick={() => setTab('templates')}
                        className={`py-3 px-1 border-b-2 text-sm font-medium ${tab === 'templates' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                        Templates
                    </button>
                    <button onClick={() => setTab('campaigns')}
                        className={`py-3 px-1 border-b-2 text-sm font-medium ${tab === 'campaigns' ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                        Campaigns
                    </button>
                </nav>
            </div>

            {tab === 'templates' && <TemplatesTab campaignId={campaignId} />}
            {tab === 'campaigns' && <CampaignsTab campaignId={campaignId} />}
        </div>
    );
}

function TemplatesTab({ campaignId }) {
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [channelFilter, setChannelFilter] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [showEdit, setShowEdit] = useState(null);
    const [form, setForm] = useState({ name: '', channel: 'sms', subject: '', body: '' });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [page, setPage] = useState(1);
    const [meta, setMeta] = useState({});

    const fetch = useCallback(async (pg = page) => {
        setLoading(true);
        try {
            const params = { page: pg };
            if (search.trim()) params.search = search.trim();
            if (channelFilter) params.channel = channelFilter;
            const { data } = await api.get(`/campaigns/${campaignId}/messaging/templates`, { params });
            setItems(data.data || []);
            setMeta(data.meta || { current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, search, channelFilter, page]);

    useEffect(() => { fetch(); }, [fetch]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            if (showEdit) {
                await api.put(`/campaigns/${campaignId}/messaging/templates/${showEdit.id}`, form);
                setShowEdit(null);
            } else {
                await api.post(`/campaigns/${campaignId}/messaging/templates`, form);
                setShowCreate(false);
            }
            setForm({ name: '', channel: 'sms', subject: '', body: '' });
            fetch();
        } catch (err) {
            setError(err.response?.data?.message || 'Something went wrong.');
        }
        setSubmitting(false);
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Delete this template?')) return;
        try { await api.delete(`/campaigns/${campaignId}/messaging/templates/${id}`); fetch(); } catch { /* handled */ }
    };

    const handleApprove = async (id) => {
        try { await api.post(`/campaigns/${campaignId}/messaging/templates/${id}/approve`); fetch(); } catch { /* handled */ }
    };

    const columns = [
        { key: 'name', label: 'Name', render: (r) => {
            const Icon = channelIcons[r.channel] || EnvelopeIcon;
            return (
                <div className="flex items-center space-x-2">
                    <Icon className="h-4 w-4 text-gray-400" />
                    <span className="font-medium text-gray-900">{r.name}</span>
                </div>
            );
        }},
        { key: 'channel', label: 'Channel', render: (r) => <span className="text-xs uppercase text-gray-500">{r.channel}</span> },
        { key: 'status', label: 'Status', render: (r) => (
            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[r.status] || statusColors.draft}`}>{r.status}</span>
        )},
        { key: 'creator', label: 'Author', render: (r) => r.creator?.name || '—' },
        { key: 'updated_at', label: 'Updated', render: (r) => new Date(r.updated_at).toLocaleDateString() },
        { key: 'actions', label: '', render: (r) => (
            <div className="flex items-center space-x-2">
                {r.status === 'draft' && (
                    <PermissionGate permission="messaging.approve">
                        <button onClick={() => handleApprove(r.id)} className="text-gray-400 hover:text-green-600" title="Approve"><CheckCircleIcon className="h-4 w-4" /></button>
                    </PermissionGate>
                )}
                <PermissionGate permission="messaging.edit">
                    <button onClick={() => { setForm({ name: r.name, channel: r.channel, subject: r.subject || '', body: r.body }); setShowEdit(r); setError(null); }} className="text-gray-400 hover:text-primary-600"><PencilIcon className="h-4 w-4" /></button>
                    <button onClick={() => handleDelete(r.id)} className="text-gray-400 hover:text-red-600"><TrashIcon className="h-4 w-4" /></button>
                </PermissionGate>
            </div>
        )},
    ];

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div className="flex items-center space-x-4 flex-1">
                    <div className="relative max-w-md flex-1">
                        <MagnifyingGlassIcon className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input type="text" placeholder="Search templates..." value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                            className="w-full pl-10 pr-4 py-2 border rounded-lg text-sm" />
                    </div>
                    <select value={channelFilter} onChange={(e) => { setChannelFilter(e.target.value); setPage(1); }} className="border rounded-lg px-3 py-2 text-sm">
                        <option value="">All Channels</option>
                        {CHANNELS.map((c) => <option key={c} value={c}>{c.toUpperCase()}</option>)}
                    </select>
                </div>
                <PermissionGate permission="messaging.create">
                    <button onClick={() => { setForm({ name: '', channel: 'sms', subject: '', body: '' }); setShowCreate(true); setError(null); }}
                        className="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                        <PlusIcon className="h-4 w-4 mr-2" /> New Template
                    </button>
                </PermissionGate>
            </div>

            {loading ? (
                <div className="flex justify-center py-12"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" /></div>
            ) : items.length === 0 ? (
                <EmptyState icon={EnvelopeIcon} title="No templates" description="Create message templates for SMS, WhatsApp, and email campaigns." />
            ) : (
                <DataTable columns={columns} data={items} />
            )}

            <Modal open={showCreate || !!showEdit} onClose={() => { setShowCreate(false); setShowEdit(null); }} title={showEdit ? 'Edit Template' : 'New Template'}>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm">{error}</div>}
                    <div><label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="w-full border rounded-lg px-3 py-2" required /></div>
                    {!showEdit && (
                        <div><label className="block text-sm font-medium text-gray-700 mb-1">Channel</label>
                            <select value={form.channel} onChange={(e) => setForm({ ...form, channel: e.target.value })} className="w-full border rounded-lg px-3 py-2">
                                {CHANNELS.map((c) => <option key={c} value={c}>{c.toUpperCase()}</option>)}</select></div>
                    )}
                    {(form.channel === 'email') && (
                        <div><label className="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                            <input type="text" value={form.subject} onChange={(e) => setForm({ ...form, subject: e.target.value })} className="w-full border rounded-lg px-3 py-2" /></div>
                    )}
                    <div><label className="block text-sm font-medium text-gray-700 mb-1">Body</label>
                        <textarea value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} className="w-full border rounded-lg px-3 py-2" rows={6} required /></div>
                    <div className="flex justify-end space-x-3 pt-4">
                        <button type="button" onClick={() => { setShowCreate(false); setShowEdit(null); }} className="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button type="submit" disabled={submitting} className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 disabled:opacity-50">
                            {submitting ? 'Saving...' : showEdit ? 'Update' : 'Create'}</button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}

function CampaignsTab({ campaignId }) {
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [channelFilter, setChannelFilter] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [form, setForm] = useState({ name: '', channel: 'sms', subject: '', body: '', audience_filters: {} });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [page, setPage] = useState(1);
    const [meta, setMeta] = useState({});
    const { can } = useCampaignPermissions();

    const fetch = useCallback(async (pg = page) => {
        setLoading(true);
        try {
            const params = { page: pg };
            if (search.trim()) params.search = search.trim();
            if (channelFilter) params.channel = channelFilter;
            const { data } = await api.get(`/campaigns/${campaignId}/messaging/campaigns`, { params });
            setItems(data.data || []);
            setMeta(data.meta || { current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, search, channelFilter, page]);

    useEffect(() => { fetch(); }, [fetch]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            await api.post(`/campaigns/${campaignId}/messaging/campaigns`, form);
            setShowCreate(false);
            setForm({ name: '', channel: 'sms', subject: '', body: '', audience_filters: {} });
            fetch();
        } catch (err) {
            setError(err.response?.data?.message || 'Something went wrong.');
        }
        setSubmitting(false);
    };

    const handleApprove = async (id) => {
        try { await api.post(`/campaigns/${campaignId}/messaging/campaigns/${id}/approve`); fetch(); } catch { /* handled */ }
    };

    const handleSend = async (id) => {
        if (!window.confirm('Send this campaign? This action cannot be undone.')) return;
        try { await api.post(`/campaigns/${campaignId}/messaging/campaigns/${id}/send`); fetch(); } catch { /* handled */ }
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Delete this campaign?')) return;
        try { await api.delete(`/campaigns/${campaignId}/messaging/campaigns/${id}`); fetch(); } catch { /* handled */ }
    };

    const columns = [
        { key: 'name', label: 'Campaign', render: (r) => {
            const Icon = channelIcons[r.channel] || EnvelopeIcon;
            return (
                <div className="flex items-center space-x-2">
                    <Icon className="h-4 w-4 text-gray-400" />
                    <span className="font-medium text-gray-900">{r.name}</span>
                </div>
            );
        }},
        { key: 'channel', label: 'Channel', render: (r) => <span className="text-xs uppercase text-gray-500">{r.channel}</span> },
        { key: 'status', label: 'Status', render: (r) => (
            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[r.status] || statusColors.draft}`}>{r.status}</span>
        )},
        { key: 'stats', label: 'Sent / Total', render: (r) => `${r.sent_count || 0} / ${r.total_recipients || 0}` },
        { key: 'created_at', label: 'Created', render: (r) => new Date(r.created_at).toLocaleDateString() },
        { key: 'actions', label: '', render: (r) => (
            <div className="flex items-center space-x-2">
                {r.status === 'draft' && (
                    <PermissionGate permission="messaging.approve">
                        <button onClick={() => handleApprove(r.id)} className="text-gray-400 hover:text-green-600" title="Approve"><CheckCircleIcon className="h-4 w-4" /></button>
                    </PermissionGate>
                )}
                {r.status === 'approved' && (
                    <button onClick={() => handleSend(r.id)} className="text-gray-400 hover:text-blue-600" title="Send"><PaperAirplaneIcon className="h-4 w-4" /></button>
                )}
                {!['sending', 'sent'].includes(r.status) && (
                    <PermissionGate permission="messaging.edit">
                        <button onClick={() => handleDelete(r.id)} className="text-gray-400 hover:text-red-600"><TrashIcon className="h-4 w-4" /></button>
                    </PermissionGate>
                )}
            </div>
        )},
    ];

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <div className="flex items-center space-x-4 flex-1">
                    <div className="relative max-w-md flex-1">
                        <MagnifyingGlassIcon className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input type="text" placeholder="Search campaigns..." value={search} onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                            className="w-full pl-10 pr-4 py-2 border rounded-lg text-sm" />
                    </div>
                    <select value={channelFilter} onChange={(e) => { setChannelFilter(e.target.value); setPage(1); }} className="border rounded-lg px-3 py-2 text-sm">
                        <option value="">All Channels</option>
                        {CHANNELS.map((c) => <option key={c} value={c}>{c.toUpperCase()}</option>)}
                    </select>
                </div>
                <PermissionGate permission="messaging.create">
                    <button onClick={() => { setForm({ name: '', channel: 'sms', subject: '', body: '', audience_filters: {} }); setShowCreate(true); setError(null); }}
                        className="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                        <PlusIcon className="h-4 w-4 mr-2" /> New Campaign
                    </button>
                </PermissionGate>
            </div>

            {loading ? (
                <div className="flex justify-center py-12"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" /></div>
            ) : items.length === 0 ? (
                <EmptyState icon={PaperAirplaneIcon} title="No message campaigns" description="Create a message campaign to reach your voters." />
            ) : (
                <DataTable columns={columns} data={items} />
            )}

            <Modal open={showCreate} onClose={() => setShowCreate(false)} title="New Message Campaign">
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm">{error}</div>}
                    <div><label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="w-full border rounded-lg px-3 py-2" required /></div>
                    <div><label className="block text-sm font-medium text-gray-700 mb-1">Channel</label>
                        <select value={form.channel} onChange={(e) => setForm({ ...form, channel: e.target.value })} className="w-full border rounded-lg px-3 py-2">
                            {CHANNELS.map((c) => <option key={c} value={c}>{c.toUpperCase()}</option>)}</select></div>
                    {form.channel === 'email' && (
                        <div><label className="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                            <input type="text" value={form.subject} onChange={(e) => setForm({ ...form, subject: e.target.value })} className="w-full border rounded-lg px-3 py-2" /></div>
                    )}
                    <div><label className="block text-sm font-medium text-gray-700 mb-1">Body</label>
                        <textarea value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} className="w-full border rounded-lg px-3 py-2" rows={6} required /></div>
                    <div className="flex justify-end space-x-3 pt-4">
                        <button type="button" onClick={() => setShowCreate(false)} className="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button type="submit" disabled={submitting} className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 disabled:opacity-50">
                            {submitting ? 'Saving...' : 'Create Campaign'}</button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
