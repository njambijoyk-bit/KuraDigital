import React, { useState, useEffect, useCallback, useRef, useMemo } from 'react';
import { useParams } from 'react-router-dom';
import { MapContainer, TileLayer, Marker, CircleMarker, Popup } from 'react-leaflet';
import MarkerClusterGroup from 'react-leaflet-cluster';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import api from '../../lib/api';
import useSituationRoomChannel from '../hooks/useSituationRoomChannel';
import {
    ArrowsPointingOutIcon,
    ArrowsPointingInIcon,
    ArrowPathIcon,
    SpeakerWaveIcon,
    SpeakerXMarkIcon,
    SignalIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    MapPinIcon,
    UserGroupIcon,
    ChartBarIcon,
    ShieldExclamationIcon,
    BellAlertIcon,
} from '@heroicons/react/24/outline';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
});

const DEFAULT_CENTER = [-1.286389, 36.817223];
const DEFAULT_ZOOM = 7;

const severityColors = {
    low: 'bg-gray-100 text-gray-700',
    medium: 'bg-yellow-50 text-yellow-700',
    high: 'bg-orange-50 text-orange-700',
    critical: 'bg-red-100 text-red-800',
};

const severityDotColors = {
    low: '#9CA3AF',
    medium: '#F59E0B',
    high: '#F97316',
    critical: '#EF4444',
};

const statusColors = {
    pending: '#F59E0B',
    open: '#10B981',
    closed: '#6B7280',
    disputed: '#EF4444',
};

function createStationIcon(status, hasIncidents) {
    const color = hasIncidents ? '#EF4444' : (statusColors[status] || '#6B7280');
    return L.divIcon({
        className: 'custom-marker',
        html: `<div style="background:${color};width:14px;height:14px;border-radius:2px;border:2px solid white;box-shadow:0 1px 3px rgba(0,0,0,0.3);"></div>`,
        iconSize: [14, 14],
        iconAnchor: [7, 7],
    });
}

function createAgentIcon(checkedInAt) {
    const minutesAgo = checkedInAt
        ? (Date.now() - new Date(checkedInAt).getTime()) / 60000
        : Infinity;
    let color = '#EF4444'; // offline (>2h)
    if (minutesAgo <= 30) color = '#10B981'; // active
    else if (minutesAgo <= 120) color = '#F59E0B'; // stale
    return L.divIcon({
        className: 'custom-marker',
        html: `<div style="background:${color};width:12px;height:12px;border-radius:50%;border:2px solid white;box-shadow:0 1px 3px rgba(0,0,0,0.3);"></div>`,
        iconSize: [12, 12],
        iconAnchor: [6, 6],
    });
}

function createIncidentIcon(severity) {
    const color = severityDotColors[severity] || '#F59E0B';
    return L.divIcon({
        className: 'custom-marker',
        html: `<div style="width:0;height:0;border-left:8px solid transparent;border-right:8px solid transparent;border-bottom:14px solid ${color};filter:drop-shadow(0 1px 2px rgba(0,0,0,0.3));"></div>`,
        iconSize: [16, 14],
        iconAnchor: [8, 14],
    });
}

