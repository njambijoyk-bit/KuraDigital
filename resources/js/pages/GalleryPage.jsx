import React, { useEffect, useState } from 'react';
import useSiteStore from '../stores/useSiteStore';
import useContentStore from '../stores/useContentStore';

const defaultGallery = [
    { id: 1, url: null, caption: 'Campaign launch rally', category: 'Rallies' },
    { id: 2, url: null, caption: 'Community engagement', category: 'Community' },
    { id: 3, url: null, caption: 'Youth forum', category: 'Youth' },
    { id: 4, url: null, caption: 'Development project', category: 'Projects' },
    { id: 5, url: null, caption: 'Women empowerment event', category: 'Community' },
    { id: 6, url: null, caption: 'Education bursary ceremony', category: 'Projects' },
];

export default function GalleryPage() {
    const { site } = useSiteStore();
    const { gallery, fetchGallery } = useContentStore();
    const [filter, setFilter] = useState('All');

    useEffect(() => {
        if (site?.id) fetchGallery(site.id);
    }, [site?.id, fetchGallery]);

    const displayGallery = gallery.length > 0 ? gallery : defaultGallery;
    const categories = ['All', ...new Set(displayGallery.map((g) => g.category).filter(Boolean))];
    const filtered = filter === 'All' ? displayGallery : displayGallery.filter((g) => g.category === filter);

    return (
        <div>
            <section className="bg-dark-900 text-white py-20">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h1 className="text-4xl md:text-5xl font-heading font-bold mb-4">Photo Gallery</h1>
                    <p className="text-lg text-gray-300 max-w-2xl mx-auto">
                        Moments from the campaign trail, community events, and development projects.
                    </p>
                </div>
            </section>

            <section className="py-16 bg-gray-50">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex flex-wrap gap-2 justify-center mb-10">
                        {categories.map((cat) => (
                            <button
                                key={cat}
                                onClick={() => setFilter(cat)}
                                className={`px-4 py-2 rounded-full text-sm font-medium transition-colors ${
                                    filter === cat
                                        ? 'bg-primary-600 text-white'
                                        : 'bg-white text-gray-700 hover:bg-gray-100 border'
                                }`}
                            >
                                {cat}
                            </button>
                        ))}
                    </div>

                    <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        {filtered.map((photo) => (
                            <div key={photo.id} className="group relative rounded-xl overflow-hidden shadow-md hover:shadow-xl transition-shadow aspect-square">
                                {photo.url ? (
                                    <img src={photo.url} alt={photo.caption} className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                                ) : (
                                    <div className="w-full h-full bg-gradient-to-br from-primary-100 to-accent-100 flex items-center justify-center">
                                        <span className="text-4xl">📷</span>
                                    </div>
                                )}
                                <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-4">
                                    <p className="text-white text-sm font-medium">{photo.caption}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>
        </div>
    );
}
