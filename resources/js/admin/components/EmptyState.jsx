import React from 'react';

export default function EmptyState({ icon: Icon, title, description, action }) {
    return (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
            {Icon && <Icon className="h-12 w-12 text-gray-300 mx-auto mb-4" />}
            <h3 className="text-lg font-medium text-gray-900 mb-1">{title}</h3>
            {description && <p className="text-sm text-gray-500 mb-4">{description}</p>}
            {action}
        </div>
    );
}