export default function SituationRoomPage() {
    const { campaignId } = useParams();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [isFullscreen, setIsFullscreen] = useState(false);
    const [soundEnabled, setSoundEnabled] = useState(false);
    const [lastUpdated, setLastUpdated] = useState(null);
    const [activities, setActivities] = useState([]);
    const containerRef = useRef(null);
    const debounceRef = useRef(null);

    const fetchData = useCallback(async () => {
        try {
            const { data: resp } = await api.get(`/campaigns/${campaignId}/election-day/situation-room`);
            setData(resp);
            setActivities(resp.activity_stream || []);
            setLastUpdated(new Date());
        } catch {
            /* handled */
        }
        setLoading(false);
    }, [campaignId]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    // Fallback polling every 60s
    useEffect(() => {
        const interval = setInterval(fetchData, 60000);
        return () => clearInterval(interval);
    }, [fetchData]);

    const debouncedRefresh = useCallback(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(fetchData, 5000);
    }, [fetchData]);

    useSituationRoomChannel(campaignId, {
        onSituationRoomUpdate: (e) => {
            if (e.activity) {
                setActivities((prev) => [e.activity, ...prev].slice(0, 50));
            }
            debouncedRefresh();
            if (soundEnabled) {
                try { new Audio('/sounds/notification.mp3').play(); } catch { /* no audio */ }
            }
        },
        onTallySubmitted: () => debouncedRefresh(),
        onTallyVerified: () => debouncedRefresh(),
        onIncidentReported: () => debouncedRefresh(),
    });

    const toggleFullscreen = useCallback(() => {
        if (!document.fullscreenElement) {
            containerRef.current?.requestFullscreen?.();
            setIsFullscreen(true);
        } else {
            document.exitFullscreen?.();
            setIsFullscreen(false);
        }
    }, []);

    useEffect(() => {
        const handleChange = () => setIsFullscreen(!!document.fullscreenElement);
        document.addEventListener('fullscreenchange', handleChange);
        return () => document.removeEventListener('fullscreenchange', handleChange);
    }, []);

    if (loading && !data) {
        return (
            <div className="flex items-center justify-center py-20">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" />
            </div>
        );
    }

    return (
        <div
            ref={containerRef}
            className={`${isFullscreen ? 'bg-gray-950 p-4' : ''} flex flex-col`}
            style={{ height: isFullscreen ? '100vh' : 'calc(100vh - 80px)' }}
        >
            {/* Header Bar */}
            <HeaderBar
                lastUpdated={lastUpdated}
                isFullscreen={isFullscreen}
                soundEnabled={soundEnabled}
                onToggleFullscreen={toggleFullscreen}
                onToggleSound={() => setSoundEnabled(!soundEnabled)}
                onRefresh={fetchData}
                criticalCount={data?.incidents?.critical || 0}
            />

            {/* 4-Quadrant Grid */}
            <div className="flex-1 grid grid-cols-1 lg:grid-cols-2 gap-3 mt-3 min-h-0">
                <TallyBoardQuadrant data={data?.tally_board} />
                <LiveMapQuadrant
                    stations={data?.map?.stations || []}
                    agents={data?.agents?.locations || []}
                    incidents={data?.map?.incidents || []}
                />
                <KeyMetricsQuadrant data={data} />
                <IncidentFeedQuadrant incidents={data?.incidents?.recent || []} />
            </div>

            {/* Activity Ticker */}
            <ActivityTicker activities={activities} />
        </div>
    );
}

