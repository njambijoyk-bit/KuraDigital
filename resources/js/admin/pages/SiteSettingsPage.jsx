import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { CogIcon } from '@heroicons/react/24/outline';
import api from '../../lib/api';

export default function SiteSettingsPage() {
    const { campaignId } = useParams();
    const [site, setSite] = useState(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState(null);
    const [form, setForm] = useState({
        candidate_name: '', slug: '', tagline: '', tagline_sw: '',
        biography: '', biography_sw: '', primary_color: '#16a34a',
        secondary_color: '#f97316', logo_url: '', banner_url: '',
        facebook_url: '', twitter_url: '', instagram_url: '', youtube_url: '',
        tiktok_url: '', whatsapp_number: '', phone_number: '', email: '',
    });

    useEffect(() => {
        const load = async () => {
            setLoading(true);
            try {
                const { data } = await api.get(`/campaigns/${campaignId}/site`);
                const s = data.site || data.data;
                setSite(s);
                setForm({
                    candidate_name: s.candidate_name || '',
                    slug: s.slug || '',
                    tagline: s.tagline || '',
                    tagline_sw: s.tagline_sw || '',
                    biography: s.biography || '',
                    biography_sw: s.biography_sw || '',
                    primary_color: s.primary_color || '#16a34a',
                    secondary_color: s.secondary_color || '#f97316',
                    logo_url: s.logo_url || '',
                    banner_url: s.banner_url || '',
                    facebook_url: s.facebook_url || '',
                    twitter_url: s.twitter_url || '',
                    instagram_url: s.instagram_url || '',
                    youtube_url: s.youtube_url || '',
                    tiktok_url: s.tiktok_url || '',
                    whatsapp_number: s.whatsapp_number || '',
                    phone_number: s.phone_number || '',
                    email: s.email || '',
                });
            } catch (err) {
                if (err.response?.status === 404) {
                    setSite(null);
                }
            }
            setLoading(false);
        };
        load();
    }, [campaignId]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setMessage(null);
        try {
            if (site) {
                const { data } = await api.put(`/campaigns/${campaignId}/site`, form);
                setSite(data.site || data.data);
                setMessage({ type: 'success', text: 'Site settings updated' });
            } else {
                const { data } = await api.post(`/campaigns/${campaignId}/site`, form);
                setSite(data.site || data.data);
                setMessage({ type: 'success', text: 'Site created' });
            }
        } catch (err) {
            setMessage({
                type: 'error',
                text: err.response?.data?.errors
                    ? Object.values(err.response.data.errors).flat().join(', ')
                    : err.response?.data?.message || 'Failed to save',
            });
        }
        setSaving(false);
    };

    const update = (field) => (e) => setForm((f) => ({ ...f, [field]: e.target.value }));

    if (loading) {
        return (
            <div className="flex items-center justify-center py-20">
                <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600" />
            </div>
        );
    }

    return (
        <div className="max-w-3xl">
            <form onSubmit={handleSubmit} className="space-y-6">
                {message && (
                    <div className={`p-3 rounded-lg text-sm ${message.type === 'success' ? 'bg-primary-50 text-primary-700 border border-primary-200' : 'bg-red-50 text-red-700 border border-red-200'}`}>
                        {message.text}
                    </div>
                )}

                {/* Basic Info */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 className="font-heading font-semibold text-gray-900 mb-4 flex items-center space-x-2">
                        <CogIcon className="h-5 w-5 text-gray-400" />
                        <span>Basic Information</span>
                    </h3>
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Candidate Name</label>
                                <input type="text" value={form.candidate_name} onChange={update('candidate_name')} required className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Site Slug</label>
                                <input type="text" value={form.slug} onChange={update('slug')} required className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="john-kamau" />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Tagline (English)</label>
                                <input type="text" value={form.tagline} onChange={update('tagline')} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Tagline (Swahili)</label>
                                <input type="text" value={form.tagline_sw} onChange={update('tagline_sw')} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                            </div>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Biography (English)</label>
                            <textarea value={form.biography} onChange={update('biography')} rows={3} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Biography (Swahili)</label>
                            <textarea value={form.biography_sw} onChange={update('biography_sw')} rows={3} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                        </div>
                    </div>
                </div>

                {/* Branding */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 className="font-heading font-semibold text-gray-900 mb-4">Branding</h3>
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Primary Color</label>
                                <div className="flex items-center space-x-2">
                                    <input type="color" value={form.primary_color} onChange={update('primary_color')} className="h-10 w-10 rounded border-gray-300 cursor-pointer" />
                                    <input type="text" value={form.primary_color} onChange={update('primary_color')} className="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm" />
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Secondary Color</label>
                                <div className="flex items-center space-x-2">
                                    <input type="color" value={form.secondary_color} onChange={update('secondary_color')} className="h-10 w-10 rounded border-gray-300 cursor-pointer" />
                                    <input type="text" value={form.secondary_color} onChange={update('secondary_color')} className="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm" />
                                </div>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Logo URL</label>
                                <input type="url" value={form.logo_url} onChange={update('logo_url')} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Banner URL</label>
                                <input type="url" value={form.banner_url} onChange={update('banner_url')} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm" />
                            </div>
                        </div>
                    </div>
                </div>

                {/* Contact & Social */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 className="font-heading font-semibold text-gray-900 mb-4">Contact & Social Media</h3>
                    <div className="space-y-4">
                        <div className="grid grid-cols-3 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input type="email" value={form.email} onChange={update('email')} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input type="tel" value={form.phone_number} onChange={update('phone_number')} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">WhatsApp</label>
                                <input type="tel" value={form.whatsapp_number} onChange={update('whatsapp_number')} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm" />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            {['facebook_url', 'twitter_url', 'instagram_url', 'youtube_url', 'tiktok_url'].map((field) => (
                                <div key={field}>
                                    <label className="block text-sm font-medium text-gray-700 mb-1 capitalize">
                                        {field.replace('_url', '').replace('_', ' ')}
                                    </label>
                                    <input type="url" value={form[field]} onChange={update(field)} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm" />
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="flex justify-end">
                    <button type="submit" disabled={saving} className="btn-primary !py-2.5 !px-6 disabled:opacity-50">
                        {saving ? 'Saving...' : site ? 'Update Settings' : 'Create Site'}
                    </button>
                </div>
            </form>
        </div>
    );
}
