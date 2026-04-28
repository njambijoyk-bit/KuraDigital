import React, { useState } from 'react';
import axios from 'axios';
import useSiteStore from '../stores/useSiteStore';

export default function VolunteerPage() {
    const { site } = useSiteStore();
    const [form, setForm] = useState({ name: '', phone: '', email: '', ward: '', role: 'volunteer', skills: '' });
    const [status, setStatus] = useState(null);
    const [sending, setSending] = useState(false);

    const handleChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSending(true);
        try {
            await axios.post(`/api/sites/${site?.id}/volunteers`, form);
            setStatus('success');
            setForm({ name: '', phone: '', email: '', ward: '', role: 'volunteer', skills: '' });
        } catch {
            setStatus('error');
        }
        setSending(false);
    };

    return (
        <div>
            <section className="bg-dark-900 text-white py-20">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h1 className="text-4xl md:text-5xl font-heading font-bold mb-4">Get Involved</h1>
                    <p className="text-lg text-gray-300 max-w-2xl mx-auto">
                        Join our growing team of supporters and help build a better future for {site?.constituency || 'our constituency'}.
                    </p>
                </div>
            </section>

            <section className="py-16 bg-gray-50">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid md:grid-cols-2 gap-12 items-start">
                        <div>
                            <h2 className="font-heading font-bold text-2xl mb-6">Why Volunteer?</h2>
                            <div className="space-y-4">
                                {[
                                    { icon: '🤝', title: 'Make an Impact', desc: 'Your time and skills directly contribute to positive change in the community.' },
                                    { icon: '📢', title: 'Amplify the Message', desc: 'Help spread the word about our vision and manifesto to every voter.' },
                                    { icon: '🏘️', title: 'Build Community', desc: 'Connect with like-minded people who share your passion for development.' },
                                    { icon: '📊', title: 'Election Day Support', desc: 'Serve as polling agents, mobilisers, or logistics coordinators on voting day.' },
                                ].map((item, i) => (
                                    <div key={i} className="flex items-start space-x-4">
                                        <span className="text-2xl mt-1">{item.icon}</span>
                                        <div>
                                            <h4 className="font-semibold">{item.title}</h4>
                                            <p className="text-gray-600 text-sm">{item.desc}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {site?.donation_info && (
                                <div className="mt-8 p-6 bg-accent-50 rounded-xl border border-accent-200">
                                    <h3 className="font-heading font-bold text-lg mb-2">Support the Campaign</h3>
                                    <div className="text-sm text-gray-700" dangerouslySetInnerHTML={{ __html: site.donation_info }} />
                                </div>
                            )}
                        </div>

                        <div className="card p-8">
                            <h2 className="font-heading font-bold text-2xl mb-6">Sign Up</h2>
                            {status === 'success' && (
                                <div className="mb-4 p-3 bg-green-50 text-green-700 rounded-lg text-sm">
                                    Welcome aboard! We'll be in touch soon.
                                </div>
                            )}
                            {status === 'error' && (
                                <div className="mb-4 p-3 bg-red-50 text-red-700 rounded-lg text-sm">
                                    Something went wrong. Please try again.
                                </div>
                            )}
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                                    <input type="text" name="name" value={form.name} onChange={handleChange} required
                                           className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                                        <input type="tel" name="phone" value={form.phone} onChange={handleChange} required
                                               className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                        <input type="email" name="email" value={form.email} onChange={handleChange}
                                               className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                                    </div>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Ward / Location</label>
                                    <input type="text" name="ward" value={form.ward} onChange={handleChange}
                                           className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                    <select name="role" value={form.role} onChange={handleChange}
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                        <option value="volunteer">General Volunteer</option>
                                        <option value="agent">Polling Agent</option>
                                        <option value="mobiliser">Community Mobiliser</option>
                                        <option value="social_media">Social Media Ambassador</option>
                                        <option value="logistics">Logistics & Transport</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Skills / How can you help?</label>
                                    <textarea name="skills" value={form.skills} onChange={handleChange} rows={3}
                                              className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                                </div>
                                <button type="submit" disabled={sending} className="btn-primary w-full">
                                    {sending ? 'Signing Up...' : 'Join the Team'}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    );
}
