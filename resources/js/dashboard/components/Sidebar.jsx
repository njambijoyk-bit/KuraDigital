import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import useAuthStore from '../stores/useAuthStore';
import useCampaignStore from '../stores/useCampaignStore';

const navItems = [
    { path: '/dashboard', label: 'Overview', icon: '📊' },
    { path: '/dashboard/campaigns', label: 'Campaigns', icon: '🏛️' },
    { path: '/dashboard/team', label: 'Team', icon: '👥' },
    { path: '/dashboard/research', label: 'Research', icon: '🔍', disabled: true },
    { path: '/dashboard/narrative', label: 'SEO & Narrative', icon: '📢', disabled: true },
    { path: '/dashboard/tallying', label: 'Vote Tallying', icon: '🗳️', disabled: true },
    { path: '/dashboard/finance', label: 'Finance', icon: '💰', disabled: true },
    { path: '/dashboard/analytics', label: 'Analytics', icon: '📈', disabled: true },
];

export default function Sidebar() {
    const location = useLocation();
    const { user, logout } = useAuthStore();
    const { currentCampaign } = useCampaignStore();

    return (
        <aside className="w-64 bg-gray-900 text-white min-h-screen flex flex-col">
            <div className="p-4 border-b border-gray-700">
                <Link to="/dashboard" className="text-xl font-bold text-green-400">
                    Kura Digital
                </Link>
                <p className="text-xs text-gray-400 mt-1">Campaign Manager</p>
            </div>

            {currentCampaign && (
                <div className="px-4 py-3 border-b border-gray-700 bg-gray-800">
                    <p className="text-xs text-gray-400 uppercase tracking-wider">Active Campaign</p>
                    <p className="text-sm font-medium text-white truncate">{currentCampaign.candidate_name}</p>
                    <p className="text-xs text-gray-400">{currentCampaign.constituency || currentCampaign.county}</p>
                </div>
            )}

            <nav className="flex-1 py-4">
                {navItems.map((item) => {
                    const isActive = location.pathname === item.path ||
                        (item.path !== '/dashboard' && location.pathname.startsWith(item.path));
                    return (
                        <Link
                            key={item.path}
                            to={item.disabled ? '#' : item.path}
                            className={`flex items-center gap-3 px-4 py-2.5 text-sm transition-colors ${
                                item.disabled
                                    ? 'text-gray-600 cursor-not-allowed'
                                    : isActive
                                    ? 'bg-green-600 text-white'
                                    : 'text-gray-300 hover:bg-gray-800 hover:text-white'
                            }`}
                        >
                            <span>{item.icon}</span>
                            <span>{item.label}</span>
                            {item.disabled && (
                                <span className="ml-auto text-xs bg-gray-700 text-gray-400 px-1.5 py-0.5 rounded">
                                    Soon
                                </span>
                            )}
                        </Link>
                    );
                })}
            </nav>

            <div className="p-4 border-t border-gray-700">
                <div className="flex items-center gap-3 mb-3">
                    <div className="w-8 h-8 rounded-full bg-green-600 flex items-center justify-center text-sm font-bold">
                        {user?.name?.charAt(0)?.toUpperCase()}
                    </div>
                    <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium truncate">{user?.name}</p>
                        <p className="text-xs text-gray-400 truncate">{user?.email}</p>
                    </div>
                </div>
                <button
                    onClick={logout}
                    className="w-full text-left text-sm text-gray-400 hover:text-red-400 transition-colors"
                >
                    Sign Out
                </button>
            </div>
        </aside>
    );
}
