import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
    PlusIcon,
    BuildingOffice2Icon,
    MapPinIcon,
    ArrowRightOnRectangleIcon,
} from '@heroicons/react/24/outline';
import api from '../../lib/api';
import useAuthStore from '../stores/useAuthStore';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';

const CAMPAIGN_TYPES = ['presidential', 'governor', 'senator', 'woman_rep', 'mp', 'mca'];
const LEVELS = ['national', 'county', 'constituency', 'ward'];

export default function CampaignsPage() {
    const [campaigns, setCampaigns] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showCreate, setShowCreate] = useState(false);
    const [creating, setCreating] = useState(false);
    const [form, setForm] = useState({
        name: '', type: 'mp', level: 'constituency', county: '', constituency: '', ward: '', description: '',
    });
    const [formError, setFormError] = useState(null);
    const user = useAuthStore((s) => s.user);
    const logout = useAuthStore((s) => s.logout);
    const navigate = useNavigate();

    const fetchCampaigns = async () => {
        setLoading(true);
        try {
            const { data } = await api.get('/campaigns');
            setCampaigns(data.data || []);
        } catch {
            // handled by interceptor
        }
        setLoading(false);
    };

    useEffect(() => { fetchCampaigns(); }, []);

    const handleCreate = async (e) => {
        e.preventDefault();
        setCreating(true);
        setFormError(null);
        try {
            const { data } = await api.post('/campaigns', form);
            setShowCreate(false);
            setForm({ name: '', type: 'mp', level: 'constituency', county: '', constituency: '', ward: '', description: '' });
            navigate(`/admin/campaigns/${data.data.id}`);
        } catch (err) {
            setFormError(
                err.response?.data?.errors
                    ? Object.values(err.response.data.errors).flat().join(', ')
                    : err.response?.data?.message || 'Failed to create campaign'
            );
        }
        setCreating(false);
    };

    const handleLogout = async () => {
        await logout();
        navigate('/admin/login');
    };

    const update = (field) => (e) => setForm((f) => ({ ...f, [field]: e.target.value }));

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Top bar */}
            <header className="bg-white border-b border-gray-200">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
                    <div className="flex items-center space-x-2">
                        <BuildingOffice2Icon className="h-8 w-8 text-primary-600" />
                        <span className="font-heading font-bold text-xl text-gray-900">KuraDigital</span>
                    </div>
                    <div className="flex items-center space-x-4">
                        <span className="text-sm text-gray-600">{user?.name}</span>
                        <button
                            onClick={handleLogout}
                            className="text-gray-400 hover:text-red-500 transition-colors"
                            title="Sign out"
                        >
                            <ArrowRightOnRectangleIcon className="h-5 w-5" />
                        </button>
                    </div>
                </div>
            </header>

            {/* Content */}
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-heading font-bold text-gray-900">Your Campaigns</h1>
                        <p className="text-sm text-gray-500 mt-1">Select a campaign to manage or create a new one</p>
                    </div>
                    <button onClick={() => setShowCreate(true)} className="btn-primary !py-2 !px-4 text-sm">
                        <PlusIcon className="h-4 w-4 mr-1.5" />
                        New Campaign
                    </button>
                </div>

                {loading ? (
                    <div className="text-center py-20">
                        <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600 mx-auto" />
                    </div>
                ) : campaigns.length === 0 ? (
                    <EmptyState
                        icon={BuildingOffice2Icon}
                        title="No campaigns yet"
                        description="Create your first campaign to get started with KuraDigital"
                        action={
                            <button onClick={() => setShowCreate(true)} className="btn-primary !py-2 !px-4 text-sm">
                                <PlusIcon className="h-4 w-4 mr-1.5" />
                                Create Campaign
                            </button>
                        }
                    />
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        {campaigns.map((c) => (
                            <button
                                key={c.id}
                                onClick={() => navigate(`/admin/campaigns/${c.id}`)}
                                className="bg-white rounded-xl shadow-sm border border-gray-100 p-5 text-left hover:shadow-md hover:border-primary-200 transition-all group"
                            >
                                <div className="flex items-start justify-between">
                                    <div className="flex-1 min-w-0">
                                        <h3 className="font-heading font-semibold text-gray-900 group-hover:text-primary-700 truncate">
                                            {c.name}
                                        </h3>
                                        <span className="inline-block mt-1 px-2 py-0.5 bg-primary-50 text-primary-700 text-xs font-medium rounded-full capitalize">
                                            {c.type?.replace('_', ' ')}
                                        </span>
                                    </div>
                                </div>
                                {(c.county || c.constituency || c.ward) && (
                                    <div className="flex items-center space-x-1 mt-3 text-xs text-gray-400">
                                        <MapPinIcon className="h-3.5 w-3.5" />
                                        <span className="truncate">
                                            {[c.county, c.constituency, c.ward].filter(Boolean).join(' / ')}
                                        </span>
                                    </div>
                                )}
                                <p className="text-xs text-gray-400 mt-2 capitalize">{c.level} level</p>
                            </button>
                        ))}
                    </div>
                )}
            </div>

            {/* Create Campaign Modal */}
            <Modal open={showCreate} onClose={() => setShowCreate(false)} title="Create Campaign" size="md">
                {formError && (
                    <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                        {formError}
                    </div>
                )}
                <form onSubmit={handleCreate} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Campaign Name</label>
                        <input
                            type="text"
                            value={form.name}
                            onChange={update('name')}
                            required
                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="e.g. John Kamau for Starehe MP 2027"
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select value={form.type} onChange={update('type')} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                {CAMPAIGN_TYPES.map((t) => (
                                    <option key={t} value={t}>{t.replace('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase())}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Level</label>
                            <select value={form.level} onChange={update('level')} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                {LEVELS.map((l) => (
                                    <option key={l} value={l}>{l.charAt(0).toUpperCase() + l.slice(1)}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">County</label>
                            <input type="text" value={form.county} onChange={update('county')} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm" placeholder="Nairobi" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Constituency</label>
                            <input type="text" value={form.constituency} onChange={update('constituency')} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm" placeholder="Starehe" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Ward</label>
                            <input type="text" value={form.ward} onChange={update('ward')} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm" placeholder="Ngara" />
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
                        <textarea
                            value={form.description}
                            onChange={update('description')}
                            rows={2}
                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        />
                    </div>
                    <div className="flex justify-end space-x-3 pt-2">
                        <button type="button" onClick={() => setShowCreate(false)} className="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" disabled={creating} className="btn-primary !py-2 !px-5 text-sm disabled:opacity-50">
                            {creating ? 'Creating...' : 'Create Campaign'}
                        </button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
