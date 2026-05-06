import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import axios from 'axios';
import useSiteStore from '../stores/useSiteStore';

const PRESET_AMOUNTS = [500, 1000, 2500, 5000, 10000, 25000];

function formatCurrency(amount) {
    return `KES ${Number(amount).toLocaleString('en-KE')}`;
}

function ProgressBar({ raised, goal }) {
    if (!goal || goal <= 0) return null;
    const pct = Math.min(Math.round((raised / goal) * 100), 100);
    return (
        <div className="mb-8">
            <div className="flex justify-between text-sm mb-2">
                <span className="font-semibold text-gray-900">{formatCurrency(raised)} raised</span>
                <span className="text-gray-500">of {formatCurrency(goal)} goal</span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                <div
                    className="h-4 rounded-full bg-primary-600 transition-all duration-700"
                    style={{ width: `${pct}%` }}
                />
            </div>
            <p className="text-xs text-gray-500 mt-1">{pct}% funded</p>
        </div>
    );
}

function RecentDonation({ donor }) {
    return (
        <div className="flex items-center justify-between py-3 border-b border-gray-100 last:border-0">
            <div className="flex items-center gap-3">
                <div className="w-8 h-8 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-sm font-semibold">
                    {donor.donor_name?.charAt(0)?.toUpperCase() || 'S'}
                </div>
                <div>
                    <p className="text-sm font-medium text-gray-900">{donor.donor_name}</p>
                    <p className="text-xs text-gray-500">
                        {donor.donated_at ? new Date(donor.donated_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) : ''}
                    </p>
                </div>
            </div>
            <span className="text-sm font-semibold text-primary-700">{formatCurrency(donor.amount)}</span>
        </div>
    );
}

