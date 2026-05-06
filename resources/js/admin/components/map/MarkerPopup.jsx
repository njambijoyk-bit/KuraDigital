import React from 'react';

const severityColors = {
    low: 'bg-yellow-100 text-yellow-800',
    medium: 'bg-orange-100 text-orange-800',
    high: 'bg-red-100 text-red-800',
    critical: 'bg-gray-900 text-white',
};

const outcomeColors = {
    positive: 'bg-green-100 text-green-800',
    negative: 'bg-red-100 text-red-800',
    neutral: 'bg-gray-100 text-gray-800',
    undecided: 'bg-yellow-100 text-yellow-800',
};

function formatTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString();
}

export function CheckInPopup({ data }) {
    return (
        <div className="text-sm max-w-xs">
            <div className="font-semibold text-gray-900">{data.label}</div>
            <div className="text-xs text-gray-500 mt-1">
                {data.status && <span className="capitalize">{data.status}</span>}
                {data.ward && <span> &middot; {data.ward}</span>}
            </div>
            {data.location_name && <div className="text-xs text-gray-600 mt-1">{data.location_name}</div>}
            {data.notes && <div className="text-xs text-gray-600 mt-1 italic">{data.notes}</div>}
            {data.time && <div className="text-xs text-gray-400 mt-1">{formatTime(data.time)}</div>}
        </div>
    );
}

export function FieldReportPopup({ data }) {
    const typeColors = { photo: '#10B981', audio: '#8B5CF6', video: '#F59E0B', text: '#6B7280' };
    return (
        <div className="text-sm max-w-xs">
            <div className="flex items-center gap-2">
                <span className="w-2 h-2 rounded-full flex-shrink-0" style={{ backgroundColor: typeColors[data.type] || '#6B7280' }} />
                <span className="font-semibold text-gray-900">{data.label}</span>
            </div>
            <div className="text-xs text-gray-500 mt-1">
                <span className="capitalize">{data.type}</span>
                {data.ward && <span> &middot; {data.ward}</span>}
                {data.status && <span> &middot; {data.status}</span>}
            </div>
            {data.agent && <div className="text-xs text-gray-600 mt-1">Agent: {data.agent}</div>}
            {data.time && <div className="text-xs text-gray-400 mt-1">{formatTime(data.time)}</div>}
        </div>
    );
}

export function PollingStationPopup({ data }) {
    return (
        <div className="text-sm max-w-xs">
            <div className="font-semibold text-gray-900">{data.label}</div>
            {data.code && <div className="text-xs text-gray-500">Code: {data.code}</div>}
            <div className="text-xs text-gray-500 mt-1">
                {data.ward && <span>{data.ward}</span>}
                {data.registered_voters > 0 && <span> &middot; {data.registered_voters.toLocaleString()} voters</span>}
            </div>
            {data.assigned_agent && <div className="text-xs text-gray-600 mt-1">Agent: {data.assigned_agent}</div>}
            {data.status && (
                <span className={`inline-block mt-1 text-xs px-2 py-0.5 rounded-full capitalize ${data.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                    {data.status}
                </span>
            )}
        </div>
    );
}

export function IncidentPopup({ data }) {
    return (
        <div className="text-sm max-w-xs">
            <div className="font-semibold text-gray-900">{data.label}</div>
            <div className="flex items-center gap-2 mt-1">
                {data.severity && (
                    <span className={`text-xs px-2 py-0.5 rounded-full capitalize ${severityColors[data.severity] || severityColors.low}`}>
                        {data.severity}
                    </span>
                )}
                {data.category && <span className="text-xs text-gray-500 capitalize">{data.category}</span>}
            </div>
            {data.ward && <div className="text-xs text-gray-500 mt-1">{data.ward}</div>}
            {data.reporter && <div className="text-xs text-gray-600 mt-1">Reported by: {data.reporter}</div>}
            {data.status && <div className="text-xs text-gray-500 mt-1 capitalize">Status: {data.status}</div>}
            {data.time && <div className="text-xs text-gray-400 mt-1">{formatTime(data.time)}</div>}
        </div>
    );
}

export function InteractionPopup({ data }) {
    return (
        <div className="text-sm max-w-xs">
            <div className="font-semibold text-gray-900">{data.label}</div>
            <div className="flex items-center gap-2 mt-1">
                {data.type && <span className="text-xs text-gray-500 capitalize">{data.type}</span>}
                {data.outcome && (
                    <span className={`text-xs px-2 py-0.5 rounded-full capitalize ${outcomeColors[data.outcome] || outcomeColors.neutral}`}>
                        {data.outcome}
                    </span>
                )}
            </div>
            {data.ward && <div className="text-xs text-gray-500 mt-1">{data.ward}</div>}
            {data.agent && <div className="text-xs text-gray-600 mt-1">Agent: {data.agent}</div>}
            {data.time && <div className="text-xs text-gray-400 mt-1">{formatTime(data.time)}</div>}
        </div>
    );
}
