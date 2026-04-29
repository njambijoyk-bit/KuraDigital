import React from 'react';

export default function StatsCard({ title, value, icon: Icon, color = 'primary', subtitle }) {
    const colors = {
        primary: 'bg-primary-50 text-primary-600',
        accent: 'bg-accent-50 text-accent-600',
        blue: 'bg-blue-50 text-blue-600',
        purple: 'bg-purple-50 text-purple-600',
        red: 'bg-red-50 text-red-600',
        gray: 'bg-gray-100 text-gray-600',
    };

    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-sm font-medium text-gray-500">{title}</p>
                    <p className="text-2xl font-bold text-gray-900 mt-1">{value}</p>
                    {subtitle && <p className="text-xs text-gray-400 mt-1">{subtitle}</p>}
                </div>
                {Icon && (
                    <div className={`p-2.5 rounded-lg ${colors[color]}`}>
                        <Icon className="h-5 w-5" />
                    </div>
                )}
            </div>
        </div>
    );
}
