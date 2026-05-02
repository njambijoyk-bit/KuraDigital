import React, { useState } from 'react';
import axios from 'axios';
import { Link, useParams } from 'react-router-dom';
import useSiteStore from '../stores/useSiteStore';

const emptyForm = {
    name: '', phone: '', email: '', national_id: '',
    county: '', constituency: '', ward: '', polling_station: '', gender: '',
};

export default function RegisterPage() {
    const { slug } = useParams();
    const { site } = useSiteStore();
    const base = `/${slug}`;
    const [form, setForm] = useState({ ...emptyForm });
    const [status, setStatus] = useState(null);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState(null);

    const handleChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSending(true);
        setError(null);
        try {
            await axios.post(`/api/sites/${site?.id}/register-supporter`, form);
            setStatus('success');
            setForm({ ...emptyForm });
        } catch (err) {
            setError(err.response?.data?.message || 'Something went wrong. Please try again.');
        }
        setSending(false);
    };

    const primaryColor = site?.primary_color || '#16a34a';

    return (
        <div>
            {/* Hero */}
            <section
                className="relative py-20 text-white"
                style={{
                    background: `linear-gradient(135deg, ${primaryColor}ee 0%, ${site?.secondary_color || '#0f172a'}ee 100%)`,
                }}
            >
                <div className="absolute inset-0 bg-black/20" />
                <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h1 className="text-4xl md:text-5xl font-heading font-bold mb-4">Register Your Support</h1>
                    <p className="text-lg text-gray-200 max-w-2xl mx-auto">
                        Stand with {site?.candidate_name || 'us'} and be counted.
                        Register as a supporter to receive campaign updates and help drive change in {site?.constituency || 'our constituency'}.
                    </p>
                </div>
            </section>

            {/* Content */}
            <section className="py-16 bg-gray-50">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid md:grid-cols-2 gap-12 items-start">
                        {/* Left: Info */}
                        <div>
                            <h2 className="font-heading font-bold text-2xl mb-6">Why Register?</h2>
                            <div className="space-y-4">
                                {[
                                    { icon: '📊', title: 'Be Counted', desc: 'Your registration shows the strength of our movement and helps us plan outreach in your area.' },
                                    { icon: '📱', title: 'Stay Informed', desc: 'Receive updates about campaign events, rallies, and important developments near you.' },
                                    { icon: '🗳️', title: 'Polling Station Info', desc: 'We\'ll help connect you with your local polling station details for election day.' },
                                    { icon: '🤝', title: 'Community Network', desc: 'Join a growing network of supporters committed to building a better future together.' },
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

                            <div className="mt-8 p-6 bg-white rounded-xl border border-gray-200 shadow-sm">
                                <h3 className="font-heading font-bold text-lg mb-2">Want to do more?</h3>
                                <p className="text-sm text-gray-600 mb-4">
                                    Beyond registering your support, you can volunteer with the campaign and help make a direct impact.
                                </p>
                                <Link to={`${base}/volunteer`} className="btn-primary text-sm !px-6 !py-2.5 inline-block">
                                    Become a Volunteer
                                </Link>
                            </div>
                        </div>

                        {/* Right: Form */}
                        <div className="card p-8">
                            <h2 className="font-heading font-bold text-2xl mb-6">Supporter Registration</h2>

                            {status === 'success' && (
                                <div className="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg">
                                    <p className="font-medium">Thank you for registering your support!</p>
                                    <p className="text-sm mt-1">We appreciate your commitment. Stay tuned for updates on how you can make a difference.</p>
                                </div>
                            )}

                            {error && (
                                <div className="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                                    {error}
                                </div>
                            )}

                            {status !== 'success' && (
                                <form onSubmit={handleSubmit} className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                                        <input type="text" name="name" value={form.name} onChange={handleChange} required
                                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                            <input type="tel" name="phone" value={form.phone} onChange={handleChange}
                                                placeholder="+254..."
                                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                            <input type="email" name="email" value={form.email} onChange={handleChange}
                                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">National ID</label>
                                            <input type="text" name="national_id" value={form.national_id} onChange={handleChange}
                                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Gender</label>
                                            <select name="gender" value={form.gender} onChange={handleChange}
                                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                                <option value="">— Select —</option>
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                    </div>

                                    <hr className="my-2" />

                                    <p className="text-xs text-gray-500 font-medium uppercase tracking-wider">Location Details</p>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">County</label>
                                            <input type="text" name="county" value={form.county} onChange={handleChange}
                                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Constituency</label>
                                            <input type="text" name="constituency" value={form.constituency} onChange={handleChange}
                                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Ward</label>
                                            <input type="text" name="ward" value={form.ward} onChange={handleChange}
                                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Polling Station</label>
                                            <input type="text" name="polling_station" value={form.polling_station} onChange={handleChange}
                                                className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                                        </div>
                                    </div>

                                    <div className="pt-2">
                                        <button type="submit" disabled={sending}
                                            className="w-full btn-primary !py-3 text-lg disabled:opacity-50">
                                            {sending ? 'Submitting...' : 'Register My Support'}
                                        </button>
                                    </div>

                                    <p className="text-xs text-gray-400 text-center">
                                        Your information is kept confidential and used only for campaign communications.
                                    </p>
                                </form>
                            )}

                            {status === 'success' && (
                                <div className="text-center pt-4">
                                    <button onClick={() => { setStatus(null); setForm({ ...emptyForm }); }}
                                        className="text-sm text-primary-600 hover:text-primary-700 font-medium">
                                        Register another supporter
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </section>
        </div>
    );
}
