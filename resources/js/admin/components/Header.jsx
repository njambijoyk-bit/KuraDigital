import React from 'react';
import { Menu } from '@headlessui/react';
import { UserCircleIcon, ArrowRightOnRectangleIcon } from '@heroicons/react/24/outline';
import { useNavigate } from 'react-router-dom';
import useAuthStore from '../stores/useAuthStore';

export default function Header({ title }) {
    const user = useAuthStore((s) => s.user);
    const logout = useAuthStore((s) => s.logout);
    const navigate = useNavigate();

    const handleLogout = async () => {
        await logout();
        navigate('/admin/login');
    };

    return (
        <header className="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 sticky top-0 z-30">
            <h1 className="text-lg font-heading font-semibold text-gray-900 truncate">
                {title || 'Dashboard'}
            </h1>

            <Menu as="div" className="relative">
                <Menu.Button className="flex items-center space-x-2 text-sm text-gray-700 hover:text-gray-900 transition-colors">
                    <UserCircleIcon className="h-8 w-8 text-gray-400" />
                    <span className="hidden sm:block font-medium">{user?.name || 'User'}</span>
                </Menu.Button>

                <Menu.Items
                    transition
                    className="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg ring-1 ring-black/5 py-1 focus:outline-none transition ease-out duration-100 data-[closed]:scale-95 data-[closed]:opacity-0"
                >
                    <div className="px-4 py-2 border-b border-gray-100">
                        <p className="text-sm font-medium text-gray-900 truncate">{user?.name}</p>
                        <p className="text-xs text-gray-500 truncate">{user?.email}</p>
                    </div>
                    <Menu.Item>
                        {({ active }) => (
                            <button
                                onClick={handleLogout}
                                className={`${
                                    active ? 'bg-gray-50' : ''
                                } flex items-center space-x-2 w-full px-4 py-2 text-sm text-red-600`}
                            >
                                <ArrowRightOnRectangleIcon className="h-4 w-4" />
                                <span>Sign Out</span>
                            </button>
                        )}
                    </Menu.Item>
                </Menu.Items>
            </Menu>
        </header>
    );
}
