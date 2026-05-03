import React from 'react';
import { Outlet } from 'react-router-dom';
import Sidebar from './Sidebar';
import Header from './Header';
import InstallPrompt from './InstallPrompt';
import OfflineIndicator from './OfflineIndicator';

export default function AdminLayout({ title }) {
    return (
        <div className="min-h-screen bg-gray-50">
            <Sidebar />
            <div className="ml-64 flex flex-col min-h-screen">
                <Header title={title} />
                <div className="px-6 pt-2">
                    <OfflineIndicator />
                </div>
                <main className="flex-1 p-6">
                    <Outlet />
                </main>
            </div>
            <InstallPrompt />
        </div>
    );
}
