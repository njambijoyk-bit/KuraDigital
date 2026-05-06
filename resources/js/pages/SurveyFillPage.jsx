import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import axios from 'axios';
import { Helmet } from 'react-helmet-async';
import useSiteStore from '../stores/useSiteStore';

export default function SurveyFillPage() {
    const { slug, surveyId } = useParams();
    const { site } = useSiteStore();
    const [survey, setSurvey] = useState(null);
    const [loading, setLoading] = useState(true);
    const [answers, setAnswers] = useState({});
    const [respondentName, setRespondentName] = useState('');
    const [ward, setWard] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [submitted, setSubmitted] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (!site?.id) return;
        (async () => {
            try {
                const { data } = await axios.get(`/api/sites/${site.id}/surveys/${surveyId}`);
                setSurvey(data.data);
            } catch { /* handled */ }
            setLoading(false);
        })();
    }, [site?.id, surveyId]);

    const setAnswer = (qId, value) => {
        setAnswers((prev) => ({ ...prev, [qId]: value }));
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);
        try {
            const payload = {
                answers: Object.entries(answers).map(([question_id, value]) => ({ question_id, value })),
                respondent_name: respondentName || null,
                ward: ward || null,
            };
            await axios.post(`/api/sites/${site.id}/surveys/${surveyId}/submit`, payload);
            setSubmitted(true);
        } catch (err) {
            setError(err.response?.data?.message || 'Submission failed. Please try again.');
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

    if (!survey) {
        return (
            <div className="min-h-[60vh] flex items-center justify-center">
                <div className="text-center">
                    <h2 className="text-2xl font-bold text-gray-800 mb-2">Survey Not Found</h2>
                    <p className="text-gray-500 mb-4">This survey may have been closed or doesn't exist.</p>
                    <Link to={`/${slug}/surveys`} className="text-primary-600 hover:underline">View all surveys</Link>
                </div>
            </div>
        );
    }

    if (submitted) {
        return (
            <div className="min-h-[60vh] flex items-center justify-center bg-gray-50">
                <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center max-w-md mx-4">
                    <div className="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style={{ backgroundColor: `${primaryColor}20` }}>
                        <svg className="w-8 h-8" style={{ color: primaryColor }} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h2 className="text-2xl font-bold text-gray-900 mb-2">Thank You!</h2>
                    <p className="text-gray-500 mb-6">Your response has been recorded. Your input helps shape our campaign priorities.</p>
                    <Link to={`/${slug}/surveys`} className="inline-flex items-center px-6 py-2.5 text-sm font-medium rounded-lg text-white" style={{ backgroundColor: primaryColor }}>
                        Back to Surveys
                    </Link>
                </div>
            </div>
        );
    }

    const questions = survey.questions || [];

    return (
        <div>
            <Helmet>
                <title>{survey?.title || 'Survey'} | {site?.candidate_name || 'Campaign'}</title>
                <meta name="description" content={survey?.description?.substring(0, 160) || 'Take part in our community survey.'} />
            </Helmet>

            <section className="py-10" style={{ background: `linear-gradient(135deg, ${primaryColor}ee, ${site?.secondary_color || '#0f172a'}ee)` }}>
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-white">
                    <Link to={`/${slug}/surveys`} className="text-sm opacity-75 hover:opacity-100 mb-4 inline-block">&larr; All Surveys</Link>
                    <h1 className="text-2xl md:text-3xl font-heading font-bold">{survey.title}</h1>
                    {survey.description && <p className="mt-2 opacity-90">{survey.description}</p>}
                </div>
            </section>

            <section className="py-10 bg-gray-50">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {error && <div className="bg-red-50 text-red-700 p-4 rounded-lg text-sm">{error}</div>}

                        {/* Optional respondent info */}
                        <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h3 className="text-sm font-medium text-gray-700 mb-3">Your Information (optional)</h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">Name</label>
                                    <input type="text" value={respondentName} onChange={(e) => setRespondentName(e.target.value)}
                                        className="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Optional" />
                                </div>
                                <div>
                                    <label className="block text-xs text-gray-500 mb-1">Ward</label>
                                    <input type="text" value={ward} onChange={(e) => setWard(e.target.value)}
                                        className="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Optional" />
                                </div>
                            </div>
                        </div>

                        {/* Questions */}
                        {questions.map((q, idx) => (
                            <div key={q.id} className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                                <label className="block text-sm font-medium text-gray-900 mb-3">
                                    {idx + 1}. {q.text}
                                    {q.required && <span className="text-red-500 ml-1">*</span>}
                                </label>

                                {q.type === 'text' && (
                                    <textarea value={answers[q.id] || ''} onChange={(e) => setAnswer(q.id, e.target.value)}
                                        className="w-full border rounded-lg px-3 py-2 text-sm" rows={3}
                                        required={q.required} placeholder="Your answer..." />
                                )}

                                {q.type === 'number' && (
                                    <input type="number" value={answers[q.id] || ''} onChange={(e) => setAnswer(q.id, e.target.value)}
                                        className="w-full border rounded-lg px-3 py-2 text-sm max-w-xs"
                                        required={q.required} placeholder="Enter a number" />
                                )}

                                {q.type === 'boolean' && (
                                    <div className="flex items-center gap-6">
                                        {['Yes', 'No'].map((opt) => (
                                            <label key={opt} className="flex items-center gap-2 cursor-pointer">
                                                <input type="radio" name={`q-${q.id}`} value={opt === 'Yes'}
                                                    checked={answers[q.id] === (opt === 'Yes')}
                                                    onChange={() => setAnswer(q.id, opt === 'Yes')}
                                                    className="text-primary-600" required={q.required} />
                                                <span className="text-sm">{opt}</span>
                                            </label>
                                        ))}
                                    </div>
                                )}

                                {q.type === 'select' && (
                                    <select value={answers[q.id] || ''} onChange={(e) => setAnswer(q.id, e.target.value)}
                                        className="w-full border rounded-lg px-3 py-2 text-sm" required={q.required}>
                                        <option value="">Select an option...</option>
                                        {(q.options || []).map((opt) => (
                                            <option key={opt} value={opt}>{opt}</option>
                                        ))}
                                    </select>
                                )}

                                {q.type === 'multiselect' && (
                                    <div className="space-y-2">
                                        {(q.options || []).map((opt) => (
                                            <label key={opt} className="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox"
                                                    checked={(answers[q.id] || []).includes(opt)}
                                                    onChange={(e) => {
                                                        const current = answers[q.id] || [];
                                                        setAnswer(q.id, e.target.checked
                                                            ? [...current, opt]
                                                            : current.filter((v) => v !== opt));
                                                    }}
                                                    className="rounded text-primary-600" />
                                                <span className="text-sm">{opt}</span>
                                            </label>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}

                        <div className="flex justify-end">
                            <button type="submit" disabled={submitting}
                                className="px-8 py-3 text-white rounded-lg text-sm font-medium hover:opacity-90 disabled:opacity-50 transition"
                                style={{ backgroundColor: primaryColor }}>
                                {submitting ? 'Submitting...' : 'Submit Response'}
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    );
}
