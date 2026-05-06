import React, { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    UserGroupIcon,
    MagnifyingGlassIcon,
    DocumentMagnifyingGlassIcon,
    ChevronLeftIcon,
    ShieldExclamationIcon,
    ExclamationTriangleIcon,
    ArrowPathIcon,
    ChartBarIcon,
} from '@heroicons/react/24/outline';
import api from '../../lib/api';
import PermissionGate from '../components/PermissionGate';
import useCampaignPermissions from '../hooks/useCampaignPermissions';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';
import StatsCard from '../components/StatsCard';

const THREAT_LEVELS = ['low', 'medium', 'high', 'critical'];
const CLEARANCE_LEVELS = ['public', 'internal', 'confidential', 'restricted'];

const emptyOpponentForm = {
    name: '', party: '', position: '', county: '', constituency: '', ward: '',
    threat_level: 'medium', bio: '', strengths: '', weaknesses: '',
    photo_url: '', social_facebook: '', social_twitter: '',
};

const emptyResearchForm = {
    title: '', content: '', clearance: 'internal', source: '', date_observed: '',
};

const threatColors = {
    low: 'bg-green-50 text-green-700',
    medium: 'bg-yellow-50 text-yellow-700',
    high: 'bg-orange-50 text-orange-700',
    critical: 'bg-red-50 text-red-700',
};

const clearanceColors = {
    public: 'bg-green-50 text-green-700',
    internal: 'bg-blue-50 text-blue-700',
    confidential: 'bg-yellow-50 text-yellow-700',
    restricted: 'bg-red-50 text-red-700',
};

const sentimentColors = {
    positive: 'bg-green-50 text-green-700 border-green-200',
    neutral: 'bg-gray-50 text-gray-700 border-gray-200',
    negative: 'bg-red-50 text-red-700 border-red-200',
};

const sentimentBadgeColors = {
    positive: 'bg-green-100 text-green-700',
    neutral: 'bg-gray-100 text-gray-600',
    negative: 'bg-red-100 text-red-700',
};

const sentimentBarColors = {
    positive: 'bg-green-500',
    neutral: 'bg-gray-400',
    negative: 'bg-red-500',
};

