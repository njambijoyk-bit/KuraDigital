import React, { useState, useEffect, useCallback } from 'react';
import { useParams, useSearchParams } from 'react-router-dom';
import axios from 'axios';
import { Helmet } from 'react-helmet-async';
import useSiteStore from '../stores/useSiteStore';

function formatNumber(n) {
    return Number(n || 0).toLocaleString('en-KE');
}

function StatCard({ label, value, color }) {
    const colors = {
        blue: 'bg-blue-50 text-blue-900',
        green: 'bg-green-50 text-green-900',
        indigo: 'bg-indigo-50 text-indigo-900',
        purple: 'bg-purple-50 text-purple-900',
        gray: 'bg-gray-50 text-gray-900',
        emerald: 'bg-emerald-50 text-emerald-900',
    };
    return (
        <div className={`rounded-xl p-4 ${colors[color] || 'bg-gray-50 text-gray-900'}`}>
            <p className="text-xs font-medium opacity-75">{label}</p>
            <p className="text-xl font-bold mt-1">{value}</p>
        </div>
    );
}

function ShareButton({ url, title }) {
    const [copied, setCopied] = useState(false);

    const handleCopy = () => {
        navigator.clipboard.writeText(url).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    };

    const shareNative = () => {
        if (navigator.share) {
            navigator.share({ title, url }).catch(() => {});
        } else {
            handleCopy();
        }
    };

    return (
        <div className="flex items-center gap-2">
            <button onClick={shareNative} className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white/20 hover:bg-white/30 rounded-lg text-sm text-white transition-colors">
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                </svg>
                {copied ? 'Copied!' : 'Share'}
            </button>
            <a
                href={`https://twitter.com/intent/tweet?text=${encodeURIComponent(title)}&url=${encodeURIComponent(url)}`}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center px-2 py-1.5 bg-white/20 hover:bg-white/30 rounded-lg text-sm text-white transition-colors"
                title="Share on X/Twitter"
            >
                <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" /></svg>
            </a>
            <a
                href={`https://wa.me/?text=${encodeURIComponent(title + ' ' + url)}`}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center px-2 py-1.5 bg-white/20 hover:bg-white/30 rounded-lg text-sm text-white transition-colors"
                title="Share on WhatsApp"
            >
                <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" /></svg>
            </a>
        </div>
    );
}

function WardBreakdown({ wards }) {
    const [expanded, setExpanded] = useState(false);
    if (!wards || wards.length === 0) return null;

    const displayed = expanded ? wards : wards.slice(0, 5);

    return (
        <div className="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div className="p-6 border-b border-gray-200">
                <h3 className="text-lg font-semibold text-gray-900">Results by Ward</h3>
            </div>
            <div className="divide-y divide-gray-100">
                {displayed.map((w) => (
                    <div key={w.ward} className="p-4 hover:bg-gray-50">
                        <div className="flex items-center justify-between mb-2">
                            <div>
                                <span className="font-semibold text-sm text-gray-900">{w.ward}</span>
                                {w.leading_candidate && (
                                    <span className="ml-2 text-xs text-green-600 font-medium">Leading: {w.leading_candidate}</span>
                                )}
                            </div>
                            <div className="text-right text-xs text-gray-500">
                                <span>{w.reported_stations}/{w.total_stations} stations ({w.reporting_percentage}%)</span>
                                <span className="ml-3">Turnout: {w.turnout_percentage}%</span>
                            </div>
                        </div>
                        <div className="flex gap-1 h-4 rounded-full overflow-hidden bg-gray-100">
                            {w.candidates && w.candidates.map((c, i) => {
                                const totalInWard = w.candidates.reduce((s, x) => s + x.votes, 0);
                                const pct = totalInWard > 0 ? (c.votes / totalInWard) * 100 : 0;
                                const colors = ['bg-green-500', 'bg-blue-400', 'bg-orange-400', 'bg-purple-400', 'bg-pink-400', 'bg-teal-400'];
                                return (
                                    <div
                                        key={c.candidate_name}
                                        className={`${colors[i % colors.length]} transition-all duration-500`}
                                        style={{ width: `${pct}%` }}
                                        title={`${c.candidate_name}: ${formatNumber(c.votes)} (${pct.toFixed(1)}%)`}
                                    />
                                );
                            })}
                        </div>
                        <div className="flex gap-3 mt-1.5">
                            {w.candidates && w.candidates.slice(0, 4).map((c, i) => {
                                const colors = ['text-green-600', 'text-blue-500', 'text-orange-500', 'text-purple-500'];
                                return (
                                    <span key={c.candidate_name} className={`text-xs ${colors[i % colors.length]}`}>
                                        {c.candidate_name}: {formatNumber(c.votes)}
                                    </span>
                                );
                            })}
                        </div>
                    </div>
                ))}
            </div>
            {wards.length > 5 && (
                <div className="p-3 text-center border-t border-gray-100">
                    <button onClick={() => setExpanded(!expanded)} className="text-sm text-primary-600 hover:text-primary-700 font-medium">
                        {expanded ? 'Show less' : `Show all ${wards.length} wards`}
                    </button>
                </div>
            )}
        </div>
    );
}

