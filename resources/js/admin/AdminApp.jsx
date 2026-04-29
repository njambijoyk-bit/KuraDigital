import React, { lazy, Suspense } from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import ProtectedRoute from './components/ProtectedRoute';
import AdminLayout from './components/AdminLayout';

const LoginPage = lazy(() => import('./pages/LoginPage'));
const RegisterPage = lazy(() => import('./pages/RegisterPage'));
const CampaignsPage = lazy(() => import('./pages/CampaignsPage'));
const DashboardPage = lazy(() => import('./pages/DashboardPage'));
const TeamPage = lazy(() => import('./pages/TeamPage'));
const SiteSettingsPage = lazy(() => import('./pages/SiteSettingsPage'));
const ManifestoPage = lazy(() => import('./pages/ManifestoPage'));
const EventsPage = lazy(() => import('./pages/EventsPage'));
const NewsPage = lazy(() => import('./pages/NewsPage'));
const GalleryPage = lazy(() => import('./pages/GalleryPage'));
const ProjectsPage = lazy(() => import('./pages/ProjectsPage'));
const VolunteersPage = lazy(() => import('./pages/VolunteersPage'));
const ContactsPage = lazy(() => import('./pages/ContactsPage'));
const MediaPage = lazy(() => import('./pages/MediaPage'));
const AuditLogPage = lazy(() => import('./pages/AuditLogPage'));

function Loading() {
    return (
        <div className="flex items-center justify-center py-20">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600" />
        </div>
    );
}

export default function AdminApp() {
    return (
        <Suspense fallback={<Loading />}>
            <Routes>
                <Route path="login" element={<LoginPage />} />
                <Route path="register" element={<RegisterPage />} />

                {/* Campaign list */}
                <Route
                    index
                    element={
                        <ProtectedRoute>
                            <CampaignsPage />
                        </ProtectedRoute>
                    }
                />

                {/* Campaign-scoped routes with sidebar layout */}
                <Route
                    path="campaigns/:campaignId"
                    element={
                        <ProtectedRoute>
                            <AdminLayout />
                        </ProtectedRoute>
                    }
                >
                    <Route index element={<DashboardPage />} />
                    <Route path="team" element={<TeamPage />} />
                    <Route path="site" element={<SiteSettingsPage />} />
                    <Route path="manifesto" element={<ManifestoPage />} />
                    <Route path="events" element={<EventsPage />} />
                    <Route path="news" element={<NewsPage />} />
                    <Route path="gallery" element={<GalleryPage />} />
                    <Route path="projects" element={<ProjectsPage />} />
                    <Route path="volunteers" element={<VolunteersPage />} />
                    <Route path="contacts" element={<ContactsPage />} />
                    <Route path="media" element={<MediaPage />} />
                    <Route path="audit" element={<AuditLogPage />} />
                </Route>

                <Route path="*" element={<Navigate to="/admin" replace />} />
            </Routes>
        </Suspense>
    );
}
