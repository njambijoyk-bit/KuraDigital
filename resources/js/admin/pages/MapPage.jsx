import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { useParams, Link } from 'react-router-dom';
import { MapContainer, TileLayer, Marker, Popup, CircleMarker } from 'react-leaflet';
import MarkerClusterGroup from 'react-leaflet-cluster';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import api from '../../lib/api';
import MapLayerControl from '../components/map/MapLayerControl';
import MapLegend from '../components/map/MapLegend';
import HeatmapLayer from '../components/map/HeatmapLayer';
import {
    CheckInPopup,
    FieldReportPopup,
    PollingStationPopup,
    IncidentPopup,
    InteractionPopup,
} from '../components/map/MarkerPopup';
import { ArrowsPointingOutIcon, ArrowsPointingInIcon } from '@heroicons/react/24/outline';

// Fix default Leaflet marker icon paths
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
});

function createColoredIcon(color) {
    return L.divIcon({
        className: 'custom-marker',
        html: `<div style="background:${color};width:12px;height:12px;border-radius:50%;border:2px solid white;box-shadow:0 1px 3px rgba(0,0,0,0.3);"></div>`,
        iconSize: [12, 12],
        iconAnchor: [6, 6],
    });
}

function createSquareIcon(color) {
    return L.divIcon({
        className: 'custom-marker',
        html: `<div style="background:${color};width:14px;height:14px;border-radius:2px;border:2px solid white;box-shadow:0 1px 3px rgba(0,0,0,0.3);"></div>`,
        iconSize: [14, 14],
        iconAnchor: [7, 7],
    });
}

function createTriangleIcon(color) {
    return L.divIcon({
        className: 'custom-marker',
        html: `<div style="width:0;height:0;border-left:8px solid transparent;border-right:8px solid transparent;border-bottom:14px solid ${color};filter:drop-shadow(0 1px 2px rgba(0,0,0,0.3));"></div>`,
        iconSize: [16, 14],
        iconAnchor: [8, 14],
    });
}

const REPORT_TYPE_COLORS = { photo: '#10B981', audio: '#8B5CF6', video: '#F59E0B', text: '#6B7280' };
const SEVERITY_COLORS = { low: '#FBBF24', medium: '#F97316', high: '#EF4444', critical: '#111827' };

const DEFAULT_CENTER = [-1.286389, 36.817223]; // Nairobi
const DEFAULT_ZOOM = 7;

