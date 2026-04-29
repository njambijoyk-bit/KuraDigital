import React from 'react';
import { NavLink, useParams } from 'react-router-dom';
import {
    HomeIcon,
    UsersIcon,
    CogIcon,
    DocumentTextIcon,
    CalendarDaysIcon,
    NewspaperIcon,
    PhotoIcon,
    FolderIcon,
    HandRaisedIcon,
    EnvelopeIcon,
    FilmIcon,
    ClipboardDocumentListIcon,
    BuildingOffice2Icon,
    ChevronLeftIcon,
    UserGroupIcon,
} from '@heroicons/react/24/outline';

const campaignNav = [
    { to: '', icon: HomeIcon, label: 'Dashboard', end: true },
    { to: 'team', icon: UsersIcon, label: 'Team' },
    { to: 'site', icon: CogIcon, label: 'Site Settings' },
    { to: 'manifesto', icon: DocumentTextIcon, label: 'Manifesto' },
    { to: 'events', icon: CalendarDaysIcon, label: 'Events' },
    { to: 'news', icon: NewspaperIcon, label: 'News' },
    { to: 'gallery', icon: PhotoIcon, label: 'Gallery' },
    { to: 'projects', icon: FolderIcon, label: 'Projects' },
    { to: 'volunteers', icon: HandRaisedIcon, label: 'Volunteers' },
    { to: 'contacts', icon: EnvelopeIcon, label: 'Contacts' },
    { to: 'opponents', icon: UserGroupIcon, label: 'Opponents' },
    { to: 'media', icon: FilmIcon, label: 'Media Library' },
    { to: 'audit', icon: ClipboardDocumentListIcon, label: 'Audit Log' },
];

export default function Sidebar() {
    const { campaignId } = useParams();
    const base = `/admin/campaigns/${campaignId}`;

    return (
        <aside className="w-64 bg-dark-900 text-white flex flex-col min-h-screen fixed left-0 top-0 z-40">
            <div className="px-5 py-5 border-b border-white/10">
                <NavLink to="/admin" className="flex items-center space-x-2">
                    <BuildingOffice2Icon className="h-8 w-8 text-primary-400" />
                    <span className="font-heading font-bold text-xl text-white">KuraDigital</span>
                </NavLink>
            </div>

            <div className="px-3 pt-4 pb-2">
                <NavLink
                    to="/admin"
                    className="flex items-center space-x-2 text-sm text-gray-400 hover:text-white transition-colors px-2 py-1.5 rounded"
                >
                    <ChevronLeftIcon className="h-4 w-4" />
                    <span>All Campaigns</span>
                </NavLink>
            </div>

            <nav className="flex-1 px-3 py-2 space-y-0.5 overflow-y-auto">
                {campaignNav.map((item) => (
                    <NavLink
                        key={item.label}
                        to={`${base}/${item.to}`}
                        end={item.end}
                        className={({ isActive }) =>
                            `flex items-center space-x-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors ${
                                isActive
                                    ? 'bg-primary-600 text-white'
                                    : 'text-gray-300 hover:bg-white/10 hover:text-white'
                            }`
                        }
                    >
                        <item.icon className="h-5 w-5 flex-shrink-0" />
                        <span>{item.label}</span>
                    </NavLink>
                ))}
            </nav>

            <div className="px-5 py-4 border-t border-white/10 text-xs text-gray-500">
                KuraDigital v1.0
            </div>
        </aside>
    );
}
