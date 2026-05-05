import React, { useEffect } from 'react';
import { Link, useParams } from 'react-router-dom';
import useSiteStore from '../stores/useSiteStore';
import useContentStore from '../stores/useContentStore';

const defaultNews = [
    { id: 1, title: 'Campaign Launch: A New Dawn for Our Constituency', excerpt: 'Hundreds gathered for the official campaign launch, marking the beginning of a new chapter for community-led development.', date: '2027-05-01', image_url: null },
    { id: 2, title: 'Education Initiative: 100 Students Receive Bursaries', excerpt: 'In partnership with local organisations, 100 students from disadvantaged backgrounds received full bursaries for the upcoming academic year.', date: '2027-05-10', image_url: null },
    { id: 3, title: 'Infrastructure Plan: Tarmacking of Ward Roads Begins', excerpt: 'The first phase of the road improvement project has commenced, with 15km of roads set to be tarmacked in the coming months.', date: '2027-05-15', image_url: null },
];

export default function NewsPage() {
    const { slug } = useParams();
    const { site } = useSiteStore();
    const { news, fetchNews } = useContentStore();

    useEffect(() => {
        if (site?.id) fetchNews(site.id);
    }, [site?.id, fetchNews]);

    const displayNews = news.length > 0 ? news : defaultNews;

    return (
        <div>
            <section className="bg-dark-900 text-white py-20">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h1 className="text-4xl md:text-5xl font-heading font-bold mb-4">News & Updates</h1>
                    <p className="text-lg text-gray-300 max-w-2xl mx-auto">
                        Stay informed with the latest campaign news, press releases, and community updates.
                    </p>
                </div>
            </section>

            <section className="py-16 bg-gray-50">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                        {displayNews.map((article) => (
                            <Link key={article.id} to={`/${slug}/news/${article.id}`} className="card group hover:shadow-md transition block">
                                {article.image_url ? (
                                    <img src={article.image_url} alt={article.title} className="w-full h-48 object-cover" />
                                ) : (
                                    <div className="w-full h-48 bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center">
                                        <span className="text-4xl">📰</span>
                                    </div>
                                )}
                                <div className="p-5">
                                    <span className="text-xs text-primary-600 font-semibold">
                                        {new Date(article.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}
                                    </span>
                                    <h3 className="font-heading font-bold text-lg mt-2 mb-2 group-hover:text-primary-600 transition">{article.title}</h3>
                                    <p className="text-gray-600 text-sm">{article.excerpt}</p>
                                    <span className="inline-block mt-3 text-sm text-primary-600 font-medium group-hover:underline">Read more &rarr;</span>
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>
            </section>
        </div>
    );
}
