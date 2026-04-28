import React, { useEffect } from 'react';
import { Link } from 'react-router-dom';
import useCampaignStore from '../stores/useCampaignStore';

export default function CampaignsPage() {
    const { campaigns, fetchCampaigns, loading } = useCampaignStore();

    useEffect(() => {
        fetchCampaigns();
    }, [fetchCampaigns]);

    return (
        <div className="p-8">
            <div className="flex items-center justify-between mb-8">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Campaigns</h1>
                    <p className="text-gray-500 mt-1">Manage all your political campaigns</p>
                </div>
                <Link
                    to="/dashboard/campaigns/new"
                    className="bg-green-600 text-white px-5 py-2.5 rounded-lg font-medium hover:bg-green-700 transition-colors"
                >
                    + New Campaign
                </Link>
            </div>

            {loading ? (
                <div className="text-center py-12 text-gray-500">Loading campaigns...</div>
            ) : campaigns.length === 0 ? (
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                    <div className="text-6xl mb-4">🏛️</div>
                    <h2 className="text-xl font-bold text-gray-900 mb-2">No campaigns yet</h2>
                    <p className="text-gray-500 mb-6">Create your first campaign to get started.</p>
                    <Link
                        to="/dashboard/campaigns/new"
                        className="inline-flex items-center gap-2 bg-green-600 text-white px-6 py-2.5 rounded-lg font-medium hover:bg-green-700"
                    >
                        + Create Campaign
                    </Link>
                </div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {campaigns.map((campaign) => (
                        <Link
                            key={campaign.id}
                            to={`/dashboard/campaigns/${campaign.id}`}
                            className="bg-white rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow overflow-hidden"
                        >
                            <div className="h-2" style={{ backgroundColor: campaign.primary_color || '#16a34a' }} />
                            <div className="p-6">
                                <div className="flex items-start justify-between mb-3">
                                    <h3 className="font-bold text-gray-900 text-lg">{campaign.candidate_name}</h3>
                                    <span className={`text-xs px-2 py-1 rounded-full font-medium ${
                                        campaign.status === 'active' ? 'bg-green-100 text-green-700' :
                                        campaign.status === 'paused' ? 'bg-yellow-100 text-yellow-700' :
                                        campaign.status === 'completed' ? 'bg-gray-100 text-gray-700' :
                                        'bg-blue-100 text-blue-700'
                                    }`}>
                                        {campaign.status}
                                    </span>
                                </div>
                                <p className="text-sm text-gray-500 mb-1">{campaign.position}</p>
                                <p className="text-sm text-gray-500 mb-3">{campaign.constituency || campaign.county} • {campaign.election_year}</p>
                                {campaign.party && (
                                    <p className="text-xs text-gray-400">Party: {campaign.party}</p>
                                )}
                                {campaign.slogan && (
                                    <p className="text-sm italic text-gray-600 mt-3 border-t border-gray-100 pt-3">
                                        "{campaign.slogan}"
                                    </p>
                                )}
                                <div className="mt-4 flex items-center gap-4 text-xs text-gray-400">
                                    <span>👥 {campaign.campaign_members_count || 0} members</span>
                                </div>
                            </div>
                        </Link>
                    ))}
                </div>
            )}
        </div>
    );
}
