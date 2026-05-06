import React, { useState, useEffect } from 'react';
import { MapContainer, TileLayer, CircleMarker, Popup } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import api from '../../../lib/api';

const LAYER_COLORS = {
    check_ins: '#3B82F6',
    field_reports: '#10B981',
    polling_stations: '#EF4444',
    incidents: '#F59E0B',
    interactions: '#8B5CF6',
};

const DEFAULT_CENTER = [-1.286389, 36.817223];

export default function DashboardMap({ campaignId }) {
    const [points, setPoints] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const load = async () => {
            try {
                const { data } = await api.get(`/campaigns/${campaignId}/map-data`, {
                    params: { layers: 'check_ins,field_reports,polling_stations,incidents' },
                });
                const allPoints = [];
                const mapData = data.map_data || {};

                Object.entries(mapData).forEach(([layer, items]) => {
                    if (Array.isArray(items)) {
                        items.forEach((item) => {
                            if (item.lat && item.lng) {
                                allPoints.push({
                                    id: `${layer}-${item.id}`,
                                    lat: item.lat,
                                    lng: item.lng,
                                    label: item.label,
                                    layer,
                                    color: LAYER_COLORS[layer] || '#6B7280',
                                });
                            }
                        });
                    }
                });

                setPoints(allPoints);
            } catch { /* handled */ }
            setLoading(false);
        };
        load();
    }, [campaignId]);

    return (
        <div className="rounded-lg overflow-hidden border" style={{ height: 300 }}>
            {loading ? (
                <div className="flex items-center justify-center h-full bg-gray-50">
                    <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-primary-600" />
                </div>
            ) : (
                <MapContainer
                    center={DEFAULT_CENTER}
                    zoom={7}
                    style={{ height: '100%', width: '100%' }}
                    scrollWheelZoom={false}
                    zoomControl={false}
                >
                    <TileLayer
                        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
                        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                    />
                    {points.slice(0, 200).map((p) => (
                        <CircleMarker
                            key={p.id}
                            center={[p.lat, p.lng]}
                            radius={4}
                            pathOptions={{ fillColor: p.color, fillOpacity: 0.7, color: p.color, weight: 1 }}
                        >
                            <Popup>
                                <div className="text-sm">
                                    <div className="font-medium">{p.label}</div>
                                    <div className="text-xs text-gray-500 capitalize">{p.layer.replace('_', ' ')}</div>
                                </div>
                            </Popup>
                        </CircleMarker>
                    ))}
                </MapContainer>
            )}
        </div>
    );
}
