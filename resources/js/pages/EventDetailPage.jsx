import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import axios from 'axios';
import useSiteStore from '../stores/useSiteStore';

function generateICS(event) {
    const start = new Date(event.date);
    if (event.time) {
        const [h, m] = event.time.replace(/[AP]M/i, '').trim().split(':').map(Number);
        const isPM = /PM/i.test(event.time);
        start.setHours(isPM && h !== 12 ? h + 12 : h === 12 && !isPM ? 0 : h, m || 0);
    }
    const end = new Date(start.getTime() + 2 * 60 * 60 * 1000);
    const fmt = (d) => d.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');

    return [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'BEGIN:VEVENT',
        `DTSTART:${fmt(start)}`, `DTEND:${fmt(end)}`,
        `SUMMARY:${event.title}`, `LOCATION:${event.location || ''}`,
        `DESCRIPTION:${(event.description || '').replace(/\n/g, '\\n')}`,
        'END:VEVENT', 'END:VCALENDAR',
    ].join('\r\n');
}

export default function EventDetailPage() {
    const { slug, eventId } = useParams();
    const { site } = useSiteStore();
    const [event, setEvent] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [rsvpForm, setRsvpForm] = useState({ name: '', phone: '', email: '' });
    const [rsvpSubmitting, setRsvpSubmitting] = useState(false);
    const [rsvpSuccess, setRsvpSuccess] = useState(false);
    const [rsvpError, setRsvpError] = useState(null);

    useEffect(() => {
        if (!site?.id) return;
        setLoading(true);
        axios.get(`/api/sites/${site.id}/events/${eventId}`)
            .then(({ data }) => { setEvent(data.data); setLoading(false); })
            .catch(() => { setError('Event not found'); setLoading(false); });
    }, [site?.id, eventId]);

    const handleRsvp = async (e) => {
        e.preventDefault();
        setRsvpSubmitting(true); setRsvpError(null);
        try {
            await axios.post(`/api/sites/${site.id}/events/${eventId}/rsvp`, rsvpForm);
            setRsvpSuccess(true);
        } catch (err) {
            setRsvpError(err.response?.data?.message || 'Failed to RSVP');
        }
        setRsvpSubmitting(false);
    };

    const handleAddToCalendar = () => {
        if (!event) return;
        const ics = generateICS(event);
        const blob = new Blob([ics], { type: 'text/calendar' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${event.title.replace(/\s+/g, '-')}.ics`;
        a.click();
        URL.revokeObjectURL(url);
    };

    if (loading) {
        return (
            <div className="min-h-[60vh] flex items-center justify-center">
                <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600" />
            </div>
        );
    }

    if (error || !event) {
        return (
            <div className="min-h-[60vh] flex items-center justify-center">
                <div className="text-center">
                    <h2 className="text-2xl font-bold text-gray-800 mb-2">Event Not Found</h2>
                    <Link to={`/${slug}/events`} className="text-primary-600 hover:underline">Back to Events</Link>
                </div>
            </div>
        );
    }

    const date = new Date(event.date);

    return (
        <div>
            <section className="bg-dark-900 text-white py-16">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                    <Link to={`/${slug}/events`} className="text-sm text-gray-400 hover:text-white mb-4 inline-block">&larr; Back to Events</Link>
                    <h1 className="text-3xl md:text-4xl font-heading font-bold mb-4">{event.title}</h1>
                    <div className="flex flex-wrap gap-4 text-sm text-gray-300">
                        <span className="flex items-center gap-1.5">
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            {date.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}
                        </span>
                        {event.time && (
                            <span className="flex items-center gap-1.5">
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                {event.time}
                            </span>
                        )}
                        {event.location && (
                            <span className="flex items-center gap-1.5">
                                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                {event.location}
                            </span>
                        )}
                    </div>
                </div>
            </section>

            <section className="py-12 bg-white">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid md:grid-cols-3 gap-8">
                        <div className="md:col-span-2">
                            <h2 className="text-xl font-heading font-bold mb-4">About This Event</h2>
                            <div className="text-gray-700 leading-relaxed">
                                {event.description ? (
                                    event.description.split('\n').map((para, i) => para.trim() ? <p key={i} className="mb-3">{para}</p> : null)
                                ) : (
                                    <p className="text-gray-500 italic">No description provided.</p>
                                )}
                            </div>

                            {(event.ward || event.constituency || event.county) && (
                                <div className="mt-6 p-4 bg-gray-50 rounded-lg">
                                    <h3 className="font-semibold text-sm text-gray-800 mb-2">Location Details</h3>
                                    <div className="flex flex-wrap gap-2 text-sm text-gray-600">
                                        {event.ward && <span className="bg-white px-3 py-1 rounded-full border border-gray-200">{event.ward}</span>}
                                        {event.constituency && <span className="bg-white px-3 py-1 rounded-full border border-gray-200">{event.constituency}</span>}
                                        {event.county && <span className="bg-white px-3 py-1 rounded-full border border-gray-200">{event.county}</span>}
                                    </div>
                                </div>
                            )}

                            <div className="mt-6">
                                <button
                                    onClick={handleAddToCalendar}
                                    className="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition"
                                >
                                    <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    Add to Calendar
                                </button>
                            </div>
                        </div>

                        {/* RSVP Form */}
                        <div className="bg-gray-50 rounded-xl p-6">
                            <h3 className="text-lg font-heading font-bold mb-4">RSVP</h3>
                            {rsvpSuccess ? (
                                <div className="text-center py-4">
                                    <div className="text-3xl mb-2">&#127881;</div>
                                    <p className="font-semibold text-green-700">You're registered!</p>
                                    <p className="text-sm text-gray-500 mt-1">We look forward to seeing you.</p>
                                </div>
                            ) : (
                                <form onSubmit={handleRsvp} className="space-y-3">
                                    {rsvpError && <p className="text-sm text-red-600 bg-red-50 p-2 rounded">{rsvpError}</p>}
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                                        <input type="text" required value={rsvpForm.name}
                                            onChange={(e) => setRsvpForm((f) => ({ ...f, name: e.target.value }))}
                                            className="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500" />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                                        <input type="tel" required value={rsvpForm.phone}
                                            onChange={(e) => setRsvpForm((f) => ({ ...f, phone: e.target.value }))}
                                            placeholder="+254..." className="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500" />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                        <input type="email" value={rsvpForm.email}
                                            onChange={(e) => setRsvpForm((f) => ({ ...f, email: e.target.value }))}
                                            className="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-primary-500 focus:ring-primary-500" />
                                    </div>
                                    <button type="submit" disabled={rsvpSubmitting}
                                        className="w-full btn-primary !py-2.5 text-sm disabled:opacity-50">
                                        {rsvpSubmitting ? 'Submitting...' : 'Confirm RSVP'}
                                    </button>
                                </form>
                            )}
                        </div>
                    </div>

                    <div className="mt-10 pt-8 border-t border-gray-200">
                        <Link to={`/${slug}/events`} className="text-primary-600 hover:text-primary-700 font-medium">
                            &larr; All Events
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    );
}
