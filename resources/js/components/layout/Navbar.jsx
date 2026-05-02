import React, { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import useSiteStore from '../../stores/useSiteStore';

export default function Navbar() {
    const [mobileOpen, setMobileOpen] = useState(false);
    const { slug } = useParams();
    const { site, language, toggleLanguage } = useSiteStore();
    const base = `/${slug}`;

    const navLinks = [
        { to: base, label: 'Home' },
        { to: `${base}/about`, label: 'About' },
        { to: `${base}/manifesto`, label: 'Manifesto' },
        { to: `${base}/projects`, label: 'Projects' },
        { to: `${base}/events`, label: 'Events' },
        { to: `${base}/news`, label: 'News' },
        { to: `${base}/gallery`, label: 'Gallery' },
        { to: `${base}/contact`, label: 'Contact' },
        { to: `${base}/register`, label: 'Register' },
    ];

    const primaryColor = site?.primary_color || '#16a34a';

    return (
        <nav className="bg-white shadow-sm sticky top-0 z-50">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex justify-between h-16 items-center">
                    <Link to={base} className="flex items-center space-x-3">
                        {site?.logo_url && (
                            <img src={site.logo_url} alt={site.candidate_name} className="h-10 w-10 rounded-full object-cover" />
                        )}
                        <span className="font-heading font-bold text-xl" style={{ color: primaryColor }}>
                            {site?.candidate_name || 'Campaign'}
                        </span>
                    </Link>

                    <div className="hidden md:flex items-center space-x-1">
                        {navLinks.map((link) => (
                            <Link
                                key={link.to}
                                to={link.to}
                                className="px-3 py-2 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            >
                                {link.label}
                            </Link>
                        ))}
                        <Link to={`${base}/volunteer`} className="btn-primary ml-2 text-sm !px-4 !py-2">
                            Get Involved
                        </Link>
                        <button
                            onClick={toggleLanguage}
                            className="ml-2 px-3 py-1 text-xs font-semibold border rounded-full hover:bg-gray-100 transition-colors"
                        >
                            {language === 'en' ? 'SW' : 'EN'}
                        </button>
                    </div>

                    <button
                        onClick={() => setMobileOpen(!mobileOpen)}
                        className="md:hidden p-2 rounded-md text-gray-600 hover:bg-gray-100"
                        aria-label="Toggle menu"
                    >
                        <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            {mobileOpen ? (
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            ) : (
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                            )}
                        </svg>
                    </button>
                </div>
            </div>

            {mobileOpen && (
                <div className="md:hidden border-t bg-white">
                    <div className="px-4 py-3 space-y-1">
                        {navLinks.map((link) => (
                            <Link
                                key={link.to}
                                to={link.to}
                                onClick={() => setMobileOpen(false)}
                                className="block px-3 py-2 text-base font-medium text-gray-700 rounded-md hover:bg-gray-100"
                            >
                                {link.label}
                            </Link>
                        ))}
                        <Link
                            to={`${base}/volunteer`}
                            onClick={() => setMobileOpen(false)}
                            className="block w-full btn-primary text-center mt-2"
                        >
                            Get Involved
                        </Link>
                        <button
                            onClick={toggleLanguage}
                            className="mt-2 px-3 py-1 text-sm font-semibold border rounded-full"
                        >
                            {language === 'en' ? 'Switch to Swahili' : 'Switch to English'}
                        </button>
                    </div>
                </div>
            )}
        </nav>
    );
}
