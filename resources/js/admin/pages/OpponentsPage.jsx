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
} from '@heroicons/react/24/outline';
import api from '../../lib/api';
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

    const openOpponentDetail = (opponent) => {
        setSelectedOpponent(opponent);
        fetchResearch(opponent);
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
                await api.put(`/campaigns/${campaignId}/opponents/${editing.id}`, form);
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
                            <button onClick={() => openEdit(selectedOpponent)} className="btn-secondary !py-2 !px-3 text-sm">
                                <PencilIcon className="h-4 w-4 mr-1" /> Edit
                            </button>
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

                {/* Research section */}
                <div className="flex items-center justify-between">
                    <h3 className="text-lg font-heading font-semibold text-gray-900">
                        Research Notes <span className="text-sm font-normal text-gray-400">({research.length})</span>
                    </h3>
                    <button onClick={openResearchCreate} className="btn-primary !py-2 !px-4 text-sm">
                        <PlusIcon className="h-4 w-4 mr-1" /> Add Research
                    </button>
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
                            <button onClick={openResearchCreate} className="btn-primary !py-2 !px-4 text-sm">
                                <PlusIcon className="h-4 w-4 mr-1" /> Add Research
                            </button>
                        }
                    />
                ) : (
                    <div className="space-y-3">
                        {research.map((r) => (
                            <div key={r.id} className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                                <div className="flex items-start justify-between">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center space-x-2 mb-1">
                                            <h4 className="font-medium text-gray-900">{r.title}</h4>
                                            <span className={`px-2 py-0.5 text-xs font-medium rounded-full capitalize ${clearanceColors[r.clearance]}`}>
                                                {r.clearance}
                                            </span>
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
                                        <button onClick={() => openResearchEdit(r)} className="p-1.5 text-gray-400 hover:text-primary-600 rounded">
                                            <PencilIcon className="h-4 w-4" />
                                        </button>
                                        <button onClick={() => handleResearchDelete(r.id)} className="p-1.5 text-gray-400 hover:text-red-500 rounded">
                                            <TrashIcon className="h-4 w-4" />
                                        </button>
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
                    <button onClick={(e) => { e.stopPropagation(); openEdit(r); }} className="p-1.5 text-gray-400 hover:text-primary-600 rounded">
                        <PencilIcon className="h-4 w-4" />
                    </button>
                    <button onClick={(e) => { e.stopPropagation(); handleDelete(r.id); }} className="p-1.5 text-gray-400 hover:text-red-500 rounded">
                        <TrashIcon className="h-4 w-4" />
                    </button>
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
                <button onClick={openCreate} className="btn-primary !py-2 !px-4 text-sm whitespace-nowrap">
                    <PlusIcon className="h-4 w-4 mr-1" /> Add Opponent
                </button>
            </div>

            {/* Table */}
            {opponents.length === 0 && !loading ? (
                <EmptyState
                    icon={UserGroupIcon}
                    title="No opponents tracked"
                    description="Start building your opponent intelligence by adding competitors."
                    action={
                        <button onClick={openCreate} className="btn-primary !py-2 !px-4 text-sm">
                            <PlusIcon className="h-4 w-4 mr-1" /> Add Opponent
                        </button>
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
