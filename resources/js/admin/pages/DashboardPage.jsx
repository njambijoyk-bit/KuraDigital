import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import {
    UsersIcon,
    DocumentTextIcon,
    CalendarDaysIcon,
    NewspaperIcon,
    HandRaisedIcon,
    EnvelopeIcon,
    ClipboardDocumentListIcon,
    PhotoIcon,
    UserIcon,
    MapIcon,
    DocumentChartBarIcon,
    MapPinIcon,
    CameraIcon,
} from '@heroicons/react/24/outline';
import api from '../../lib/api';
import StatsCard from '../components/StatsCard';

export default function DashboardPage() {
    const { campaignId } = useParams();
    const [campaign, setCampaign] = useState(null);
    const [stats, setStats] = useState({});
    const [recentAudit, setRecentAudit] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const load = async () => {
            setLoading(true);
            try {
                const [campRes, membersRes, manifestoRes, eventsRes, newsRes, volunteersRes, contactsRes, votersRes, auditRes, agentsRes, surveysRes, checkInsRes, fieldReportsRes] =
                    await Promise.allSettled([
                        api.get(`/campaigns/${campaignId}`),
                        api.get(`/campaigns/${campaignId}/members`),
                        api.get(`/campaigns/${campaignId}/manifesto`),
                        api.get(`/campaigns/${campaignId}/events`),
                        api.get(`/campaigns/${campaignId}/news`),
                        api.get(`/campaigns/${campaignId}/volunteers`),
                        api.get(`/campaigns/${campaignId}/contacts`),
                        api.get(`/campaigns/${campaignId}/voters/stats`),
                        api.get(`/campaigns/${campaignId}/audit-logs`),
                        api.get(`/campaigns/${campaignId}/field-agents`),
                        api.get(`/campaigns/${campaignId}/surveys`),
                        api.get(`/campaigns/${campaignId}/check-ins`),
                        api.get(`/campaigns/${campaignId}/field-reports/stats`).catch(() => null),
                    ]);

                if (campRes.status === 'fulfilled') setCampaign(campRes.value.data.campaign);

                setStats({
                    members: membersRes.status === 'fulfilled' ? (membersRes.value.data.data?.length || 0) : 0,
                    manifesto: manifestoRes.status === 'fulfilled' ? (manifestoRes.value.data.pillars?.length || manifestoRes.value.data.data?.length || 0) : 0,
                    events: eventsRes.status === 'fulfilled' ? (eventsRes.value.data.data?.length || 0) : 0,
                    news: newsRes.status === 'fulfilled' ? (newsRes.value.data.data?.length || 0) : 0,
                    volunteers: volunteersRes.status === 'fulfilled' ? (volunteersRes.value.data.meta?.total || volunteersRes.value.data.data?.length || 0) : 0,
                    contacts: contactsRes.status === 'fulfilled' ? (contactsRes.value.data.meta?.total || contactsRes.value.data.data?.length || 0) : 0,
                    voters: votersRes.status === 'fulfilled' ? (votersRes.value.data.total || 0) : 0,
                    fieldAgents: agentsRes.status === 'fulfilled' ? (agentsRes.value.data.meta?.total || agentsRes.value.data.total || agentsRes.value.data.data?.length || 0) : 0,
                    surveys: surveysRes.status === 'fulfilled' ? (surveysRes.value.data.meta?.total || surveysRes.value.data.total || surveysRes.value.data.data?.length || 0) : 0,
                    checkIns: checkInsRes.status === 'fulfilled' ? (checkInsRes.value.data.meta?.total || checkInsRes.value.data.total || checkInsRes.value.data.data?.length || 0) : 0,
                    fieldReports: fieldReportsRes.status === 'fulfilled' && fieldReportsRes.value?.data?.stats ? fieldReportsRes.value.data.stats.total : 0,
                });

                if (auditRes.status === 'fulfilled') {
                    setRecentAudit((auditRes.value.data.data || []).slice(0, 10));
                }
            } catch {
                // handled by interceptor
            }
            setLoading(false);
        };
        load();
    }, [campaignId]);

    if (loading) {
        return (
            <div className="flex items-center justify-center py-20">
                <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Campaign header */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 className="text-xl font-heading font-bold text-gray-900">{campaign?.name}</h2>
                <div className="flex flex-wrap gap-2 mt-2">
                    <span className="px-2.5 py-0.5 bg-primary-50 text-primary-700 text-xs font-medium rounded-full capitalize">
                        {campaign?.election_type?.replace('_', ' ')}
                    </span>
                    <span className="px-2.5 py-0.5 bg-gray-100 text-gray-600 text-xs font-medium rounded-full capitalize">
                        {campaign?.level}
                    </span>
                    {campaign?.county && (
                        <span className="px-2.5 py-0.5 bg-blue-50 text-blue-600 text-xs font-medium rounded-full">
                            {campaign.county}
                        </span>
                    )}
                </div>
                {campaign?.description && (
                    <p className="text-sm text-gray-500 mt-3">{campaign.description}</p>
                )}
            </div>

            {/* Stats grid */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <StatsCard title="Team Members" value={stats.members} icon={UsersIcon} color="primary" />
                <StatsCard title="Manifesto Pillars" value={stats.manifesto} icon={DocumentTextIcon} color="blue" />
                <StatsCard title="Events" value={stats.events} icon={CalendarDaysIcon} color="accent" />
                <StatsCard title="News Articles" value={stats.news} icon={NewspaperIcon} color="purple" />
                <StatsCard title="Voters" value={stats.voters} icon={UserIcon} color="blue" />
                <StatsCard title="Volunteers" value={stats.volunteers} icon={HandRaisedIcon} color="primary" />
                <StatsCard title="Messages" value={stats.contacts} icon={EnvelopeIcon} color="red" />
            </div>

            {/* Field Operations stats */}
            <div>
                <h3 className="font-heading font-semibold text-gray-900 mb-3">Field Operations</h3>
                <div className="grid grid-cols-2 lg:grid-cols-3 gap-4">
                    <StatsCard title="Field Agents" value={stats.fieldAgents} icon={MapIcon} color="accent" />
                    <StatsCard title="Surveys" value={stats.surveys} icon={DocumentChartBarIcon} color="purple" />
                    <StatsCard title="Check-ins" value={stats.checkIns} icon={MapPinIcon} color="blue" />
                    <StatsCard title="Field Reports" value={stats.fieldReports} icon={CameraIcon} color="orange" />
                </div>
            </div>

            {/* Quick actions */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                {[
                    { to: 'manifesto', icon: DocumentTextIcon, label: 'Manage Manifesto', color: 'text-blue-600 bg-blue-50' },
                    { to: 'events', icon: CalendarDaysIcon, label: 'Manage Events', color: 'text-accent-600 bg-accent-50' },
                    { to: 'voters', icon: UserIcon, label: 'Manage Voters', color: 'text-green-600 bg-green-50' },
                    { to: 'field-ops', icon: MapIcon, label: 'Field Operations', color: 'text-orange-600 bg-orange-50' },
                    { to: 'field-reports', icon: CameraIcon, label: 'Field Reports', color: 'text-teal-600 bg-teal-50' },
                    { to: 'surveys', icon: DocumentChartBarIcon, label: 'Surveys', color: 'text-purple-600 bg-purple-50' },
                    { to: 'news', icon: NewspaperIcon, label: 'Write News', color: 'text-purple-600 bg-purple-50' },
                    { to: 'team', icon: UsersIcon, label: 'Manage Team', color: 'text-primary-600 bg-primary-50' },
                    { to: 'volunteers', icon: HandRaisedIcon, label: 'Volunteers', color: 'text-teal-600 bg-teal-50' },
                ].map((action) => (
                    <Link
                        key={action.to}
                        to={action.to}
                        className="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center space-x-3 hover:shadow-md transition-shadow"
                    >
                        <div className={`p-2 rounded-lg ${action.color}`}>
                            <action.icon className="h-5 w-5" />
                        </div>
                        <span className="text-sm font-medium text-gray-700">{action.label}</span>
                    </Link>
                ))}
            </div>

            {/* Recent activity */}
            {recentAudit.length > 0 && (
                <div className="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 className="font-heading font-semibold text-gray-900">Recent Activity</h3>
                        <Link to="audit" className="text-sm text-primary-600 hover:text-primary-700 font-medium">
                            View All
                        </Link>
                    </div>
                    <div className="divide-y divide-gray-50">
                        {recentAudit.map((log) => (
                            <div key={log.id} className="px-5 py-3 flex items-center justify-between">
                                <div className="flex items-center space-x-3 min-w-0">
                                    <ClipboardDocumentListIcon className="h-4 w-4 text-gray-400 flex-shrink-0" />
                                    <div className="min-w-0">
                                        <p className="text-sm text-gray-700 truncate">
                                            <span className="font-medium">{log.user?.name || 'System'}</span>
                                            {' '}{log.action}{' '}
                                            <span className="text-gray-500">{log.auditable_type?.split('\\').pop()}</span>
                                        </p>
                                    </div>
                                </div>
                                <span className="text-xs text-gray-400 flex-shrink-0 ml-3">
                                    {new Date(log.created_at).toLocaleDateString('en-KE', {
                                        day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit',
                                    })}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}
