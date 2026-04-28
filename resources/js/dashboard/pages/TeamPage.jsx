import React, { useEffect } from 'react';
import useCampaignStore from '../stores/useCampaignStore';

const roleLabels = {
    owner: 'Owner',
    manager: 'Campaign Manager',
    researcher: 'Researcher',
    field_agent: 'Field Agent',
    analyst: 'Analyst',
    coordinator: 'Coordinator',
};

export default function TeamPage() {
    const { currentCampaign, campaigns, members, fetchMembers, fetchCampaigns } = useCampaignStore();

    useEffect(() => {
        if (!campaigns.length) fetchCampaigns();
    }, [campaigns.length, fetchCampaigns]);

    useEffect(() => {
        if (currentCampaign?.id) fetchMembers(currentCampaign.id);
    }, [currentCampaign?.id, fetchMembers]);

    if (!currentCampaign) {
        return (
            <div className="p-8">
                <h1 className="text-2xl font-bold text-gray-900 mb-2">Team</h1>
                <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
                    <p className="text-gray-500">Select a campaign first to view its team members.</p>
                    <p className="text-sm text-gray-400 mt-2">Go to Campaigns → select a campaign to set it as active.</p>
                </div>
            </div>
        );
    }

    const groupedByRole = members.reduce((acc, member) => {
        const role = member.pivot?.role || 'unknown';
        if (!acc[role]) acc[role] = [];
        acc[role].push(member);
        return acc;
    }, {});

    return (
        <div className="p-8">
            <div className="mb-8">
                <h1 className="text-2xl font-bold text-gray-900">Team</h1>
                <p className="text-gray-500 mt-1">{currentCampaign.candidate_name} — {members.length} member(s)</p>
            </div>

            <div className="space-y-6">
                {Object.entries(groupedByRole).map(([role, roleMembers]) => (
                    <div key={role} className="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div className="px-6 py-4 border-b border-gray-200">
                            <h2 className="font-bold text-gray-900">
                                {roleLabels[role] || role} ({roleMembers.length})
                            </h2>
                        </div>
                        <div className="divide-y divide-gray-100">
                            {roleMembers.map((member) => (
                                <div key={member.id} className="flex items-center justify-between p-4 px-6">
                                    <div className="flex items-center gap-3">
                                        <div className="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center text-sm font-bold text-green-700">
                                            {member.name?.charAt(0)?.toUpperCase()}
                                        </div>
                                        <div>
                                            <p className="font-medium text-gray-900">{member.name}</p>
                                            <p className="text-sm text-gray-500">{member.email}</p>
                                            {member.phone && <p className="text-sm text-gray-400">{member.phone}</p>}
                                        </div>
                                    </div>
                                    <span className={`text-xs px-2 py-1 rounded-full ${
                                        member.pivot?.status === 'active' ? 'bg-green-50 text-green-600' :
                                        member.pivot?.status === 'invited' ? 'bg-yellow-50 text-yellow-600' :
                                        'bg-red-50 text-red-600'
                                    }`}>
                                        {member.pivot?.status}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