export default function MapPage() {
    const { campaignId } = useParams();
    const [mapData, setMapData] = useState({});
    const [activeLayers, setActiveLayers] = useState(['check_ins', 'field_reports', 'polling_stations', 'incidents', 'interactions']);
    const [showHeatmap, setShowHeatmap] = useState(false);
    const [loading, setLoading] = useState(true);
    const [showLegend, setShowLegend] = useState(true);
    const [showControls, setShowControls] = useState(true);
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    const fetchMapData = useCallback(async () => {
        setLoading(true);
        try {
            const params = { layers: activeLayers.join(',') };
            if (dateFrom) params.date_from = dateFrom;
            if (dateTo) params.date_to = dateTo;
            const { data } = await api.get(`/campaigns/${campaignId}/map-data`, { params });
            setMapData(data.map_data || {});
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, activeLayers, dateFrom, dateTo]);

    useEffect(() => { fetchMapData(); }, [fetchMapData]);

    const handleToggleLayer = (layerId) => {
        setActiveLayers((prev) =>
            prev.includes(layerId) ? prev.filter((l) => l !== layerId) : [...prev, layerId]
        );
    };

    const handleDateChange = (field, value) => {
        if (field === 'from') setDateFrom(value);
        else setDateTo(value);
    };

    const counts = useMemo(() => ({
        check_ins: mapData.check_ins?.length || 0,
        field_reports: mapData.field_reports?.length || 0,
        polling_stations: mapData.polling_stations?.length || 0,
        incidents: mapData.incidents?.length || 0,
        interactions: mapData.interactions?.length || 0,
    }), [mapData]);

    const totalPoints = Object.values(counts).reduce((a, b) => a + b, 0);

    const heatmapPoints = useMemo(() => {
        if (!showHeatmap) return [];
        const points = [];
        Object.values(mapData).forEach((layer) => {
            if (Array.isArray(layer)) {
                layer.forEach((item) => {
                    if (item.lat && item.lng) {
                        points.push({ lat: item.lat, lng: item.lng, intensity: 0.5 });
                    }
                });
            }
        });
        return points;
    }, [showHeatmap, mapData]);

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-heading font-bold text-gray-900">Campaign Map</h1>
                <div className="flex items-center gap-3">
                    <span className="text-sm text-gray-500">{totalPoints} data points</span>
                    <button
                        onClick={() => setShowControls(!showControls)}
                        className="inline-flex items-center px-3 py-1.5 border rounded-lg text-sm text-gray-600 hover:bg-gray-50"
                    >
                        {showControls ? <ArrowsPointingInIcon className="h-4 w-4 mr-1" /> : <ArrowsPointingOutIcon className="h-4 w-4 mr-1" />}
                        {showControls ? 'Hide Controls' : 'Show Controls'}
                    </button>
                </div>
            </div>

            <div className="flex gap-4">
                {/* Layer controls sidebar */}
                {showControls && (
                    <div className="w-64 flex-shrink-0">
                        <MapLayerControl
                            activeLayers={activeLayers}
                            onToggleLayer={handleToggleLayer}
                            showHeatmap={showHeatmap}
                            onToggleHeatmap={() => setShowHeatmap(!showHeatmap)}
                            dateFrom={dateFrom}
                            dateTo={dateTo}
                            onDateChange={handleDateChange}
                            counts={counts}
                        />
                    </div>
                )}

                {/* Map */}
                <div className="flex-1 relative">
                    {loading && (
                        <div className="absolute inset-0 bg-white/60 z-[1000] flex items-center justify-center">
                            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" />
                        </div>
                    )}

                    <div className="rounded-lg overflow-hidden border" style={{ height: 'calc(100vh - 200px)' }}>
                        <MapContainer
                            center={DEFAULT_CENTER}
                            zoom={DEFAULT_ZOOM}
                            style={{ height: '100%', width: '100%' }}
                            scrollWheelZoom
                        >
                            <TileLayer
                                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                            />

                            {/* Heatmap */}
                            {showHeatmap && heatmapPoints.length > 0 && (
                                <HeatmapLayer points={heatmapPoints} />
                            )}

                            {/* Agent Check-ins */}
                            {activeLayers.includes('check_ins') && mapData.check_ins?.length > 0 && (
                                <MarkerClusterGroup chunkedLoading maxClusterRadius={40}>
                                    {mapData.check_ins.map((ci) => (
                                        <Marker key={ci.id} position={[ci.lat, ci.lng]} icon={createColoredIcon('#3B82F6')}>
                                            <Popup><CheckInPopup data={ci} /></Popup>
                                        </Marker>
                                    ))}
                                </MarkerClusterGroup>
                            )}

                            {/* Field Reports */}
                            {activeLayers.includes('field_reports') && mapData.field_reports?.length > 0 && (
                                <MarkerClusterGroup chunkedLoading maxClusterRadius={40}>
                                    {mapData.field_reports.map((r) => (
                                        <Marker key={`fr-${r.id}`} position={[r.lat, r.lng]} icon={createColoredIcon(REPORT_TYPE_COLORS[r.type] || '#6B7280')}>
                                            <Popup><FieldReportPopup data={r} /></Popup>
                                        </Marker>
                                    ))}
                                </MarkerClusterGroup>
                            )}

                            {/* Polling Stations */}
                            {activeLayers.includes('polling_stations') && mapData.polling_stations?.length > 0 && (
                                <>
                                    {mapData.polling_stations.map((ps) => (
                                        <Marker key={`ps-${ps.id}`} position={[ps.lat, ps.lng]} icon={createSquareIcon('#EF4444')}>
                                            <Popup><PollingStationPopup data={ps} /></Popup>
                                        </Marker>
                                    ))}
                                </>
                            )}

                            {/* Incidents */}
                            {activeLayers.includes('incidents') && mapData.incidents?.length > 0 && (
                                <>
                                    {mapData.incidents.map((inc) => (
                                        <Marker key={`inc-${inc.id}`} position={[inc.lat, inc.lng]} icon={createTriangleIcon(SEVERITY_COLORS[inc.severity] || '#FBBF24')}>
                                            <Popup><IncidentPopup data={inc} /></Popup>
                                        </Marker>
                                    ))}
                                </>
                            )}

                            {/* Voter Interactions */}
                            {activeLayers.includes('interactions') && mapData.interactions?.length > 0 && (
                                <MarkerClusterGroup chunkedLoading maxClusterRadius={50}>
                                    {mapData.interactions.map((v) => (
                                        <CircleMarker
                                            key={`vi-${v.id}`}
                                            center={[v.lat, v.lng]}
                                            radius={5}
                                            pathOptions={{ fillColor: '#8B5CF6', fillOpacity: 0.7, color: '#7C3AED', weight: 1 }}
                                        >
                                            <Popup><InteractionPopup data={v} /></Popup>
                                        </CircleMarker>
                                    ))}
                                </MarkerClusterGroup>
                            )}
                        </MapContainer>
                    </div>

                    {/* Legend overlay */}
                    {showLegend && (
                        <div className="absolute bottom-4 right-4 z-[999]">
                            <MapLegend />
                            <button
                                onClick={() => setShowLegend(false)}
                                className="absolute -top-1 -right-1 bg-white rounded-full shadow w-5 h-5 text-xs text-gray-400 hover:text-gray-600 flex items-center justify-center"
                            >
                                &times;
                            </button>
                        </div>
                    )}

                    {!showLegend && (
                        <button
                            onClick={() => setShowLegend(true)}
                            className="absolute bottom-4 right-4 z-[999] bg-white rounded-lg border shadow-sm px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-50"
                        >
                            Show Legend
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
