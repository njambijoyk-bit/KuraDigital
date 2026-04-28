import React, { useEffect } from 'react';
import useSiteStore from '../stores/useSiteStore';
import useContentStore from '../stores/useContentStore';

const defaultPillars = [
    { icon: '📚', title: 'Education', promises: ['Build 5 new secondary schools', 'Full bursary program for needy students', 'Equip all schools with ICT labs', 'Teacher training & motivation programs'] },
    { icon: '🏥', title: 'Healthcare', promises: ['Upgrade constituency hospital', 'Mobile health clinics for remote areas', 'Free maternal healthcare program', 'Mental health awareness campaigns'] },
    { icon: '🛣️', title: 'Infrastructure', promises: ['Tarmac all major ward roads', 'Street lighting for market centres', 'Clean water boreholes in every village', 'Bridge construction over major rivers'] },
    { icon: '💼', title: 'Youth & Employment', promises: ['Youth enterprise fund', 'Skills training centres', 'Internship placement program', 'Support for SMEs and startups'] },
    { icon: '🌾', title: 'Agriculture', promises: ['Subsidised farm inputs', 'Irrigation schemes', 'Market access for farmers', 'Agricultural extension officers'] },
    { icon: '🛡️', title: 'Security', promises: ['Community policing partnerships', 'CCTV in major centres', 'Advocacy for more police posts', 'Nyumba Kumi initiative support'] },
];

export default function ManifestoPage() {
    const { site } = useSiteStore();
    const { manifesto, fetchManifesto } = useContentStore();

    useEffect(() => {
        if (site?.id) fetchManifesto(site.id);
    }, [site?.id, fetchManifesto]);

    const pillars = manifesto.length > 0 ? manifesto : defaultPillars;

    return (
        <div>
            <section className="bg-dark-900 text-white py-20">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h1 className="text-4xl md:text-5xl font-heading font-bold mb-4">Our Manifesto</h1>
                    <p className="text-lg text-gray-300 max-w-2xl mx-auto">
                        A detailed plan for transforming {site?.constituency || 'our constituency'} through practical, achievable action.
                    </p>
                </div>
            </section>

            <section className="py-16 bg-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                        {pillars.map((pillar, i) => (
                            <div key={i} className="card p-6">
                                <div className="flex items-center space-x-3 mb-4">
                                    <span className="text-3xl">{pillar.icon}</span>
                                    <h3 className="font-heading font-bold text-xl">{pillar.title}</h3>
                                </div>
                                {pillar.description && (
                                    <p className="text-gray-600 text-sm mb-4">{pillar.description}</p>
                                )}
                                <ul className="space-y-2">
                                    {(pillar.promises || []).map((promise, j) => (
                                        <li key={j} className="flex items-start space-x-2 text-sm text-gray-700">
                                            <svg className="w-5 h-5 text-primary-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>{promise}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                </div>
            </section>
        </div>
    );
}
