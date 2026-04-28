import React from 'react';
import { Link, useParams } from 'react-router-dom';
import useSiteStore from '../stores/useSiteStore';

function PillarCard({ icon, title, description, color }) {
    return (
        <div className="card p-6 text-center">
            <div className="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4" style={{ backgroundColor: `${color}15` }}>
                <span className="text-2xl">{icon}</span>
            </div>
            <h3 className="font-heading font-bold text-lg mb-2">{title}</h3>
            <p className="text-gray-600 text-sm">{description}</p>
        </div>
    );
}

export default function HomePage() {
    const { slug } = useParams();
    const { site } = useSiteStore();
    const base = `/${slug}`;
    const primaryColor = site?.primary_color || '#16a34a';

    const pillars = site?.pillars || [
        { icon: '📚', title: 'Education', description: 'Quality education for every child in our constituency.' },
        { icon: '🏥', title: 'Healthcare', description: 'Accessible, affordable healthcare for all families.' },
        { icon: '🛣️', title: 'Infrastructure', description: 'Modern roads, water, and electricity for development.' },
        { icon: '💼', title: 'Youth Employment', description: 'Creating opportunities and empowering our youth.' },
    ];

    return (
        <div>
            {/* Hero Section */}
            <section
                className="relative min-h-[85vh] flex items-center"
                style={{
                    background: `linear-gradient(135deg, ${primaryColor}ee 0%, ${site?.secondary_color || '#0f172a'}ee 100%)`,
                }}
            >
                <div className="absolute inset-0 bg-black/20" />
                {site?.hero_image_url && (
                    <div
                        className="absolute inset-0 bg-cover bg-center mix-blend-overlay opacity-30"
                        style={{ backgroundImage: `url(${site.hero_image_url})` }}
                    />
                )}
                <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
                    <div className="grid md:grid-cols-2 gap-12 items-center">
                        <div className="text-white">
                            <p className="text-sm font-semibold uppercase tracking-widest mb-3 opacity-80">
                                {site?.position || 'Member of Parliament'} &bull; {site?.constituency || 'Your Constituency'}
                            </p>
                            <h1 className="text-4xl sm:text-5xl lg:text-6xl font-heading font-extrabold leading-tight mb-6">
                                {site?.candidate_name || 'Candidate Name'}
                            </h1>
                            <p className="text-xl sm:text-2xl font-light italic mb-8 opacity-90">
                                &ldquo;{site?.slogan || 'Together, we build a better future.'}&rdquo;
                            </p>
                            <div className="flex flex-wrap gap-4">
                                <Link to={`${base}/volunteer`} className="btn-accent text-lg !px-8 !py-4">
                                    Join the Movement
                                </Link>
                                <Link to={`${base}/manifesto`} className="btn-outline !border-white !text-white hover:!bg-white hover:!text-gray-900 text-lg !px-8 !py-4">
                                    Our Vision
                                </Link>
                            </div>
                        </div>
                        <div className="hidden md:flex justify-center">
                            {site?.portrait_url ? (
                                <img
                                    src={site.portrait_url}
                                    alt={site.candidate_name}
                                    className="w-80 h-80 object-cover rounded-2xl shadow-2xl border-4 border-white/20"
                                />
                            ) : (
                                <div className="w-80 h-80 rounded-2xl bg-white/10 border-4 border-white/20 flex items-center justify-center">
                                    <span className="text-white/50 text-6xl">👤</span>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </section>

            {/* Campaign Pillars */}
            <section className="py-20 bg-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h2 className="section-heading">Our Key Priorities</h2>
                    <p className="section-subheading">
                        A clear vision for transforming our constituency through focused action.
                    </p>
                    <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-8">
                        {pillars.map((pillar, i) => (
                            <PillarCard key={i} {...pillar} color={primaryColor} />
                        ))}
                    </div>
                </div>
            </section>

            {/* About Preview */}
            <section className="py-20 bg-gray-50">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid md:grid-cols-2 gap-12 items-center">
                        <div>
                            {site?.about_image_url ? (
                                <img src={site.about_image_url} alt="About" className="rounded-2xl shadow-lg w-full h-80 object-cover" />
                            ) : (
                                <div className="rounded-2xl bg-gray-200 w-full h-80 flex items-center justify-center">
                                    <span className="text-gray-400 text-4xl">📸</span>
                                </div>
                            )}
                        </div>
                        <div>
                            <h2 className="section-heading text-left">Meet {site?.candidate_name?.split(' ')[0] || 'the Candidate'}</h2>
                            <p className="text-gray-600 mb-6 leading-relaxed">
                                {site?.bio_summary || 'A dedicated leader committed to serving the people. With years of community service and a clear vision for development, they bring passion, integrity, and results.'}
                            </p>
                            <Link to={`${base}/about`} className="btn-primary">
                                Read Full Story &rarr;
                            </Link>
                        </div>
                    </div>
                </div>
            </section>

            {/* CTA Section */}
            <section className="py-16" style={{ backgroundColor: primaryColor }}>
                <div className="max-w-4xl mx-auto px-4 text-center text-white">
                    <h2 className="text-3xl md:text-4xl font-heading font-bold mb-4">
                        Ready to Make a Difference?
                    </h2>
                    <p className="text-lg opacity-90 mb-8">
                        Join thousands of supporters who believe in a better future for our constituency.
                    </p>
                    <div className="flex flex-wrap justify-center gap-4">
                        <Link to={`${base}/volunteer`} className="bg-white font-semibold px-8 py-3 rounded-lg hover:bg-gray-100 transition-colors shadow-lg" style={{ color: primaryColor }}>
                            Volunteer Now
                        </Link>
                        <Link to={`${base}/contact`} className="btn-outline !border-white !text-white hover:!bg-white hover:!text-gray-900 !px-8 !py-3">
                            Contact Us
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    );
}
