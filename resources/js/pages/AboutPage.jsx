import React from 'react';
import useSiteStore from '../stores/useSiteStore';

function TimelineItem({ year, title, description }) {
    return (
        <div className="relative pl-8 pb-8 border-l-2 border-primary-200 last:border-0 last:pb-0">
            <div className="absolute -left-[9px] top-0 w-4 h-4 rounded-full bg-primary-500 border-2 border-white" />
            <span className="text-sm font-semibold text-primary-600">{year}</span>
            <h4 className="font-heading font-bold text-lg mt-1">{title}</h4>
            <p className="text-gray-600 text-sm mt-1">{description}</p>
        </div>
    );
}

export default function AboutPage() {
    const { site } = useSiteStore();

    const milestones = site?.milestones || [
        { year: '2010', title: 'Community Service', description: 'Began active community development work.' },
        { year: '2015', title: 'Leadership Role', description: 'Elected to lead local development initiatives.' },
        { year: '2020', title: 'Public Service', description: 'Served in various public capacities driving change.' },
        { year: '2027', title: 'Running for MP', description: 'Seeking to represent the people at the national level.' },
    ];

    return (
        <div>
            <section className="bg-dark-900 text-white py-20">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h1 className="text-4xl md:text-5xl font-heading font-bold mb-4">
                        About {site?.candidate_name || 'the Candidate'}
                    </h1>
                    <p className="text-lg text-gray-300 max-w-2xl mx-auto">
                        {site?.slogan || 'A leader with a vision for a better tomorrow.'}
                    </p>
                </div>
            </section>

            <section className="py-16 bg-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid md:grid-cols-2 gap-12 items-start">
                        <div>
                            {site?.portrait_url ? (
                                <img src={site.portrait_url} alt={site.candidate_name} className="rounded-2xl shadow-lg w-full" />
                            ) : (
                                <div className="rounded-2xl bg-gray-200 w-full h-96 flex items-center justify-center">
                                    <span className="text-gray-400 text-6xl">👤</span>
                                </div>
                            )}
                        </div>
                        <div className="prose prose-lg max-w-none">
                            <h2 className="font-heading">Background</h2>
                            <div dangerouslySetInnerHTML={{
                                __html: site?.bio_full || '<p>Born and raised in the constituency, the candidate has dedicated their life to community service and development. With a strong educational background and years of professional experience, they understand the challenges facing our people and have a clear plan to address them.</p><p>Their commitment to education, healthcare, infrastructure, and youth empowerment has been demonstrated through numerous community projects and initiatives.</p>'
                            }} />

                            {site?.education && (
                                <>
                                    <h3 className="font-heading mt-8">Education</h3>
                                    <div dangerouslySetInnerHTML={{ __html: site.education }} />
                                </>
                            )}

                            {site?.experience && (
                                <>
                                    <h3 className="font-heading mt-8">Professional Experience</h3>
                                    <div dangerouslySetInnerHTML={{ __html: site.experience }} />
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </section>

            <section className="py-16 bg-gray-50">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                    <h2 className="section-heading text-center">Journey & Milestones</h2>
                    <div className="mt-12">
                        {milestones.map((m, i) => (
                            <TimelineItem key={i} {...m} />
                        ))}
                    </div>
                </div>
            </section>
        </div>
    );
}
