import React, { useEffect } from 'react';
import useSiteStore from '../stores/useSiteStore';
import useContentStore from '../stores/useContentStore';

function EventCard({ event }) {
    const date = new Date(event.date);
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    return (
        <div className="card flex flex-col sm:flex-row overflow-hidden">
            <div className="sm:w-24 bg-primary-600 text-white flex sm:flex-col items-center justify-center p-4 gap-2 sm:gap-0">
                <span className="text-sm font-semibold uppercase">{monthNames[date.getMonth()]}</span>
                <span className="text-3xl font-bold">{date.getDate()}</span>
                <span className="text-xs">{date.getFullYear()}</span>
            </div>
            <div className="p-5 flex-1">
                <h3 className="font-heading font-bold text-lg mb-1">{event.title}</h3>
                <div className="flex flex-wrap gap-4 text-sm text-gray-500 mb-3">
                    <span className="flex items-center gap-1">
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        {event.time || 'TBA'}
                    </span>
                    <span className="flex items-center gap-1">
                        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        {event.location || 'TBA'}
                    </span>
                </div>
                <p className="text-gray-600 text-sm">{event.description}</p>
            </div>
        </div>
    );
}

const defaultEvents = [
    { id: 1, title: 'Community Rally — Town Centre', date: '2027-06-15', time: '10:00 AM', location: 'Main Grounds', description: 'Join us for a major campaign rally with music, speeches, and community engagement.' },
    { id: 2, title: 'Youth Town Hall', date: '2027-06-20', time: '2:00 PM', location: 'Youth Centre', description: 'An open forum for youth to discuss employment, education, and opportunities.' },
    { id: 3, title: 'Women\'s Empowerment Forum', date: '2027-06-25', time: '9:00 AM', location: 'Community Hall', description: 'Discussing women\'s issues, table banking, and economic empowerment programs.' },
];

export default function EventsPage() {
    const { site } = useSiteStore();
    const { events, fetchEvents } = useContentStore();

    useEffect(() => {
        if (site?.id) fetchEvents(site.id);
    }, [site?.id, fetchEvents]);

    const displayEvents = events.length > 0 ? events : defaultEvents;

    return (
        <div>
            <section className="bg-dark-900 text-white py-20">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h1 className="text-4xl md:text-5xl font-heading font-bold mb-4">Upcoming Events</h1>
                    <p className="text-lg text-gray-300 max-w-2xl mx-auto">
                        Join us on the campaign trail. Meet the candidate, ask questions, and be part of the movement.
                    </p>
                </div>
            </section>

            <section className="py-16 bg-gray-50">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    {displayEvents.map((event) => (
                        <EventCard key={event.id} event={event} />
                    ))}
                    {displayEvents.length === 0 && (
                        <p className="text-center text-gray-500 py-12">No upcoming events. Check back soon!</p>
                    )}
                </div>
            </section>
        </div>
    );
}
