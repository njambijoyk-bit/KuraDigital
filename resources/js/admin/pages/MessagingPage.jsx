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
    EyeIcon,
    ChartBarIcon,
    UsersIcon,
} from '@heroicons/react/24/outline';
import api from '../../lib/api';
import PermissionGate from '../components/PermissionGate';
import useCampaignPermissions from '../hooks/useCampaignPermissions';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';
import StatsCard from '../components/StatsCard';

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

const VARIABLE_HINTS = [
    { var: '{{name}}', desc: 'Full name' },
    { var: '{{first_name}}', desc: 'First name' },
    { var: '{{phone}}', desc: 'Phone number' },
    { var: '{{email}}', desc: 'Email' },
    { var: '{{county}}', desc: 'County' },
    { var: '{{constituency}}', desc: 'Constituency' },
    { var: '{{ward}}', desc: 'Ward' },
    { var: '{{polling_station}}', desc: 'Polling station' },
];

export default function MessagingPage() {
    const { campaignId } = useParams();
    const [tab, setTab] = useState('templates');
    const { can } = useCampaignPermissions();

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-heading font-bold text-gray-900">Messaging & Outreach</h1>
            </div>

            <div className="border-b border-gray-200">
                <nav className="flex space-x-8">
                    {[
                        { id: 'templates', label: 'Templates' },
                        { id: 'campaigns', label: 'Campaigns' },
                        { id: 'overview', label: 'Overview' },
                    ].map((t) => (
                        <button key={t.id} onClick={() => setTab(t.id)}
                            className={`py-3 px-1 border-b-2 text-sm font-medium ${tab === t.id ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                            {t.label}
                        </button>
                    ))}
                </nav>
            </div>

            {tab === 'templates' && <TemplatesTab campaignId={campaignId} />}
            {tab === 'campaigns' && <CampaignsTab campaignId={campaignId} />}
            {tab === 'overview' && <OverviewTab campaignId={campaignId} />}
        </div>
    );
}

// =====================================================================
// Templates Tab (unchanged except variable hints)
// =====================================================================

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
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Body</label>
                        <textarea value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} className="w-full border rounded-lg px-3 py-2" rows={6} required />
                        <VariableHints />
                    </div>
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

// =====================================================================
// Campaigns Tab (enhanced with audience builder + delivery logs)
// =====================================================================

function CampaignsTab({ campaignId }) {
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [channelFilter, setChannelFilter] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [showDetail, setShowDetail] = useState(null);
    const [showLogs, setShowLogs] = useState(null);
    const [form, setForm] = useState({ name: '', channel: 'sms', subject: '', body: '', audience_filters: {} });
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [page, setPage] = useState(1);
    const [meta, setMeta] = useState({});
    const [audienceCount, setAudienceCount] = useState(null);
    const [audienceLoading, setAudienceLoading] = useState(false);
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

    const fetchAudienceCount = useCallback(async () => {
        setAudienceLoading(true);
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/messaging/audience-count`, {
                params: { channel: form.channel, filters: form.audience_filters },
            });
            setAudienceCount(data);
        } catch { setAudienceCount(null); }
        setAudienceLoading(false);
    }, [campaignId, form.channel, form.audience_filters]);

    useEffect(() => {
        if (showCreate) {
            const timer = setTimeout(fetchAudienceCount, 300);
            return () => clearTimeout(timer);
        }
    }, [showCreate, fetchAudienceCount]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            await api.post(`/campaigns/${campaignId}/messaging/campaigns`, form);
            setShowCreate(false);
            setForm({ name: '', channel: 'sms', subject: '', body: '', audience_filters: {} });
            setAudienceCount(null);
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
        if (!window.confirm('Send this campaign? Messages will be dispatched to all matching voters.')) return;
        try { await api.post(`/campaigns/${campaignId}/messaging/campaigns/${id}/send`); fetch(); } catch { /* handled */ }
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Delete this campaign?')) return;
        try { await api.delete(`/campaigns/${campaignId}/messaging/campaigns/${id}`); fetch(); } catch { /* handled */ }
    };

    const updateFilter = (key, value) => {
        setForm((prev) => ({
            ...prev,
            audience_filters: { ...prev.audience_filters, [key]: value || undefined },
        }));
    };

    const columns = [
        { key: 'name', label: 'Campaign', render: (r) => {
            const Icon = channelIcons[r.channel] || EnvelopeIcon;
            return (
                <div className="flex items-center space-x-2">
                    <Icon className="h-4 w-4 text-gray-400" />
                    <button onClick={() => setShowDetail(r)} className="font-medium text-primary-600 hover:text-primary-700">{r.name}</button>
                </div>
            );
        }},
        { key: 'channel', label: 'Channel', render: (r) => <span className="text-xs uppercase text-gray-500">{r.channel}</span> },
        { key: 'status', label: 'Status', render: (r) => (
            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[r.status] || statusColors.draft}`}>{r.status}</span>
        )},
        { key: 'stats', label: 'Delivery', render: (r) => {
            if (r.total_recipients === 0 && r.sent_count === 0) return <span className="text-xs text-gray-400">—</span>;
            const pct = r.total_recipients > 0 ? Math.round((r.sent_count / r.total_recipients) * 100) : 0;
            return (
                <div className="flex items-center space-x-2">
                    <div className="w-20 bg-gray-200 rounded-full h-2">
                        <div className="bg-green-500 h-2 rounded-full" style={{ width: `${pct}%` }} />
                    </div>
                    <span className="text-xs text-gray-500">{r.sent_count}/{r.total_recipients}</span>
                </div>
            );
        }},
        { key: 'created_at', label: 'Created', render: (r) => new Date(r.created_at).toLocaleDateString() },
        { key: 'actions', label: '', render: (r) => (
            <div className="flex items-center space-x-2">
                {['sent', 'sending'].includes(r.status) && (
                    <button onClick={() => setShowLogs(r)} className="text-gray-400 hover:text-blue-600" title="View Logs"><EyeIcon className="h-4 w-4" /></button>
                )}
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
                    <button onClick={() => { setForm({ name: '', channel: 'sms', subject: '', body: '', audience_filters: {} }); setShowCreate(true); setError(null); setAudienceCount(null); }}
                        className="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700">
                        <PlusIcon className="h-4 w-4 mr-2" /> New Campaign
                    </button>
                </PermissionGate>
            </div>

            {loading ? (
                <div className="flex justify-center py-12"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" /></div>
            ) : items.length === 0 ? (
                <EmptyState icon={PaperAirplaneIcon} title="No message campaigns" description="Create a message campaign to reach your voters via SMS, WhatsApp, or email." />
            ) : (
                <DataTable columns={columns} data={items} />
            )}

            {/* Create Campaign Modal with Audience Builder */}
            <Modal open={showCreate} onClose={() => { setShowCreate(false); setAudienceCount(null); }} title="New Message Campaign">
                <form onSubmit={handleSubmit} className="space-y-4">
                    {error && <div className="bg-red-50 text-red-700 p-3 rounded-lg text-sm">{error}</div>}

                    <div><label className="block text-sm font-medium text-gray-700 mb-1">Campaign Name</label>
                        <input type="text" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} className="w-full border rounded-lg px-3 py-2" required /></div>

                    <div><label className="block text-sm font-medium text-gray-700 mb-1">Channel</label>
                        <select value={form.channel} onChange={(e) => setForm({ ...form, channel: e.target.value })} className="w-full border rounded-lg px-3 py-2">
                            {CHANNELS.map((c) => <option key={c} value={c}>{c.toUpperCase()}</option>)}</select></div>

                    {form.channel === 'email' && (
                        <div><label className="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                            <input type="text" value={form.subject} onChange={(e) => setForm({ ...form, subject: e.target.value })} className="w-full border rounded-lg px-3 py-2" /></div>
                    )}

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Message Body</label>
                        <textarea value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} className="w-full border rounded-lg px-3 py-2" rows={5} required
                            placeholder="Hello {{first_name}}, we're reaching out to voters in {{ward}}..." />
                        <VariableHints />
                    </div>

                    {/* Audience Builder */}
                    <div className="border rounded-lg p-4 bg-gray-50 space-y-3">
                        <div className="flex items-center justify-between">
                            <h4 className="text-sm font-semibold text-gray-800">Audience Targeting</h4>
                            {audienceLoading ? (
                                <span className="text-xs text-gray-400">Counting...</span>
                            ) : audienceCount !== null ? (
                                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800">
                                    <UsersIcon className="h-3 w-3 mr-1" />{audienceCount.total?.toLocaleString()} recipients
                                </span>
                            ) : null}
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="block text-xs text-gray-500 mb-1">County</label>
                                <input type="text" value={form.audience_filters.county || ''} onChange={(e) => updateFilter('county', e.target.value)}
                                    className="w-full border rounded px-2 py-1.5 text-sm" placeholder="e.g. Nairobi" />
                            </div>
                            <div>
                                <label className="block text-xs text-gray-500 mb-1">Constituency</label>
                                <input type="text" value={form.audience_filters.constituency || ''} onChange={(e) => updateFilter('constituency', e.target.value)}
                                    className="w-full border rounded px-2 py-1.5 text-sm" placeholder="e.g. Westlands" />
                            </div>
                            <div>
                                <label className="block text-xs text-gray-500 mb-1">Ward</label>
                                <input type="text" value={form.audience_filters.ward || ''} onChange={(e) => updateFilter('ward', e.target.value)}
                                    className="w-full border rounded px-2 py-1.5 text-sm" placeholder="e.g. Parklands" />
                            </div>
                            <div>
                                <label className="block text-xs text-gray-500 mb-1">Supporter Status</label>
                                <select value={form.audience_filters.supporter_status || ''} onChange={(e) => updateFilter('supporter_status', e.target.value)}
                                    className="w-full border rounded px-2 py-1.5 text-sm">
                                    <option value="">All</option>
                                    <option value="supporter">Supporter</option>
                                    <option value="leaning">Leaning</option>
                                    <option value="undecided">Undecided</option>
                                    <option value="opposed">Opposed</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs text-gray-500 mb-1">Gender</label>
                                <select value={form.audience_filters.gender || ''} onChange={(e) => updateFilter('gender', e.target.value)}
                                    className="w-full border rounded px-2 py-1.5 text-sm">
                                    <option value="">All</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs text-gray-500 mb-1">Source</label>
                                <select value={form.audience_filters.source || ''} onChange={(e) => updateFilter('source', e.target.value)}
                                    className="w-full border rounded px-2 py-1.5 text-sm">
                                    <option value="">All</option>
                                    <option value="online">Online</option>
                                    <option value="field">Field</option>
                                    <option value="import">Import</option>
                                    <option value="manual">Manual</option>
                                </select>
                            </div>
                        </div>
                        <button type="button" onClick={fetchAudienceCount} className="text-xs text-primary-600 hover:text-primary-700 font-medium">
                            Refresh count
                        </button>
                    </div>

                    <div className="flex justify-end space-x-3 pt-4">
                        <button type="button" onClick={() => { setShowCreate(false); setAudienceCount(null); }} className="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button type="submit" disabled={submitting} className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700 disabled:opacity-50">
                            {submitting ? 'Creating...' : 'Create Campaign'}</button>
                    </div>
                </form>
            </Modal>

            {/* Campaign Detail Modal */}
            {showDetail && (
                <CampaignDetailModal campaign={showDetail} campaignId={campaignId} onClose={() => setShowDetail(null)} onViewLogs={() => { setShowLogs(showDetail); setShowDetail(null); }} />
            )}

            {/* Delivery Logs Modal */}
            {showLogs && (
                <DeliveryLogsModal campaign={showLogs} campaignId={campaignId} onClose={() => setShowLogs(null)} />
            )}
        </div>
    );
}

// =====================================================================
// Campaign Detail Modal
// =====================================================================

function CampaignDetailModal({ campaign, campaignId, onClose, onViewLogs }) {
    const sentPct = campaign.total_recipients > 0 ? Math.round((campaign.sent_count / campaign.total_recipients) * 100) : 0;
    const failedPct = campaign.total_recipients > 0 ? Math.round((campaign.failed_count / campaign.total_recipients) * 100) : 0;
    const Icon = channelIcons[campaign.channel] || EnvelopeIcon;
    const filters = campaign.audience_filters || {};
    const hasFilters = Object.values(filters).some((v) => v && (Array.isArray(v) ? v.length > 0 : true));

    return (
        <Modal open onClose={onClose} title="Campaign Details">
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                        <Icon className="h-5 w-5 text-gray-400" />
                        <h3 className="font-semibold text-gray-900">{campaign.name}</h3>
                    </div>
                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusColors[campaign.status] || statusColors.draft}`}>
                        {campaign.status}
                    </span>
                </div>

                {/* Delivery Progress */}
                {campaign.total_recipients > 0 && (
                    <div className="bg-gray-50 rounded-lg p-4 space-y-2">
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-600">Delivery Progress</span>
                            <span className="font-medium">{sentPct}%</span>
                        </div>
                        <div className="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div className="flex h-full">
                                <div className="bg-green-500 h-full" style={{ width: `${sentPct}%` }} />
                                <div className="bg-red-400 h-full" style={{ width: `${failedPct}%` }} />
                            </div>
                        </div>
                        <div className="flex justify-between text-xs text-gray-500">
                            <span className="text-green-600">{campaign.sent_count} sent</span>
                            <span className="text-red-500">{campaign.failed_count} failed</span>
                            <span>{campaign.total_recipients} total</span>
                        </div>
                    </div>
                )}

                {/* Message Content */}
                <div>
                    <h4 className="text-xs font-medium text-gray-500 uppercase mb-1">Message</h4>
                    {campaign.subject && <div className="text-sm font-medium text-gray-800 mb-1">Subject: {campaign.subject}</div>}
                    <div className="text-sm text-gray-600 bg-white border rounded-lg p-3 whitespace-pre-wrap">{campaign.body}</div>
                </div>

                {/* Audience Filters */}
                {hasFilters && (
                    <div>
                        <h4 className="text-xs font-medium text-gray-500 uppercase mb-1">Audience Filters</h4>
                        <div className="flex flex-wrap gap-2">
                            {Object.entries(filters).map(([key, val]) => {
                                if (!val || (Array.isArray(val) && val.length === 0)) return null;
                                return (
                                    <span key={key} className="inline-flex items-center px-2 py-1 rounded bg-blue-50 text-blue-700 text-xs">
                                        {key}: {Array.isArray(val) ? val.join(', ') : val}
                                    </span>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* Meta */}
                <div className="flex items-center justify-between text-xs text-gray-400 border-t pt-3">
                    <span>Created: {new Date(campaign.created_at).toLocaleString()}</span>
                    {campaign.sent_at && <span>Sent: {new Date(campaign.sent_at).toLocaleString()}</span>}
                </div>

                <div className="flex justify-end space-x-3">
                    {['sent', 'sending'].includes(campaign.status) && (
                        <button onClick={onViewLogs} className="inline-flex items-center px-4 py-2 bg-blue-50 text-blue-700 rounded-lg text-sm font-medium hover:bg-blue-100">
                            <EyeIcon className="h-4 w-4 mr-2" /> View Delivery Logs
                        </button>
                    )}
                    <button onClick={onClose} className="px-4 py-2 border rounded-lg text-sm">Close</button>
                </div>
            </div>
        </Modal>
    );
}

// =====================================================================
// Delivery Logs Modal
// =====================================================================

function DeliveryLogsModal({ campaign, campaignId, onClose }) {
    const [logs, setLogs] = useState([]);
    const [stats, setStats] = useState({});
    const [loading, setLoading] = useState(true);
    const [statusFilter, setStatusFilter] = useState('');
    const [page, setPage] = useState(1);
    const [meta, setMeta] = useState({});

    const fetch = useCallback(async () => {
        setLoading(true);
        try {
            const params = { page };
            if (statusFilter) params.status = statusFilter;
            const { data } = await api.get(`/campaigns/${campaignId}/messaging/campaigns/${campaign.id}/logs`, { params });
            setLogs(data.logs?.data || []);
            setMeta(data.logs?.meta || data.logs || {});
            setStats(data.stats || {});
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, campaign.id, statusFilter, page]);

    useEffect(() => { fetch(); }, [fetch]);

    const logStatusColors = {
        pending: 'text-gray-500',
        sent: 'text-blue-600',
        delivered: 'text-green-600',
        failed: 'text-red-600',
    };

    return (
        <Modal open onClose={onClose} title={`Delivery Logs — ${campaign.name}`}>
            <div className="space-y-4">
                {/* Stats summary */}
                <div className="grid grid-cols-4 gap-3">
                    <div className="bg-gray-50 rounded-lg p-3 text-center">
                        <div className="text-lg font-bold text-gray-900">{stats.total || 0}</div>
                        <div className="text-xs text-gray-500">Total</div>
                    </div>
                    <div className="bg-green-50 rounded-lg p-3 text-center">
                        <div className="text-lg font-bold text-green-700">{stats.sent || 0}</div>
                        <div className="text-xs text-gray-500">Sent</div>
                    </div>
                    <div className="bg-blue-50 rounded-lg p-3 text-center">
                        <div className="text-lg font-bold text-blue-700">{stats.delivered || 0}</div>
                        <div className="text-xs text-gray-500">Delivered</div>
                    </div>
                    <div className="bg-red-50 rounded-lg p-3 text-center">
                        <div className="text-lg font-bold text-red-700">{stats.failed || 0}</div>
                        <div className="text-xs text-gray-500">Failed</div>
                    </div>
                </div>

                {/* Filter */}
                <select value={statusFilter} onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }} className="border rounded-lg px-3 py-2 text-sm">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="sent">Sent</option>
                    <option value="delivered">Delivered</option>
                    <option value="failed">Failed</option>
                </select>

                {/* Logs table */}
                {loading ? (
                    <div className="flex justify-center py-8"><div className="animate-spin rounded-full h-6 w-6 border-b-2 border-primary-600" /></div>
                ) : logs.length === 0 ? (
                    <div className="text-center text-sm text-gray-500 py-8">No delivery logs yet.</div>
                ) : (
                    <div className="max-h-64 overflow-y-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 sticky top-0">
                                <tr>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">Voter</th>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">Recipient</th>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">Status</th>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">Error</th>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500">Time</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {logs.map((log) => (
                                    <tr key={log.id}>
                                        <td className="px-3 py-2 text-gray-800">{log.voter?.name || '—'}</td>
                                        <td className="px-3 py-2 text-gray-500 font-mono text-xs">{log.recipient}</td>
                                        <td className="px-3 py-2">
                                            <span className={`font-medium capitalize ${logStatusColors[log.status] || 'text-gray-500'}`}>{log.status}</span>
                                        </td>
                                        <td className="px-3 py-2 text-red-500 text-xs max-w-[150px] truncate">{log.error || ''}</td>
                                        <td className="px-3 py-2 text-gray-400 text-xs">{log.sent_at ? new Date(log.sent_at).toLocaleString() : '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Pagination */}
                {(meta.last_page || 0) > 1 && (
                    <div className="flex justify-center gap-2">
                        <button disabled={page <= 1} onClick={() => setPage(page - 1)} className="px-3 py-1 border rounded text-sm disabled:opacity-50">Prev</button>
                        <span className="px-3 py-1 text-sm text-gray-500">Page {page} of {meta.last_page}</span>
                        <button disabled={page >= meta.last_page} onClick={() => setPage(page + 1)} className="px-3 py-1 border rounded text-sm disabled:opacity-50">Next</button>
                    </div>
                )}

                <div className="flex justify-end pt-2">
                    <button onClick={onClose} className="px-4 py-2 border rounded-lg text-sm">Close</button>
                </div>
            </div>
        </Modal>
    );
}

// =====================================================================
// Overview Tab (stats dashboard)
// =====================================================================

function OverviewTab({ campaignId }) {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const load = async () => {
            try {
                const { data } = await api.get(`/campaigns/${campaignId}/messaging/stats`);
                setStats(data.stats);
            } catch { /* handled */ }
            setLoading(false);
        };
        load();
    }, [campaignId]);

    if (loading) {
        return <div className="flex justify-center py-12"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" /></div>;
    }

    if (!stats) {
        return <EmptyState icon={ChartBarIcon} title="No data" description="Send some message campaigns to see stats here." />;
    }

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <StatsCard title="Campaigns" value={stats.total_campaigns} icon={PaperAirplaneIcon} color="primary" />
                <StatsCard title="Sent Campaigns" value={stats.sent_campaigns} icon={CheckCircleIcon} color="green" />
                <StatsCard title="Messages Sent" value={stats.total_sent} icon={EnvelopeIcon} color="blue" />
                <StatsCard title="Delivery Rate" value={`${stats.delivery_rate}%`} icon={ChartBarIcon} color="accent" />
            </div>

            {stats.total_failed > 0 && (
                <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div className="text-sm text-red-700">
                        <span className="font-medium">{stats.total_failed}</span> messages failed to deliver across all campaigns.
                    </div>
                </div>
            )}

            {/* Channel breakdown */}
            {stats.by_channel?.length > 0 && (
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 className="font-heading font-semibold text-gray-900 mb-4">By Channel</h3>
                    <div className="space-y-3">
                        {stats.by_channel.map((ch) => {
                            const Icon = channelIcons[ch.channel] || EnvelopeIcon;
                            const total = (parseInt(ch.sent) || 0) + (parseInt(ch.failed) || 0);
                            const sentPct = total > 0 ? Math.round(((parseInt(ch.sent) || 0) / total) * 100) : 0;
                            return (
                                <div key={ch.channel} className="flex items-center gap-4">
                                    <div className="flex items-center gap-2 w-24">
                                        <Icon className="h-4 w-4 text-gray-400" />
                                        <span className="text-sm font-medium text-gray-700 uppercase">{ch.channel}</span>
                                    </div>
                                    <div className="flex-1 bg-gray-200 rounded-full h-2">
                                        <div className="bg-primary-500 h-2 rounded-full" style={{ width: `${sentPct}%` }} />
                                    </div>
                                    <span className="text-sm text-gray-500 w-32 text-right">{ch.campaigns} campaigns &middot; {ch.sent} sent</span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}

// =====================================================================
// Variable Hints
// =====================================================================

function VariableHints() {
    const [show, setShow] = useState(false);
    return (
        <div className="mt-1">
            <button type="button" onClick={() => setShow(!show)} className="text-xs text-primary-600 hover:text-primary-700">
                {show ? 'Hide' : 'Show'} available variables
            </button>
            {show && (
                <div className="mt-1 flex flex-wrap gap-1.5">
                    {VARIABLE_HINTS.map((v) => (
                        <span key={v.var} className="inline-flex items-center px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs font-mono" title={v.desc}>
                            {v.var}
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}
