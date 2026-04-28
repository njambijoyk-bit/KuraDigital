import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import useCampaignStore from '../stores/useCampaignStore';

const positions = [
    'Member of Parliament',
    'Senator',
    'Governor',
    'Women Representative',
    'Member of County Assembly',
    'President',
];

export default function NewCampaignPage() {
    const navigate = useNavigate();
    const { createCampaign, error } = useCampaignStore();
    const [submitting, setSubmitting] = useState(false);
    const [form, setForm] = useState({
        candidate_name: '',
        position: 'Member of Parliament',
        constituency: '',
        county: '',
        party: '',
        election_year: '2027',
        slogan: '',
        description: '',
    });

    const handleChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        const campaign = await createCampaign(form);
        setSubmitting(false);
        if (campaign) navigate(`/dashboard/campaigns/${campaign.id}`);
    };

    return (
        <div className="p-8 max-w-2xl mx-auto">
            <h1 className="text-2xl font-bold text-gray-900 mb-2">Create New Campaign</h1>
            <p className="text-gray-500 mb-8">Set up a new political campaign to manage</p>

            {error && (
                <div className="mb-6 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-600">
                    {error}
                </div>
            )}

            <form onSubmit={handleSubmit} className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-6">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Candidate Name *</label>
                    <input
                        type="text"
                        name="candidate_name"
                        required
                        value={form.candidate_name}
                        onChange={handleChange}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        placeholder="e.g. John Kamau"
                    />
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Position *</label>
                        <select
                            name="position"
                            value={form.position}
                            onChange={handleChange}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        >
                            {positions.map((p) => (
                                <option key={p} value={p}>{p}</option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Election Year</label>
                        <select
                            name="election_year"
                            value={form.election_year}
                            onChange={handleChange}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        >
                            <option value="2027">2027</option>
                            <option value="2028">2028</option>
                            <option value="2032">2032</option>
                        </select>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Constituency</label>
                        <input
                            type="text"
                            name="constituency"
                            value={form.constituency}
                            onChange={handleChange}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-green-500"
                            placeholder="e.g. Starehe"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">County</label>
                        <input
                            type="text"
                            name="county"
                            value={form.county}
                            onChange={handleChange}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-green-500"
                            placeholder="e.g. Nairobi"
                        />
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Party</label>
                        <input
                            type="text"
                            name="party"
                            value={form.party}
                            onChange={handleChange}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-green-500"
                            placeholder="e.g. Independent"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Campaign Slogan</label>
                        <input
                            type="text"
                            name="slogan"
                            value={form.slogan}
                            onChange={handleChange}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-green-500"
                            placeholder="e.g. Together, We Build a Better Future"
                        />
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea
                        name="description"
                        rows={3}
                        value={form.description}
                        onChange={handleChange}
                        className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        placeholder="Brief description of the campaign goals and strategy"
                    />
                </div>

                <div className="flex items-center gap-4 pt-4 border-t border-gray-100">
                    <button
                        type="submit"
                        disabled={submitting}
                        className="bg-green-600 text-white px-6 py-2.5 rounded-lg font-medium hover:bg-green-700 transition-colors disabled:opacity-50"
                    >
                        {submitting ? 'Creating...' : 'Create Campaign'}
                    </button>
                    <button
                        type="button"
                        onClick={() => navigate('/dashboard/campaigns')}
                        className="text-gray-500 hover:text-gray-700"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    );
}