// ---------------------------------------------------------------------------
// Header Bar
// ---------------------------------------------------------------------------
function HeaderBar({ lastUpdated, isFullscreen, soundEnabled, onToggleFullscreen, onToggleSound, onRefresh, criticalCount }) {
    const [pulse, setPulse] = useState(true);

    useEffect(() => {
        const interval = setInterval(() => setPulse((p) => !p), 1000);
        return () => clearInterval(interval);
    }, []);

    return (
        <div className={`flex items-center justify-between px-4 py-2 rounded-lg ${isFullscreen ? 'bg-gray-900' : 'bg-white border'}`}>
            <div className="flex items-center space-x-3">
                <div className="flex items-center space-x-2">
                    <div className={`w-2.5 h-2.5 rounded-full ${pulse ? 'bg-red-500' : 'bg-red-400'} animate-pulse`} />
                    <span className={`text-sm font-bold uppercase tracking-wider ${isFullscreen ? 'text-red-400' : 'text-red-600'}`}>
                        LIVE
                    </span>
                </div>
                <span className={`text-xs ${isFullscreen ? 'text-gray-400' : 'text-gray-500'}`}>
                    Situation Room
                </span>
                {criticalCount > 0 && (
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 animate-pulse">
                        <BellAlertIcon className="h-3 w-3 mr-1" />
                        {criticalCount} Critical
                    </span>
                )}
            </div>

            <div className="flex items-center space-x-2">
                {lastUpdated && (
                    <span className={`text-xs ${isFullscreen ? 'text-gray-500' : 'text-gray-400'}`}>
                        Updated {lastUpdated.toLocaleTimeString()}
                    </span>
                )}
                <button
                    onClick={onRefresh}
                    className={`p-1.5 rounded-lg transition-colors ${isFullscreen ? 'text-gray-400 hover:bg-gray-800' : 'text-gray-500 hover:bg-gray-100'}`}
                    title="Refresh"
                >
                    <ArrowPathIcon className="h-4 w-4" />
                </button>
                <button
                    onClick={onToggleSound}
                    className={`p-1.5 rounded-lg transition-colors ${isFullscreen ? 'text-gray-400 hover:bg-gray-800' : 'text-gray-500 hover:bg-gray-100'}`}
                    title={soundEnabled ? 'Mute' : 'Unmute'}
                >
                    {soundEnabled ? <SpeakerWaveIcon className="h-4 w-4" /> : <SpeakerXMarkIcon className="h-4 w-4" />}
                </button>
                <button
                    onClick={onToggleFullscreen}
                    className={`p-1.5 rounded-lg transition-colors ${isFullscreen ? 'text-gray-400 hover:bg-gray-800' : 'text-gray-500 hover:bg-gray-100'}`}
                    title={isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'}
                >
                    {isFullscreen ? <ArrowsPointingInIcon className="h-4 w-4" /> : <ArrowsPointingOutIcon className="h-4 w-4" />}
                </button>
            </div>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Q1: Tally Board
// ---------------------------------------------------------------------------
function TallyBoardQuadrant({ data }) {
    if (!data) return <QuadrantShell title="Tally Board" icon={ChartBarIcon}><EmptyQuadrant /></QuadrantShell>;

    const { candidates, overview } = data;
    const maxVotes = candidates?.length > 0 ? Math.max(...candidates.map((c) => c.total_votes)) : 1;

    return (
        <QuadrantShell title="Tally Board" icon={ChartBarIcon}>
            <div className="space-y-3 overflow-y-auto flex-1">
                {/* Overview bar */}
                <div className="flex items-center justify-between text-xs text-gray-500 px-1">
                    <span>{overview?.reporting_percentage ?? 0}% Reporting ({overview?.reported_stations}/{overview?.total_stations} stations)</span>
                    <span>Turnout: {overview?.turnout_percentage ?? 0}%</span>
                </div>

                {/* Candidate bars */}
                {candidates?.map((c, i) => (
                    <div key={i} className="space-y-1">
                        <div className="flex items-center justify-between text-sm">
                            <div className="flex items-center space-x-2">
                                <span className="font-semibold text-gray-900">{c.candidate_name}</span>
                                {c.party && <span className="text-xs text-gray-500">({c.party})</span>}
                            </div>
                            <span className="font-bold text-gray-900">{(c.total_votes || 0).toLocaleString()}</span>
                        </div>
                        <div className="w-full bg-gray-100 rounded-full h-2.5">
                            <div
                                className={`h-2.5 rounded-full ${i === 0 ? 'bg-primary-600' : i === 1 ? 'bg-blue-400' : 'bg-gray-400'}`}
                                style={{ width: `${maxVotes > 0 ? ((c.total_votes || 0) / maxVotes) * 100 : 0}%` }}
                            />
                        </div>
                        <div className="flex items-center space-x-3 text-xs text-gray-400">
                            <span>{c.stations_reported} stations</span>
                            <span>{c.verified_count} verified</span>
                        </div>
                    </div>
                ))}

                {(!candidates || candidates.length === 0) && (
                    <p className="text-sm text-gray-400 text-center py-4">No tallies yet</p>
                )}
            </div>
        </QuadrantShell>
    );
}

// ---------------------------------------------------------------------------
// Q2: Live Map
// ---------------------------------------------------------------------------
function LiveMapQuadrant({ stations, agents, incidents }) {
    const center = useMemo(() => {
        const allPoints = [
            ...stations.map((s) => [s.latitude, s.longitude]),
            ...agents.map((a) => [a.latitude, a.longitude]),
        ].filter(([lat, lng]) => lat && lng);
        if (allPoints.length === 0) return DEFAULT_CENTER;
        const avgLat = allPoints.reduce((s, p) => s + p[0], 0) / allPoints.length;
        const avgLng = allPoints.reduce((s, p) => s + p[1], 0) / allPoints.length;
        return [avgLat, avgLng];
    }, [stations, agents]);

    return (
        <QuadrantShell title="Live Map" icon={MapPinIcon} noPadding>
            <div className="h-full w-full rounded-b-lg overflow-hidden">
                <MapContainer
                    center={center}
                    zoom={DEFAULT_ZOOM}
                    style={{ height: '100%', width: '100%' }}
                    scrollWheelZoom
                    zoomControl={false}
                >
                    <TileLayer
                        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>'
                        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                    />

                    {/* Stations */}
                    {stations.length > 0 && (
                        <MarkerClusterGroup chunkedLoading maxClusterRadius={30}>
                            {stations.map((s) => (
                                <Marker
                                    key={`st-${s.id}`}
                                    position={[s.latitude, s.longitude]}
                                    icon={createStationIcon(s.status, s.has_incidents)}
                                >
                                    <Popup>
                                        <div className="text-xs">
                                            <p className="font-semibold">{s.name}</p>
                                            <p className="text-gray-500">{s.code} &middot; {s.ward}</p>
                                            <p className="capitalize">{s.status}{s.has_tallies ? ' · Reported' : ''}</p>
                                        </div>
                                    </Popup>
                                </Marker>
                            ))}
                        </MarkerClusterGroup>
                    )}

                    {/* Agents */}
                    {agents.map((a) => (
                        a.latitude && a.longitude ? (
                            <Marker
                                key={`ag-${a.user_id}`}
                                position={[a.latitude, a.longitude]}
                                icon={createAgentIcon(a.checked_in_at)}
                            >
                                <Popup>
                                    <div className="text-xs">
                                        <p className="font-semibold">{a.name}</p>
                                        <p className="text-gray-500">
                                            Last seen: {a.checked_in_at ? new Date(a.checked_in_at).toLocaleTimeString() : 'Unknown'}
                                        </p>
                                    </div>
                                </Popup>
                            </Marker>
                        ) : null
                    ))}

                    {/* Incidents */}
                    {incidents.map((inc) => (
                        <Marker
                            key={`inc-${inc.id}`}
                            position={[inc.latitude, inc.longitude]}
                            icon={createIncidentIcon(inc.severity)}
                        >
                            <Popup>
                                <div className="text-xs">
                                    <p className="font-semibold">{inc.title}</p>
                                    <p className="capitalize text-gray-500">{inc.severity} · {inc.category}</p>
                                </div>
                            </Popup>
                        </Marker>
                    ))}
                </MapContainer>
            </div>

            {/* Map legend */}
            <div className="absolute bottom-2 left-2 bg-white/90 backdrop-blur rounded-lg px-2 py-1.5 text-[10px] space-y-0.5 z-[999]">
                <div className="flex items-center space-x-1"><div className="w-2 h-2 rounded-sm bg-green-500" /><span>Open Station</span></div>
                <div className="flex items-center space-x-1"><div className="w-2 h-2 rounded-full bg-green-500" /><span>Agent (active)</span></div>
                <div className="flex items-center space-x-1"><div className="w-2 h-2 rounded-full bg-yellow-500" /><span>Agent (stale)</span></div>
                <div className="flex items-center space-x-1"><div className="w-2 h-2 rounded-full bg-red-500" /><span>Agent (offline)</span></div>
                <div className="flex items-center space-x-1"><div className="w-0 h-0 border-l-[4px] border-l-transparent border-r-[4px] border-r-transparent border-b-[6px] border-b-red-500" /><span>Incident</span></div>
            </div>
        </QuadrantShell>
    );
}

// ---------------------------------------------------------------------------
// Q3: Key Metrics
// ---------------------------------------------------------------------------
function KeyMetricsQuadrant({ data }) {
    if (!data) return <QuadrantShell title="Key Metrics" icon={SignalIcon}><EmptyQuadrant /></QuadrantShell>;

    const overview = data.tally_board?.overview || {};
    const incidents = data.incidents || {};
    const agents = data.agents || {};
    const tallies = data.tallies || {};

    const leadMargin = useMemo(() => {
        const candidates = data.tally_board?.candidates || [];
        if (candidates.length < 2) return null;
        return candidates[0].total_votes - candidates[1].total_votes;
    }, [data]);

    const metrics = [
        { label: 'Turnout', value: `${overview.turnout_percentage ?? 0}%`, color: 'blue', sub: `${(overview.total_votes_cast || 0).toLocaleString()} / ${(overview.total_registered || 0).toLocaleString()}` },
        { label: 'Reporting', value: `${overview.reporting_percentage ?? 0}%`, color: 'green', sub: `${overview.reported_stations || 0} / ${overview.total_stations || 0} stations` },
        { label: 'Active Agents', value: agents.checked_in || 0, color: 'indigo', sub: `${agents.total || 0} total` },
        { label: 'Unresolved Incidents', value: incidents.unresolved || 0, color: incidents.unresolved > 0 ? 'red' : 'green' },
        { label: 'Critical Incidents', value: incidents.critical || 0, color: incidents.critical > 0 ? 'red' : 'green' },
        { label: 'Unmanned Stations', value: agents.unmanned_stations || 0, color: agents.unmanned_stations > 0 ? 'orange' : 'green' },
        { label: 'Verified Tallies', value: tallies.verified || 0, color: 'emerald', sub: `${tallies.disputed || 0} disputed` },
        { label: 'Lead Margin', value: leadMargin !== null ? leadMargin.toLocaleString() : '—', color: 'purple' },
    ];

    const colorClasses = {
        blue: 'bg-blue-50 text-blue-900 border-blue-100',
        green: 'bg-green-50 text-green-900 border-green-100',
        indigo: 'bg-indigo-50 text-indigo-900 border-indigo-100',
        purple: 'bg-purple-50 text-purple-900 border-purple-100',
        red: 'bg-red-50 text-red-900 border-red-100',
        orange: 'bg-orange-50 text-orange-900 border-orange-100',
        emerald: 'bg-emerald-50 text-emerald-900 border-emerald-100',
    };

    return (
        <QuadrantShell title="Key Metrics" icon={SignalIcon}>
            <div className="grid grid-cols-2 xl:grid-cols-4 gap-2 overflow-y-auto flex-1">
                {metrics.map((m, i) => (
                    <div key={i} className={`rounded-xl p-3 border ${colorClasses[m.color] || 'bg-gray-50 text-gray-900 border-gray-100'}`}>
                        <p className="text-[10px] font-medium opacity-75 uppercase tracking-wider">{m.label}</p>
                        <p className="text-lg font-bold mt-0.5">{m.value}</p>
                        {m.sub && <p className="text-[10px] opacity-60 mt-0.5">{m.sub}</p>}
                    </div>
                ))}
            </div>
        </QuadrantShell>
    );
}

// ---------------------------------------------------------------------------
// Q4: Incident Feed
// ---------------------------------------------------------------------------
function IncidentFeedQuadrant({ incidents }) {
    return (
        <QuadrantShell title={`Incident Feed (${incidents.length})`} icon={ShieldExclamationIcon}>
            <div className="overflow-y-auto flex-1 space-y-1">
                {incidents.length === 0 && (
                    <p className="text-sm text-gray-400 text-center py-4">No incidents reported</p>
                )}
                {incidents.map((inc) => (
                    <div key={inc.id} className="flex items-start space-x-2 p-2 rounded-lg hover:bg-gray-50 transition-colors">
                        <div className="mt-0.5">
                            {inc.severity === 'critical' ? (
                                <ExclamationTriangleIcon className="h-4 w-4 text-red-500" />
                            ) : inc.severity === 'high' ? (
                                <ExclamationTriangleIcon className="h-4 w-4 text-orange-500" />
                            ) : (
                                <ShieldExclamationIcon className="h-4 w-4 text-gray-400" />
                            )}
                        </div>
                        <div className="flex-1 min-w-0">
                            <div className="flex items-center space-x-2">
                                <span className="text-sm font-medium text-gray-900 truncate">{inc.title}</span>
                                <span className={`inline-flex px-1.5 py-0.5 rounded text-[10px] font-medium ${severityColors[inc.severity] || ''}`}>
                                    {inc.severity}
                                </span>
                            </div>
                            <div className="flex items-center space-x-2 text-xs text-gray-500 mt-0.5">
                                {inc.station_name && <span>{inc.station_name}</span>}
                                {inc.ward && <span>· {inc.ward}</span>}
                                <span>· {inc.created_at ? new Date(inc.created_at).toLocaleTimeString() : ''}</span>
                            </div>
                        </div>
                        <span className={`text-[10px] px-1.5 py-0.5 rounded capitalize ${
                            inc.status === 'resolved' ? 'bg-green-50 text-green-700' :
                            inc.status === 'escalated' ? 'bg-red-50 text-red-700' :
                            'bg-gray-100 text-gray-600'
                        }`}>
                            {inc.status}
                        </span>
                    </div>
                ))}
            </div>
        </QuadrantShell>
    );
}

// ---------------------------------------------------------------------------
// Activity Ticker
// ---------------------------------------------------------------------------
function ActivityTicker({ activities }) {
    const tickerRef = useRef(null);

    useEffect(() => {
        if (tickerRef.current) {
            tickerRef.current.scrollLeft = 0;
        }
    }, [activities]);

    const iconForType = (type) => {
        switch (type) {
            case 'tally_submitted': return <ChartBarIcon className="h-3 w-3 text-blue-500 flex-shrink-0" />;
            case 'tally_verified': return <CheckCircleIcon className="h-3 w-3 text-green-500 flex-shrink-0" />;
            case 'incident_reported': return <ExclamationTriangleIcon className="h-3 w-3 text-red-500 flex-shrink-0" />;
            default: return <SignalIcon className="h-3 w-3 text-gray-400 flex-shrink-0" />;
        }
    };

    if (!activities || activities.length === 0) return null;

    return (
        <div className="mt-2 bg-gray-900 rounded-lg px-3 py-1.5">
            <div
                ref={tickerRef}
                className="flex items-center space-x-4 overflow-x-auto scrollbar-hide"
            >
                <span className="text-[10px] text-gray-500 uppercase tracking-wider font-medium flex-shrink-0">Activity</span>
                {activities.slice(0, 30).map((a) => (
                    <div key={a.id} className="flex items-center space-x-1.5 flex-shrink-0">
                        {iconForType(a.type)}
                        <span className="text-xs text-gray-300 max-w-[300px] truncate">{a.message}</span>
                        <span className="text-[10px] text-gray-600">
                            {a.time ? new Date(a.time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Shared Components
// ---------------------------------------------------------------------------
function QuadrantShell({ title, icon: Icon, children, noPadding }) {
    return (
        <div className="bg-white rounded-lg border flex flex-col min-h-0 relative overflow-hidden">
            <div className="flex items-center space-x-2 px-3 py-2 border-b bg-gray-50/50">
                {Icon && <Icon className="h-4 w-4 text-gray-400" />}
                <h3 className="text-xs font-semibold text-gray-700 uppercase tracking-wider">{title}</h3>
            </div>
            <div className={`flex-1 min-h-0 flex flex-col ${noPadding ? '' : 'p-3'}`}>
                {children}
            </div>
        </div>
    );
}

function EmptyQuadrant() {
    return (
        <div className="flex items-center justify-center flex-1">
            <p className="text-sm text-gray-400">Loading…</p>
        </div>
    );
}
