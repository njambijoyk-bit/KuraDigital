import React, { useState, useEffect, useCallback } from 'react';
import {
    CalendarDaysIcon,
    PencilIcon,
    TrashIcon,
    MagnifyingGlassIcon,
    PlusIcon,
    UserGroupIcon,
    CheckCircleIcon,
    ClockIcon,
    ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';
import api from '../../../lib/api';
import Modal from '../Modal';
import ScheduleForm from './ScheduleForm';
import PermissionGate from '../PermissionGate';

const shiftColors = {
    morning: 'bg-yellow-50 text-yellow-700 border-yellow-200',
    afternoon: 'bg-orange-50 text-orange-700 border-orange-200',
    evening: 'bg-purple-50 text-purple-700 border-purple-200',
    night: 'bg-indigo-50 text-indigo-700 border-indigo-200',
    full_day: 'bg-blue-50 text-blue-700 border-blue-200',
    custom: 'bg-gray-50 text-gray-700 border-gray-200',
};

const statusColors = {
    scheduled: 'bg-blue-50 text-blue-700',
    in_progress: 'bg-green-50 text-green-700',
    completed: 'bg-gray-100 text-gray-600',
    cancelled: 'bg-red-50 text-red-700',
    no_show: 'bg-amber-50 text-amber-700',
};

export default function ScheduleList({ campaignId, agents }) {
    const [schedules, setSchedules] = useState([]);
    const [loading, setLoading] = useState(true);
    const [dateFilter, setDateFilter] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [agentFilter, setAgentFilter] = useState('');
    const [showCreate, setShowCreate] = useState(false);
    const [showEdit, setShowEdit] = useState(null);
    const [showBulk, setShowBulk] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [warnings, setWarnings] = useState([]);
    const [page, setPage] = useState(1);
    const [meta, setMeta] = useState({});

    const fetchSchedules = useCallback(async (pg = page) => {
        setLoading(true);
        try {
            const params = { page: pg };
            if (dateFilter) params.date = dateFilter;
            if (statusFilter) params.status = statusFilter;
            if (agentFilter) params.field_agent_id = agentFilter;
            const { data } = await api.get(`/campaigns/${campaignId}/agent-schedules`, { params });
            setSchedules(data.data || []);
            setMeta(data.meta || { current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, dateFilter, statusFilter, agentFilter, page]);

    useEffect(() => { fetchSchedules(); }, [fetchSchedules]);

    const handleCreate = async (form) => {
        setSubmitting(true);
        setError(null);
        setWarnings([]);
        try {
            const { data } = await api.post(`/campaigns/${campaignId}/agent-schedules`, form);
            if (data.warnings) setWarnings(data.warnings);
            setShowCreate(false);
            fetchSchedules();
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to create schedule.');
        }
        setSubmitting(false);
    };

    const handleUpdate = async (form) => {
        setSubmitting(true);
        setError(null);
        try {
            await api.put(`/campaigns/${campaignId}/agent-schedules/${showEdit.id}`, form);
            setShowEdit(null);
            fetchSchedules();
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to update schedule.');
        }
        setSubmitting(false);
    };

    const handleDelete = async (id) => {
        if (!window.confirm('Delete this schedule?')) return;
        try {
            await api.delete(`/campaigns/${campaignId}/agent-schedules/${id}`);
            fetchSchedules();
        } catch { /* handled */ }
    };

    const handleBulkSubmit = async (form) => {
        setSubmitting(true);
        setError(null);
        setWarnings([]);
        try {
            const { data } = await api.post(`/campaigns/${campaignId}/agent-schedules/bulk`, form);
            if (data.warnings) setWarnings(data.warnings);
            setShowBulk(false);
            fetchSchedules();
        } catch (err) {
            setError(err.response?.data?.message || 'Failed to create bulk schedules.');
        }
        setSubmitting(false);
    };

    return (
        <div className="space-y-4">
            {/* Warnings banner */}
            {warnings.length > 0 && (
                <div className="bg-amber-50 border border-amber-200 rounded-lg p-3">
                    <div className="flex items-center gap-2 text-amber-700 font-medium text-sm mb-1">
                        <ExclamationTriangleIcon className="h-4 w-4" />
                        Scheduling Conflicts Detected
                    </div>
                    {warnings.map((w, i) => (
                        <p key={i} className="text-xs text-amber-600">
                            {typeof w === 'string' ? w : `${w.agent_name} on ${w.date}: ${w.warnings?.join(', ')}`}
                        </p>
                    ))}
                    <button onClick={() => setWarnings([])} className="text-xs text-amber-500 mt-1 underline">Dismiss</button>
                </div>
            )}

            {/* Filters & actions */}
            <div className="flex flex-wrap items-center gap-3">
                <input
                    type="date"
                    value={dateFilter}
                    onChange={(e) => { setDateFilter(e.target.value); setPage(1); }}
                    className="border rounded-lg px-3 py-2 text-sm"
                />
                <select
                    value={statusFilter}
                    onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
                    className="border rounded-lg px-3 py-2 text-sm"
                >
                    <option value="">All Statuses</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="no_show">No Show</option>
                </select>
                <select
                    value={agentFilter}
                    onChange={(e) => { setAgentFilter(e.target.value); setPage(1); }}
                    className="border rounded-lg px-3 py-2 text-sm"
                >
                    <option value="">All Agents</option>
                    {agents.map((a) => (
                        <option key={a.id} value={a.id}>{a.user?.name || a.agent_code}</option>
                    ))}
                </select>
                <div className="flex-1" />
                <PermissionGate permission="field.assign-stations">
                    <button
                        onClick={() => { setError(null); setShowBulk(true); }}
                        className="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50"
                    >
                        <UserGroupIcon className="h-4 w-4 mr-1" /> Bulk Schedule
                    </button>
                    <button
                        onClick={() => { setError(null); setShowCreate(true); }}
                        className="inline-flex items-center px-3 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700"
                    >
                        <PlusIcon className="h-4 w-4 mr-1" /> Schedule Agent
                    </button>
                </PermissionGate>
            </div>

            {/* Schedule table */}
            {loading ? (
                <div className="flex justify-center py-12">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" />
                </div>
            ) : schedules.length === 0 ? (
                <div className="text-center py-12 text-gray-500">
                    <CalendarDaysIcon className="h-12 w-12 mx-auto mb-3 text-gray-300" />
                    <p className="font-medium">No schedules found</p>
                    <p className="text-sm">Create a schedule to assign agents to shifts.</p>
                </div>
            ) : (
                <div className="bg-white rounded-lg border overflow-hidden">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Agent</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Shift</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Check-in</th>
                                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {schedules.map((s) => (
                                <tr key={s.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <div className="font-medium text-sm text-gray-900">{s.field_agent?.user?.name || '—'}</div>
                                        {s.title && <div className="text-xs text-gray-500">{s.title}</div>}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-700">
                                        {new Date(s.date).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border ${shiftColors[s.shift_type] || shiftColors.custom}`}>
                                            {s.shift_type.replace('_', ' ')}
                                        </span>
                                        <div className="text-xs text-gray-400 mt-0.5">{s.start_time?.slice(0, 5)}–{s.end_time?.slice(0, 5)}</div>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-700">
                                        <div>{s.ward || '—'}</div>
                                        {s.polling_station && <div className="text-xs text-gray-400">{s.polling_station}</div>}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusColors[s.status] || ''}`}>
                                            {s.status?.replace('_', ' ')}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-gray-500">
                                        {s.checked_in_at ? (
                                            <span className="text-green-600 flex items-center gap-1">
                                                <CheckCircleIcon className="h-3.5 w-3.5" />
                                                {new Date(s.checked_in_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                            </span>
                                        ) : (
                                            <span className="text-gray-400">—</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <PermissionGate permission="field.assign-stations">
                                            <div className="flex items-center justify-end space-x-2">
                                                <button
                                                    onClick={() => {
                                                        setShowEdit(s);
                                                        setError(null);
                                                    }}
                                                    className="text-gray-400 hover:text-primary-600"
                                                >
                                                    <PencilIcon className="h-4 w-4" />
                                                </button>
                                                <button onClick={() => handleDelete(s.id)} className="text-gray-400 hover:text-red-600">
                                                    <TrashIcon className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </PermissionGate>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {/* Pagination */}
            {meta.last_page > 1 && (
                <div className="flex justify-center space-x-2">
                    {Array.from({ length: meta.last_page }, (_, i) => (
                        <button
                            key={i + 1}
                            onClick={() => { setPage(i + 1); fetchSchedules(i + 1); }}
                            className={`px-3 py-1 rounded text-sm ${page === i + 1 ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'}`}
                        >
                            {i + 1}
                        </button>
                    ))}
                </div>
            )}

            {/* Create modal */}
            <Modal isOpen={showCreate} onClose={() => setShowCreate(false)} title="Schedule Agent">
                <ScheduleForm
                    agents={agents}
                    onSubmit={handleCreate}
                    onCancel={() => setShowCreate(false)}
                    submitting={submitting}
                    error={error}
                    isEdit={false}
                />
            </Modal>

            {/* Edit modal */}
            <Modal isOpen={!!showEdit} onClose={() => setShowEdit(null)} title="Edit Schedule">
                {showEdit && (
                    <ScheduleForm
                        agents={agents}
                        initial={{
                            field_agent_id: showEdit.field_agent_id,
                            title: showEdit.title || '',
                            shift_type: showEdit.shift_type,
                            date: showEdit.date?.split('T')[0],
                            start_time: showEdit.start_time?.slice(0, 5),
                            end_time: showEdit.end_time?.slice(0, 5),
                            ward: showEdit.ward || '',
                            constituency: showEdit.constituency || '',
                            county: showEdit.county || '',
                            polling_station: showEdit.polling_station || '',
                            status: showEdit.status,
                            notes: showEdit.notes || '',
                        }}
                        onSubmit={handleUpdate}
                        onCancel={() => setShowEdit(null)}
                        submitting={submitting}
                        error={error}
                        isEdit={true}
                    />
                )}
            </Modal>

            {/* Bulk modal */}
            <Modal isOpen={showBulk} onClose={() => setShowBulk(false)} title="Bulk Schedule Agents">
                <BulkScheduleForm
                    agents={agents}
                    onSubmit={handleBulkSubmit}
                    onCancel={() => setShowBulk(false)}
                    submitting={submitting}
                    error={error}
                />
            </Modal>
        </div>
    );
}

function BulkScheduleForm({ agents, onSubmit, onCancel, submitting, error }) {
    const [selectedAgents, setSelectedAgents] = useState([]);
    const [dates, setDates] = useState([new Date().toISOString().split('T')[0]]);
    const [shiftType, setShiftType] = useState('full_day');
    const [startTime, setStartTime] = useState('06:00');
    const [endTime, setEndTime] = useState('18:00');
    const [title, setTitle] = useState('');
    const [ward, setWard] = useState('');
    const [pollingStation, setPollingStation] = useState('');
    const [constituency, setConstituency] = useState('');
    const [county, setCounty] = useState('');
    const [notes, setNotes] = useState('');

    const SHIFT_PRESETS = {
        morning: { start: '06:00', end: '12:00' },
        afternoon: { start: '12:00', end: '18:00' },
        evening: { start: '18:00', end: '00:00' },
        night: { start: '00:00', end: '06:00' },
        full_day: { start: '06:00', end: '18:00' },
    };

    const toggleAgent = (id) => {
        setSelectedAgents((prev) =>
            prev.includes(id) ? prev.filter((a) => a !== id) : [...prev, id]
        );
    };

    const addDate = () => {
        const last = dates[dates.length - 1];
        const next = new Date(last);
        next.setDate(next.getDate() + 1);
        setDates([...dates, next.toISOString().split('T')[0]]);
    };

    const removeDate = (idx) => {
        if (dates.length <= 1) return;
        setDates(dates.filter((_, i) => i !== idx));
    };

    const handleShiftChange = (type) => {
        setShiftType(type);
        const preset = SHIFT_PRESETS[type];
        if (preset) {
            setStartTime(preset.start);
            setEndTime(preset.end);
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        onSubmit({
            field_agent_ids: selectedAgents,
            dates,
            shift_type: shiftType,
            start_time: shiftType === 'custom' ? startTime : undefined,
            end_time: shiftType === 'custom' ? endTime : undefined,
            title: title || undefined,
            ward: ward || undefined,
            polling_station: pollingStation || undefined,
            constituency: constituency || undefined,
            county: county || undefined,
            notes: notes || undefined,
        });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            {error && <div className="bg-red-50 text-red-700 px-3 py-2 rounded text-sm">{error}</div>}

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                    Select Agents ({selectedAgents.length} selected)
                </label>
                <div className="border rounded-lg max-h-40 overflow-y-auto p-2 space-y-1">
                    {agents.map((a) => (
                        <label key={a.id} className="flex items-center gap-2 px-2 py-1 hover:bg-gray-50 rounded cursor-pointer text-sm">
                            <input
                                type="checkbox"
                                checked={selectedAgents.includes(a.id)}
                                onChange={() => toggleAgent(a.id)}
                                className="rounded border-gray-300 text-primary-600"
                            />
                            {a.user?.name || a.agent_code || `Agent #${a.id}`}
                            {a.ward && <span className="text-xs text-gray-400">— {a.ward}</span>}
                        </label>
                    ))}
                </div>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Dates</label>
                {dates.map((d, i) => (
                    <div key={i} className="flex items-center gap-2 mb-1">
                        <input
                            type="date"
                            value={d}
                            onChange={(e) => {
                                const newDates = [...dates];
                                newDates[i] = e.target.value;
                                setDates(newDates);
                            }}
                            className="border rounded-lg px-3 py-1.5 text-sm flex-1"
                        />
                        {dates.length > 1 && (
                            <button type="button" onClick={() => removeDate(i)} className="text-red-400 hover:text-red-600 text-sm">✕</button>
                        )}
                    </div>
                ))}
                <button type="button" onClick={addDate} className="text-primary-600 text-sm hover:underline mt-1">+ Add date</button>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Shift</label>
                <select value={shiftType} onChange={(e) => handleShiftChange(e.target.value)} className="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="morning">Morning (06:00–12:00)</option>
                    <option value="afternoon">Afternoon (12:00–18:00)</option>
                    <option value="evening">Evening (18:00–00:00)</option>
                    <option value="night">Night (00:00–06:00)</option>
                    <option value="full_day">Full Day (06:00–18:00)</option>
                    <option value="custom">Custom</option>
                </select>
            </div>

            {shiftType === 'custom' && (
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Start</label>
                        <input type="time" value={startTime} onChange={(e) => setStartTime(e.target.value)} className="w-full border rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">End</label>
                        <input type="time" value={endTime} onChange={(e) => setEndTime(e.target.value)} className="w-full border rounded-lg px-3 py-2 text-sm" />
                    </div>
                </div>
            )}

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Title (optional)</label>
                <input type="text" value={title} onChange={(e) => setTitle(e.target.value)} placeholder="e.g., Election Day" className="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Ward</label>
                    <input type="text" value={ward} onChange={(e) => setWard(e.target.value)} className="w-full border rounded-lg px-3 py-2 text-sm" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Polling Station</label>
                    <input type="text" value={pollingStation} onChange={(e) => setPollingStation(e.target.value)} className="w-full border rounded-lg px-3 py-2 text-sm" />
                </div>
            </div>

            <div className="flex justify-between items-center pt-2">
                <span className="text-xs text-gray-500">
                    {selectedAgents.length} agent(s) × {dates.length} date(s) = {selectedAgents.length * dates.length} schedule(s)
                </span>
                <div className="flex space-x-3">
                    <button type="button" onClick={onCancel} className="px-4 py-2 border rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" disabled={submitting || selectedAgents.length === 0} className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                        {submitting ? 'Creating...' : `Create ${selectedAgents.length * dates.length} Schedule(s)`}
                    </button>
                </div>
            </div>
        </form>
    );
}