export default function DonatePage() {
    const { slug } = useParams();
    const { site } = useSiteStore();
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);
    const [form, setForm] = useState({
        phone_number: '',
        amount: '',
        donor_name: '',
        donor_email: '',
        is_anonymous: false,
    });
    const [customAmount, setCustomAmount] = useState('');
    const [selectedPreset, setSelectedPreset] = useState(null);
    const [submitting, setSubmitting] = useState(false);
    const [success, setSuccess] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (!site?.id) return;
        axios.get(`/api/sites/${site.id}/donations/stats`)
            .then(({ data }) => { setStats(data.data); setLoading(false); })
            .catch(() => setLoading(false));
    }, [site?.id]);

    const handlePresetClick = (amount) => {
        setSelectedPreset(amount);
        setCustomAmount('');
        setForm((prev) => ({ ...prev, amount: amount }));
    };

    const handleCustomAmount = (value) => {
        setCustomAmount(value);
        setSelectedPreset(null);
        setForm((prev) => ({ ...prev, amount: value ? Number(value) : '' }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!form.amount || form.amount < 10) {
            setError('Please enter an amount of at least KES 10.');
            return;
        }
        if (!form.phone_number) {
            setError('Please enter your M-Pesa phone number.');
            return;
        }

        setSubmitting(true);
        setError(null);
        try {
            const { data } = await axios.post(`/api/sites/${site.id}/donate`, form);
            setSuccess(data.message);
            setForm({ phone_number: '', amount: '', donor_name: '', donor_email: '', is_anonymous: false });
            setSelectedPreset(null);
            setCustomAmount('');
            // Refresh stats
            const statsRes = await axios.get(`/api/sites/${site.id}/donations/stats`);
            setStats(statsRes.data.data);
        } catch (err) {
            setError(err.response?.data?.message || 'Something went wrong. Please try again.');
        }
        setSubmitting(false);
    };

    const primaryColor = site?.primary_color || '#16a34a';

    if (loading) {
        return (
            <div className="min-h-[60vh] flex items-center justify-center">
                <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600" />
            </div>
        );
    }

    return (
        <div>
            {/* Hero */}
            <section className="py-16" style={{ background: `linear-gradient(135deg, ${primaryColor}ee, ${site?.secondary_color || '#0f172a'}ee)` }}>
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-white">
                    <h1 className="text-3xl md:text-4xl font-heading font-bold mb-4">
                        Support {site?.candidate_name || 'Our Campaign'}
                    </h1>
                    <p className="text-lg opacity-90 max-w-2xl mx-auto">
                        Your contribution helps build a better future for {site?.constituency || 'our community'}.
                        Every shilling makes a difference.
                    </p>
                </div>
            </section>

            {/* Main content */}
            <section className="py-12 bg-gray-50">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid md:grid-cols-3 gap-8">
                        {/* Donation form */}
                        <div className="md:col-span-2">
                            <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 md:p-8">
                                {stats?.donation_goal > 0 && (
                                    <ProgressBar raised={stats.total_raised} goal={stats.donation_goal} />
                                )}

                                {success ? (
                                    <div className="text-center py-8">
                                        <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                            <svg className="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                        <h3 className="text-xl font-bold text-gray-900 mb-2">Thank You!</h3>
                                        <p className="text-gray-600 mb-6">{success}</p>
                                        <button
                                            onClick={() => setSuccess(null)}
                                            className="btn-primary"
                                        >
                                            Make Another Donation
                                        </button>
                                    </div>
                                ) : (
                                    <form onSubmit={handleSubmit} className="space-y-6">
                                        <div>
                                            <h3 className="text-lg font-semibold text-gray-900 mb-3">Select Amount</h3>
                                            <div className="grid grid-cols-3 gap-3">
                                                {PRESET_AMOUNTS.map((amt) => (
                                                    <button
                                                        key={amt}
                                                        type="button"
                                                        onClick={() => handlePresetClick(amt)}
                                                        className={`py-3 px-4 rounded-xl text-sm font-semibold border-2 transition-all ${
                                                            selectedPreset === amt
                                                                ? 'border-primary-600 bg-primary-50 text-primary-700'
                                                                : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'
                                                        }`}
                                                    >
                                                        KES {amt.toLocaleString()}
                                                    </button>
                                                ))}
                                            </div>
                                            <div className="mt-3">
                                                <label className="block text-sm font-medium text-gray-700 mb-1">Or enter custom amount (KES)</label>
                                                <input
                                                    type="number"
                                                    min="10"
                                                    max="150000"
                                                    value={customAmount}
                                                    onChange={(e) => handleCustomAmount(e.target.value)}
                                                    placeholder="e.g. 3,000"
                                                    className="w-full border border-gray-300 rounded-xl px-4 py-3 text-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                                />
                                            </div>
                                        </div>

                                        <div>
                                            <h3 className="text-lg font-semibold text-gray-900 mb-3">Your Details</h3>
                                            <div className="space-y-3">
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700 mb-1">M-Pesa Phone Number *</label>
                                                    <input
                                                        type="tel"
                                                        value={form.phone_number}
                                                        onChange={(e) => setForm({ ...form, phone_number: e.target.value })}
                                                        placeholder="0712345678"
                                                        className="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                                        required
                                                    />
                                                </div>
                                                <div className="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label className="block text-sm font-medium text-gray-700 mb-1">Your Name</label>
                                                        <input
                                                            type="text"
                                                            value={form.donor_name}
                                                            onChange={(e) => setForm({ ...form, donor_name: e.target.value })}
                                                            placeholder="John Doe"
                                                            className="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                                        />
                                                    </div>
                                                    <div>
                                                        <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                                        <input
                                                            type="email"
                                                            value={form.donor_email}
                                                            onChange={(e) => setForm({ ...form, donor_email: e.target.value })}
                                                            placeholder="you@example.com"
                                                            className="w-full border border-gray-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                                        />
                                                    </div>
                                                </div>
                                                <label className="flex items-center gap-2 cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        checked={form.is_anonymous}
                                                        onChange={(e) => setForm({ ...form, is_anonymous: e.target.checked })}
                                                        className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                                    />
                                                    <span className="text-sm text-gray-600">Make this donation anonymous</span>
                                                </label>
                                            </div>
                                        </div>

                                        {error && (
                                            <div className="bg-red-50 text-red-700 px-4 py-3 rounded-xl text-sm">
                                                {error}
                                            </div>
                                        )}

                                        <button
                                            type="submit"
                                            disabled={submitting || !form.amount}
                                            className="w-full btn-primary !py-4 text-lg disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            {submitting ? (
                                                <span className="flex items-center justify-center gap-2">
                                                    <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none"/><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                                    Processing...
                                                </span>
                                            ) : (
                                                `Donate ${form.amount ? formatCurrency(form.amount) : ''} via M-Pesa`
                                            )}
                                        </button>

                                        <p className="text-xs text-gray-500 text-center">
                                            Payments are processed securely via Safaricom M-Pesa.
                                            You will receive an STK push notification on your phone.
                                        </p>
                                    </form>
                                )}
                            </div>
                        </div>

                        {/* Sidebar */}
                        <div className="space-y-6">
                            {/* Stats */}
                            <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                                <h3 className="font-semibold text-gray-900 mb-4">Campaign Fund</h3>
                                <div className="space-y-3">
                                    <div className="flex justify-between">
                                        <span className="text-sm text-gray-500">Total Raised</span>
                                        <span className="text-sm font-bold text-gray-900">{formatCurrency(stats?.total_raised || 0)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-sm text-gray-500">Supporters</span>
                                        <span className="text-sm font-bold text-gray-900">{stats?.donor_count || 0}</span>
                                    </div>
                                    {stats?.donation_goal > 0 && (
                                        <div className="flex justify-between">
                                            <span className="text-sm text-gray-500">Goal</span>
                                            <span className="text-sm font-bold text-gray-900">{formatCurrency(stats.donation_goal)}</span>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Recent donations */}
                            {stats?.recent_donations?.length > 0 && (
                                <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                                    <h3 className="font-semibold text-gray-900 mb-4">Recent Supporters</h3>
                                    <div>
                                        {stats.recent_donations.map((d, i) => (
                                            <RecentDonation key={i} donor={d} />
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* M-Pesa info */}
                            {site?.donation_info && (
                                <div className="bg-primary-50 rounded-2xl p-6 border border-primary-100">
                                    <h3 className="font-semibold text-primary-900 mb-3">Other Ways to Donate</h3>
                                    <div className="text-sm text-primary-800 prose prose-sm" dangerouslySetInnerHTML={{ __html: site.donation_info }} />
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </section>
        </div>
    );
}
