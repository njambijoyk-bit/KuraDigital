import React from 'react';

const LEGEND_ITEMS = [
    { label: 'Agent Check-in', color: '#3B82F6', shape: 'circle' },
    { label: 'Photo Report', color: '#10B981', shape: 'circle' },
    { label: 'Audio Report', color: '#8B5CF6', shape: 'circle' },
    { label: 'Video Report', color: '#F59E0B', shape: 'circle' },
    { label: 'Text Report', color: '#6B7280', shape: 'circle' },
    { label: 'Polling Station', color: '#EF4444', shape: 'square' },
    { label: 'Incident (Low)', color: '#FBBF24', shape: 'triangle' },
    { label: 'Incident (Medium)', color: '#F97316', shape: 'triangle' },
    { label: 'Incident (High)', color: '#EF4444', shape: 'triangle' },
    { label: 'Incident (Critical)', color: '#111827', shape: 'triangle' },
    { label: 'Voter Interaction', color: '#8B5CF6', shape: 'diamond' },
];

export default function MapLegend() {
    return (
        <div className="bg-white/95 backdrop-blur-sm rounded-lg border shadow-sm p-3">
            <h4 className="text-xs font-semibold text-gray-700 uppercase mb-2">Legend</h4>
            <div className="grid grid-cols-2 gap-x-4 gap-y-1">
                {LEGEND_ITEMS.map((item) => (
                    <div key={item.label} className="flex items-center gap-2">
                        {item.shape === 'circle' && (
                            <span className="w-3 h-3 rounded-full flex-shrink-0" style={{ backgroundColor: item.color }} />
                        )}
                        {item.shape === 'square' && (
                            <span className="w-3 h-3 rounded-sm flex-shrink-0" style={{ backgroundColor: item.color }} />
                        )}
                        {item.shape === 'triangle' && (
                            <span className="flex-shrink-0" style={{ width: 0, height: 0, borderLeft: '6px solid transparent', borderRight: '6px solid transparent', borderBottom: `10px solid ${item.color}` }} />
                        )}
                        {item.shape === 'diamond' && (
                            <span className="w-3 h-3 flex-shrink-0 rotate-45" style={{ backgroundColor: item.color }} />
                        )}
                        <span className="text-xs text-gray-600">{item.label}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}