export default function OpponentsPage() {
    const { campaignId } = useParams();

    // Opponent list state
    const [opponents, setOpponents] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [filterThreat, setFilterThreat] = useState('');
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState(emptyOpponentForm);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);

    // Research view state
    const [selectedOpponent, setSelectedOpponent] = useState(null);
    const [research, setResearch] = useState([]);
    const [researchLoading, setResearchLoading] = useState(false);
    const [showResearchForm, setShowResearchForm] = useState(false);
    const [editingResearch, setEditingResearch] = useState(null);
    const [researchForm, setResearchForm] = useState(emptyResearchForm);
    const [researchSubmitting, setResearchSubmitting] = useState(false);
    const [researchError, setResearchError] = useState(null);
    const { can } = useCampaignPermissions();

    // Sentiment state
    const [sentiment, setSentiment] = useState(null);
    const [sentimentLoading, setSentimentLoading] = useState(false);
    const [sentimentTab, setSentimentTab] = useState('overview');
    const [reanalyzing, setReanalyzing] = useState(false);

    const fetchOpponents = useCallback(async () => {
        setLoading(true);
        try {
            const params = {};
            if (search) params.search = search;
            if (filterThreat) params.threat_level = filterThreat;
            const { data } = await api.get(`/campaigns/${campaignId}/opponents`, { params });
            setOpponents(data.data || []);
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, search, filterThreat]);

    useEffect(() => { fetchOpponents(); }, [fetchOpponents]);

    const fetchResearch = async (opponent) => {
        setResearchLoading(true);
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/opponents/${opponent.id}/research`);
            setResearch(data.data || []);
        } catch { /* handled */ }
        setResearchLoading(false);
    };

    const fetchSentiment = async (opponent) => {
        setSentimentLoading(true);
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/opponents/${opponent.id}/sentiment`);
            setSentiment(data);
        } catch { /* handled */ }
        setSentimentLoading(false);
    };

    const openOpponentDetail = (opponent) => {
        setSelectedOpponent(opponent);
        fetchResearch(opponent);
        fetchSentiment(opponent);
    };

    const handleReanalyze = async (researchId) => {
        setReanalyzing(true);
        try {
            await api.post(`/campaigns/${campaignId}/opponents/${selectedOpponent.id}/research/${researchId}/reanalyze`);
            fetchResearch(selectedOpponent);
            fetchSentiment(selectedOpponent);
        } catch (err) { alert(err.response?.data?.message || 'Failed to re-analyze'); }
        setReanalyzing(false);
    };

    const handleBulkReanalyze = async () => {
        if (!confirm('Re-analyze sentiment for all research notes?')) return;
        setReanalyzing(true);
        try {
            const { data } = await api.post(`/campaigns/${campaignId}/opponents/${selectedOpponent.id}/sentiment/reanalyze-all`);
            alert(data.message);
            fetchResearch(selectedOpponent);
            fetchSentiment(selectedOpponent);
        } catch (err) { alert(err.response?.data?.message || 'Failed to re-analyze'); }
        setReanalyzing(false);
    };

    // --- Opponent CRUD handlers ---
    const openCreate = () => { setEditing(null); setForm(emptyOpponentForm); setError(null); setShowForm(true); };

    const openEdit = (item) => {
        setEditing(item);
        setForm({
            name: item.name || '', party: item.party || '', position: item.position || '',
            county: item.county || '', constituency: item.constituency || '', ward: item.ward || '',
            threat_level: item.threat_level || 'medium', bio: item.bio || '',
            strengths: item.strengths || '', weaknesses: item.weaknesses || '',
            photo_url: item.photo_url || '', social_facebook: item.social_facebook || '',
            social_twitter: item.social_twitter || '',
        });
        setError(null); setShowForm(true);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true); setError(null);
        try {
            if (editing) {
                const { data } = await api.put(`/campaigns/${campaignId}/opponents/${editing.id}`, form);
                if (selectedOpponent && selectedOpponent.id === editing.id) {
                    setSelectedOpponent(data.data || data.opponent || { ...selectedOpponent, ...form });
                }
            } else {
                await api.post(`/campaigns/${campaignId}/opponents`, form);
            }
            setShowForm(false); fetchOpponents();
        } catch (err) {
            setError(err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(', ') : err.response?.data?.message || 'Failed to save');
        }
        setSubmitting(false);
    };

    const handleDelete = async (id) => {
        if (!confirm('Delete this opponent and all their research?')) return;
        try { await api.delete(`/campaigns/${campaignId}/opponents/${id}`); fetchOpponents(); }
        catch (err) { alert(err.response?.data?.message || 'Failed to delete'); }
    };

    // --- Research CRUD handlers ---
    const openResearchCreate = () => {
        setEditingResearch(null); setResearchForm(emptyResearchForm); setResearchError(null); setShowResearchForm(true);
    };

    const openResearchEdit = (item) => {
        setEditingResearch(item);
        setResearchForm({
            title: item.title || '', content: item.content || '',
            clearance: item.clearance || 'internal', source: item.source || '',
            date_observed: item.date_observed ? item.date_observed.split('T')[0] : '',
        });
        setResearchError(null); setShowResearchForm(true);
    };

    const handleResearchSubmit = async (e) => {
        e.preventDefault();
        setResearchSubmitting(true); setResearchError(null);
        try {
            if (editingResearch) {
                await api.put(`/campaigns/${campaignId}/opponents/${selectedOpponent.id}/research/${editingResearch.id}`, researchForm);
            } else {
                await api.post(`/campaigns/${campaignId}/opponents/${selectedOpponent.id}/research`, researchForm);
            }
            setShowResearchForm(false); fetchResearch(selectedOpponent);
        } catch (err) {
            setResearchError(err.response?.data?.errors ? Object.values(err.response.data.errors).flat().join(', ') : err.response?.data?.message || 'Failed to save');
        }
        setResearchSubmitting(false);
    };

    const handleResearchDelete = async (id) => {
        if (!confirm('Delete this research note?')) return;
        try {
            await api.delete(`/campaigns/${campaignId}/opponents/${selectedOpponent.id}/research/${id}`);
            fetchResearch(selectedOpponent);
        } catch (err) { alert(err.response?.data?.message || 'Failed to delete'); }
    };

    // --- Stats ---
    const stats = {
        total: opponents.length,
        critical: opponents.filter(o => o.threat_level === 'critical').length,
        high: opponents.filter(o => o.threat_level === 'high').length,
        researchNotes: opponents.reduce((sum, o) => sum + (o.research_count || 0), 0),
    };

    // --- Opponent detail / research view ---
    if (selectedOpponent) {
        return (
            <div className="space-y-4">
                <button
                    onClick={() => setSelectedOpponent(null)}
                    className="flex items-center space-x-1 text-sm text-gray-500 hover:text-gray-700 transition-colors"
                >
                    <ChevronLeftIcon className="h-4 w-4" />
                    <span>Back to opponents</span>
                </button>

                {/* Opponent header */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div className="flex items-start justify-between">
                        <div className="flex items-center space-x-4">
                            {selectedOpponent.photo_url ? (
                                <img src={selectedOpponent.photo_url} alt={selectedOpponent.name} className="h-16 w-16 rounded-full object-cover" />
                            ) : (
                                <div className="h-16 w-16 rounded-full bg-gray-200 flex items-center justify-center">
                                    <UserGroupIcon className="h-8 w-8 text-gray-400" />
                                </div>
                            )}
                            <div>
                                <h2 className="text-xl font-heading font-bold text-gray-900">{selectedOpponent.name}</h2>
                                <div className="flex flex-wrap gap-2 mt-1">
                                    {selectedOpponent.party && (
                                        <span className="px-2.5 py-0.5 bg-gray-100 text-gray-600 text-xs font-medium rounded-full">{selectedOpponent.party}</span>
                                    )}
                                    {selectedOpponent.position && (
                                        <span className="px-2.5 py-0.5 bg-blue-50 text-blue-600 text-xs font-medium rounded-full">{selectedOpponent.position}</span>
                                    )}
                                    <span className={`px-2.5 py-0.5 text-xs font-medium rounded-full capitalize ${threatColors[selectedOpponent.threat_level]}`}>
                                        {selectedOpponent.threat_level} threat
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div className="flex space-x-2">
                            {can('opponents.edit') && <button onClick={() => openEdit(selectedOpponent)} className="btn-secondary !py-2 !px-3 text-sm">
                                <PencilIcon className="h-4 w-4 mr-1" /> Edit
                            </button>}
                        </div>
                    </div>

                    {/* Bio / strengths / weaknesses */}
                    {(selectedOpponent.bio || selectedOpponent.strengths || selectedOpponent.weaknesses) && (
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5 pt-5 border-t border-gray-100">
                            {selectedOpponent.bio && (
                                <div>
                                    <h4 className="text-xs font-semibold text-gray-500 uppercase mb-1">Bio</h4>
                                    <p className="text-sm text-gray-700">{selectedOpponent.bio}</p>
                                </div>
                            )}
                            {selectedOpponent.strengths && (
                                <div>
                                    <h4 className="text-xs font-semibold text-green-600 uppercase mb-1">Strengths</h4>
                                    <p className="text-sm text-gray-700">{selectedOpponent.strengths}</p>
                                </div>
                            )}
                            {selectedOpponent.weaknesses && (
                                <div>
                                    <h4 className="text-xs font-semibold text-red-600 uppercase mb-1">Weaknesses</h4>
                                    <p className="text-sm text-gray-700">{selectedOpponent.weaknesses}</p>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Location + social */}
                    {(selectedOpponent.county || selectedOpponent.constituency || selectedOpponent.ward || selectedOpponent.social_facebook || selectedOpponent.social_twitter) && (
                        <div className="flex flex-wrap gap-4 mt-4 pt-4 border-t border-gray-100 text-sm text-gray-500">
                            {selectedOpponent.county && <span>County: {selectedOpponent.county}</span>}
                            {selectedOpponent.constituency && <span>Constituency: {selectedOpponent.constituency}</span>}
                            {selectedOpponent.ward && <span>Ward: {selectedOpponent.ward}</span>}
                            {selectedOpponent.social_facebook && (
                                <a href={selectedOpponent.social_facebook} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline">Facebook</a>
                            )}
                            {selectedOpponent.social_twitter && (
                                <a href={selectedOpponent.social_twitter} target="_blank" rel="noopener noreferrer" className="text-blue-500 hover:underline">Twitter/X</a>
                            )}
                        </div>
                    )}
                </div>

                {/* Sentiment Analysis Panel */}
                <SentimentPanel
                    sentiment={sentiment}
                    sentimentLoading={sentimentLoading}
                    sentimentTab={sentimentTab}
                    setSentimentTab={setSentimentTab}
                    reanalyzing={reanalyzing}
                    onBulkReanalyze={handleBulkReanalyze}
                    can={can}
                />

                {/* Research section */}
                <div className="flex items-center justify-between">
                    <h3 className="text-lg font-heading font-semibold text-gray-900">
                        Research Notes <span className="text-sm font-normal text-gray-400">({research.length})</span>
                    </h3>
                    <PermissionGate permission="opponents.add-research">
                        <button onClick={openResearchCreate} className="btn-primary !py-2 !px-4 text-sm">
                            <PlusIcon className="h-4 w-4 mr-1" /> Add Research
                        </button>
                    </PermissionGate>
                </div>

                {researchLoading ? (
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto" />
                    </div>
                ) : research.length === 0 ? (
                    <EmptyState
                        icon={DocumentMagnifyingGlassIcon}
                        title="No research yet"
                        description="Add research notes, intel, and observations about this opponent."
                        action={
                            <PermissionGate permission="opponents.add-research">
                                <button onClick={openResearchCreate} className="btn-primary !py-2 !px-4 text-sm">
                                    <PlusIcon className="h-4 w-4 mr-1" /> Add Research
                                </button>
                            </PermissionGate>
                        }
                    />
                ) : (
                    <div className="space-y-3">
                        {research.map((r) => (
                            <div key={r.id} className={`bg-white rounded-xl shadow-sm border p-5 ${r.sentiment_label ? sentimentColors[r.sentiment_label]?.split(' ').find(c => c.startsWith('border-')) || 'border-gray-100' : 'border-gray-100'}`}>
                                <div className="flex items-start justify-between">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center space-x-2 mb-1">
                                            <h4 className="font-medium text-gray-900">{r.title}</h4>
                                            <span className={`px-2 py-0.5 text-xs font-medium rounded-full capitalize ${clearanceColors[r.clearance]}`}>
                                                {r.clearance}
                                            </span>
                                            {r.sentiment_label && (
                                                <span className={`px-2 py-0.5 text-xs font-medium rounded-full capitalize ${sentimentBadgeColors[r.sentiment_label]}`}>
                                                    {r.sentiment_label} {r.sentiment_score != null ? `(${r.sentiment_score > 0 ? '+' : ''}${r.sentiment_score})` : ''}
                                                </span>
                                            )}
                                        </div>
                                        <p className="text-sm text-gray-600 whitespace-pre-line">{r.content}</p>
                                        <div className="flex items-center space-x-3 mt-3 text-xs text-gray-400">
                                            {r.author?.name && <span>By {r.author.name}</span>}
                                            {r.source && <span>Source: {r.source}</span>}
                                            {r.date_observed && <span>Observed: {new Date(r.date_observed).toLocaleDateString('en-KE')}</span>}
                                            <span>{new Date(r.created_at).toLocaleDateString('en-KE', { day: 'numeric', month: 'short', year: 'numeric' })}</span>
                                        </div>
                                    </div>
                                    <div className="flex items-center space-x-1 ml-3 flex-shrink-0">
                                        {can('opponents.edit-research') && (
                                            <button
                                                onClick={() => handleReanalyze(r.id)}
                                                disabled={reanalyzing}
                                                className="p-1.5 text-gray-400 hover:text-blue-600 rounded"
                                                title="Re-analyze sentiment"
                                            >
                                                <ArrowPathIcon className={`h-4 w-4 ${reanalyzing ? 'animate-spin' : ''}`} />
                                            </button>
                                        )}
                                        {can('opponents.edit-research') && <button onClick={() => openResearchEdit(r)} className="p-1.5 text-gray-400 hover:text-primary-600 rounded">
                                            <PencilIcon className="h-4 w-4" />
                                        </button>}
                                        {can('opponents.delete-research') && <button onClick={() => handleResearchDelete(r.id)} className="p-1.5 text-gray-400 hover:text-red-500 rounded">
                                            <TrashIcon className="h-4 w-4" />
                                        </button>}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Research form modal */}
                <Modal open={showResearchForm} onClose={() => setShowResearchForm(false)} title={editingResearch ? 'Edit Research Note' : 'Add Research Note'} size="lg">
                    <form onSubmit={handleResearchSubmit} className="space-y-4">
                        {researchError && <p className="text-sm text-red-600 bg-red-50 p-2 rounded">{researchError}</p>}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                            <input type="text" required value={researchForm.title} onChange={e => setResearchForm({ ...researchForm, title: e.target.value })}
                                className="input w-full" placeholder="e.g., Rally attendance analysis" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Content *</label>
                            <textarea rows={5} required value={researchForm.content} onChange={e => setResearchForm({ ...researchForm, content: e.target.value })}
                                className="input w-full" placeholder="Research findings, observations, intelligence..." />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Clearance Level</label>
                                <select value={researchForm.clearance} onChange={e => setResearchForm({ ...researchForm, clearance: e.target.value })} className="input w-full">
                                    {CLEARANCE_LEVELS.map(c => <option key={c} value={c}>{c.charAt(0).toUpperCase() + c.slice(1)}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Date Observed</label>
                                <input type="date" value={researchForm.date_observed} onChange={e => setResearchForm({ ...researchForm, date_observed: e.target.value })}
                                    className="input w-full" />
                            </div>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Source</label>
                            <input type="text" value={researchForm.source} onChange={e => setResearchForm({ ...researchForm, source: e.target.value })}
                                className="input w-full" placeholder="e.g., Field agent report, media article" />
                        </div>
                        <div className="flex justify-end space-x-3 pt-2">
                            <button type="button" onClick={() => setShowResearchForm(false)} className="btn-secondary !py-2 !px-4 text-sm">Cancel</button>
                            <button type="submit" disabled={researchSubmitting} className="btn-primary !py-2 !px-4 text-sm">
                                {researchSubmitting ? 'Saving...' : editingResearch ? 'Update' : 'Add Note'}
                            </button>
                        </div>
                    </form>
                </Modal>

                {/* Opponent edit modal (reused) */}
                <Modal open={showForm} onClose={() => setShowForm(false)} title="Edit Opponent" size="lg">
                    <OpponentForm form={form} setForm={setForm} error={error} submitting={submitting} onSubmit={handleSubmit} onCancel={() => setShowForm(false)} editing={editing} />
                </Modal>
            </div>
        );
    }

    // --- Main opponents list view ---
    const columns = [
        {
            key: 'name', label: 'Opponent', render: (r) => (
                <div className="flex items-center space-x-3">
                    {r.photo_url ? (
                        <img src={r.photo_url} alt={r.name} className="h-8 w-8 rounded-full object-cover" />
                    ) : (
                        <div className="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center">
                            <UserGroupIcon className="h-4 w-4 text-gray-400" />
                        </div>
                    )}
                    <span className="font-medium text-gray-900">{r.name}</span>
                </div>
            ),
        },
        { key: 'party', label: 'Party', render: (r) => <span className="text-gray-500 text-sm">{r.party || '—'}</span> },
        { key: 'position', label: 'Position', render: (r) => <span className="text-gray-500 text-sm">{r.position || '—'}</span> },
        {
            key: 'threat_level', label: 'Threat', render: (r) => (
                <span className={`px-2 py-0.5 text-xs font-medium rounded-full capitalize ${threatColors[r.threat_level]}`}>{r.threat_level}</span>
            ),
        },
        { key: 'research_count', label: 'Research', render: (r) => <span className="text-gray-500 text-sm">{r.research_count || 0} notes</span> },
        {
            key: 'actions', label: '', render: (r) => (
                <div className="flex items-center space-x-1">
                    <button onClick={(e) => { e.stopPropagation(); openOpponentDetail(r); }} className="p-1.5 text-gray-400 hover:text-blue-600 rounded" title="View research">
                        <DocumentMagnifyingGlassIcon className="h-4 w-4" />
                    </button>
                    {can('opponents.edit') && <button onClick={(e) => { e.stopPropagation(); openEdit(r); }} className="p-1.5 text-gray-400 hover:text-primary-600 rounded">
                        <PencilIcon className="h-4 w-4" />
                    </button>}
                    {can('opponents.delete') && <button onClick={(e) => { e.stopPropagation(); handleDelete(r.id); }} className="p-1.5 text-gray-400 hover:text-red-500 rounded">
                        <TrashIcon className="h-4 w-4" />
                    </button>}
                </div>
            ),
        },
    ];

    return (
        <div className="space-y-4">
            {/* Stats */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <StatsCard title="Total Opponents" value={stats.total} icon={UserGroupIcon} color="primary" />
                <StatsCard title="Critical Threat" value={stats.critical} icon={ShieldExclamationIcon} color="red" />
                <StatsCard title="High Threat" value={stats.high} icon={ExclamationTriangleIcon} color="accent" />
                <StatsCard title="Research Notes" value={stats.researchNotes} icon={DocumentMagnifyingGlassIcon} color="blue" />
            </div>

            {/* Filters */}
            <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <div className="relative flex-1">
                    <MagnifyingGlassIcon className="h-4 w-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        type="text"
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        placeholder="Search opponents..."
                        className="input w-full pl-9"
                    />
                </div>
                <select value={filterThreat} onChange={e => setFilterThreat(e.target.value)} className="input w-auto">
                    <option value="">All threat levels</option>
                    {THREAT_LEVELS.map(t => <option key={t} value={t}>{t.charAt(0).toUpperCase() + t.slice(1)}</option>)}
                </select>
                <PermissionGate permission="opponents.create">
                    <button onClick={openCreate} className="btn-primary !py-2 !px-4 text-sm whitespace-nowrap">
                        <PlusIcon className="h-4 w-4 mr-1" /> Add Opponent
                    </button>
                </PermissionGate>
            </div>

            {/* Table */}
            {opponents.length === 0 && !loading ? (
                <EmptyState
                    icon={UserGroupIcon}
                    title="No opponents tracked"
                    description="Start building your opponent intelligence by adding competitors."
                    action={
                        <PermissionGate permission="opponents.create">
                            <button onClick={openCreate} className="btn-primary !py-2 !px-4 text-sm">
                                <PlusIcon className="h-4 w-4 mr-1" /> Add Opponent
                            </button>
                        </PermissionGate>
                    }
                />
            ) : (
                <DataTable columns={columns} data={opponents} loading={loading} onRowClick={openOpponentDetail} />
            )}

            {/* Opponent form modal */}
            <Modal open={showForm} onClose={() => setShowForm(false)} title={editing ? 'Edit Opponent' : 'Add Opponent'} size="lg">
                <OpponentForm form={form} setForm={setForm} error={error} submitting={submitting} onSubmit={handleSubmit} onCancel={() => setShowForm(false)} editing={editing} />
            </Modal>
        </div>
    );
}

function OpponentForm({ form, setForm, error, submitting, onSubmit, onCancel, editing }) {
    return (
        <form onSubmit={onSubmit} className="space-y-4">
            {error && <p className="text-sm text-red-600 bg-red-50 p-2 rounded">{error}</p>}
            <div className="grid grid-cols-2 gap-4">
                <div className="col-span-2">
                    <label className="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                    <input type="text" required value={form.name} onChange={e => setForm({ ...form, name: e.target.value })}
                        className="input w-full" placeholder="Opponent's full name" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Party</label>
                    <input type="text" value={form.party} onChange={e => setForm({ ...form, party: e.target.value })}
                        className="input w-full" placeholder="e.g., ODM, UDA" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Position</label>
                    <input type="text" value={form.position} onChange={e => setForm({ ...form, position: e.target.value })}
                        className="input w-full" placeholder="e.g., Governor, Senator" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Threat Level</label>
                    <select value={form.threat_level} onChange={e => setForm({ ...form, threat_level: e.target.value })} className="input w-full">
                        {THREAT_LEVELS.map(t => <option key={t} value={t}>{t.charAt(0).toUpperCase() + t.slice(1)}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">County</label>
                    <input type="text" value={form.county} onChange={e => setForm({ ...form, county: e.target.value })}
                        className="input w-full" placeholder="County" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Constituency</label>
                    <input type="text" value={form.constituency} onChange={e => setForm({ ...form, constituency: e.target.value })}
                        className="input w-full" placeholder="Constituency" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Ward</label>
                    <input type="text" value={form.ward} onChange={e => setForm({ ...form, ward: e.target.value })}
                        className="input w-full" placeholder="Ward" />
                </div>
            </div>
            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Bio</label>
                <textarea rows={3} value={form.bio} onChange={e => setForm({ ...form, bio: e.target.value })}
                    className="input w-full" placeholder="Background information..." />
            </div>
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Strengths</label>
                    <textarea rows={3} value={form.strengths} onChange={e => setForm({ ...form, strengths: e.target.value })}
                        className="input w-full" placeholder="Known strengths..." />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Weaknesses</label>
                    <textarea rows={3} value={form.weaknesses} onChange={e => setForm({ ...form, weaknesses: e.target.value })}
                        className="input w-full" placeholder="Known weaknesses..." />
                </div>
            </div>
            <div className="grid grid-cols-3 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Photo URL</label>
                    <input type="text" value={form.photo_url} onChange={e => setForm({ ...form, photo_url: e.target.value })}
                        className="input w-full" placeholder="https://..." />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Facebook</label>
                    <input type="text" value={form.social_facebook} onChange={e => setForm({ ...form, social_facebook: e.target.value })}
                        className="input w-full" placeholder="https://facebook.com/..." />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Twitter/X</label>
                    <input type="text" value={form.social_twitter} onChange={e => setForm({ ...form, social_twitter: e.target.value })}
                        className="input w-full" placeholder="https://x.com/..." />
                </div>
            </div>
            <div className="flex justify-end space-x-3 pt-2">
                <button type="button" onClick={onCancel} className="btn-secondary !py-2 !px-4 text-sm">Cancel</button>
                <button type="submit" disabled={submitting} className="btn-primary !py-2 !px-4 text-sm">
                    {submitting ? 'Saving...' : editing ? 'Update' : 'Add Opponent'}
                </button>
            </div>
        </form>
    );
}

function SentimentPanel({ sentiment, sentimentLoading, sentimentTab, setSentimentTab, reanalyzing, onBulkReanalyze, can }) {
    if (sentimentLoading) {
        return (
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600 mx-auto mb-2" />
                <p className="text-sm text-gray-400">Loading sentiment analysis...</p>
            </div>
        );
    }

    if (!sentiment) return null;

    const { overall, research_breakdown, research_timeline, field_report_mentions, voter_interaction_mentions } = sentiment;

    const tabs = [
        { id: 'overview', label: 'Overview' },
        { id: 'timeline', label: 'Timeline' },
        { id: 'field_reports', label: `Field Reports (${field_report_mentions?.length || 0})` },
        { id: 'interactions', label: `Voter Interactions (${voter_interaction_mentions?.length || 0})` },
    ];

    const breakdownTotal = ['positive', 'neutral', 'negative'].reduce(
        (sum, key) => sum + (research_breakdown?.[key]?.count || 0), 0
    );

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            {/* Header */}
            <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div className="flex items-center space-x-2">
                    <ChartBarIcon className="h-5 w-5 text-gray-400" />
                    <h3 className="text-lg font-heading font-semibold text-gray-900">Sentiment Analysis</h3>
                </div>
                <div className="flex items-center space-x-3">
                    {/* Overall score badge */}
                    <span className={`px-3 py-1 text-sm font-medium rounded-full capitalize ${sentimentBadgeColors[overall?.label || 'neutral']}`}>
                        {overall?.label || 'neutral'} ({overall?.avg_score > 0 ? '+' : ''}{overall?.avg_score || 0})
                    </span>
                    <span className="text-xs text-gray-400">{overall?.total_sources || 0} sources</span>
                    {can('opponents.edit-research') && (
                        <button
                            onClick={onBulkReanalyze}
                            disabled={reanalyzing}
                            className="btn-secondary !py-1.5 !px-3 text-xs"
                            title="Re-analyze all research notes"
                        >
                            <ArrowPathIcon className={`h-3.5 w-3.5 mr-1 ${reanalyzing ? 'animate-spin' : ''}`} />
                            Re-analyze All
                        </button>
                    )}
                </div>
            </div>

            {/* Tabs */}
            <div className="flex border-b border-gray-100">
                {tabs.map(tab => (
                    <button
                        key={tab.id}
                        onClick={() => setSentimentTab(tab.id)}
                        className={`px-4 py-2.5 text-sm font-medium transition-colors ${sentimentTab === tab.id ? 'text-primary-600 border-b-2 border-primary-600' : 'text-gray-500 hover:text-gray-700'}`}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            {/* Tab content */}
            <div className="p-6">
                {sentimentTab === 'overview' && (
                    <div className="space-y-4">
                        {/* Sentiment distribution bar */}
                        <div>
                            <h4 className="text-sm font-medium text-gray-700 mb-2">Research Note Sentiment Distribution</h4>
                            {breakdownTotal > 0 ? (
                                <>
                                    <div className="flex h-6 rounded-full overflow-hidden bg-gray-100">
                                        {['positive', 'neutral', 'negative'].map(key => {
                                            const count = research_breakdown?.[key]?.count || 0;
                                            const pct = breakdownTotal > 0 ? (count / breakdownTotal) * 100 : 0;
                                            if (pct === 0) return null;
                                            return (
                                                <div
                                                    key={key}
                                                    className={`${sentimentBarColors[key]} flex items-center justify-center transition-all`}
                                                    style={{ width: `${pct}%` }}
                                                    title={`${key}: ${count} (${Math.round(pct)}%)`}
                                                >
                                                    {pct > 10 && <span className="text-xs text-white font-medium">{Math.round(pct)}%</span>}
                                                </div>
                                            );
                                        })}
                                    </div>
                                    <div className="flex items-center justify-center space-x-6 mt-3">
                                        {['positive', 'neutral', 'negative'].map(key => (
                                            <div key={key} className="flex items-center space-x-1.5">
                                                <div className={`w-3 h-3 rounded-full ${sentimentBarColors[key]}`} />
                                                <span className="text-xs text-gray-600 capitalize">{key}: {research_breakdown?.[key]?.count || 0}</span>
                                            </div>
                                        ))}
                                    </div>
                                </>
                            ) : (
                                <p className="text-sm text-gray-400">No sentiment data yet. Add research notes to see analysis.</p>
                            )}
                        </div>

                        {/* Summary stats */}
                        <div className="grid grid-cols-3 gap-4 pt-2">
                            {['positive', 'neutral', 'negative'].map(key => (
                                <div key={key} className={`rounded-lg p-3 ${sentimentColors[key]}`}>
                                    <div className="text-2xl font-bold">{research_breakdown?.[key]?.count || 0}</div>
                                    <div className="text-xs capitalize font-medium">{key} notes</div>
                                    {research_breakdown?.[key]?.avg_score != null && (
                                        <div className="text-xs mt-0.5 opacity-75">
                                            Avg: {Number(research_breakdown[key].avg_score) > 0 ? '+' : ''}{Number(research_breakdown[key].avg_score).toFixed(2)}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {sentimentTab === 'timeline' && (
                    <div>
                        <h4 className="text-sm font-medium text-gray-700 mb-3">Sentiment Over Time (Monthly Avg)</h4>
                        {research_timeline && research_timeline.length > 0 ? (
                            <div className="space-y-2">
                                {research_timeline.map((point) => {
                                    const score = Number(point.avg_score);
                                    const label = score >= 0.3 ? 'positive' : score <= -0.3 ? 'negative' : 'neutral';
                                    const barWidth = Math.abs(score) * 100;
                                    return (
                                        <div key={point.month} className="flex items-center space-x-3">
                                            <span className="text-xs text-gray-500 w-20 flex-shrink-0">{point.month}</span>
                                            <div className="flex-1 flex items-center">
                                                <div className="w-full bg-gray-100 rounded-full h-4 relative">
                                                    <div className="absolute inset-y-0 left-1/2 w-px bg-gray-300" />
                                                    {score >= 0 ? (
                                                        <div
                                                            className="absolute inset-y-0 left-1/2 bg-green-400 rounded-r-full"
                                                            style={{ width: `${barWidth / 2}%` }}
                                                        />
                                                    ) : (
                                                        <div
                                                            className="absolute inset-y-0 bg-red-400 rounded-l-full"
                                                            style={{ width: `${barWidth / 2}%`, right: '50%' }}
                                                        />
                                                    )}
                                                </div>
                                            </div>
                                            <span className={`text-xs font-medium w-16 text-right ${sentimentBadgeColors[label]?.split(' ').find(c => c.startsWith('text-')) || 'text-gray-600'}`}>
                                                {score > 0 ? '+' : ''}{score.toFixed(2)}
                                            </span>
                                            <span className="text-xs text-gray-400 w-12 text-right">{point.count} notes</span>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <p className="text-sm text-gray-400">No timeline data available yet.</p>
                        )}
                    </div>
                )}

                {sentimentTab === 'field_reports' && (
                    <MentionsList
                        items={field_report_mentions}
                        emptyText="No opponent mentions found in field reports."
                        renderItem={(item) => (
                            <div key={item.id} className={`p-3 rounded-lg border ${sentimentColors[item.sentiment_label] || 'border-gray-200'}`}>
                                <div className="flex items-center justify-between mb-1">
                                    <div className="flex items-center space-x-2">
                                        <span className="text-sm font-medium text-gray-900">{item.title || 'Field Report'}</span>
                                        {item.type && <span className="px-2 py-0.5 text-xs bg-blue-50 text-blue-600 rounded-full">{item.type}</span>}
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        <span className={`px-2 py-0.5 text-xs font-medium rounded-full capitalize ${sentimentBadgeColors[item.sentiment_label]}`}>
                                            {item.sentiment_label} ({item.sentiment_score > 0 ? '+' : ''}{item.sentiment_score})
                                        </span>
                                        <span className="text-xs text-gray-400">{item.date}</span>
                                    </div>
                                </div>
                                <p className="text-xs text-gray-500 line-clamp-2">{item.excerpt}</p>
                            </div>
                        )}
                    />
                )}

                {sentimentTab === 'interactions' && (
                    <MentionsList
                        items={voter_interaction_mentions}
                        emptyText="No opponent mentions found in voter interactions."
                        renderItem={(item) => (
                            <div key={item.id} className={`p-3 rounded-lg border ${sentimentColors[item.sentiment_label] || 'border-gray-200'}`}>
                                <div className="flex items-center justify-between mb-1">
                                    <div className="flex items-center space-x-2">
                                        <span className="text-sm font-medium text-gray-900 capitalize">{item.type || 'Interaction'}</span>
                                        {item.outcome && <span className="px-2 py-0.5 text-xs bg-purple-50 text-purple-600 rounded-full">{item.outcome}</span>}
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        <span className={`px-2 py-0.5 text-xs font-medium rounded-full capitalize ${sentimentBadgeColors[item.sentiment_label]}`}>
                                            {item.sentiment_label} ({item.sentiment_score > 0 ? '+' : ''}{item.sentiment_score})
                                        </span>
                                        <span className="text-xs text-gray-400">{item.date}</span>
                                    </div>
                                </div>
                                <p className="text-xs text-gray-500 line-clamp-2">{item.excerpt}</p>
                            </div>
                        )}
                    />
                )}
            </div>
        </div>
    );
}

function MentionsList({ items, emptyText, renderItem }) {
    if (!items || items.length === 0) {
        return <p className="text-sm text-gray-400">{emptyText}</p>;
    }

    return (
        <div className="space-y-2 max-h-80 overflow-y-auto">
            {items.map(renderItem)}
        </div>
    );
}
