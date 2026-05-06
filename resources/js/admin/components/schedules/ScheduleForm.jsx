import React, { useState, useEffect } from 'react';

const SHIFT_TYPES = [
    { value: 'morning', label: 'Morning (06:00–12:00)' },
    { value: 'afternoon', label: 'Afternoon (12:00–18:00)' },
    { value: 'evening', label: 'Evening (18:00–00:00)' },
    { value: 'night', label: 'Night (00:00–06:00)' },
    { value: 'full_day', label: 'Full Day (06:00–18:00)' },
    { value: 'custom', label: 'Custom' },
];

const SHIFT_PRESETS = {
    morning: { start: '06:00', end: '12:00' },
    afternoon: { start: '12:00', end: '18:00' },
    evening: { start: '18:00', end: '00:00' },
    night: { start: '00:00', end: '06:00' },
    full_day: { start: '06:00', end: '18:00' },
};

const STATUSES = ['scheduled', 'in_progress', 'completed', 'cancelled', 'no_show'];

export default function ScheduleForm({ agents, initial, onSubmit, onCancel, submitting, error, isEdit }) {
    const [form, setForm] = useState({
        field_agent_id: '',
        title: '',
        shift_type: 'full_day',
        date: new Date().toISOString().split('T')[0],
        start_time: '06:00',
        end_time: '18:00',
        ward: '',
        constituency: '',
        county: '',
        polling_station: '',
        status: 'scheduled',
        notes: '',
        ...initial,
    });

    useEffect(() => {
        if (initial) setForm((prev) => ({ ...prev, ...initial }));
    }, [initial]);

    const handleShiftChange = (shiftType) => {
        const preset = SHIFT_PRESETS[shiftType];
        setForm((prev) => ({
            ...prev,
            shift_type: shiftType,
            ...(preset ? { start_time: preset.start, end_time: preset.end } : {}),
        }));
    };

    const handleAgentChange = (agentId) => {
        const agent = agents.find((a) => String(a.id) === String(agentId));
        setForm((prev) => ({
            ...prev,
            field_agent_id: agentId,
            ward: agent?.ward || prev.ward,
            constituency: agent?.constituency || prev.constituency,
            county: agent?.county || prev.county,
            polling_station: agent?.polling_station || prev.polling_station,
        }));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        onSubmit(form);
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            {error && <div className="bg-red-50 text-red-700 px-3 py-2 rounded text-sm">{error}</div>}

            {!isEdit && (
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Agent *</label>
                    <select
                        value={form.field_agent_id}
                        onChange={(e) => handleAgentChange(e.target.value)}
                        className="w-full border rounded-lg px-3 py-2 text-sm"
                        required
                    >
                        <option value="">Select an agent...</option>
                        {agents.map((a) => (
                            <option key={a.id} value={a.id}>
                                {a.user?.name || a.agent_code || `Agent #${a.id}`}
                                {a.ward ? ` — ${a.ward}` : ''}
                            </option>
                        ))}
                    </select>
                </div>
            )}

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Title (optional)</label>
                <input
                    type="text"
                    value={form.title}
                    onChange={(e) => setForm({ ...form, title: e.target.value })}
                    placeholder="e.g., Election Day Coverage"
                    className="w-full border rounded-lg px-3 py-2 text-sm"
                />
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                    <input
                        type="date"
                        value={form.date}
                        onChange={(e) => setForm({ ...form, date: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2 text-sm"
                        required
                    />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Shift *</label>
                    <select
                        value={form.shift_type}
                        onChange={(e) => handleShiftChange(e.target.value)}
                        className="w-full border rounded-lg px-3 py-2 text-sm"
                    >
                        {SHIFT_TYPES.map((s) => (
                            <option key={s.value} value={s.value}>{s.label}</option>
                        ))}
                    </select>
                </div>
            </div>

            {form.shift_type === 'custom' && (
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Start Time *</label>
                        <input
                            type="time"
                            value={form.start_time}
                            onChange={(e) => setForm({ ...form, start_time: e.target.value })}
                            className="w-full border rounded-lg px-3 py-2 text-sm"
                            required
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">End Time *</label>
                        <input
                            type="time"
                            value={form.end_time}
                            onChange={(e) => setForm({ ...form, end_time: e.target.value })}
                            className="w-full border rounded-lg px-3 py-2 text-sm"
                            required
                        />
                    </div>
                </div>
            )}

            {isEdit && (
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select
                        value={form.status}
                        onChange={(e) => setForm({ ...form, status: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2 text-sm"
                    >
                        {STATUSES.map((s) => (
                            <option key={s} value={s}>{s.replace('_', ' ')}</option>
                        ))}
                    </select>
                </div>
            )}

            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Ward</label>
                    <input
                        type="text"
                        value={form.ward}
                        onChange={(e) => setForm({ ...form, ward: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2 text-sm"
                    />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Polling Station</label>
                    <input
                        type="text"
                        value={form.polling_station}
                        onChange={(e) => setForm({ ...form, polling_station: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2 text-sm"
                    />
                </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Constituency</label>
                    <input
                        type="text"
                        value={form.constituency}
                        onChange={(e) => setForm({ ...form, constituency: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2 text-sm"
                    />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">County</label>
                    <input
                        type="text"
                        value={form.county}
                        onChange={(e) => setForm({ ...form, county: e.target.value })}
                        className="w-full border rounded-lg px-3 py-2 text-sm"
                    />
                </div>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                <textarea
                    value={form.notes}
                    onChange={(e) => setForm({ ...form, notes: e.target.value })}
                    className="w-full border rounded-lg px-3 py-2 text-sm"
                    rows={2}
                />
            </div>

            <div className="flex justify-end space-x-3 pt-2">
                <button type="button" onClick={onCancel} className="px-4 py-2 border rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" disabled={submitting} className="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 disabled:opacity-50">
                    {submitting ? 'Saving...' : isEdit ? 'Update Schedule' : 'Create Schedule'}
                </button>
            </div>
        </form>
    );
}
