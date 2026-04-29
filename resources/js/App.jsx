import React, { useEffect } from 'react';
import { Routes, Route, useParams } from 'react-router-dom';
import Layout from './components/layout/Layout';
import HomePage from './pages/HomePage';
import AboutPage from './pages/AboutPage';
import ManifestoPage from './pages/ManifestoPage';
import EventsPage from './pages/EventsPage';
import NewsPage from './pages/NewsPage';
import GalleryPage from './pages/GalleryPage';
import ContactPage from './pages/ContactPage';
import VolunteerPage from './pages/VolunteerPage';
import ProjectsPage from './pages/ProjectsPage';
import NotFoundPage from './pages/NotFoundPage';
import useSiteStore from './stores/useSiteStore';
import AdminApp from './admin/AdminApp';

function SiteWrapper() {
    const { slug } = useParams();
    const { fetchSite, loading, error } = useSiteStore();

    useEffect(() => {
        fetchSite(slug);
    }, [slug, fetchSite]);

    if (loading) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-gray-50">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary-600 mx-auto mb-4" />
                    <p className="text-gray-500">Loading...</p>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-gray-50">
                <div className="text-center">
                    <h1 className="text-4xl font-bold text-gray-800 mb-2">Site Not Found</h1>
                    <p className="text-gray-500">{error}</p>
                </div>
            </div>
        );
    }

    return (
        <Layout>
            <Routes>
                <Route index element={<HomePage />} />
                <Route path="about" element={<AboutPage />} />
                <Route path="manifesto" element={<ManifestoPage />} />
                <Route path="events" element={<EventsPage />} />
                <Route path="news" element={<NewsPage />} />
                <Route path="gallery" element={<GalleryPage />} />
                <Route path="contact" element={<ContactPage />} />
                <Route path="volunteer" element={<VolunteerPage />} />
                <Route path="projects" element={<ProjectsPage />} />
                <Route path="*" element={<NotFoundPage />} />
            </Routes>
        </Layout>
    );
}

export default function App() {
    return (
        <Routes>
            <Route path="/admin/*" element={<AdminApp />} />
            <Route path="/:slug/*" element={<SiteWrapper />} />
            <Route path="*" element={<NotFoundPage />} />
        </Routes>
    );
}
