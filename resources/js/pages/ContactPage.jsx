import React, { useState } from 'react';
import axios from 'axios';
import useSiteStore from '../stores/useSiteStore';

export default function ContactPage() {
    const { site } = useSiteStore();
    const [form, setForm] = useState({ name: '', email: '', phone: '', message: '' });
    const [status, setStatus] = useState(null);
    const [sending, setSending] = useState(false);

    const handleChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSending(true);
        try {
            await axios.post(`/api/sites/${site?.id}/contact`, form);
            setStatus('success');
            setForm({ name: '', email: '', phone: '', message: '' });
        } catch {
            setStatus('error');
        }
        setSending(false);
    };

    return (
        <div>
            <section className="bg-dark-900 text-white py-20">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h1 className="text-4xl md:text-5xl font-heading font-bold mb-4">Contact Us</h1>
                    <p className="text-lg text-gray-300 max-w-2xl mx-auto">
                        Have a question, concern, or want to share your ideas? We'd love to hear from you.
                    </p>
                </div>
            </section>

            <section className="py-16 bg-gray-50">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid md:grid-cols-2 gap-12">
                        <div>
                            <h2 className="font-heading font-bold text-2xl mb-6">Get in Touch</h2>
                            <div className="space-y-6">
                                {site?.office_address && (
                                    <div className="flex items-start space-x-4">
                                        <div className="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center flex-shrink-0">
                                            <svg className="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        </div>
                                        <div>
                                            <h4 className="font-semibold">Office Address</h4>
                                            <p className="text-gray-600 text-sm">{site.office_address}</p>
                                        </div>
                                    </div>
                                )}
                                {site?.phone && (
                                    <div className="flex items-start space-x-4">
                                        <div className="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center flex-shrink-0">
                                            <svg className="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                        </div>
                                        <div>
                                            <h4 className="font-semibold">Phone</h4>
                                            <a href={`tel:${site.phone}`} className="text-primary-600 hover:underline text-sm">{site.phone}</a>
                                        </div>
                                    </div>
                                )}
                                {site?.email && (
                                    <div className="flex items-start space-x-4">
                                        <div className="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center flex-shrink-0">
                                            <svg className="w-5 h-5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                        </div>
                                        <div>
                                            <h4 className="font-semibold">Email</h4>
                                            <a href={`mailto:${site.email}`} className="text-primary-600 hover:underline text-sm">{site.email}</a>
                                        </div>
                                    </div>
                                )}
                            </div>

                            {!site?.office_address && !site?.phone && !site?.email && (
                                <p className="text-gray-500 italic">Contact details will be displayed once configured by the candidate.</p>
                            )}
                        </div>

                        <div>
                            <div className="card p-8">
                                <h2 className="font-heading font-bold text-2xl mb-6">Send a Message</h2>
                                {status === 'success' && (
                                    <div className="mb-4 p-3 bg-green-50 text-green-700 rounded-lg text-sm">
                                        Thank you! Your message has been sent successfully.
                                    </div>
                                )}
                                {status === 'error' && (
                                    <div className="mb-4 p-3 bg-red-50 text-red-700 rounded-lg text-sm">
                                        Something went wrong. Please try again.
                                    </div>
                                )}
                                <form onSubmit={handleSubmit} className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                        <input type="text" name="name" value={form.name} onChange={handleChange} required
                                               className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                                    </div>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                            <input type="email" name="email" value={form.email} onChange={handleChange}
                                                   className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                            <input type="tel" name="phone" value={form.phone} onChange={handleChange}
                                                   className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                                        </div>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Message</label>
                                        <textarea name="message" value={form.message} onChange={handleChange} rows={4} required
                                                  className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                                    </div>
                                    <button type="submit" disabled={sending} className="btn-primary w-full">
                                        {sending ? 'Sending...' : 'Send Message'}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    );
}
