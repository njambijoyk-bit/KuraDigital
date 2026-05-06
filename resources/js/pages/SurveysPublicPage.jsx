import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import axios from 'axios';
import useSiteStore from '../stores/useSiteStore';

export default function SurveysPublicPage() {
    const { slug } = useParams();
    const { site } = useSiteStore();
    const [surveys, setSurveys] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (!site?.id) return;
        (async () => {
            try {
                const { data } = await axios.get(`/api/sites/${site.id}/surveys`);
                setSurveys(data.data || []);
            } catch { /* handled */ }
            setLoading(false);
        })();
    }, [site?.id]);

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
            <section className="py-12" style={{ background: `linear-gradient(135deg, ${primaryColor}ee, ${site?.secondary_color || '#0f172a'}ee)` }}>
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-white">
                    <h1 className="text-3xl md:text-4xl font-heading font-bold mb-2">Community Surveys</h1>
                    <p className="text-lg opacity-90">Share your voice and help shape our campaign priorities</p>
                </div>
            </section>

            <section className="py-10 bg-gray-50">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    {surveys.length === 0 ? (
                        <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center">
                            <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg className="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <h3 className="text-lg font-semibold text-gray-900 mb-1">No Active Surveys</h3>
                            <p className="text-gray-500">Check back later for community surveys you can participate in.</p>
                        </div>
                    ) : (
                        <div className="grid gap-6 md:grid-cols-2">
                            {surveys.map((s) => (
                                <Link key={s.id} to={`/${slug}/surveys/${s.id}`}
                                    className="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow">
                                    <h3 className="text-lg font-semibold text-gray-900 mb-2">{s.title}</h3>
                                    {s.description && <p className="text-sm text-gray-500 mb-4 line-clamp-2">{s.description}</p>}
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-gray-400">{s.questions?.length || 0} questions</span>
                                        {s.ends_at && (
                                            <span className="text-gray-400">Ends: {new Date(s.ends_at).toLocaleDateString()}</span>
                                        )}
                                    </div>
                                    <div className="mt-4">
                                        <span className="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg text-white" style={{ backgroundColor: primaryColor }}>
                                            Take Survey
                                        </span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </section>
        </div>
    );
}
