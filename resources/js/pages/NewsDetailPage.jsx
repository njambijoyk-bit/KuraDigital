import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import axios from 'axios';
import useSiteStore from '../stores/useSiteStore';

export default function NewsDetailPage() {
    const { slug, articleId } = useParams();
    const { site } = useSiteStore();
    const [article, setArticle] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (!site?.id) return;
        setLoading(true);
        axios.get(`/api/sites/${site.id}/news/${articleId}`)
            .then(({ data }) => { setArticle(data.data); setLoading(false); })
            .catch(() => { setError('Article not found'); setLoading(false); });
    }, [site?.id, articleId]);

    if (loading) {
        return (
            <div className="min-h-[60vh] flex items-center justify-center">
                <div className="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600" />
            </div>
        );
    }

    if (error || !article) {
        return (
            <div className="min-h-[60vh] flex items-center justify-center">
                <div className="text-center">
                    <h2 className="text-2xl font-bold text-gray-800 mb-2">Article Not Found</h2>
                    <Link to={`/${slug}/news`} className="text-primary-600 hover:underline">Back to News</Link>
                </div>
            </div>
        );
    }

    return (
        <div>
            <section className="bg-dark-900 text-white py-16">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                    <Link to={`/${slug}/news`} className="text-sm text-gray-400 hover:text-white mb-4 inline-block">&larr; Back to News</Link>
                    <h1 className="text-3xl md:text-4xl font-heading font-bold mb-4">{article.title}</h1>
                    <div className="flex items-center gap-4 text-sm text-gray-400">
                        <span>{new Date(article.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}</span>
                        {article.author && <span>By {article.author.name}</span>}
                    </div>
                </div>
            </section>

            <article className="py-12 bg-white">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                    {article.image_url && (
                        <img src={article.image_url} alt={article.title} className="w-full rounded-xl mb-8 max-h-96 object-cover" />
                    )}

                    {article.excerpt && (
                        <p className="text-lg text-gray-600 font-medium mb-6 border-l-4 border-primary-500 pl-4 italic">
                            {article.excerpt}
                        </p>
                    )}

                    <div className="prose prose-lg max-w-none text-gray-700 leading-relaxed">
                        {article.body ? (
                            article.body.split('\n').map((para, i) => para.trim() ? <p key={i}>{para}</p> : null)
                        ) : (
                            <p>{article.excerpt}</p>
                        )}
                    </div>

                    <div className="mt-12 pt-8 border-t border-gray-200">
                        <Link to={`/${slug}/news`} className="text-primary-600 hover:text-primary-700 font-medium">
                            &larr; All News & Updates
                        </Link>
                    </div>
                </div>
            </article>
        </div>
    );
}
