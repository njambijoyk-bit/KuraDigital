import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import axios from 'axios';

export default function PlatformHomePage() {
    const [sites, setSites] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');

    const fetchSites = async (q = '') => {
        setLoading(true);
        try {
            const params = {};
            if (q.trim()) params.search = q.trim();
            const { data } = await axios.get('/api/sites', { params });
            setSites(data.data || []);
        } catch { /* handled */ }
        setLoading(false);
    };

    useEffect(() => { fetchSites(); }, []);

    const handleSearch = (e) => {
        e.preventDefault();
        fetchSites(search);
    };

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Hero */}
            <header className="bg-gradient-to-br from-green-700 to-green-900 text-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex items-center justify-between">
                    <h1 className="text-2xl font-heading font-bold tracking-tight">
                        Kura<span className="text-green-300">Digital</span>
                    </h1>
                    <Link to="/admin" className="text-sm font-medium bg-white/10 hover:bg-white/20 px-4 py-2 rounded-lg transition">
                        Campaign Manager
                    </Link>
                </div>
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16 pt-8 text-center">
                    <h2 className="text-4xl md:text-5xl font-heading font-extrabold mb-4">
                        Kenya&rsquo;s Campaign Platform
                    </h2>
                    <p className="text-lg text-green-100 max-w-2xl mx-auto mb-8">
                        Discover candidates, explore their manifestos, and engage with campaigns across the country.
                    </p>
                    <form onSubmit={handleSearch} className="max-w-lg mx-auto flex gap-2">
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by name, county, constituency, or position..."
                            className="flex-1 rounded-lg border-0 px-4 py-3 text-gray-900 shadow-sm placeholder:text-gray-400 focus:ring-2 focus:ring-green-400"
                        />
                        <button type="submit" className="bg-green-500 hover:bg-green-400 text-white font-semibold px-6 py-3 rounded-lg shadow transition">
                            Search
                        </button>
                    </form>
                </div>
            </header>

            {/* Campaign Sites Grid */}
            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                {loading ? (
                    <div className="text-center py-20">
                        <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-green-600 mx-auto mb-3" />
                        <p className="text-gray-500">Loading campaigns...</p>
                    </div>
                ) : sites.length === 0 ? (
                    <div className="text-center py-20">
                        <p className="text-xl text-gray-500 mb-2">No campaigns found</p>
                        <p className="text-gray-400">Try adjusting your search or check back later.</p>
                    </div>
                ) : (
                    <>
                        <p className="text-sm text-gray-500 mb-6">{sites.length} active campaign{sites.length !== 1 ? 's' : ''}</p>
                        <div className="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            {sites.map((site) => (
                                <Link
                                    key={site.id}
                                    to={`/${site.slug}`}
                                    className="group bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md hover:border-gray-200 transition"
                                >
                                    <div
                                        className="h-3"
                                        style={{ backgroundColor: site.primary_color || '#16a34a' }}
                                    />
                                    <div className="p-5">
                                        <div className="flex items-start gap-4">
                                            {site.portrait_url ? (
                                                <img
                                                    src={site.portrait_url}
                                                    alt={site.candidate_name}
                                                    className="w-16 h-16 rounded-full object-cover border-2 border-gray-100 flex-shrink-0"
                                                />
                                            ) : (
                                                <div
                                                    className="w-16 h-16 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xl font-bold"
                                                    style={{ backgroundColor: site.primary_color || '#16a34a' }}
                                                >
                                                    {(site.candidate_name || '?').charAt(0)}
                                                </div>
                                            )}
                                            <div className="min-w-0">
                                                <h3 className="font-heading font-bold text-gray-900 group-hover:text-green-700 transition truncate">
                                                    {site.candidate_name}
                                                </h3>
                                                <p className="text-sm text-gray-500 truncate">{site.position}</p>
                                                <p className="text-xs text-gray-400 mt-1 truncate">
                                                    {[site.constituency, site.county].filter(Boolean).join(', ')}
                                                </p>
                                            </div>
                                        </div>
                                        {site.slogan && (
                                            <p className="text-sm text-gray-500 italic mt-3 line-clamp-2">
                                                &ldquo;{site.slogan}&rdquo;
                                            </p>
                                        )}
                                        {site.party && (
                                            <span className="inline-block mt-3 text-xs font-medium px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">
                                                {site.party}
                                            </span>
                                        )}
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </>
                )}
            </main>

            <footer className="bg-gray-900 text-gray-400 text-center py-8 text-sm">
                <p>KuraDigital &mdash; Empowering Kenyan Democracy Through Technology</p>
            </footer>
        </div>
    );
}
