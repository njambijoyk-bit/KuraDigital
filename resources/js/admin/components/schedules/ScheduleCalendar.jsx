import React, { useState, useEffect, useCallback } from 'react';
import { ChevronLeftIcon, ChevronRightIcon, UserIcon } from '@heroicons/react/24/outline';
import api from '../../../lib/api';

const shiftDots = {
    morning: 'bg-yellow-400',
    afternoon: 'bg-orange-400',
    evening: 'bg-purple-400',
    night: 'bg-indigo-400',
    full_day: 'bg-blue-400',
    custom: 'bg-gray-400',
};

const statusBorder = {
    scheduled: 'border-l-blue-400',
    in_progress: 'border-l-green-400',
    completed: 'border-l-gray-300',
    cancelled: 'border-l-red-300',
    no_show: 'border-l-amber-400',
};

function getWeekDates(referenceDate) {
    const d = new Date(referenceDate);
    const day = d.getDay();
    const monday = new Date(d);
    monday.setDate(d.getDate() - ((day + 6) % 7));
    const dates = [];
    for (let i = 0; i < 7; i++) {
        const date = new Date(monday);
        date.setDate(monday.getDate() + i);
        dates.push(date);
    }
    return dates;
}

function formatDate(d) {
    return d.toISOString().split('T')[0];
}

export default function ScheduleCalendar({ campaignId }) {
    const [currentDate, setCurrentDate] = useState(new Date());
    const [calendar, setCalendar] = useState({});
    const [loading, setLoading] = useState(true);

    const weekDates = getWeekDates(currentDate);
    const dateFrom = formatDate(weekDates[0]);
    const dateTo = formatDate(weekDates[6]);

    const fetchCalendar = useCallback(async () => {
        setLoading(true);
        try {
            const { data } = await api.get(`/campaigns/${campaignId}/agent-schedules/calendar`, {
                params: { date_from: dateFrom, date_to: dateTo },
            });
            setCalendar(data.calendar || {});
        } catch { /* handled */ }
        setLoading(false);
    }, [campaignId, dateFrom, dateTo]);

    useEffect(() => { fetchCalendar(); }, [fetchCalendar]);

    const prevWeek = () => {
        const d = new Date(currentDate);
        d.setDate(d.getDate() - 7);
        setCurrentDate(d);
    };

    const nextWeek = () => {
        const d = new Date(currentDate);
        d.setDate(d.getDate() + 7);
        setCurrentDate(d);
    };

    const goToday = () => setCurrentDate(new Date());

    const today = formatDate(new Date());

    return (
        <div className="space-y-4">
            {/* Navigation */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <button onClick={prevWeek} className="p-1.5 rounded-lg border hover:bg-gray-50">
                        <ChevronLeftIcon className="h-4 w-4" />
                    </button>
                    <button onClick={goToday} className="px-3 py-1.5 rounded-lg border text-sm hover:bg-gray-50 font-medium">
                        Today
                    </button>
                    <button onClick={nextWeek} className="p-1.5 rounded-lg border hover:bg-gray-50">
                        <ChevronRightIcon className="h-4 w-4" />
                    </button>
                    <span className="ml-2 text-sm font-medium text-gray-700">
                        {weekDates[0].toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })} – {weekDates[6].toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}
                    </span>
                </div>
                <div className="flex items-center gap-3 text-xs text-gray-500">
                    {Object.entries(shiftDots).map(([key, color]) => (
                        <span key={key} className="flex items-center gap-1">
                            <span className={`h-2.5 w-2.5 rounded-full ${color}`} />
                            {key.replace('_', ' ')}
                        </span>
                    ))}
                </div>
            </div>

            {loading ? (
                <div className="flex justify-center py-12">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" />
                </div>
            ) : (
                <div className="grid grid-cols-7 gap-px bg-gray-200 rounded-lg overflow-hidden border">
                    {/* Day headers */}
                    {weekDates.map((d) => {
                        const dateStr = formatDate(d);
                        const isToday = dateStr === today;
                        return (
                            <div key={dateStr} className={`bg-gray-50 px-2 py-2 text-center ${isToday ? 'bg-primary-50' : ''}`}>
                                <div className="text-xs font-medium text-gray-500 uppercase">
                                    {d.toLocaleDateString('en-GB', { weekday: 'short' })}
                                </div>
                                <div className={`text-lg font-semibold ${isToday ? 'text-primary-600' : 'text-gray-900'}`}>
                                    {d.getDate()}
                                </div>
                            </div>
                        );
                    })}

                    {/* Day cells */}
                    {weekDates.map((d) => {
                        const dateStr = formatDate(d);
                        const daySchedules = calendar[dateStr] || [];
                        const isToday = dateStr === today;
                        return (
                            <div key={`cell-${dateStr}`} className={`bg-white min-h-[160px] p-1.5 ${isToday ? 'bg-primary-50/30' : ''}`}>
                                {daySchedules.length === 0 ? (
                                    <div className="h-full flex items-center justify-center">
                                        <span className="text-xs text-gray-300">No schedules</span>
                                    </div>
                                ) : (
                                    <div className="space-y-1">
                                        {daySchedules.map((s) => (
                                            <div
                                                key={s.id}
                                                className={`rounded px-1.5 py-1 border-l-2 ${statusBorder[s.status] || 'border-l-gray-300'} bg-white shadow-sm hover:shadow transition-shadow`}
                                                title={`${s.field_agent?.user?.name || 'Agent'} — ${s.shift_type} (${s.start_time?.slice(0, 5)}–${s.end_time?.slice(0, 5)})${s.ward ? ' @ ' + s.ward : ''}`}
                                            >
                                                <div className="flex items-center gap-1">
                                                    <span className={`h-2 w-2 rounded-full ${shiftDots[s.shift_type] || shiftDots.custom} flex-shrink-0`} />
                                                    <span className="text-xs font-medium text-gray-900 truncate">
                                                        {s.field_agent?.user?.name || 'Agent'}
                                                    </span>
                                                </div>
                                                <div className="text-[10px] text-gray-400 ml-3">
                                                    {s.start_time?.slice(0, 5)}–{s.end_time?.slice(0, 5)}
                                                    {s.ward && <span> · {s.ward}</span>}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
