import React, { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import useCampaignStore from '../stores/useCampaignStore';

const roleLabels = {
    owner: 'Owner',
    manager: 'Campaign Manager',
    researcher: 'Researcher',
    field_agent: 'Field Agent',
    analyst: 'Analyst',
    coordinator: 'Coordinator',
};

const roleColors = {
    owner: 'bg-purple-100 text-purple-700',
    manager: 'bg-blue-100 text-blue-700',
    researcher: 'bg-orange-100 text-orange-700',
    field_agent: 'bg-green-100 text-green-700',
    analyst: 'bg-cyan-100 text-cyan-700',
    coordinator: 'bg-pink-100 text-pink-700',
};

export default function CampaignDetailPage() {
    const { id } = useParams();
    const { currentCampaign, fetchCampaign, fetchMembers, members, updateCampaign, inviteMember, loading, error } = useCampaignStore();
    const [showInvite, setShowInvite] = useState(false);
    const [inviteForm, setInviteForm] = useState({ name: '', email: '', role: 'field_agent' });
    const [inviting, setInviting] = useState(false);
    const [editing, setEditing] = useState(false);

    useEffect(() => {
        fetchCampaign(id);
        fetchMembers(id);
    }, [id, fetchCampaign, fetchMembers]);

    const handleStatusChange = async (status) => {
        await updateCampaign(id, { status });
    };

    const handleInvite = async (e) => {
        e.preventDefault();
        setInviting(true);
        const ok = await inviteMember(id, inviteForm);
        setInviting(false);
        if (ok) {
            setInviteForm({ name: '', email: '', role: 'field_agent' });
            setShowInvite(false);
        }
    };

    if (loading || !currentCampaign) {
        return <div className="p-8 text-center text-gray-500">Loading campaign...</div>;
    }

    const campaign = currentCampaign;

    return (
        <div className="p-8">
            <div className="flex items-start justify-between mb-8">
                <div>
                    <div className="flex items-center gap-3 mb-1">
                        <Link to="/dashboard/campaigns" className="text-gray-400 hover:text-gray-600">← Campaigns</Link>
                    </div>
                    <h1 className="text-2xl font-bold text-gray-900">{campaign.candidate_name}</h1>
                    <p className="text-gray-500">
                        {campaign.position} • {campaign.constituency || campaign.county} • {campaign.election_year}
                    </p>
                    {campaign.slogan && (
                        <p className="text-sm italic text-gray-600 mt-1">"{campaign.slogan}"</p>
                    )}
                </div>
                <div className="flex items-center gap-2">
                    {['draft', 'active', 'paused', 'completed'].map((s) => (
                        <button
                            key={s}
                            onClick={() => handleStatusChange(s)}
                            className={`text-xs px-3 py-1.5 rounded-full font-medium capitalize transition-colors ${
                                campaign.status === s
                                    ? s === 'active' ? 'bg-green-600 text-white' :
                                      s === 'paused' ? 'bg-yellow-500 text-white' :
                                      s === 'completed' ? 'bg-gray-600 text-white' :
                                      'bg-blue-600 text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            }`}
                        >
                            {s}
                        </button>
                    ))}
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 space-y-6">
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 className="text-lg font-bold text-gray-900 mb-4">Campaign Details</h2>
                        <div className="grid grid-cols-2 gap-4 text-sm">
                            <InfoItem label="Candidate" value={campaign.candidate_name} />
                            <InfoItem label="Position" value={campaign.position} />
                            <InfoItem label="Constituency" value={campaign.constituency || '—'} />
                            <InfoItem label="County" value={campaign.county || '—'} />
                            <InfoItem label="Party" value={campaign.party || 'Independent'} />
                            <InfoItem label="Election Year" value={campaign.election_year} />
                        </div>
                        {campaign.description && (
                            <div className="mt-4 pt-4 border-t border-gray-100">
                                <p className="text-sm text-gray-600">{campaign.description}</p>
                            </div>
                        )}
                    </div>

                    <div className="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                            <h2 className="text-lg font-bold text-gray-900">
                                Team Members ({members.length})
                            </h2>
                            <button
                                onClick={() => setShowInvite(!showInvite)}
                                className="text-sm bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors"
                            >
                                + Invite Member
                            </button>
                        </div>

                        {showInvite && (
                            <form onSubmit={handleInvite} className="p-4 bg-gray-50 border-b border-gray-200">
                                {error && (
                                    <div className="mb-3 p-2 bg-red-50 border border-red-200 rounded text-sm text-red-600">
                                        {error}
                                    </div>
                                )}
                                <div className="grid grid-cols-3 gap-3">
                                    <input
                                        type="text"
                                        required
                                        placeholder="Name"
                                        value={inviteForm.name}
                                        onChange={(e) => setInviteForm({ ...inviteForm, name: e.target.value })}
                                        className="border border-gray-300 rounded-lg px-3 py-2 text-sm"
                                    />
                                    <input
                                        type="email"
                                        required
                                        placeholder="Email"
                                        value={inviteForm.email}
                                        onChange={(e) => setInviteForm({ ...inviteForm, email: e.target.value })}
                                        className="border border-gray-300 rounded-lg px-3 py-2 text-sm"
                                    />
                                    <select
                                        value={inviteForm.role}
                                        onChange={(e) => setInviteForm({ ...inviteForm, role: e.target.value })}
                                        className="border border-gray-300 rounded-lg px-3 py-2 text-sm"
                                    >
                                        <option value="manager">Campaign Manager</option>
                                        <option value="researcher">Researcher</option>
                                        <option value="field_agent">Field Agent</option>
                                        <option value="analyst">Analyst</option>
                                        <option value="coordinator">Coordinator</option>
                                    </select>
                                </div>
                                <div className="mt-3 flex gap-2">
                                    <button
                                        type="submit"
                                        disabled={inviting}
                                        className="text-sm bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 disabled:opacity-50"
                                    >
                                        {inviting ? 'Inviting...' : 'Send Invite'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setShowInvite(false)}
                                        className="text-sm text-gray-500 hover:text-gray-700"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        )}

                        <div className="divide-y divide-gray-100">
                            {members.map((member) => (
                                <div key={member.id} className="flex items-center justify-between p-4">
                                    <div className="flex items-center gap-3">
                                        <div className="w-9 h-9 rounded-full bg-gray-200 flex items-center justify-center text-sm font-bold text-gray-600">
                                            {member.name?.charAt(0)?.toUpperCase()}
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-gray-900">{member.name}</p>
                                            <p className="text-xs text-gray-500">{member.email}</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className={`text-xs px-2 py-1 rounded-full font-medium ${
                                            roleColors[member.pivot?.role] || 'bg-gray-100 text-gray-700'
                                        }`}>
                                            {roleLabels[member.pivot?.role] || member.pivot?.role}
                                        </span>
                                        <span className={`text-xs px-2 py-1 rounded-full ${
                                            member.pivot?.status === 'active' ? 'bg-green-50 text-green-600' :
                                            member.pivot?.status === 'invited' ? 'bg-yellow-50 text-yellow-600' :
                                            'bg-red-50 text-red-600'
                                        }`}>
                                            {member.pivot?.status}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="space-y-6">
                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 className="font-bold text-gray-900 mb-4">Quick Actions</h3>
                        <div className="space-y-2">
                            <QuickAction icon="🔍" label="Research Opponents" disabled />
                            <QuickAction icon="📢" label="Plan Content" disabled />
                            <QuickAction icon="📊" label="View Analytics" disabled />
                            <QuickAction icon="🗳️" label="Tallying Setup" disabled />
                            {campaign.site && (
                                <a
                                    href={`/${campaign.site.slug}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors text-sm"
                                >
                                    <span>🌐</span>
                                    <span>View Candidate Website</span>
                                </a>
                            )}
                        </div>
                    </div>

                    <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h3 className="font-bold text-gray-900 mb-4">Campaign Colors</h3>
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-lg border" style={{ backgroundColor: campaign.primary_color }} />
                            <div>
                                <p className="text-xs text-gray-500">Primary</p>
                                <p className="text-sm font-mono">{campaign.primary_color}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3 mt-3">
                            <div className="w-10 h-10 rounded-lg border" style={{ backgroundColor: campaign.secondary_color }} />
                            <div>
                                <p className="text-xs text-gray-500">Secondary</p>
                                <p className="text-sm font-mono">{campaign.secondary_color}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function InfoItem({ label, value }) {
    return (
        <div>
            <p className="text-gray-500">{label}</p>
            <p className="font-medium text-gray-900">{value}</p>
        </div>
    );
}

function QuickAction({ icon, label, disabled }) {
    return (
        <div className={`flex items-center gap-3 p-3 rounded-lg text-sm ${
            disabled ? 'text-gray-400 cursor-not-allowed' : 'hover:bg-gray-50 cursor-pointer'
        }`}>
            <span>{icon}</span>
            <span>{label}</span>
            {disabled && <span className="ml-auto text-xs bg-gray-100 text-gray-400 px-1.5 py-0.5 rounded">Soon</span>}
        </div>
    );
}
