import React, { useEffect } from 'react';
import { Link } from 'react-router-dom';
import useAuthStore from '../stores/useAuthStore';
import useCampaignStore from '../stores/useCampaignStore';

export default function OverviewPage() {
    const { user } = useAuthStore();
    const { campaigns, fetchCampaigns, loading, currentCampaign } = useCampaignStore();

    useEffect(() => {
        fetchCampaigns();
    }, [fetchCampaigns]);

    return (
        <div className="p-8">
            <div className="mb-8">
                <h1 className="text-2xl font-bold text-gray-900">
                    Welcome back, {user?.name?.split(' ')[0]}
                </h1>
                <p className="text-gray-500 mt-1">Here's your campaign overview</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <StatCard label="Active Campaigns" value={campaigns.filter((c) => c.status === 'active').length} icon="🏛️" color="green" />
                <StatCard label="Team Members" value={currentCampaign?.campaign_members_count || '—'} icon="👥" color="blue" />
                <StatCard label="Days to Election" value={daysToElection()} icon="📅" color="orange" />
                <StatCard label="Research Notes" value="—" icon="🔍" color="purple" />
            </div>

            {campaigns.length === 0 && !loading ? (
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                    <div className="text-6xl mb-4">🏛️</div>
                    <h2 className="text-xl font-bold text-gray-900 mb-2">No campaigns yet</h2>
                    <p className="text-gray-500 mb-6">
                        Create your first campaign to start managing your political operations.
                    </p>
                    <Link
                        to="/dashboard/campaigns/new"
                        className="inline-flex items-center gap-2 bg-green-600 text-white px-6 py-2.5 rounded-lg font-medium hover:bg-green-700 transition-colors"
                    >
                        + Create Campaign
                    </Link>
                </div>
            ) : (
                <div className="bg-white rounded-xl shadow-sm border border-gray-200">
                    <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                        <h2 className="text-lg font-bold text-gray-900">Your Campaigns</h2>
                        <Link
                            to="/dashboard/campaigns/new"
                            className="text-sm bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors"
                        >
                            + New Campaign
                        </Link>
                    </div>
                    <div className="divide-y divide-gray-100">
                        {campaigns.map((campaign) => (
                            <Link
                                key={campaign.id}
                                to={`/dashboard/campaigns/${campaign.id}`}
                                className="flex items-center justify-between p-4 hover:bg-gray-50 transition-colors"
                            >
                                <div className="flex items-center gap-4">
                                    <div
                                        className="w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold"
                                        style={{ backgroundColor: campaign.primary_color || '#16a34a' }}
                                    >
                                        {campaign.candidate_name?.charAt(0)}
                                    </div>
                                    <div>
                                        <p className="font-medium text-gray-900">{campaign.candidate_name}</p>
                                        <p className="text-sm text-gray-500">
                                            {campaign.position} • {campaign.constituency || campaign.county} • {campaign.election_year}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className={`text-xs px-2 py-1 rounded-full font-medium ${
                                        campaign.status === 'active' ? 'bg-green-100 text-green-700' :
                                        campaign.status === 'paused' ? 'bg-yellow-100 text-yellow-700' :
                                        campaign.status === 'completed' ? 'bg-gray-100 text-gray-700' :
                                        'bg-blue-100 text-blue-700'
                                    }`}>
                                        {campaign.status}
                                    </span>
                                    <span className="text-gray-400">→</span>
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function StatCard({ label, value, icon, color }) {
    const colorClasses = {
        green: 'bg-green-50 text-green-700',
        blue: 'bg-blue-50 text-blue-700',
        orange: 'bg-orange-50 text-orange-700',
        purple: 'bg-purple-50 text-purple-700',
    };

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div className="flex items-center justify-between mb-3">
                <span className={`text-2xl p-2 rounded-lg ${colorClasses[color]}`}>{icon}</span>
            </div>
            <p className="text-2xl font-bold text-gray-900">{value}</p>
            <p className="text-sm text-gray-500">{label}</p>
        </div>
    );
}

function daysToElection() {
    const electionDate = new Date('2027-08-10');
    const today = new Date();
    const diff = Math.ceil((electionDate - today) / (1000 * 60 * 60 * 24));
    return diff > 0 ? diff : '—';
}
