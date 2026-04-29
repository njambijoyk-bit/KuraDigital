import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { ClipboardDocumentListIcon, EyeIcon } from '@heroicons/react/24/outline';
import api from '../../lib/api';
import DataTable from '../components/DataTable';
import Modal from '../components/Modal';
import EmptyState from '../components/EmptyState';

export default function AuditLogPage() {
    const { campaignId } = useParams();
    const [logs, setLogs] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showDetail, setShowDetail] = useState(null);

    useEffect(() => {
        const load = async () => {
            setLoading(true);
            try {
                const { data } = await api.get(`/campaigns/${campaignId}/audit-logs`);
                setLogs(data.data || []);
            } catch { /* handled */ }
            setLoading(false);
        };
        load();
    }, [campaignId]);

    const openDetail = async (log) => {
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/audit-logs/${log.id}`);
            setShowDetail(data.audit_log || data.data);
        } catch {
            setShowDetail(log);
        }
    };

    const actionColors = {
        created: 'bg-green-50 text-green-700',
        updated: 'bg-blue-50 text-blue-700',
        deleted: 'bg-red-50 text-red-700',
    };

    const columns = [
        { key: 'created_at', label: 'Time', render: (r) => (
            <span className="text-xs text-gray-500">
                {new Date(r.created_at).toLocaleString('en-KE', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })}
            </span>
        )},
        { key: 'user', label: 'User', render: (r) => (
            <span className="text-sm font-medium text-gray-900">{r.user?.name || 'System'}</span>
        )},
        { key: 'action', label: 'Action', render: (r) => (
            <span className={`px-2 py-0.5 text-xs font-medium rounded-full capitalize ${actionColors[r.action] || 'bg-gray-100 text-gray-600'}`}>
                {r.action}
            </span>
        )},
        { key: 'model', label: 'Model', render: (r) => (
            <span className="text-sm text-gray-600">{r.auditable_type?.split('\\').pop()}</span>
        )},
        { key: 'ip', label: 'IP', render: (r) => <span className="text-xs text-gray-400 font-mono">{r.ip_address || '—'}</span> },
        { key: 'actions', label: '', render: (r) => (
            <button onClick={() => openDetail(r)} className="p-1.5 text-gray-400 hover:text-primary-600 rounded">
                <EyeIcon className="h-4 w-4" />
            </button>
        )},
    ];

    return (
        <div className="space-y-4">
            <p className="text-sm text-gray-500">{logs.length} audit log entr{logs.length !== 1 ? 'ies' : 'y'}</p>

            {logs.length === 0 && !loading ? (
                <EmptyState icon={ClipboardDocumentListIcon} title="No audit logs" description="Actions in this campaign will be tracked here" />
            ) : (
                <DataTable columns={columns} data={logs} loading={loading} />
            )}

            <Modal open={!!showDetail} onClose={() => setShowDetail(null)} title="Audit Log Detail" size="lg">
                {showDetail && (
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <p className="text-xs text-gray-400 mb-0.5">User</p>
                                <p className="text-sm font-medium">{showDetail.user?.name || 'System'}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400 mb-0.5">Time</p>
                                <p className="text-sm">{new Date(showDetail.created_at).toLocaleString('en-KE')}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400 mb-0.5">Action</p>
                                <span className={`px-2 py-0.5 text-xs font-medium rounded-full capitalize ${actionColors[showDetail.action] || 'bg-gray-100 text-gray-600'}`}>
                                    {showDetail.action}
                                </span>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400 mb-0.5">Model</p>
                                <p className="text-sm">{showDetail.auditable_type?.split('\\').pop()} #{showDetail.auditable_id}</p>
                            </div>
                            <div>
                                <p className="text-xs text-gray-400 mb-0.5">IP Address</p>
                                <p className="text-sm font-mono">{showDetail.ip_address || '—'}</p>
                            </div>
                        </div>

                        {showDetail.old_values && Object.keys(showDetail.old_values).length > 0 && (
                            <div>
                                <p className="text-xs text-gray-400 mb-1">Old Values</p>
                                <pre className="bg-red-50 rounded-lg p-3 text-xs text-gray-700 overflow-x-auto">
                                    {JSON.stringify(showDetail.old_values, null, 2)}
                                </pre>
                            </div>
                        )}

                        {showDetail.new_values && Object.keys(showDetail.new_values).length > 0 && (
                            <div>
                                <p className="text-xs text-gray-400 mb-1">New Values</p>
                                <pre className="bg-green-50 rounded-lg p-3 text-xs text-gray-700 overflow-x-auto">
                                    {JSON.stringify(showDetail.new_values, null, 2)}
                                </pre>
                            </div>
                        )}
                    </div>
                )}
            </Modal>
        </div>
    );
}
