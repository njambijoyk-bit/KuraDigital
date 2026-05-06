import React from 'react';
import {
    MapPinIcon,
    DocumentTextIcon,
    BuildingOffice2Icon,
    ExclamationTriangleIcon,
    ChatBubbleLeftRightIcon,
    FireIcon,
} from '@heroicons/react/24/outline';

const LAYERS = [
    { id: 'check_ins', label: 'Agent Check-ins', icon: MapPinIcon, color: '#3B82F6' },
    { id: 'field_reports', label: 'Field Reports', icon: DocumentTextIcon, color: '#10B981' },
    { id: 'polling_stations', label: 'Polling Stations', icon: BuildingOffice2Icon, color: '#EF4444' },
    { id: 'incidents', label: 'Incidents', icon: ExclamationTriangleIcon, color: '#F59E0B' },
    { id: 'interactions', label: 'Voter Interactions', icon: ChatBubbleLeftRightIcon, color: '#8B5CF6' },
];

export default function MapLayerControl({ activeLayers, onToggleLayer, showHeatmap, onToggleHeatmap, dateFrom, dateTo, onDateChange, counts }) {
    return (
        <div className="bg-white rounded-lg border shadow-sm p-4 space-y-4">
            <h3 className="font-semibold text-sm text-gray-900">Map Layers</h3>

            <div className="space-y-2">
                {LAYERS.map((layer) => {
                    const Icon = layer.icon;
                    const isActive = activeLayers.includes(layer.id);
                    const count = counts?.[layer.id] ?? 0;
                    return (
                        <label
                            key={layer.id}
                            className={`flex items-center gap-3 p-2 rounded-lg cursor-pointer transition-colors ${isActive ? 'bg-gray-50' : 'hover:bg-gray-50'}`}
                        >
                            <input
                                type="checkbox"
                                checked={isActive}
                                onChange={() => onToggleLayer(layer.id)}
                                className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                            />
                            <span className="w-3 h-3 rounded-full flex-shrink-0" style={{ backgroundColor: layer.color }} />
                            <Icon className="h-4 w-4 text-gray-500 flex-shrink-0" />
                            <span className="text-sm text-gray-700 flex-1">{layer.label}</span>
                            <span className="text-xs text-gray-400 font-mono">{count}</span>
                        </label>
                    );
                })}
            </div>

            <div className="border-t pt-3">
                <label className="flex items-center gap-3 p-2 rounded-lg cursor-pointer hover:bg-gray-50">
                    <input
                        type="checkbox"
                        checked={showHeatmap}
                        onChange={onToggleHeatmap}
                        className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    />
                    <FireIcon className="h-4 w-4 text-orange-500" />
                    <span className="text-sm text-gray-700">Heatmap View</span>
                </label>
            </div>

            <div className="border-t pt-3 space-y-2">
                <h4 className="text-xs font-medium text-gray-500 uppercase">Date Range</h4>
                <div>
                    <label className="block text-xs text-gray-500 mb-1">From</label>
                    <input
                        type="date"
                        value={dateFrom}
                        onChange={(e) => onDateChange('from', e.target.value)}
                        className="w-full border rounded px-2 py-1.5 text-sm"
                    />
                </div>
                <div>
                    <label className="block text-xs text-gray-500 mb-1">To</label>
                    <input
                        type="date"
                        value={dateTo}
                        onChange={(e) => onDateChange('to', e.target.value)}
                        className="w-full border rounded px-2 py-1.5 text-sm"
                    />
                </div>
            </div>
        </div>
    );
}

export { LAYERS };
