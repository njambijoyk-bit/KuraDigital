import React, { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import {
    CameraIcon,
    VideoCameraIcon,
    MicrophoneIcon,
    DocumentTextIcon,
    MagnifyingGlassIcon,
    FunnelIcon,
    TrashIcon,
    EyeIcon,
    PencilIcon,
    ArrowPathIcon,
    PhotoIcon,
    ClockIcon,
    MapPinIcon,
    TagIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import api from '../../lib/api';
import PermissionGate from '../components/PermissionGate';
import useCampaignPermissions from '../hooks/useCampaignPermissions';
import StatsCard from '../components/StatsCard';
import Modal from '../components/Modal';

const TYPE_OPTIONS = ['photo', 'video', 'audio', 'text'];
const STATUS_OPTIONS = ['submitted', 'processing', 'processed', 'flagged'];

const typeIcons = {
    photo: CameraIcon,
    video: VideoCameraIcon,
    audio: MicrophoneIcon,
    text: DocumentTextIcon,
};

const typeColors = {
    photo: 'bg-blue-50 text-blue-600',
    video: 'bg-purple-50 text-purple-600',
    audio: 'bg-green-50 text-green-600',
    text: 'bg-gray-100 text-gray-600',
};

const statusColors = {
    draft: 'bg-gray-100 text-gray-500',
    submitted: 'bg-blue-50 text-blue-700',
    processing: 'bg-yellow-50 text-yellow-700',
    processed: 'bg-green-50 text-green-700',
    flagged: 'bg-red-50 text-red-700',
};

export default function FieldReportsPage() {
    const { campaignId } = useParams();
    const [reports, setReports] = useState([]);
    const [meta, setMeta] = useState({});
    const [loading, setLoading] = useState(true);
    const [stats, setStats] = useState(null);
    const [search, setSearch] = useState('');
    const [typeFilter, setTypeFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [page, setPage] = useState(1);
    const [selectedReport, setSelectedReport] = useState(null);
    const [showDetail, setShowDetail] = useState(false);
    const { can } = useCampaignPermissions();

    const fetchReports = useCallback(async (pg = page) => {
        setLoading(true);
        try {
            const params = { page: pg };
            if (search.trim()) params.search = search.trim();
            if (typeFilter) params.type = typeFilter;
            if (statusFilter) params.status = statusFilter;
            const { data } = await api.get(`/campaigns/${campaignId}/field-reports`, { params });
            setReports(data.data || []);
            setMeta(data.meta || { current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, search, typeFilter, statusFilter, page]);

    const fetchStats = useCallback(async () => {
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/field-reports/stats`);
            setStats(data.stats);
        } catch { /* handled */ }
    }, [campaignId]);

    useEffect(() => { fetchReports(); fetchStats(); }, [fetchReports, fetchStats]);

    const handleDelete = async (id) => {
        if (!window.confirm('Delete this field report?')) return;
        try {
            await api.delete(`/campaigns/${campaignId}/field-reports/${id}`);
            fetchReports();
            fetchStats();
        } catch { /* handled */ }
    };

    const openDetail = async (report) => {
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/field-reports/${report.id}`);
            setSelectedReport(data.field_report);
            setShowDetail(true);
        } catch { /* handled */ }
    };

    const handleStatusUpdate = async (id, status) => {
        try {
            await api.put(`/campaigns/${campaignId}/field-reports/${id}`, { status });
            fetchReports();
            fetchStats();
            if (selectedReport?.id === id) {
                setSelectedReport((prev) => ({ ...prev, status }));
            }
        } catch { /* handled */ }
    };

    const handleReprocess = async (id) => {
        try {
            await api.post(`/campaigns/${campaignId}/field-reports/${id}/reprocess`);
            openDetail({ id });
            fetchReports();
            fetchStats();
        } catch { /* handled */ }
    };

    const TypeIcon = ({ type }) => {
        const Icon = typeIcons[type] || DocumentTextIcon;
        return (
            <div className={`p-2 rounded-lg ${typeColors[type] || typeColors.text}`}>
                <Icon className="h-5 w-5" />
            </div>
        );
    };

    const formatDate = (dateStr) => {
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleDateString('en-KE', {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit',
        });
    };

    const formatSize = (bytes) => {
        if (!bytes) return '';
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        let size = bytes;
        while (size >= 1024 && i < units.length - 1) { size /= 1024; i++; }
        return `${size.toFixed(1)} ${units[i]}`;
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-heading font-bold text-gray-900">Field Reports</h1>
                    <p className="text-sm text-gray-500 mt-1">Photos, videos, audio recordings, and text reports from field agents</p>
                </div>
            </div>

            {/* Stats */}
            {stats && (
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <StatsCard title="Total Reports" value={stats.total} icon={DocumentTextIcon} color="primary" />
                    <StatsCard title="Photos" value={stats.by_type?.photo || 0} icon={CameraIcon} color="blue" />
                    <StatsCard title="Videos" value={stats.by_type?.video || 0} icon={VideoCameraIcon} color="purple" />
                    <StatsCard title="Audio" value={stats.by_type?.audio || 0} icon={MicrophoneIcon} color="green" />
                    <StatsCard title="Text" value={stats.by_type?.text || 0} icon={DocumentTextIcon} color="gray" />
                    <StatsCard title="Today" value={stats.today || 0} icon={ClockIcon} color="orange" />
                </div>
            )}

            {/* Filters */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <div className="flex flex-wrap gap-3">
                    <div className="flex-1 min-w-[200px]">
                        <div className="relative">
                            <MagnifyingGlassIcon className="h-5 w-5 absolute left-3 top-2.5 text-gray-400" />
                            <input
                                type="text"
                                placeholder="Search reports..."
                                value={search}
                                onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                                className="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                            />
                        </div>
                    </div>
                    <select
                        value={typeFilter}
                        onChange={(e) => { setTypeFilter(e.target.value); setPage(1); }}
                        className="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                    >
                        <option value="">All Types</option>
                        {TYPE_OPTIONS.map((t) => (
                            <option key={t} value={t}>{t.charAt(0).toUpperCase() + t.slice(1)}</option>
                        ))}
                    </select>
                    <select
                        value={statusFilter}
                        onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
                        className="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                    >
                        <option value="">All Statuses</option>
                        {STATUS_OPTIONS.map((s) => (
                            <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                        ))}
                    </select>
                </div>
            </div>

            {/* Reports Grid */}
            {loading ? (
                <div className="flex justify-center py-12">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" />
                </div>
            ) : reports.length === 0 ? (
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
                    <DocumentTextIcon className="h-12 w-12 text-gray-300 mx-auto mb-3" />
                    <h3 className="text-lg font-medium text-gray-900">No field reports yet</h3>
                    <p className="text-sm text-gray-500 mt-1">Field agents can submit reports via the mobile app</p>
                </div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {reports.map((report) => (
                        <div
                            key={report.id}
                            className="bg-white rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow cursor-pointer"
                            onClick={() => openDetail(report)}
                        >
                            {/* Thumbnail / Preview */}
                            {report.media && report.media.length > 0 && report.media[0].url ? (
                                <div className="h-40 rounded-t-xl overflow-hidden bg-gray-100">
                                    {report.type === 'photo' ? (
                                        <img
                                            src={report.media[0].thumbnail_url || report.media[0].url}
                                            alt={report.title || 'Report media'}
                                            className="w-full h-full object-cover"
                                        />
                                    ) : report.type === 'video' && report.media[0].thumbnail_url ? (
                                        <img
                                            src={report.media[0].thumbnail_url}
                                            alt="Video thumbnail"
                                            className="w-full h-full object-cover"
                                        />
                                    ) : (
                                        <div className="w-full h-full flex items-center justify-center">
                                            <TypeIcon type={report.type} />
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="h-24 rounded-t-xl bg-gray-50 flex items-center justify-center">
                                    <TypeIcon type={report.type} />
                                </div>
                            )}

                            <div className="p-4 space-y-2">
                                <div className="flex items-start justify-between">
                                    <h3 className="text-sm font-semibold text-gray-900 line-clamp-1">
                                        {report.title || `${report.type.charAt(0).toUpperCase() + report.type.slice(1)} Report`}
                                    </h3>
                                    <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${statusColors[report.status]}`}>
                                        {report.status}
                                    </span>
                                </div>

                                {report.body && (
                                    <p className="text-xs text-gray-500 line-clamp-2">{report.body}</p>
                                )}

                                <div className="flex items-center justify-between text-xs text-gray-400">
                                    <span className="flex items-center gap-1">
                                        <ClockIcon className="h-3.5 w-3.5" />
                                        {formatDate(report.captured_at)}
                                    </span>
                                    {report.media_count > 0 && (
                                        <span className="flex items-center gap-1">
                                            <PhotoIcon className="h-3.5 w-3.5" />
                                            {report.media_count}
                                        </span>
                                    )}
                                </div>

                                <div className="flex items-center justify-between text-xs">
                                    <span className="text-gray-500">{report.user?.name || 'Unknown agent'}</span>
                                    {report.ward && (
                                        <span className="flex items-center gap-1 text-gray-400">
                                            <MapPinIcon className="h-3.5 w-3.5" />
                                            {report.ward}
                                        </span>
                                    )}
                                </div>

                                {report.tags && report.tags.length > 0 && (
                                    <div className="flex flex-wrap gap-1">
                                        {report.tags.slice(0, 3).map((tag) => (
                                            <span key={tag} className="text-xs px-1.5 py-0.5 bg-gray-100 text-gray-500 rounded">
                                                {tag}
                                            </span>
                                        ))}
                                        {report.tags.length > 3 && (
                                            <span className="text-xs text-gray-400">+{report.tags.length - 3}</span>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Pagination */}
            {meta.last_page > 1 && (
                <div className="flex items-center justify-between bg-white rounded-xl shadow-sm border border-gray-100 px-4 py-3">
                    <p className="text-sm text-gray-500">
                        Page {meta.current_page} of {meta.last_page} ({meta.total} reports)
                    </p>
                    <div className="flex gap-2">
                        <button
                            onClick={() => setPage((p) => Math.max(1, p - 1))}
                            disabled={meta.current_page <= 1}
                            className="px-3 py-1.5 text-sm border rounded-lg disabled:opacity-50 hover:bg-gray-50"
                        >
                            Previous
                        </button>
                        <button
                            onClick={() => setPage((p) => p + 1)}
                            disabled={meta.current_page >= meta.last_page}
                            className="px-3 py-1.5 text-sm border rounded-lg disabled:opacity-50 hover:bg-gray-50"
                        >
                            Next
                        </button>
                    </div>
                </div>
            )}

            {/* Detail Modal */}
            <Modal open={showDetail} onClose={() => setShowDetail(false)} title="Field Report" size="xl">
                {selectedReport && (
                    <div className="space-y-5">
                        {/* Header */}
                        <div className="flex items-start justify-between">
                            <div className="flex items-center gap-3">
                                <TypeIcon type={selectedReport.type} />
                                <div>
                                    <h3 className="font-semibold text-gray-900">
                                        {selectedReport.title || `${selectedReport.type.charAt(0).toUpperCase() + selectedReport.type.slice(1)} Report`}
                                    </h3>
                                    <p className="text-sm text-gray-500">
                                        by {selectedReport.user?.name || 'Unknown'} • {formatDate(selectedReport.captured_at)}
                                    </p>
                                </div>
                            </div>
                            <span className={`text-xs px-2.5 py-1 rounded-full font-medium ${statusColors[selectedReport.status]}`}>
                                {selectedReport.status}
                            </span>
                        </div>

                        {/* Body */}
                        {selectedReport.body && (
                            <div className="bg-gray-50 rounded-lg p-4">
                                <p className="text-sm text-gray-700 whitespace-pre-wrap">{selectedReport.body}</p>
                            </div>
                        )}

                        {/* Media Gallery */}
                        {selectedReport.media && selectedReport.media.length > 0 && (
                            <div>
                                <h4 className="text-sm font-medium text-gray-900 mb-2">Attachments ({selectedReport.media.length})</h4>
                                <div className="grid grid-cols-2 gap-3">
                                    {selectedReport.media.map((m) => (
                                        <div key={m.id} className="border rounded-lg overflow-hidden">
                                            {m.mime_type?.startsWith('image/') ? (
                                                <img
                                                    src={m.url}
                                                    alt={m.original_filename}
                                                    className="w-full h-48 object-cover"
                                                />
                                            ) : m.mime_type?.startsWith('video/') ? (
                                                <video controls className="w-full h-48 object-cover">
                                                    <source src={m.url} type={m.mime_type} />
                                                </video>
                                            ) : m.mime_type?.startsWith('audio/') ? (
                                                <div className="p-4 bg-gray-50">
                                                    <audio controls className="w-full">
                                                        <source src={m.url} type={m.mime_type} />
                                                    </audio>
                                                </div>
                                            ) : (
                                                <div className="p-4 bg-gray-50 text-center">
                                                    <DocumentTextIcon className="h-8 w-8 text-gray-400 mx-auto" />
                                                </div>
                                            )}
                                            <div className="p-2 text-xs text-gray-500">
                                                <p className="truncate">{m.original_filename}</p>
                                                <p>{formatSize(m.size)}</p>
                                                {m.processing_status !== 'pending' && (
                                                    <span className={`inline-block mt-1 px-1.5 py-0.5 rounded text-xs ${statusColors[m.processing_status] || 'bg-gray-100 text-gray-500'}`}>
                                                        {m.processing_status}
                                                    </span>
                                                )}
                                            </div>
                                            {/* Processing results */}
                                            {m.processing_result && (
                                                <div className="px-2 pb-2">
                                                    {m.processing_result.ocr_text && (
                                                        <div className="text-xs bg-blue-50 p-2 rounded mt-1">
                                                            <p className="font-medium text-blue-700 mb-0.5">OCR Text:</p>
                                                            <p className="text-blue-600 line-clamp-3">{m.processing_result.ocr_text}</p>
                                                        </div>
                                                    )}
                                                    {m.processing_result.transcription && (
                                                        <div className="text-xs bg-green-50 p-2 rounded mt-1">
                                                            <p className="font-medium text-green-700 mb-0.5">Transcription:</p>
                                                            <p className="text-green-600 line-clamp-3">{m.processing_result.transcription}</p>
                                                        </div>
                                                    )}
                                                    {m.processing_result.labels && (
                                                        <div className="flex flex-wrap gap-1 mt-1">
                                                            {m.processing_result.labels.map((label) => (
                                                                <span key={label} className="text-xs px-1.5 py-0.5 bg-purple-50 text-purple-600 rounded">
                                                                    {label}
                                                                </span>
                                                            ))}
                                                        </div>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Metadata */}
                        <div className="grid grid-cols-2 gap-4 text-sm">
                            {selectedReport.ward && (
                                <div>
                                    <p className="text-gray-500">Location</p>
                                    <p className="font-medium text-gray-900">
                                        {[selectedReport.ward, selectedReport.constituency, selectedReport.county].filter(Boolean).join(', ')}
                                    </p>
                                </div>
                            )}
                            {selectedReport.latitude && (
                                <div>
                                    <p className="text-gray-500">GPS</p>
                                    <p className="font-medium text-gray-900">
                                        {selectedReport.latitude}, {selectedReport.longitude}
                                    </p>
                                </div>
                            )}
                            {selectedReport.field_agent && (
                                <div>
                                    <p className="text-gray-500">Agent Code</p>
                                    <p className="font-medium text-gray-900">{selectedReport.field_agent.agent_code || '—'}</p>
                                </div>
                            )}
                            {selectedReport.tags && selectedReport.tags.length > 0 && (
                                <div>
                                    <p className="text-gray-500">Tags</p>
                                    <div className="flex flex-wrap gap-1 mt-1">
                                        {selectedReport.tags.map((tag) => (
                                            <span key={tag} className="text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full">{tag}</span>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Actions */}
                        <div className="flex items-center justify-between border-t pt-4">
                            <div className="flex gap-2">
                                {selectedReport.status === 'submitted' && (
                                    <button
                                        onClick={() => handleStatusUpdate(selectedReport.id, 'flagged')}
                                        className="px-3 py-1.5 text-sm bg-red-50 text-red-700 rounded-lg hover:bg-red-100"
                                    >
                                        Flag Report
                                    </button>
                                )}
                                {selectedReport.status === 'flagged' && (
                                    <button
                                        onClick={() => handleStatusUpdate(selectedReport.id, 'submitted')}
                                        className="px-3 py-1.5 text-sm bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100"
                                    >
                                        Unflag
                                    </button>
                                )}
                                {selectedReport.media && selectedReport.media.length > 0 && (
                                    <button
                                        onClick={() => handleReprocess(selectedReport.id)}
                                        className="px-3 py-1.5 text-sm bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 flex items-center gap-1"
                                    >
                                        <ArrowPathIcon className="h-4 w-4" />
                                        Reprocess
                                    </button>
                                )}
                            </div>
                            <PermissionGate permission="field.manage-agents">
                                <button
                                    onClick={() => { handleDelete(selectedReport.id); setShowDetail(false); }}
                                    className="px-3 py-1.5 text-sm bg-red-50 text-red-700 rounded-lg hover:bg-red-100 flex items-center gap-1"
                                >
                                    <TrashIcon className="h-4 w-4" />
                                    Delete
                                </button>
                            </PermissionGate>
                        </div>
                    </div>
                )}
            </Modal>
        </div>
    );
}
