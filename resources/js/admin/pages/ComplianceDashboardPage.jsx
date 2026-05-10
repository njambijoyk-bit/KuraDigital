import React, { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import {
    ShieldCheckIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    XCircleIcon,
    DocumentTextIcon,
    Cog6ToothIcon,
    BellAlertIcon,
} from '@heroicons/react/24/outline';
import api from '../../lib/api';
import PermissionGate from '../components/PermissionGate';
import useCampaignPermissions from '../hooks/useCampaignPermissions';
import Modal from '../components/Modal';

const severityColors = {
    critical: 'bg-red-100 text-red-800 border-red-200',
    high: 'bg-orange-100 text-orange-800 border-orange-200',
    medium: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    low: 'bg-blue-100 text-blue-800 border-blue-200',
};

const scoreStatusColors = {
    healthy: 'text-green-600',
    warning: 'text-yellow-600',
    critical: 'text-red-600',
};

function formatCurrency(amount, currency = 'KES') {
    return `${currency} ${Number(amount).toLocaleString('en-KE', { minimumFractionDigits: 2 })}`;
}

export default function ComplianceDashboardPage() {
    const { campaignId } = useParams();
    const { can } = useCampaignPermissions();
    const [tab, setTab] = useState('overview');
    const [dashboard, setDashboard] = useState(null);
    const [alerts, setAlerts] = useState([]);
    const [settings, setSettings] = useState(null);
    const [loading, setLoading] = useState(true);
    const [showSettings, setShowSettings] = useState(false);
    const [settingsForm, setSettingsForm] = useState({});
    const [saving, setSaving] = useState(false);

    const fetchDashboard = useCallback(async () => {
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/compliance/dashboard`);
            setDashboard(data);
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId]);

    const fetchAlerts = useCallback(async () => {
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/compliance/alerts`, { params: { resolved: false } });
            setAlerts(data.data || []);
        } catch { /* handled */ }
    }, [campaignId]);

    const fetchSettings = useCallback(async () => {
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/compliance/settings`);
            setSettings(data);
            setSettingsForm({
                spending_cap: data.spending_cap || '',
                individual_donation_cap: data.individual_donation_cap || '',
                corporate_donation_cap: data.corporate_donation_cap || '',
                disclosure_threshold: data.disclosure_threshold || '',
                election_date: data.election_date || '',
                alert_at_percent: data.alert_at_percent || 75,
                critical_at_percent: data.critical_at_percent || 90,
                require_segregation_of_duties: data.require_segregation_of_duties || false,
            });
        } catch { /* handled */ }
    }, [campaignId]);

    useEffect(() => {
        fetchDashboard();
        fetchAlerts();
        fetchSettings();
    }, [fetchDashboard, fetchAlerts, fetchSettings]);

    const handleResolve = async (alertId, notes = '') => {
        try {
            await api.post(`/campaigns/${campaignId}/compliance/alerts/${alertId}/resolve`, { resolution_notes: notes });
            fetchAlerts();
            fetchDashboard();
        } catch { /* handled */ }
    };

    const handleSaveSettings = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const payload = { ...settingsForm };
            Object.keys(payload).forEach((k) => {
                if (payload[k] === '') payload[k] = null;
            });
            await api.put(`/campaigns/${campaignId}/compliance/settings`, payload);
            setShowSettings(false);
            fetchSettings();
            fetchDashboard();
        } catch { /* handled */ }
        setSaving(false);
    };

    if (loading) return <div className="text-center py-10 text-gray-500">Loading compliance data...</div>;

    const score = dashboard?.score;
    const spendingCap = dashboard?.spending_cap;

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <div className="flex items-center space-x-3">
                    <ShieldCheckIcon className="h-7 w-7 text-primary-600" />
                    <h1 className="text-2xl font-heading font-bold text-gray-900">Compliance</h1>
                </div>
                <div className="flex space-x-2">
                    <PermissionGate permission="compliance.manage-settings">
                        <button
                            onClick={() => setShowSettings(true)}
                            className="flex items-center space-x-1 px-3 py-2 border rounded-lg text-sm hover:bg-gray-50"
                        >
                            <Cog6ToothIcon className="h-4 w-4" /><span>Settings</span>
                        </button>
                    </PermissionGate>
                </div>
            </div>

            <div className="border-b border-gray-200">
                <nav className="flex space-x-8">
                    {[
                        { id: 'overview', label: 'Overview' },
                        { id: 'alerts', label: `Alerts (${alerts.length})` },
                        { id: 'reports', label: 'IEBC Reports', perm: 'compliance.generate-reports' },
                    ].filter((t) => !t.perm || can(t.perm)).map((t) => (
                        <button key={t.id} onClick={() => setTab(t.id)}
                            className={`py-3 px-1 border-b-2 text-sm font-medium ${tab === t.id ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                            {t.label}
                        </button>
                    ))}
                </nav>
            </div>

            {tab === 'overview' && <OverviewTab score={score} spendingCap={spendingCap} dashboard={dashboard} alertCount={alerts.length} />}
            {tab === 'alerts' && <AlertsTab alerts={alerts} onResolve={handleResolve} can={can} />}
            {tab === 'reports' && <ReportsTab campaignId={campaignId} />}

            <Modal open={showSettings} onClose={() => setShowSettings(false)} title="Compliance Settings" size="lg">
                <form onSubmit={handleSaveSettings} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Spending Cap (KES)</label>
                            <input type="number" step="0.01" value={settingsForm.spending_cap} onChange={(e) => setSettingsForm({ ...settingsForm, spending_cap: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Individual Donation Cap</label>
                            <input type="number" step="0.01" value={settingsForm.individual_donation_cap} onChange={(e) => setSettingsForm({ ...settingsForm, individual_donation_cap: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Corporate Donation Cap</label>
                            <input type="number" step="0.01" value={settingsForm.corporate_donation_cap} onChange={(e) => setSettingsForm({ ...settingsForm, corporate_donation_cap: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Disclosure Threshold</label>
                            <input type="number" step="0.01" value={settingsForm.disclosure_threshold} onChange={(e) => setSettingsForm({ ...settingsForm, disclosure_threshold: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Election Date</label>
                            <input type="date" value={settingsForm.election_date} onChange={(e) => setSettingsForm({ ...settingsForm, election_date: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                        <div>
                            <label className="flex items-center space-x-2 mt-6">
                                <input type="checkbox" checked={settingsForm.require_segregation_of_duties} onChange={(e) => setSettingsForm({ ...settingsForm, require_segregation_of_duties: e.target.checked })} className="rounded" />
                                <span className="text-sm text-gray-700">Require segregation of duties</span>
                            </label>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Alert at % of cap</label>
                            <input type="number" min="1" max="100" value={settingsForm.alert_at_percent} onChange={(e) => setSettingsForm({ ...settingsForm, alert_at_percent: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Critical at % of cap</label>
                            <input type="number" min="1" max="100" value={settingsForm.critical_at_percent} onChange={(e) => setSettingsForm({ ...settingsForm, critical_at_percent: e.target.value })} className="w-full border rounded-lg px-3 py-2" />
                        </div>
                    </div>
                    <div className="flex justify-end space-x-3 pt-2">
                        <button type="button" onClick={() => setShowSettings(false)} className="px-4 py-2 text-sm border rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" disabled={saving} className="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm font-medium disabled:opacity-50">
                            {saving ? 'Saving...' : 'Save Settings'}
                        </button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}

function OverviewTab({ score, spendingCap, dashboard, alertCount }) {
    return (
        <div className="space-y-6">
            {score && (
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="bg-white rounded-xl shadow-sm border p-6 text-center">
                        <p className="text-sm font-medium text-gray-500">Compliance Score</p>
                        <p className={`text-4xl font-bold mt-2 ${scoreStatusColors[score.status] || ''}`}>
                            {score.overall}%
                        </p>
                        <p className={`text-sm mt-1 capitalize ${scoreStatusColors[score.status] || ''}`}>
                            {score.status}
                        </p>
                    </div>
                    <div className="bg-white rounded-xl shadow-sm border p-6 text-center">
                        <p className="text-sm font-medium text-gray-500">Active Alerts</p>
                        <p className={`text-4xl font-bold mt-2 ${alertCount > 0 ? 'text-red-600' : 'text-green-600'}`}>
                            {alertCount}
                        </p>
                    </div>
                    {spendingCap && (
                        <div className="bg-white rounded-xl shadow-sm border p-6">
                            <p className="text-sm font-medium text-gray-500">Spending Cap</p>
                            <p className="text-lg font-bold mt-1">
                                {formatCurrency(spendingCap.spent)} / {formatCurrency(spendingCap.cap)}
                            </p>
                            <div className="w-full bg-gray-200 rounded-full h-3 mt-2">
                                <div
                                    className={`h-3 rounded-full ${spendingCap.percentage > 90 ? 'bg-red-500' : spendingCap.percentage > 75 ? 'bg-yellow-500' : 'bg-green-500'}`}
                                    style={{ width: `${Math.min(spendingCap.percentage, 100)}%` }}
                                />
                            </div>
                            <p className="text-xs text-gray-500 mt-1">{spendingCap.percentage}% used</p>
                        </div>
                    )}
                </div>
            )}

            {score?.factors && (
                <div className="bg-white rounded-xl shadow-sm border p-6">
                    <h3 className="text-lg font-semibold mb-4">Score Factors</h3>
                    <div className="space-y-3">
                        {Object.entries(score.factors).map(([key, factor]) => (
                            <div key={key}>
                                <div className="flex justify-between text-sm mb-1">
                                    <span className="font-medium capitalize">{key.replace(/_/g, ' ')}</span>
                                    <span className="text-gray-500">{factor.score}/{factor.max_score} ({factor.weight * 100}% weight)</span>
                                </div>
                                <div className="w-full bg-gray-200 rounded-full h-2">
                                    <div
                                        className={`h-2 rounded-full ${factor.score / factor.max_score > 0.7 ? 'bg-green-500' : factor.score / factor.max_score > 0.4 ? 'bg-yellow-500' : 'bg-red-500'}`}
                                        style={{ width: `${(factor.score / factor.max_score) * 100}%` }}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {dashboard?.donations_by_source && Object.keys(dashboard.donations_by_source).length > 0 && (
                <div className="bg-white rounded-xl shadow-sm border p-6">
                    <h3 className="text-lg font-semibold mb-4">Donations by Source</h3>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        {Object.entries(dashboard.donations_by_source).map(([source, data]) => (
                            <div key={source} className="bg-gray-50 rounded-lg p-4 text-center">
                                <p className="text-sm font-medium capitalize text-gray-600">{source || 'Unknown'}</p>
                                <p className="text-lg font-bold mt-1">{formatCurrency(data.total)}</p>
                                <p className="text-xs text-gray-400">{data.count} donations</p>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function AlertsTab({ alerts, onResolve, can }) {
    const [resolveId, setResolveId] = useState(null);
    const [notes, setNotes] = useState('');

    const handleResolve = () => {
        onResolve(resolveId, notes);
        setResolveId(null);
        setNotes('');
    };

    if (alerts.length === 0) {
        return (
            <div className="text-center py-12">
                <CheckCircleIcon className="h-12 w-12 text-green-400 mx-auto" />
                <p className="mt-3 text-gray-500">No unresolved compliance alerts.</p>
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {alerts.map((alert) => (
                <div key={alert.id} className={`p-4 rounded-lg border ${severityColors[alert.severity] || 'bg-gray-50'}`}>
                    <div className="flex items-start justify-between">
                        <div className="flex items-start space-x-3">
                            {alert.severity === 'critical' ? (
                                <XCircleIcon className="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
                            ) : (
                                <ExclamationTriangleIcon className="h-5 w-5 text-yellow-600 flex-shrink-0 mt-0.5" />
                            )}
                            <div>
                                <p className="font-medium">{alert.title}</p>
                                <p className="text-sm mt-0.5">{alert.message}</p>
                                <p className="text-xs opacity-60 mt-1">
                                    {new Date(alert.created_at).toLocaleString()} · {alert.type}
                                </p>
                            </div>
                        </div>
                        {can('compliance.resolve-alerts') && (
                            <button
                                onClick={() => { setResolveId(alert.id); setNotes(''); }}
                                className="px-3 py-1 text-xs font-medium bg-white border rounded-lg hover:bg-gray-50"
                            >
                                Resolve
                            </button>
                        )}
                    </div>
                </div>
            ))}

            <Modal open={!!resolveId} onClose={() => setResolveId(null)} title="Resolve Alert">
                <div className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Resolution Notes (optional)</label>
                        <textarea value={notes} onChange={(e) => setNotes(e.target.value)} rows={3} className="w-full border rounded-lg px-3 py-2" />
                    </div>
                    <div className="flex justify-end space-x-3">
                        <button onClick={() => setResolveId(null)} className="px-4 py-2 text-sm border rounded-lg hover:bg-gray-50">Cancel</button>
                        <button onClick={handleResolve} className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium">
                            Mark Resolved
                        </button>
                    </div>
                </div>
            </Modal>
        </div>
    );
}

function ReportsTab({ campaignId }) {
    const [report, setReport] = useState(null);
    const [loading, setLoading] = useState(false);
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');

    const generateReport = async () => {
        setLoading(true);
        try {
            const params = {};
            if (startDate) params.start_date = startDate;
            if (endDate) params.end_date = endDate;
            const { data } = await api.get(`/campaigns/${campaignId}/compliance/report/iebc`, { params });
            setReport(data);
        } catch { /* handled */ }
        setLoading(false);
    };

    return (
        <div className="space-y-4">
            <div className="bg-white rounded-xl shadow-sm border p-6">
                <div className="flex items-center space-x-3 mb-4">
                    <DocumentTextIcon className="h-5 w-5 text-primary-600" />
                    <h3 className="text-lg font-semibold">IEBC Compliance Report</h3>
                </div>
                <div className="flex items-end space-x-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} className="border rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} className="border rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <button
                        onClick={generateReport}
                        disabled={loading}
                        className="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm font-medium disabled:opacity-50"
                    >
                        {loading ? 'Generating...' : 'Generate Report'}
                    </button>
                </div>
            </div>

            {report && (
                <div className="bg-white rounded-xl shadow-sm border p-6 space-y-6">
                    <div>
                        <h4 className="font-semibold mb-2">Campaign: {report.campaign_name}</h4>
                        <p className="text-sm text-gray-500">Period: {report.period?.start || 'All time'} to {report.period?.end || 'Present'}</p>
                    </div>

                    {report.spending_summary && (
                        <div>
                            <h4 className="font-medium mb-2">Spending Summary</h4>
                            <div className="grid grid-cols-3 gap-4">
                                <div className="bg-gray-50 rounded-lg p-3">
                                    <p className="text-xs text-gray-500">Total Expenses</p>
                                    <p className="text-lg font-bold">{formatCurrency(report.spending_summary.total_expenses)}</p>
                                </div>
                                <div className="bg-gray-50 rounded-lg p-3">
                                    <p className="text-xs text-gray-500">Total Donations</p>
                                    <p className="text-lg font-bold">{formatCurrency(report.spending_summary.total_donations)}</p>
                                </div>
                                <div className="bg-gray-50 rounded-lg p-3">
                                    <p className="text-xs text-gray-500">Net Position</p>
                                    <p className="text-lg font-bold">{formatCurrency(report.spending_summary.net_position)}</p>
                                </div>
                            </div>
                        </div>
                    )}

                    {report.compliance_status && (
                        <div>
                            <h4 className="font-medium mb-2">Compliance Status</h4>
                            <div className="grid grid-cols-2 gap-4">
                                {Object.entries(report.compliance_status).map(([key, val]) => (
                                    <div key={key} className="flex items-center space-x-2">
                                        {val ? <CheckCircleIcon className="h-5 w-5 text-green-500" /> : <XCircleIcon className="h-5 w-5 text-red-500" />}
                                        <span className="text-sm capitalize">{key.replace(/_/g, ' ')}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