function ConstituencyBreakdown({ constituencies }) {
    if (!constituencies || constituencies.length === 0) return null;

    return (
        <div className="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div className="p-6 border-b border-gray-200">
                <h3 className="text-lg font-semibold text-gray-900">Results by Constituency</h3>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Constituency</th>
                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Stations</th>
                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Reporting</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Leading</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {constituencies.map((c) => (
                            <tr key={c.constituency} className="hover:bg-gray-50">
                                <td className="px-6 py-3 text-sm font-semibold">{c.constituency}</td>
                                <td className="px-6 py-3 text-sm text-right text-gray-500">{c.reported_stations}/{c.total_stations}</td>
                                <td className="px-6 py-3 text-sm text-right">
                                    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${c.reporting_percentage >= 75 ? 'bg-green-100 text-green-700' : c.reporting_percentage >= 25 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'}`}>
                                        {c.reporting_percentage}%
                                    </span>
                                </td>
                                <td className="px-6 py-3 text-sm text-green-600 font-medium">{c.leading_candidate || '-'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function ResultsPage() {
    const { slug } = useParams();
    const [searchParams] = useSearchParams();
    const isEmbed = searchParams.get('embed') === '1';
    const { site } = useSiteStore();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [lastRefresh, setLastRefresh] = useState(null);
    const [autoRefresh, setAutoRefresh] = useState(true);
    const [activeTab, setActiveTab] = useState('overall');

    const fetchResults = useCallback(async () => {
        if (!site?.id) return;
        try {
            const { data: resp } = await axios.get(`/api/sites/${site.id}/election-results`);
            setData(resp.data);
            setLastRefresh(new Date());
        } catch { /* handled */ }
        setLoading(false);
    }, [site?.id]);

    useEffect(() => {
        fetchResults();
    }, [fetchResults]);

    useEffect(() => {
        if (!autoRefresh) return;
        const interval = setInterval(fetchResults, 30000);
        return () => clearInterval(interval);
    }, [autoRefresh, fetchResults]);

    const primaryColor = site?.primary_color || '#16a34a';
    const shareUrl = typeof window !== 'undefined' ? window.location.href.replace(/[?&]embed=1/, '') : '';
    const shareTitle = `Live Election Results — ${data?.site?.candidate_name || site?.candidate_name || 'Campaign'}`;

    if (loading) {
        return (
            <div className="min-h-[60vh] flex items-center justify-center">
                <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600" />
            </div>
        );
    }

    const overview = data?.overview;
    const candidates = data?.candidates || [];
    const maxVotes = candidates[0]?.total_votes || 1;
    const byWard = data?.by_ward || [];
    const byConstituency = data?.by_constituency || [];

    const tabs = [
        { key: 'overall', label: 'Overall' },
        ...(byWard.length > 0 ? [{ key: 'ward', label: `By Ward (${byWard.length})` }] : []),
        ...(byConstituency.length > 0 ? [{ key: 'constituency', label: `By Constituency (${byConstituency.length})` }] : []),
    ];

    return (
        <div className={isEmbed ? 'bg-gray-50 min-h-screen' : ''}>
            <Helmet>
                <title>Election Results | {data?.site?.candidate_name || site?.candidate_name || 'Campaign'}</title>
                <meta name="description" content={`Live election results for ${data?.site?.candidate_name || site?.candidate_name || 'the campaign'}.`} />
                <meta property="og:title" content={shareTitle} />
                <meta property="og:description" content={`${overview?.reporting_percentage || 0}% stations reporting — ${formatNumber(overview?.total_votes_cast || 0)} votes cast`} />
                <meta property="og:type" content="website" />
            </Helmet>

            {/* Hero */}
            <section className="py-12" style={{ background: `linear-gradient(135deg, ${primaryColor}ee, ${data?.site?.secondary_color || site?.secondary_color || '#0f172a'}ee)` }}>
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center text-white">
                    <h1 className="text-3xl md:text-4xl font-heading font-bold mb-2">Election Results</h1>
                    <p className="text-lg opacity-90">{data?.site?.constituency || site?.constituency || data?.site?.candidate_name || site?.candidate_name || 'Live Results'}</p>
                    <div className="mt-4 flex items-center justify-center gap-4 text-sm opacity-75 flex-wrap">
                        {lastRefresh && (
                            <span>Updated: {lastRefresh.toLocaleTimeString()}</span>
                        )}
                        <label className="flex items-center gap-1.5 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={autoRefresh}
                                onChange={(e) => setAutoRefresh(e.target.checked)}
                                className="rounded border-white/30"
                            />
                            Auto-refresh (30s)
                        </label>
                    </div>
                    <div className="mt-3 flex justify-center">
                        <ShareButton url={shareUrl} title={shareTitle} />
                    </div>
                </div>
            </section>

            <section className="py-10 bg-gray-50">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                    {/* Overview stats */}
                    {overview && (
                        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                            <StatCard label="Total Stations" value={formatNumber(overview.total_stations)} color="blue" />
                            <StatCard label="Reported" value={formatNumber(overview.reported_stations)} color="green" />
                            <StatCard label="Reporting %" value={`${overview.reporting_percentage}%`} color="indigo" />
                            <StatCard label="Votes Cast" value={formatNumber(overview.total_votes_cast)} color="purple" />
                            <StatCard label="Registered" value={formatNumber(overview.total_registered)} color="gray" />
                            <StatCard label="Turnout" value={`${overview.turnout_percentage}%`} color="emerald" />
                        </div>
                    )}

                    {/* Tabs */}
                    {tabs.length > 1 && (
                        <div className="flex gap-1 bg-white rounded-xl shadow-sm border border-gray-200 p-1">
                            {tabs.map(t => (
                                <button
                                    key={t.key}
                                    onClick={() => setActiveTab(t.key)}
                                    className={`flex-1 px-4 py-2 text-sm font-medium rounded-lg transition-colors ${activeTab === t.key ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50'}`}
                                    style={activeTab === t.key ? { backgroundColor: primaryColor } : {}}
                                >
                                    {t.label}
                                </button>
                            ))}
                        </div>
                    )}

                    {/* Overall candidate results */}
                    {activeTab === 'overall' && candidates.length > 0 && (
                        <div className="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                            <div className="p-6 border-b border-gray-200 flex items-center justify-between">
                                <h3 className="text-lg font-semibold text-gray-900">Candidate Results</h3>
                                <button
                                    onClick={fetchResults}
                                    className="text-sm text-primary-600 hover:text-primary-700 font-medium"
                                >
                                    Refresh
                                </button>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Candidate</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Party</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Votes</th>
                                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Stations</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Progress</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200">
                                        {candidates.map((c, i) => {
                                            const pct = Math.round((c.total_votes / maxVotes) * 100);
                                            const totalVotes = candidates.reduce((s, x) => s + x.total_votes, 0);
                                            const votePct = totalVotes > 0 ? ((c.total_votes / totalVotes) * 100).toFixed(1) : 0;
                                            return (
                                                <tr key={c.candidate_name} className={i === 0 ? 'bg-green-50' : ''}>
                                                    <td className="px-6 py-4 text-sm font-bold">{i + 1}</td>
                                                    <td className="px-6 py-4">
                                                        <p className="text-sm font-semibold">{c.candidate_name}</p>
                                                        {c.verified_count > 0 && (
                                                            <span className="text-xs text-green-600">{c.verified_count} verified</span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 text-sm text-gray-500">{c.party || '-'}</td>
                                                    <td className="px-6 py-4 text-sm text-right">
                                                        <span className="font-bold">{formatNumber(c.total_votes)}</span>
                                                        <span className="text-gray-400 ml-1">({votePct}%)</span>
                                                    </td>
                                                    <td className="px-6 py-4 text-sm text-right text-gray-500">{c.stations_reported}</td>
                                                    <td className="px-6 py-4 w-48">
                                                        <div className="w-full bg-gray-200 rounded-full h-3">
                                                            <div
                                                                className={`h-3 rounded-full transition-all duration-500 ${i === 0 ? 'bg-green-500' : 'bg-blue-400'}`}
                                                                style={{ width: `${pct}%` }}
                                                            />
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {activeTab === 'overall' && candidates.length === 0 && (
                        <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-12 text-center">
                            <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg className="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <h3 className="text-lg font-semibold text-gray-900 mb-1">No Results Yet</h3>
                            <p className="text-gray-500">Results will appear here once polling station agents begin submitting tallies.</p>
                        </div>
                    )}

                    {activeTab === 'ward' && <WardBreakdown wards={byWard} />}
                    {activeTab === 'constituency' && <ConstituencyBreakdown constituencies={byConstituency} />}

                    {/* Embed code */}
                    {!isEmbed && (
                        <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
                            <h3 className="text-sm font-semibold text-gray-900 mb-2">Embed Results</h3>
                            <p className="text-xs text-gray-500 mb-3">Copy this code to embed live results on your website or blog:</p>
                            <div className="bg-gray-50 rounded-lg p-3 text-xs font-mono text-gray-600 break-all select-all">
                                {`<iframe src="${shareUrl}?embed=1" width="100%" height="800" frameborder="0" style="border-radius:16px;overflow:hidden;" allowfullscreen></iframe>`}
                            </div>
                        </div>
                    )}

                    {/* Disclaimer */}
                    <p className="text-xs text-gray-400 text-center">
                        Results shown are provisional and subject to official verification by the electoral commission.
                        {data?.last_updated && ` Last data received: ${new Date(data.last_updated).toLocaleString()}.`}
                    </p>

                    {/* Powered by footer for embeds */}
                    {isEmbed && (
                        <p className="text-xs text-gray-400 text-center pt-2">
                            Powered by <a href="https://kuradigital.co.ke" target="_blank" rel="noopener noreferrer" className="text-primary-600 hover:underline">Kura Digital</a>
                        </p>
                    )}
                </div>
            </section>
        </div>
    );
}
